<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * Routing is the whole point of a partitioned table, and only the server does it — so these insert
 * into the parent and look in the children.
 */
class PartitionTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        foreach (['test_table', 'test_logs', 'test_shards', 'test_orphan'] as $table) {
            Schema::dropIfExistsCascade($table);
        }

        parent::tearDown();
    }

    #[Test]
    public function rowsReachThePartitionTheirKeyBelongsTo(): void
    {
        $this->rangeParent();
        Schema::create('test_table_2026', static fn(Blueprint $t) => $t->partitionOf('test_table')
            ->fromTo('2026-01-01', '2027-01-01'));
        Schema::create('test_table_rest', static fn(Blueprint $t) => $t->partitionOf('test_table')
            ->asDefault());

        DB::table('test_table')->insert([
            [
                'id' => 1,
                'at' => '2026-06-01',
            ],
            [
                'id' => 2,
                'at' => '2030-01-01',
            ],
        ]);

        self::assertSame(1, DB::table('test_table_2026')->count());
        self::assertSame(1, DB::table('test_table_rest')->count(), 'the default partition takes the rest');
        self::assertSame(2, DB::table('test_table')->count(), 'the parent sees both');
    }

    #[Test]
    public function aListPartitionTakesTheValuesItNames(): void
    {
        Schema::create('test_logs', static function (Blueprint $t) {
            $t->bigInteger('id');
            $t->string('tenant');
            $t->partitionBy('list', 'tenant');
        });
        Schema::create('test_logs_ab', static fn(Blueprint $t) => $t->partitionOf('test_logs')
            ->in(['a', 'b']));

        DB::table('test_logs')->insert([['id' => 1, 'tenant' => 'a'], ['id' => 2, 'tenant' => 'b']]);

        self::assertSame(2, DB::table('test_logs_ab')->count());
    }

    #[Test]
    public function hashPartitionsDivideTheRows(): void
    {
        Schema::create('test_shards', static function (Blueprint $t) {
            $t->bigInteger('id');
            $t->partitionBy('hash', 'id');
        });

        foreach ([0, 1] as $remainder) {
            Schema::create(
                "test_shards_$remainder",
                static fn(Blueprint $t) => $t->partitionOf('test_shards')->hash(2, $remainder)
            );
        }

        DB::table('test_shards')->insert([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]]);

        self::assertSame(4, DB::table('test_shards')->count());
        self::assertSame(
            4,
            DB::table('test_shards_0')->count() + DB::table('test_shards_1')->count(),
            'every row landed in exactly one shard'
        );
    }

    #[Test]
    public function attachingAdoptsAnExistingTable(): void
    {
        $this->rangeParent();
        DB::statement('create table test_orphan (like test_table including all)');

        Schema::table('test_table', static fn(Blueprint $t) => $t->attachPartition('test_orphan')
            ->fromTo('2025-01-01', '2026-01-01'));

        DB::table('test_table')->insert(['id' => 1, 'at' => '2025-06-01']);

        self::assertSame(1, DB::table('test_orphan')->count());
    }

    #[Test]
    public function detachingLeavesTheRowsBehindInATableOfItsOwn(): void
    {
        $this->rangeParent();
        DB::statement('create table test_orphan (like test_table including all)');
        Schema::table('test_table', static fn(Blueprint $t) => $t->attachPartition('test_orphan')
            ->fromTo('2025-01-01', '2026-01-01'));
        DB::table('test_table')->insert(['id' => 1, 'at' => '2025-06-01']);

        Schema::table('test_table', static fn(Blueprint $t) => $t->detachPartition('test_orphan'));

        self::assertSame(1, DB::table('test_orphan')->count(), 'the rows stay with the table');
        self::assertSame(0, DB::table('test_table')->count(), 'the parent no longer sees them');
    }

    #[Test]
    public function theParentIsAPartitionedRelation(): void
    {
        $this->rangeParent();

        self::assertSame(
            'p',
            DB::selectOne('select relkind from pg_class where relname = ?', ['test_table'])->relkind
        );
    }

    private function rangeParent(): void
    {
        Schema::create('test_table', static function (Blueprint $t) {
            $t->bigInteger('id');
            $t->timestamp('at');
            $t->primary(['id', 'at']);
            $t->partitionBy('range', 'at');
        });
    }
}
