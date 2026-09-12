<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * Statistics exist to change what the planner believes, so the test that matters asks the planner.
 */
class StatisticsTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExistsCascade('test_table');

        parent::tearDown();
    }

    #[Test]
    public function theCatalogueRecordsTheKindsAsked(): void
    {
        $this->table(static function (Blueprint $t) {
            $t->statistics('test_all')->on('kind', 'region');
            $t->statistics('test_two')->on('kind', 'region')->kinds('ndistinct', 'dependencies');
        });

        self::assertSame(
            '{d,f,m}',
            $this->kindsOf('test_all'),
            'all three by default: ndistinct, dependencies, mcv'
        );
        self::assertSame('{d,f}', $this->kindsOf('test_two'));
    }

    /**
     * `kind` and `region` are perfectly correlated here. Without statistics the planner multiplies
     * the two selectivities as though they were independent and expects about a ninth of the
     * table; with them it knows one implies the other and expects a third.
     */
    #[Test]
    public function theyChangeWhatThePlannerExpects(): void
    {
        $this->table(static fn(Blueprint $t) => null);
        $this->fill();

        $independent = $this->estimate();

        Schema::table(
            'test_table',
            static fn(Blueprint $t) => $t->statistics('test_correlated')->on('kind', 'region')
        );
        DB::statement('analyze test_table');

        $informed = $this->estimate();

        self::assertGreaterThan(
            $independent * 2,
            $informed,
            'the planner stopped treating the two columns as independent'
        );
    }

    #[Test]
    public function ifNotExistsMakesTheMigrationRepeatable(): void
    {
        $this->table(static fn(Blueprint $t) => $t->statistics('test_s')->on('kind', 'region'));

        Schema::table(
            'test_table',
            static fn(Blueprint $t) => $t->statistics('test_s')->ifNotExists()->on('kind', 'region')
        );

        self::assertSame(1, $this->countNamed('test_s'));
    }

    #[Test]
    public function droppingRemovesThem(): void
    {
        $this->table(static function (Blueprint $t) {
            $t->statistics('test_a')->on('kind', 'region');
            $t->statistics('test_b')->on('kind', 'at');
        });

        Schema::table('test_table', static fn(Blueprint $t) => $t->dropStatistics('test_a', 'test_b'));

        self::assertSame(0, $this->countNamed('test_a') + $this->countNamed('test_b'));
    }

    private function table(callable $extra): void
    {
        Schema::create('test_table', static function (Blueprint $t) use ($extra) {
            $t->increments('id');
            $t->string('kind');
            $t->string('region');
            $t->date('at');

            $extra($t);
        });
    }

    private function fill(): void
    {
        DB::statement(
            'insert into test_table (kind, region, at)
             select (i % 3)::text, (i % 3)::text, current_date from generate_series(1, 5000) i'
        );
        DB::statement('analyze test_table');
    }

    private function estimate(): float
    {
        $plan = DB::selectOne(
            'explain (format json) select * from test_table where kind = ?::text and region = ?::text',
            [
                '1', '1',
            ]
        );

        return (float)json_decode($plan->{'QUERY PLAN'}, true)[0]['Plan']['Plan Rows'];
    }

    private function kindsOf(string $name): string
    {
        return DB::selectOne('select stxkind from pg_statistic_ext where stxname = ?', [$name])->stxkind;
    }

    private function countNamed(string $name): int
    {
        return (int)DB::selectOne(
            'select count(*) as c from pg_statistic_ext where stxname = ?',
            [$name]
        )->c;
    }
}
