<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * `online()` and `nullsNotDistinct()` are the framework's own names for these; Laravel 13 offers
 * both on an ordinary index and neither on a partial one.
 */
final class IndexModifiersTest extends UnitTestCase
{
    // ---------------------------------------------------------------- concurrently

    #[Test]
    public function onlineBuildsAPartialIndexConcurrently(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $t) => $t->partial('code', 'ix')->whereNull('deleted_at')->online()
        );

        self::assertSame(
            'create index concurrently "ix" on "docs" ("code") where ("deleted_at" is null)',
            $sql[0]
        );
    }

    #[Test]
    public function onlineBuildsAUniquePartialIndexConcurrently(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $t) => $t->uniquePartial('code', 'ix')->whereNull('deleted_at')->online()
        );

        self::assertSame(
            'create unique index concurrently "ix" on "docs" ("code") where ("deleted_at" is null)',
            $sql[0]
        );
    }

    #[Test]
    public function withoutOnlineNothingIsConcurrent(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $t) => $t->partial('code', 'ix')->whereNull('deleted_at')
        );

        self::assertStringNotContainsString('concurrently', $sql[0]);
    }

    #[Test]
    public function onlineCanBeTurnedOffExplicitly(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $t) => $t->partial('code', 'ix')->whereNull('deleted_at')->online(false)
        );

        self::assertStringNotContainsString('concurrently', $sql[0]);
    }

    // ---------------------------------------------------------------- nulls

    /** @return iterable<string, array{bool|null, string}> */
    public static function nullsSettings(): iterable
    {
        yield 'not distinct' => [
            true,
            ' nulls not distinct',
        ];
        yield 'distinct, said out loud' => [
            false,
            ' nulls distinct',
        ];
        yield 'left alone' => [
            null,
            '',
        ];
    }

    #[Test]
    #[DataProvider('nullsSettings')]
    public function theNullsClauseSitsBetweenTheColumnsAndThePredicate(?bool $setting, string $expected): void
    {
        $sql = $this->sqlFor(
            'docs',
            static function (Blueprint $t) use ($setting): void {
                $index = $t->uniquePartial('code', 'ix');

                if ($setting !== null) {
                    $index->nullsNotDistinct($setting);
                }

                $index->whereNull('deleted_at');
            }
        );

        self::assertSame(
            'create unique index "ix" on "docs" ("code")' . $expected . ' where ("deleted_at" is null)',
            $sql[0]
        );
    }

    /**
     * PostgreSQL parses `NULLS NOT DISTINCT` on a plain index and then ignores it, so accepting it
     * here would mean quietly doing nothing.
     */
    #[Test]
    public function aNonUniquePartialIndexRejectsTheNullsClause(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('only means something for a unique index');

        $this->sqlFor(
            'docs',
            static fn(Blueprint $t) => $t->partial('code', 'ix')->whereNull('deleted_at')->nullsNotDistinct()
        );
    }

    // ---------------------------------------------------------------- include

    /**
     * `INCLUDE` carries extra columns in the index leaf without indexing them, so a query reading
     * only those never touches the table. PostgreSQL 11 and later; Laravel has no form for it.
     */
    #[Test]
    public function includedColumnsFollowTheIndexedOnes(): void
    {
        $sql = $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->partial('room_id', 'ix')
                ->include(['during', 'guest'])
                ->whereNull('cancelled_at')
        );

        self::assertSame(
            'create index "ix" on "bookings" ("room_id") include ("during", "guest") '
            . 'where ("cancelled_at" is null)',
            $sql[0]
        );
    }

    #[Test]
    public function aUniquePartialIndexCanCoverToo(): void
    {
        $sql = $this->sqlFor(
            'users',
            static fn(Blueprint $t) => $t->uniquePartial('email', 'ux')
                ->include(['name'])
                ->whereNull('deleted_at')
        );

        self::assertSame(
            'create unique index "ux" on "users" ("email") include ("name") '
            . 'where ("deleted_at" is null)',
            $sql[0]
        );
    }

    #[Test]
    public function withoutIncludeNothingIsEmitted(): void
    {
        $sql = $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->partial('room_id', 'ix')->whereNull('cancelled_at')
        );

        self::assertStringNotContainsString('include', $sql[0]);
    }

    /**
     * `uniquePartial()` reroutes anything `PartialBuilder` declares to the predicates, so a
     * modifier reached by Fluent magic must survive either side of a `where`.
     */
    #[Test]
    public function includeSurvivesEitherSideOfThePredicate(): void
    {
        $sql = $this->sqlFor(
            'users',
            static function (Blueprint $t): void {
                $t->uniquePartial('email', 'before')->include(['name'])->whereNull('deleted_at');
                $t->uniquePartial('email', 'after')->whereNull('deleted_at')->include(['name']);
            }
        );

        self::assertSame(str_replace('before', 'after', $sql[0]), $sql[1]);
        self::assertStringContainsString('include ("name")', $sql[1]);
    }

    // ---------------------------------------------------------------- chaining

    /**
     * `UniqueBuilder::__call` used to hand back the constraint builder, so a modifier written
     * after a predicate set its attribute on the wrong object and vanished — while the same
     * modifier written before the predicate worked. The order must not matter.
     */
    #[Test]
    public function modifiersSurviveWhereverTheyAppearInTheChain(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static function (Blueprint $t): void {
                $t->uniquePartial('code', 'ix_before')
                    ->online()
                    ->nullsNotDistinct()
                    ->algorithm('btree')
                    ->whereNull('deleted_at');

                $t->uniquePartial('code', 'ix_after')
                    ->whereNull('deleted_at')
                    ->online()
                    ->nullsNotDistinct()
                    ->algorithm('btree');
            }
        );

        self::assertSame(
            str_replace('ix_before', 'ix_after', $sql[0]),
            $sql[1],
            'the same modifiers must compile the same either side of the predicate'
        );
        self::assertStringContainsString('concurrently', $sql[1]);
        self::assertStringContainsString('using btree', $sql[1]);
        self::assertStringContainsString('nulls not distinct', $sql[1]);
    }

    /**
     * Predicates must still accumulate rather than replace one another when chained.
     */
    #[Test]
    public function chainedPredicatesAccumulate(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $t) => $t->uniquePartial('code', 'ix')
                ->whereNull('deleted_at')
                ->whereTrue('published')
        );

        self::assertSame(
            'create unique index "ix" on "docs" ("code") '
            . 'where ("deleted_at" is null) and ("published" is true)',
            $sql[0]
        );
    }
}
