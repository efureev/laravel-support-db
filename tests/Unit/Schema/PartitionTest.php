<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Declarative partitioning: a parent that holds no rows and children that do. Laravel has no form
 * for any of it.
 */
final class PartitionTest extends UnitTestCase
{
    // ---------------------------------------------------------------- the parent

    #[Test]
    public function theStrategyAndKeyFollowTheColumnList(): void
    {
        $sql = $this->sqlForCreate(
            'events',
            static function (Blueprint $t): void {
                $t->bigInteger('id');
                $t->timestamp('at');
                $t->partitionBy('range', 'at');
            }
        );

        self::assertStringEndsWith('partition by range ("at")', $sql[0]);
    }

    #[Test]
    public function severalKeysAreListed(): void
    {
        $sql = $this->sqlForCreate(
            'events',
            static function (Blueprint $t): void {
                $t->string('tenant');
                $t->timestamp('at');
                $t->partitionBy('range', ['tenant', 'at']);
            }
        );

        self::assertStringEndsWith('partition by range ("tenant", "at")', $sql[0]);
    }

    #[Test]
    public function anUnknownStrategyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown partition strategy');

        $this->sqlForCreate(
            'events',
            static function (Blueprint $t): void {
                $t->timestamp('at');
                $t->partitionBy('rangey', 'at');
            }
        );
    }

    #[Test]
    public function aPartitionedTableNeedsAKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one partition key');

        $this->sqlForCreate(
            'events',
            static function (Blueprint $t): void {
                $t->timestamp('at');
                $t->partitionBy('range', []);
            }
        );
    }

    /**
     * PostgreSQL requires the primary key to contain every partitioning column and says so
     * obscurely. `bigIncrements('id')` beside `partitionBy('range', 'at')` is the ordinary-looking
     * pair that trips it, so the package says it first.
     */
    #[Test]
    public function aPrimaryKeyThatMissesThePartitionKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain every partitioning column');

        $this->sqlForCreate(
            'events',
            static function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->timestamp('at');
                $t->partitionBy('range', 'at');
            }
        );
    }

    #[Test]
    public function aCompositePrimaryKeyCoveringTheKeyIsFine(): void
    {
        $sql = $this->sqlForCreate(
            'events',
            static function (Blueprint $t): void {
                $t->bigInteger('id');
                $t->timestamp('at');
                $t->primary(['id', 'at']);
                $t->partitionBy('range', 'at');
            }
        );

        self::assertStringEndsWith('partition by range ("at")', $sql[0]);
    }

    #[Test]
    public function withoutAPrimaryKeyThereIsNothingToCheck(): void
    {
        $sql = $this->sqlForCreate(
            'events',
            static function (Blueprint $t): void {
                $t->timestamp('at');
                $t->partitionBy('range', 'at');
            }
        );

        self::assertStringEndsWith('partition by range ("at")', $sql[0]);
    }

    // ---------------------------------------------------------------- the children

    /** @return iterable<string, array{callable(Blueprint): mixed, string}> */
    public static function bounds(): iterable
    {
        yield 'range' => [
            static fn(Blueprint $t) => $t->partitionOf('events')->fromTo('2026-01-01', '2027-01-01'),
            'for values from (\'2026-01-01\') to (\'2027-01-01\')',
        ];
        yield 'range over numbers' => [
            static fn(Blueprint $t) => $t->partitionOf('events')->fromTo(0, 1000),
            'for values from (0) to (1000)',
        ];
        yield 'list' => [
            static fn(Blueprint $t) => $t->partitionOf('logs')->in(['a', 'b']),
            'for values in (\'a\', \'b\')',
        ];
        yield 'hash' => [
            static fn(Blueprint $t) => $t->partitionOf('shards')->hash(4, 1),
            'for values with (modulus 4, remainder 1)',
        ];
        yield 'default' => [
            static fn(Blueprint $t) => $t->partitionOf('events')->asDefault(),
            'default',
        ];
    }

    #[Test]
    #[DataProvider('bounds')]
    public function aChildCarriesItsBoundsAndNoColumnList(callable $define, string $expected): void
    {
        $sql = $this->sqlForCreate('part', static fn(Blueprint $t) => $define($t));

        self::assertStringContainsString(' partition of ', $sql[0]);
        self::assertStringEndsWith($expected, $sql[0]);
        self::assertStringNotContainsString('(  )', $sql[0], 'a child inherits the parent\'s columns');
    }

    #[Test]
    public function aQuoteInABoundIsEscaped(): void
    {
        $sql = $this->sqlForCreate(
            'part',
            static fn(Blueprint $t) => $t->partitionOf('logs')->in(["O'Brien"])
        );

        self::assertStringEndsWith("for values in ('O''Brien')", $sql[0]);
    }

    #[Test]
    public function aPartitionBelongsToOneStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already has range bounds');

        $this->sqlForCreate(
            'part',
            static fn(Blueprint $t) => $t->partitionOf('events')->fromTo(1, 2)->in(['a'])
        );
    }

    #[Test]
    public function aPartitionWithoutBoundsIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no bounds');

        $this->sqlForCreate('part', static fn(Blueprint $t) => $t->partitionOf('events'));
    }

    /** @return iterable<string, array{int, int}> */
    public static function badHashBounds(): iterable
    {
        yield 'modulus below one' => [
            0, 0,
        ];
        yield 'remainder equal to the modulus' => [
            4, 4,
        ];
        yield 'negative remainder' => [
            4, -1,
        ];
    }

    #[Test]
    #[DataProvider('badHashBounds')]
    public function hashBoundsMustMakeSense(int $modulus, int $remainder): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sqlForCreate(
            'part',
            static fn(Blueprint $t) => $t->partitionOf('shards')->hash($modulus, $remainder)
        );
    }

    #[Test]
    public function anEmptyListIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one value');

        $this->sqlForCreate('part', static fn(Blueprint $t) => $t->partitionOf('logs')->in([]));
    }

    // ---------------------------------------------------------------- attach and detach

    #[Test]
    public function attachingNamesTheBounds(): void
    {
        $sql = $this->sqlFor(
            'events',
            static fn(Blueprint $t) => $t->attachPartition('events_2025')->fromTo('2025-01-01', '2026-01-01')
        );

        self::assertSame(
            'alter table "events" attach partition "events_2025" '
            . 'for values from (\'2025-01-01\') to (\'2026-01-01\')',
            $sql[0]
        );
    }

    #[Test]
    public function detachingDoesNot(): void
    {
        $sql = $this->sqlFor(
            'events',
            static function (Blueprint $t): void {
                $t->detachPartition('events_2024');
                $t->detachPartition('events_2023', true);
            }
        );

        self::assertSame('alter table "events" detach partition "events_2024"', $sql[0]);
        self::assertSame(
            'alter table "events" detach partition "events_2023" concurrently',
            $sql[1]
        );
    }

    #[Test]
    public function everyStatementCarriesTheTablePrefix(): void
    {
        $sql = $this->sqlFor(
            'events',
            static function (Blueprint $t): void {
                $t->attachPartition('events_2025')->asDefault();
                $t->detachPartition('events_2024');
            },
            'pref_'
        );

        self::assertStringStartsWith('alter table "pref_events" attach partition "pref_events_2025"', $sql[0]);
        self::assertStringStartsWith('alter table "pref_events" detach partition "pref_events_2024"', $sql[1]);
    }
}
