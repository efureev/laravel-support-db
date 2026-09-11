<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Regression tests for D4, D5, D7 and D8 — partial and partial-unique indexes.
 */
final class PartialIndexTest extends UnitTestCase
{
    #[Test]
    public function theTablePrefixIsAppliedToTheIndexedTable(): void
    {
        // `createIndexName()` already prefixes the index name, so an unprefixed table here
        // produced an index pointing at a relation that does not exist.
        $sql = $this->sqlFor('users', static function (Blueprint $table): void {
            $table->partial('name')->whereNull('deleted_at');
        }, 'pref_');

        self::assertSame(
            'create index "pref_users_name_partial" on "pref_users" ("name") where ("deleted_at" is null)',
            $sql[0]
        );
    }

    #[Test]
    public function identifiersAreQuoted(): void
    {
        $sql = $this->sqlFor('users', static function (Blueprint $table): void {
            $table->partial(['order', 'user'])->whereTrue('active');
        });

        self::assertStringContainsString('on "users" ("order", "user")', $sql[0]);
    }

    #[Test]
    public function stringValuesAreEscaped(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('a')->where('a', '=', "O'Brien");
        });

        self::assertStringContainsString("\"a\" = 'O''Brien'", $sql[0]);
    }

    #[Test]
    public function aValueCannotBreakOutOfItsLiteral(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('a')->where('a', '=', "x'); drop table users; --");
        });

        self::assertStringContainsString("'x''); drop table users; --'", $sql[0]);
        // A single statement, with the payload contained inside the literal.
        self::assertCount(1, $sql);
        self::assertStringEndsWith("--')", $sql[0]);
    }

    #[Test]
    public function nonStringValuesKeepTheirType(): void
    {
        // Everything used to be cast with `(int)`, turning 3.14 into 3 and null into 0.
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('a')->whereIn('a', [3.14, null, true, 7]);
        });

        self::assertStringContainsString('in (3.14,null,true,7)', $sql[0]);
    }

    #[Test]
    public function whereWithAnIntegerValueIsAccepted(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('a')->where('phone', '=', 1234);
        });

        self::assertStringContainsString('"phone" = 1234', $sql[0]);
    }

    #[Test]
    public function rawPredicatesMayContainPercentSigns(): void
    {
        // The bindings used to be applied with `sprintf()`, so `%b` was read as a format specifier.
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('a')->whereRaw("name like 'a%b'");
        });

        self::assertStringContainsString("where (name like 'a%b')", $sql[0]);
    }

    #[Test]
    public function rawPredicateBindingsAreStillSubstituted(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('a')->whereRaw('x = ? and y = ?', ["O'Hara", 2]);
        });

        self::assertStringContainsString("where (x = 'O''Hara' and y = 2)", $sql[0]);
    }

    #[Test]
    public function aUniquePartialIndexWithoutPredicatesBecomesAPlainUniqueConstraint(): void
    {
        $sql = $this->sqlFor('users', static function (Blueprint $table): void {
            $table->uniquePartial('email');
        });

        self::assertSame('alter table "users" add constraint "users_email_unique" unique ("email")', $sql[0]);
    }

    #[Test]
    public function aNonWhereCallDoesNotTurnTheIndexIntoAPartialOne(): void
    {
        // `->algorithm('btree')` used to be rerouted into the constraint builder, producing
        // `CREATE UNIQUE INDEX ... WHERE` with an empty predicate.
        $sql = $this->sqlFor('users', static function (Blueprint $table): void {
            $table->uniquePartial('email')->algorithm('btree');
        });

        foreach ($sql as $statement) {
            self::assertStringNotContainsString('where', $statement);
        }

        self::assertStringContainsString('using btree', $sql[0]);
    }

    #[Test]
    public function predicatesAccumulateAcrossSeparateCalls(): void
    {
        // Each call used to build a fresh constraint builder, discarding the previous predicate.
        $sql = $this->sqlFor('users', static function (Blueprint $table): void {
            $unique = $table->uniquePartial('email');
            $unique->whereNull('deleted_at');
            $unique->whereTrue('active');
        });

        self::assertStringContainsString('where ("deleted_at" is null) and ("active" is true)', $sql[0]);
    }

    #[Test]
    public function theAlgorithmPassedPositionallyIsEmitted(): void
    {
        // The third argument used to be stored on the Fluent and never read.
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('code', null, 'gin')->whereNull('deleted_at');
        });

        self::assertStringContainsString('on "t" using gin ("code")', $sql[0]);
    }

    #[Test]
    public function theAlgorithmSetFluentlyIsEmitted(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('code')->algorithm('gin')->whereNull('deleted_at');
        });

        self::assertStringContainsString('on "t" using gin ("code")', $sql[0]);
    }

    #[Test]
    public function aUniquePartialIndexKeepsItsAlgorithm(): void
    {
        // With a predicate the unique path goes through UniqueCompiler, which used to drop it.
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->uniquePartial('email')->algorithm('btree')->whereTrue('active');
        });

        self::assertSame(
            'create unique index "t_email_unique" on "t" using btree ("email") where ("active" is true)',
            $sql[0]
        );
    }

    #[Test]
    public function aUniquePartialIndexAcceptsThePositionalAlgorithm(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->uniquePartial('email', null, 'btree')->whereTrue('active');
        });

        self::assertStringContainsString('using btree ("email")', $sql[0]);
    }

    #[Test]
    public function noAlgorithmMeansNoUsingClause(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('code')->whereNull('deleted_at');
        });

        self::assertStringNotContainsString('using', $sql[0]);
    }

    #[Test]
    public function anInvalidAlgorithmIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid index algorithm');

        $this->sqlFor('t', static function (Blueprint $table): void {
            $table->partial('code', null, 'gin; drop table users; --')->whereNull('deleted_at');
        });
    }

    #[Test]
    public function uniquePartialIndexesAreAlsoPrefixedAndQuoted(): void
    {
        $sql = $this->sqlFor('users', static function (Blueprint $table): void {
            $table->uniquePartial('email')->whereTrue('active');
        }, 'pref_');

        self::assertSame(
            'create unique index "pref_users_email_unique" on "pref_users" ("email") where ("active" is true)',
            $sql[0]
        );
    }
}
