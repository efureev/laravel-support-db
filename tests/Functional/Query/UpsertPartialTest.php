<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Query;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * The package's own partial unique index is what makes the framework's `upsert()` unusable, so
 * these run against a server: only PostgreSQL can say whether the conflict target it was given
 * actually matches the index.
 */
class UpsertPartialTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('email');
                $table->string('name');
                $table->softDeletes();

                $table->uniquePartial('email')->whereNull('deleted_at');
            }
        );

        DB::table('test_table')->insert(['email' => 'a@x.io', 'name' => 'A', 'deleted_at' => null]);
        DB::table('test_table')->insert(['email' => 'a@x.io', 'name' => 'gone', 'deleted_at' => now()]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    /** The state this feature exists to fix. */
    #[Test]
    public function withoutThePredicatePostgresRefusesTheConflictTarget(): void
    {
        $this->assertRefused(
            static fn() => DB::table('test_table')
                ->upsert([['email' => 'a@x.io', 'name' => 'X']], ['email'], ['name'])
        );
    }

    #[Test]
    public function withThePredicateTheLiveRowIsUpdated(): void
    {
        $this->upsert('a@x.io', 'B');

        self::assertSame('B', DB::table('test_table')->whereNull('deleted_at')->value('name'));
        self::assertSame(2, DB::table('test_table')->count(), 'updated, not inserted');
    }

    #[Test]
    public function theRowsOutsideThePredicateAreNotTouched(): void
    {
        $this->upsert('a@x.io', 'B');

        self::assertSame(
            'gone',
            DB::table('test_table')->whereNotNull('deleted_at')->value('name'),
            'the soft-deleted row is not part of the index and must not be updated'
        );
    }

    #[Test]
    public function anUnseenValueIsStillInserted(): void
    {
        $this->upsert('b@x.io', 'C');

        self::assertSame(3, DB::table('test_table')->count());
        self::assertSame('C', DB::table('test_table')->where('email', 'b@x.io')->value('name'));
    }

    /**
     * The predicate has to match the index, not merely be valid SQL: PostgreSQL compares the two
     * and rejects one that does not correspond.
     */
    #[Test]
    public function aPredicateThatDoesNotMatchTheIndexIsRejected(): void
    {
        $this->assertRefused(
            static fn() => DB::table('test_table')
                ->onConflictWhere(static fn(PartialBuilder $w) => $w->whereNotNull('deleted_at'))
                ->upsert([['email' => 'a@x.io', 'name' => 'X']], ['email'], ['name'])
        );
    }

    /**
     * A statement PostgreSQL refuses aborts the transaction the whole suite runs inside, and
     * everything after it — `tearDown()` included — then fails with `current transaction is
     * aborted`. Running it in a nested transaction confines the damage to a savepoint.
     */
    private function assertRefused(callable $statement): void
    {
        try {
            DB::transaction(static function () use ($statement): void {
                $statement();
            });
            self::fail('PostgreSQL was expected to refuse the ON CONFLICT target');
        } catch (QueryException $e) {
            self::assertStringContainsString(
                'no unique or exclusion constraint matching the ON CONFLICT',
                $e->getMessage()
            );
        }
    }

    private function upsert(string $email, string $name): void
    {
        DB::table('test_table')
            ->onConflictWhere(static fn(PartialBuilder $where) => $where->whereNull('deleted_at'))
            ->upsert([['email' => $email, 'name' => $name]], ['email'], ['name']);
    }
}
