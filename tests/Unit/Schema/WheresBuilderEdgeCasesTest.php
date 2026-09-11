<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\PartialCompiler;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\UniqueCompiler;
use Php\Support\Laravel\Database\Tests\Unit\Fixtures\Status;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;
use Stringable;
use stdClass;

/**
 * The predicate compiler decides how a value reaches the database inside DDL, where no binding
 * can protect it. These cover the value types and the failure paths the happy-path tests miss.
 */
final class WheresBuilderEdgeCasesTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('values')]
    public function eachValueTypeIsRenderedAsItsOwnSqlLiteral(mixed $value, string $expected): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table) use ($value): void {
                $table->partial('a')->where('a', '=', $value);
            }
        );

        self::assertStringContainsString("\"a\" = $expected", $sql[0]);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function values(): iterable
    {
        yield 'plain string' => [
            'plain',
            "'plain'",
        ];
        yield 'apostrophe' => [
            "O'Brien",
            "'O''Brien'",
        ];
        yield 'doubled apostrophe' => [
            "a''b",
            "'a''''b'",
        ];
        yield 'backslash stays literal' => [
            'a\\b',
            "'a\\b'",
        ];
        yield 'percent is not a format' => [
            '50%',
            "'50%'",
        ];
        yield 'empty string' => [
            '',
            "''",
        ];
        yield 'int' => [
            42,
            '42',
        ];
        yield 'negative int' => [
            -1,
            '-1',
        ];
        yield 'zero' => [
            0,
            '0',
        ];
        yield 'float' => [
            3.5,
            '3.5',
        ];
        yield 'true' => [
            true,
            'true',
        ];
        yield 'false' => [
            false,
            'false',
        ];
        yield 'null' => [
            null,
            'null',
        ];
        yield 'stringable' => [
            new class implements Stringable {
                public function __toString(): string
                {
                    return "it's";
                }
            },
            "'it''s'",
        ];
        yield 'datetime' => [
            new DateTimeImmutable('2026-01-02 03:04:05'),
            "'2026-01-02 03:04:05'",
        ];
    }

    #[Test]
    public function aBackedEnumIsRenderedAsItsValue(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->partial('a')->where('a', '=', Status::Active);
            }
        );

        self::assertStringContainsString('"a" = \'active\'', $sql[0]);
    }

    #[Test]
    public function anUnsupportedValueTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value of type');

        // An object that is neither Stringable nor a date has no defensible SQL rendering.
        $this->compilePredicate(
            [
                'type'     => 'Basic',
                'boolean'  => 'and',
                'column'   => 'a',
                'operator' => '=',
                'value'    => new stdClass(),
            ]
        );
    }

    #[Test]
    public function anUnknownPredicateTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported index predicate type [Nonsense]');

        $this->compilePredicate(['type' => 'Nonsense', 'boolean' => 'and']);
    }

    /**
     * More bindings than placeholders must not corrupt the SQL; the extra ones are dropped.
     */
    #[Test]
    public function surplusRawBindingsAreIgnored(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->partial('a')->whereRaw('x = ?', ['used', 'surplus']);
            }
        );

        self::assertStringContainsString("where (x = 'used')", $sql[0]);
        self::assertStringNotContainsString('surplus', $sql[0]);
    }

    #[Test]
    public function aRawBindingContainingAPlaceholderIsNotRescanned(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->partial('a')->whereRaw('x = ? and y = ?', ['what?', 'second']);
            }
        );

        self::assertStringContainsString("where (x = 'what?' and y = 'second')", $sql[0]);
    }

    #[Test]
    public function anEmptyInListBecomesAContradiction(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->partial('a')->whereIn('a', []);
            }
        );

        self::assertStringContainsString('where (0 = 1)', $sql[0]);
    }

    #[Test]
    public function anEmptyNotInListBecomesATautology(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->partial('a')->whereNotIn('a', []);
            }
        );

        self::assertStringContainsString('where (1 = 1)', $sql[0]);
    }

    /**
     * Callers are expected to fall back to a plain unique index when there is no predicate.
     * Reaching the compiler anyway would emit a dangling `WHERE`, so it refuses.
     */
    #[Test]
    public function theUniqueCompilerRefusesAnEmptyPredicate(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires at least one where clause');

        $connection = $this->connection();

        UniqueCompiler::compile(
            $this->grammar(),
            new Blueprint($connection, 't'),
            new UniqueBuilder(['index' => 'i', 'columns' => ['a']]),
            new PartialBuilder()
        );
    }

    #[Test]
    public function orIsPreservedBetweenPredicates(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->partial('a')
                ->whereNull('deleted_at')
                ->whereTrue('active', 'or');
            }
        );

        self::assertStringContainsString(
            'where ("deleted_at" is null) or ("active" is true)',
            $sql[0]
        );
    }

    /**
     * `removeLeadingBoolean()` strips the first boolean; it must not eat one that belongs to a
     * column name such as `brand` or `origin`.
     */
    #[Test]
    public function aLeadingBooleanIsStrippedWithoutTouchingColumnNames(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->partial('a')->where('brand', '=', 'Ford');
            }
        );

        self::assertStringContainsString('where ("brand" = \'Ford\')', $sql[0]);
        self::assertStringNotContainsString('where and', $sql[0]);
    }

    /**
     * Drives the compiler with a hand-built predicate, which is the only way to reach the
     * guards the fluent API cannot produce.
     *
     * @param array<string, mixed> $where
     */
    private function compilePredicate(array $where): string
    {
        $connection = $this->connection();

        return PartialCompiler::compile(
            $this->grammar(),
            new Blueprint($connection, 't'),
            new PartialBuilder(['index' => 'i', 'columns' => ['a'], 'wheres' => [$where]])
        );
    }

    #[Test]
    public function aFluentWithoutPredicatesCompilesWithoutAWhereClause(): void
    {
        $sql = $this->sqlFor(
            't',
            static function (Blueprint $table): void {
                $table->partial('a');
            }
        );

        self::assertSame('create index "t_a_partial" on "t" ("a")', $sql[0]);
    }
}
