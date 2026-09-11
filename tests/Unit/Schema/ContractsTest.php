<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as FrameworkGrammar;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\CreateCompiler;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;
use ReflectionMethod;

/**
 * Contracts that were reachable from the public API yet asserted nowhere.
 */
final class ContractsTest extends UnitTestCase
{
    // ---------------------------------------------------------------- delegation

    /**
     * `compileCreate()` hands a plain create back to the framework and only takes over when one
     * of this package's extensions is present. The two must agree byte for byte: the fallback in
     * `CreateCompiler` is what runs whenever an extension IS present, and the columns part of the
     * statement has to look the same either way.
     *
     * Compared against `CreateCompiler` directly rather than through the blueprint — going
     * through the blueprint would exercise the framework on both sides of the assertion and prove
     * nothing.
     */
    #[Test]
    public function theFallbackCompilerAgreesWithTheFramework(): void
    {
        $connection = $this->connection('pref_');

        $blueprint = new Blueprint($connection, 't');
        $blueprint->create();
        $blueprint->string('c');
        $blueprint->integer('n')->nullable();

        /** @var Grammar $grammar */
        $grammar = $connection->getSchemaGrammar();

        // `getColumns()` is the framework's own protected helper; reaching it by reflection keeps
        // the production class free of a test-only accessor.
        $getColumns = new ReflectionMethod($grammar, 'getColumns');

        $ours = CreateCompiler::compile($grammar, $blueprint, $getColumns->invoke($grammar, $blueprint), []);

        $theirs = (new FrameworkGrammar($connection))
            ->compileCreate($blueprint, $blueprint->getCommands()[0]);

        self::assertSame($theirs, $ours);
    }

    /** @return iterable<string, array{callable(Blueprint): mixed, string}> */
    public static function extensionCommands(): iterable
    {
        yield 'like' => [
            static fn
        (Blueprint $t) => $t->like('src'), '(like "src")',
        ];
        yield 'fromTable' => [
            static fn
        (Blueprint $t) => $t->fromTable('src'), 'as table "src"',
        ];
        yield 'fromSelect' => [
            static fn
        (Blueprint $t) => $t->fromSelect('select 1'), 'as (select 1)',
        ];
        yield 'ifNotExists' => [
            static fn
        (Blueprint $t) => $t->ifNotExists(), 'if not exists',
        ];
    }

