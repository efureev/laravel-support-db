<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * "No two bookings for the same room may overlap" is one statement in PostgreSQL and nothing at
 * all in Laravel. Only the server can say whether the constraint actually holds, so these book
 * rooms and watch.
 */
class ExclusionConstraintTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // a scalar column alongside a range needs btree_gist; gist alone cannot index the int
        Schema::createExtensionIfNotExists('btree_gist');

        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->integer('room_id');
                $table->tsRange('during');
                $table->timestamp('cancelled_at')->nullable();

                $table->exclusion('test_table_no_overlap')
                    ->using('gist')
                    ->with('room_id', '=')
                    ->with('during', '&&')
                    ->whereNull('cancelled_at');
            }
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    #[Test]
    public function bookingsThatDoNotOverlapAreAccepted(): void
    {
        $this->book(1, '[2026-01-01,2026-01-05)');
        $this->book(1, '[2026-01-05,2026-01-09)');

        self::assertSame(2, DB::table('test_table')->count());
    }

    #[Test]
    public function anotherRoomMayOverlapFreely(): void
    {
        $this->book(1, '[2026-01-01,2026-01-05)');
        $this->book(2, '[2026-01-01,2026-01-05)');

        self::assertSame(2, DB::table('test_table')->count());
    }

    #[Test]
    public function anOverlappingBookingIsRefused(): void
    {
        $this->book(1, '[2026-01-01,2026-01-05)');

        $this->assertRefused(fn() => $this->book(1, '[2026-01-03,2026-01-08)'));
    }

    /**
     * The predicate narrows the constraint the same way it narrows a partial index: a cancelled
     * booking is not part of it and may overlap anything.
     */
    #[Test]
    public function aRowOutsideThePredicateIsNotConstrained(): void
    {
        $this->book(1, '[2026-01-01,2026-01-05)');
        $this->book(1, '[2026-01-02,2026-01-04)', cancelled: true);

        self::assertSame(2, DB::table('test_table')->count());
    }

    #[Test]
    public function theCatalogueShowsWhatWasAskedFor(): void
    {
        self::assertSame(
            'EXCLUDE USING gist (room_id WITH =, during WITH &&) WHERE ((cancelled_at IS NULL))',
            DB::selectOne(
                'select pg_get_constraintdef(oid) as def from pg_constraint where conname = ?',
                ['test_table_no_overlap']
            )->def
        );
    }

    #[Test]
    public function droppingItStopsTheEnforcement(): void
    {
        Schema::table(
            'test_table',
            static fn(Blueprint $table) => $table->dropConstraint('test_table_no_overlap')
        );

        $this->book(1, '[2026-01-01,2026-01-05)');
        $this->book(1, '[2026-01-03,2026-01-08)');

        self::assertSame(2, DB::table('test_table')->count());
    }

    private function book(int $room, string $during, bool $cancelled = false): void
    {
        DB::table('test_table')->insert([
            'room_id'      => $room,
            'during'       => $during,
            'cancelled_at' => $cancelled ? now() : null,
        ]);
    }

    /**
     * A refused write aborts the transaction the suite runs in; a nested one confines that to a
     * savepoint so `tearDown()` still has a usable connection.
     */
    private function assertRefused(callable $write): void
    {
        try {
            DB::transaction(static function () use ($write): void {
                $write();
            });
            self::fail('the exclusion constraint was expected to refuse the booking');
        } catch (QueryException $e) {
            self::assertStringContainsString('test_table_no_overlap', $e->getMessage());
        }
    }
}
