<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Query;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * Reading back what the database generated is the point, so these need a database: defaults,
 * sequences and `now()` only exist on the server.
 */
class InsertAndReturnTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->primaryUUID();
                $table->string('state')->default('new');
                $table->integer('total');
            }
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    #[Test]
    public function itReturnsTheColumnsTheDatabaseGenerated(): void
    {
        $rows = DB::table('test_table')->insertAndReturn(['total' => 10], 'id', 'state');

        self::assertCount(1, $rows);
        self::assertNotEmpty($rows[0]->id, 'the generated uuid comes back');
        self::assertSame('new', $rows[0]->state, 'so does the column default');
    }

    /**
     * The reason this exists: `insertGetId()` can return one key of one row, and no more.
     */
    #[Test]
    public function aBatchReturnsEveryRow(): void
    {
        $rows = DB::table('test_table')->insertAndReturn(
            [
                ['total' => 20], ['total' => 30], ['total' => 40],
            ],
            'id',
            'total'
        );

        self::assertCount(3, $rows);

        $totals = array_map(static fn(object $row): int => $row->total, $rows);
        sort($totals);

        self::assertSame([20, 30, 40], $totals);
        self::assertCount(3, array_unique(array_map(static fn(object $r): string => $r->id, $rows)));
    }

    #[Test]
    public function starReturnsWholeRows(): void
    {
        $rows = DB::table('test_table')->insertAndReturn(['total' => 50], '*');

        self::assertNotEmpty($rows[0]->id);
        self::assertSame('new', $rows[0]->state);
        self::assertSame(50, $rows[0]->total);
    }

    #[Test]
    public function theRowsAreActuallyInserted(): void
    {
        DB::table('test_table')->insertAndReturn([['total' => 60], ['total' => 70]], 'id');

        self::assertSame(2, DB::table('test_table')->count());
    }

    #[Test]
    public function nothingToInsertInsertsNothing(): void
    {
        self::assertSame([], DB::table('test_table')->insertAndReturn([], 'id'));
        self::assertSame(0, DB::table('test_table')->count());
    }

    /**
     * Naming no column emits no `RETURNING`, and PostgreSQL then reports one column-less row per
     * row affected. Odd, but it is what `updateAndReturn()` and `deleteAndReturn()` have always
     * done, and the three should not disagree.
     */
    #[Test]
    public function namingNoColumnMatchesTheOtherTwoVerbs(): void
    {
        $rows = DB::table('test_table')->insertAndReturn(['total' => 80]);

        self::assertCount(1, $rows);
        self::assertSame([], (array)$rows[0]);
        self::assertSame(1, DB::table('test_table')->count(), 'the row is still inserted');
    }

    /**
     * Rows arrive the way `DB::select()` would return them, not as arrays.
     */
    #[Test]
    public function rowsRespectTheConnectionsFetchMode(): void
    {
        $rows = DB::table('test_table')->insertAndReturn(['total' => 90], 'total');

        self::assertIsObject($rows[0]);
    }
}