    /**
     * The other side of the same guard: each extension must divert compilation away from the
     * framework.
     */
    #[Test]
    #[DataProvider('extensionCommands')]
    public function anExtensionCommandTakesOverCompilation(callable $define, string $expected): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table) use ($define): void {
                $table->string('c');
                $define($table);
            }
        );

        self::assertStringContainsString($expected, $sql[0]);
    }

    /**
     * When more than one extension is present the order is `like`, `fromSelect`, `fromTable`.
     * Nothing asserted it, so reordering the arms was invisible.
     */
    #[Test]
    public function likeWinsOverTheOtherSources(): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table): void {
                $table->fromTable('from_table');
                $table->fromSelect('select 1');
                $table->like('like_table');
            }
        );

        self::assertStringContainsString('(like "like_table")', $sql[0]);
        self::assertStringNotContainsString('from_table', $sql[0]);
    }

    #[Test]
    public function fromSelectWinsOverFromTable(): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table): void {
                $table->fromTable('from_table');
                $table->fromSelect('select 1');
            }
        );

        self::assertStringContainsString('as (select 1)', $sql[0]);
        self::assertStringNotContainsString('from_table', $sql[0]);
    }

    // ---------------------------------------------------------------- prefix

    /**
     * The functional suite runs with an empty prefix, so every one of these would survive losing
     * its `wrapTable()`.
     *
     * @return iterable<string, array{callable(Blueprint): mixed, string}>
     */
    public static function prefixedStatements(): iterable
    {
        yield 'createView' => [
            static fn
        (Blueprint $t) => $t->createView('v', 'select 1'),
            'create view "pref_v" as select 1',
        ];
        yield 'createViewOrReplace' => [
            static fn
        (Blueprint $t) => $t->createViewOrReplace('v', 'select 1'),
            'create or replace view "pref_v" as select 1',
        ];
        yield 'createView materialized' => [
            static fn
        (Blueprint $t) => $t->createView('v', 'select 1', true),
            'create materialized view "pref_v" as select 1',
        ];
        yield 'dropView' => [
            static fn
        (Blueprint $t) => $t->dropView('v'),
            'drop view "pref_v"',
        ];
        yield 'dropViewIfExists materialized' => [
            static fn
        (Blueprint $t) => $t->dropViewIfExists('v', true),
            'drop materialized view if exists "pref_v"',
        ];
    }

    #[Test]
    #[DataProvider('prefixedStatements')]
    public function theTablePrefixReachesEveryStatement(callable $define, string $expected): void
    {
        $sql = $this->sqlFor('v', static fn(Blueprint $table) => $define($table), 'pref_');

        self::assertSame($expected, $sql[0]);
    }

    #[Test]
    public function refreshingAMaterializedViewUsesThePrefix(): void
    {
        self::assertSame(
            'refresh materialized view "pref_v"',
            $this->grammar('pref_')->compileRefreshMaterializedView('v')
        );
    }

    #[Test]
    public function theStandaloneCompressionStatementUsesThePrefix(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->text('a')->compression('lz4')->change();
            },
            'pref_'
        );

        self::assertContains('alter table "pref_t" alter column "a" set compression lz4', $sql);
    }

    // ---------------------------------------------------------------- cascade

    /**
     * The whole point of `dropIfExistsCascade()`; asserted nowhere, only relied on in teardowns.
     */
    #[Test]
    public function dropIfExistsAppendsCascadeWhenAsked(): void
    {
        $plain = $this->sqlFor('t', static fn(Blueprint $table) => $table->dropIfExists(), 'pref_');
        self::assertSame('drop table if exists "pref_t"', $plain[0]);

        $cascading = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->dropIfExists()['cascade'] = true;
            },
            'pref_'
        );
        self::assertSame('drop table if exists "pref_t" cascade', $cascading[0]);
    }

    // ---------------------------------------------------------------- generateUUID

    /** @return iterable<string, array{mixed, string}> */
    public static function uuidDefaults(): iterable
    {
        yield 'true uses the native generator' => [
            true,
            'default gen_random_uuid()',
        ];
        yield 'callable builds the expression' => [
            static fn
        (string $column): string => "some_fn('$column')",
            "default some_fn('c')",
        ];
        yield 'expression passes through' => [
            new Expression('uuid_generate_v4()'),
            'default uuid_generate_v4()',
        ];
    }

    #[Test]
    #[DataProvider('uuidDefaults')]
    public function generateUuidEmitsTheRequestedDefault(mixed $default, string $expected): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table) use ($default): void {
                $table->generateUUID('c', $default);
            }
        );

        self::assertStringContainsString($expected, $sql[0]);
    }

    #[Test]
    public function generateUuidWithFalseHasNoDefault(): void
    {
        $sql = $this->sqlForCreate('t', static fn(Blueprint $t) => $t->generateUUID('c', false));

        self::assertSame('create table "t" ("c" uuid not null)', $sql[0]);
    }

    #[Test]
    public function generateUuidWithNullIsNullableAndUndefaulted(): void
    {
        $sql = $this->sqlForCreate('t', static fn(Blueprint $t) => $t->generateUUID('c', null));

        self::assertSame('create table "t" ("c" uuid null)', $sql[0]);
    }

    #[Test]
    public function primaryUuidIsAPrimaryKeyOnTopOfGenerateUuid(): void
    {
        $sql = $this->sqlForCreate('t', static fn(Blueprint $t) => $t->primaryUUID());

        self::assertStringContainsString('"id" uuid not null default gen_random_uuid()', $sql[0]);
        self::assertStringContainsString('add primary key ("id")', $sql[1]);
    }

    // ---------------------------------------------------------------- rejected input

    /** @return iterable<string, array{callable(Blueprint): mixed, string}> */
    public static function rejectedInput(): iterable
    {
        yield 'between with no values' => [
            static fn
        (Blueprint $t) => $t->partial('a')->whereBetween('a', []),
            'exactly two values, 0 given',
        ];
        yield 'between with one value' => [
            static fn
        (Blueprint $t) => $t->partial('a')->whereBetween('a', [1]),
            'exactly two values, 1 given',
        ];
        yield 'between with three values' => [
            static fn
        (Blueprint $t) => $t->partial('a')->whereBetween('a', [1, 2, 3]),
            'exactly two values, 3 given',
        ];
        yield 'partial without columns' => [
            static fn
        (Blueprint $t) => $t->partial([]),
            'at least one column',
        ];
        yield 'unique partial without columns' => [
            static fn
        (Blueprint $t) => $t->uniquePartial([]),
            'at least one column',
        ];
        yield 'unique partial with a bogus algorithm' => [
            static fn
        (Blueprint $t) => $t->uniquePartial('e', null, 'gin; drop table x')->whereTrue('a'),
            'Invalid index algorithm',
        ];
    }

    #[Test]
    #[DataProvider('rejectedInput')]
    public function invalidInputIsRejectedRatherThanCompiled(callable $define, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->sqlFor('t', static fn(Blueprint $table) => $define($table));
    }

    /** @return iterable<string, array{mixed}> */
    public static function emptyAlgorithms(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'false' => [false];
    }

    #[Test]
    #[DataProvider('emptyAlgorithms')]
    public function anEmptyAlgorithmEmitsNoUsingClause(mixed $algorithm): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table) use ($algorithm): void {
                $table->partial('a')->algorithm($algorithm)->whereTrue('b');
            }
        );

        self::assertStringNotContainsString('using', $sql[0]);
    }

    #[Test]
    public function compressionSetToFalseEmitsNothing(): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table): void {
                $table->string('c')->compression(false);
            }
        );

        self::assertSame('create table "t" ("c" varchar(255) not null)', $sql[0]);
    }

    // ---------------------------------------------------------------- extensions

    #[Test]
    public function namingQuotesAndJoinsEveryExtension(): void
    {
        self::assertSame(
            '"uuid-ossp", "tablefunc"',
            $this->grammar()->naming(['uuid-ossp', 'tablefunc'])
        );
    }
}
