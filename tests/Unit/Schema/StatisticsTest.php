<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * The planner assumes columns are independent. Extended statistics tell it where they are not.
 */
final class StatisticsTest extends UnitTestCase
{
    #[Test]
    public function theColumnsAndTheTableAreNamed(): void
    {
        self::assertSame(
            'create statistics "events_kind_region" on "kind", "region" from "events"',
            $this->sqlFor(
                'events',
                static fn(Blueprint $t) => $t->statistics('events_kind_region')->on('kind', 'region')
            )[0]
        );
    }

    /** Without a kind list PostgreSQL collects all three. */
    #[Test]
    public function kindsAreOptional(): void
    {
        self::assertStringNotContainsString(
            '(',
            substr(
                $this->sqlFor('events', static fn(Blueprint $t) => $t->statistics('s')->on('a', 'b'))[0],
                0,
                40
            )
        );
    }

    #[Test]
    public function namedKindsPrecedeTheColumns(): void
    {
        self::assertSame(
            'create statistics "s" (ndistinct, dependencies) on "a", "b" from "events"',
            $this->sqlFor(
                'events',
                static fn(Blueprint $t) => $t->statistics('s')->on('a', 'b')->kinds('ndistinct', 'dependencies')
            )[0]
        );
    }

    #[Test]
    public function ifNotExistsMakesItRepeatable(): void
    {
        self::assertStringStartsWith(
            'create statistics if not exists "s"',
            $this->sqlFor(
                'events',
                static fn(Blueprint $t) => $t->statistics('s')->ifNotExists()->on('a', 'b')
            )[0]
        );
    }

    /**
     * An `Expression` reaches PostgreSQL verbatim, the same as in an index column list. It needs
     * PostgreSQL 14, which is where statistics over expressions arrived.
     */
    #[Test]
    public function anExpressionIsPassedThrough(): void
    {
        self::assertStringContainsString(
            'on lower(kind), "region"',
            $this->sqlFor(
                'events',
                static fn(Blueprint $t) => $t->statistics('s')->on(new Expression('lower(kind)'), 'region')
            )[0]
        );
    }

    #[Test]
    public function theTablePrefixReachesBothNames(): void
    {
        $sql = $this->sqlFor(
            'events',
            static function (Blueprint $t): void {
                $t->statistics('s')->on('a', 'b');
                $t->dropStatistics('s');
            },
            'pref_'
        );

        self::assertStringContainsString('from "pref_events"', $sql[0]);
        self::assertSame('drop statistics if exists "pref_s"', $sql[1]);
    }

    #[Test]
    public function droppingTakesSeveralAtOnce(): void
    {
        self::assertSame(
            'drop statistics if exists "a", "b"',
            $this->sqlFor('events', static fn(Blueprint $t) => $t->dropStatistics('a', 'b'))[0]
        );
    }

    /** PostgreSQL refuses one column, since that is what it already gathers by itself. */
    #[Test]
    public function oneColumnIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least two columns');

        $this->sqlFor('events', static fn(Blueprint $t) => $t->statistics('s')->on('a'));
    }

    #[Test]
    public function statisticsWithoutColumnsAreRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('call on() with at least two');

        $this->sqlFor('events', static fn(Blueprint $t) => $t->statistics('s'));
    }

    /** @return iterable<string, array{string}> */
    public static function kinds(): iterable
    {
        foreach (['ndistinct', 'dependencies', 'mcv'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[Test]
    #[DataProvider('kinds')]
    public function everyKindPostgresKnowsIsAccepted(string $kind): void
    {
        self::assertStringContainsString(
            "($kind)",
            $this->sqlFor('events', static fn(Blueprint $t) => $t->statistics('s')->on('a', 'b')->kinds($kind))[0]
        );
    }

    #[Test]
    public function anUnknownKindIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown statistics kind');

        $this->sqlFor(
            'events',
            static fn(Blueprint $t) => $t->statistics('s')->on('a', 'b')->kinds('histogram')
        );
    }

    #[Test]
    public function droppingNothingIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one statistics object');

        $this->sqlFor('events', static fn(Blueprint $t) => $t->dropStatistics());
    }
}
