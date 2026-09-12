<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * How a table is stored: unlogged, and the per-table parameters that tune it.
 */
final class StorageTest extends UnitTestCase
{
    #[Test]
    public function unloggedChangesTheCreateVerb(): void
    {
        self::assertSame(
            'create unlogged table "cache" ("k" varchar(255) not null)',
            $this->sqlForCreate('cache', static function (Blueprint $t): void {
                $t->string('k');
                $t->unlogged();
            })[0]
        );
    }

    #[Test]
    public function temporaryStillWins(): void
    {
        $sql = $this->sqlForCreate('cache', static function (Blueprint $t): void {
            $t->string('k');
            $t->temporary();
            $t->unlogged();
        });

        self::assertStringStartsWith('create temporary table', $sql[0], 'the two are mutually exclusive');
    }

    #[Test]
    public function withoutItNothingChanges(): void
    {
        self::assertStringStartsWith(
            'create table',
            $this->sqlForCreate('cache', static fn(Blueprint $t) => $t->string('k'))[0]
        );
    }

    /**
     * Emitted as its own statement rather than as `WITH (…)` on the create, so the same call works
     * on a new table and on one that already exists.
     */
    #[Test]
    public function storageParametersAreTheirOwnStatement(): void
    {
        $sql = $this->sqlForCreate('cache', static function (Blueprint $t): void {
            $t->string('k');
            $t->storageParameters(['fillfactor' => 70, 'autovacuum_vacuum_scale_factor' => 0.05]);
        });

        self::assertSame('create table "cache" ("k" varchar(255) not null)', $sql[0]);
        self::assertSame(
            'alter table "cache" set (fillfactor = 70, autovacuum_vacuum_scale_factor = 0.05)',
            $sql[1]
        );
    }

    #[Test]
    public function resettingNamesTheParameters(): void
    {
        self::assertSame(
            'alter table "cache" reset (fillfactor, autovacuum_enabled)',
            $this->sqlFor(
                'cache',
                static fn(Blueprint $t) => $t->resetStorageParameters('fillfactor', 'autovacuum_enabled')
            )[0]
        );
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function values(): iterable
    {
        yield 'int' => [
            70, 'fillfactor = 70',
        ];
        yield 'float' => [
            0.05, 'fillfactor = 0.05',
        ];
        yield 'true' => [
            true, 'fillfactor = true',
        ];
        yield 'false' => [
            false, 'fillfactor = false',
        ];
        yield 'bare word' => [
            'on', 'fillfactor = on',
        ];
    }

    #[Test]
    #[DataProvider('values')]
    public function aValueMayBeANumberOrABareWord(mixed $value, string $expected): void
    {
        self::assertStringContainsString(
            $expected,
            $this->sqlFor('cache', static fn(Blueprint $t) => $t->storageParameters(['fillfactor' => $value]))[0]
        );
    }

    /** Both halves are interpolated into DDL, so both are checked rather than trusted. */
    #[Test]
    public function anythingElseIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a number or a bare word');

        $this->sqlFor(
            'cache',
            static fn(Blueprint $t) => $t->storageParameters(['fillfactor' => '70); drop table x --'])
        );
    }

    #[Test]
    public function aBogusParameterNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid storage parameter');

        $this->sqlFor('cache', static fn(Blueprint $t) => $t->storageParameters(['fill factor)' => 70]));
    }

    /** Resetting interpolates the name too, so it is checked on that path as well. */
    #[Test]
    public function aBogusNameIsRejectedWhenResettingToo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid storage parameter');

        $this->sqlFor(
            'cache',
            static fn(Blueprint $t) => $t->resetStorageParameters('fillfactor); drop table x --')
        );
    }

    #[Test]
    public function namingNoParameterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one storage parameter');

        $this->sqlFor('cache', static fn(Blueprint $t) => $t->storageParameters([]));
    }

    #[Test]
    public function resettingNothingIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one storage parameter');

        $this->sqlFor('cache', static fn(Blueprint $t) => $t->resetStorageParameters());
    }
}
