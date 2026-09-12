<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * A unique index says two rows must not be equal; an exclusion constraint says they must not
 * overlap, intersect, or whatever else an operator expresses. It is what a range column exists
 * for, and Laravel has no form for it.
 */
final class ExclusionConstraintTest extends UnitTestCase
{
    #[Test]
    public function pairsAreListedInTheOrderTheyWereGiven(): void
    {
        $sql = $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->exclusion('no_overlap')
                ->using('gist')
                ->with('room_id', '=')
                ->with('during', '&&')
        );

        self::assertSame(
            'alter table "bookings" add constraint "no_overlap" '
            . 'exclude using gist ("room_id" with =, "during" with &&)',
            $sql[0]
        );
    }

    #[Test]
    public function aPredicateRestrictsTheRowsItCovers(): void
    {
        $sql = $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->exclusion('no_overlap')
                ->using('gist')
                ->with('during', '&&')
                ->whereNull('cancelled_at')
        );

        self::assertSame(
            'alter table "bookings" add constraint "no_overlap" '
            . 'exclude using gist ("during" with &&) where ("cancelled_at" is null)',
            $sql[0]
        );
    }

    /**
     * Without `using`, PostgreSQL falls back to btree, which supports only `=`.
     */
    #[Test]
    public function theAccessMethodMayBeLeftToPostgres(): void
    {
        $sql = $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->exclusion('one_per_room')->with('room_id', '=')
        );

        self::assertSame(
            'alter table "bookings" add constraint "one_per_room" exclude ("room_id" with =)',
            $sql[0]
        );
    }

    #[Test]
    public function itIsSeparateFromTheCreateStatement(): void
    {
        $sql = $this->sqlForCreate(
            'bookings',
            static function (Blueprint $t): void {
                $t->tsRange('during');
                $t->exclusion('no_overlap')->using('gist')->with('during', '&&');
            }
        );

        self::assertSame('create table "bookings" ("during" tsrange not null)', $sql[0]);
        self::assertStringStartsWith('alter table "bookings" add constraint', $sql[1]);
    }

    #[Test]
    public function theTablePrefixReachesTheStatement(): void
    {
        $sql = $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->exclusion('no_overlap')->with('room_id', '='),
            'pref_'
        );

        self::assertStringStartsWith('alter table "pref_bookings" ', $sql[0]);
    }

    /** @return iterable<string, array{string}> */
    public static function operators(): iterable
    {
        foreach (['=', '&&', '<>', '@>', '<@', '&<', '~=', '||'] as $operator) {
            yield $operator => [$operator];
        }
    }

    #[Test]
    #[DataProvider('operators')]
    public function anyPostgresOperatorNameIsAccepted(string $operator): void
    {
        $sql = $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->exclusion('c')->with('during', $operator)
        );

        self::assertStringContainsString("(\"during\" with $operator)", $sql[0]);
    }

    /**
     * The operator is interpolated into DDL — PostgreSQL takes no parameter there — so it is
     * checked rather than trusted, the same way the access method is.
     */
    #[Test]
    public function anythingThatIsNotAnOperatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid exclusion operator');

        $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->exclusion('c')->with('during', '&& ); drop table x --')
        );
    }

    #[Test]
    public function aBogusAccessMethodIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid index algorithm');

        $this->sqlFor(
            'bookings',
            static fn(Blueprint $t) => $t->exclusion('c')->using('gist; drop table x')->with('a', '=')
        );
    }

    #[Test]
    public function aConstraintWithNoPairsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one column and operator');

        $this->sqlFor('bookings', static fn(Blueprint $t) => $t->exclusion('c')->using('gist'));
    }

    #[Test]
    public function droppingNamesTheConstraint(): void
    {
        $sql = $this->sqlFor(
            'bookings',
            static function (Blueprint $t): void {
                $t->dropConstraint('no_overlap');
                $t->dropCheck('price_positive');
            }
        );

        self::assertSame('alter table "bookings" drop constraint "no_overlap"', $sql[0]);
        self::assertSame(
            'alter table "bookings" drop constraint "price_positive"',
            $sql[1],
            'dropCheck() is the same statement, named for what it usually drops'
        );
    }
}
