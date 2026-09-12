<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Query;

use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Database\Factories\TestModelFactory;
use Php\Support\Laravel\Database\Tests\Models\TestModel;

/**
 * `Connection::bindValues()` takes a different path when PDO emulates prepared statements, and
 * `affectingStatementArray()` has a pretend branch. Neither is reachable from the default test
 * connection, so both went unexercised.
 */
class ConnectionBindingsTest extends AbstractTestCase
{
    /** @var list<string> */
    protected array $migrations = ['2021_11_15_000000_create_test_table.php'];

    #[Test]
    public function valuesRoundTripWhenPdoEmulatesPreparedStatements(): void
    {
        $connection = DB::connection();
        $connection->getPdo()->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        try {
            self::assertTrue(
                (bool)$connection->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES),
                'the driver must actually honour the attribute for this test to mean anything'
            );

            DB::table('tests')->insert(
                [
                    'id'      => '11111111-1111-4111-8111-111111111111',
                    'name'    => "O'Brien",
                    'enabled' => true,
                ]
            );

            $row = DB::table('tests')->where('name', "O'Brien")->first();

            self::assertNotNull($row);
            self::assertTrue($row->enabled);

            // Each arm of the match in the emulated branch: bool, null and the default.
            $probe = DB::selectOne(
                'select ?::boolean as b, ?::text as n, ?::int as i, ?::text as s',
                [
                    false,
                    null,
                    7,
                    "quote'd",
                ]
            );

            self::assertFalse($probe->b);
            self::assertNull($probe->n);
            self::assertSame(7, $probe->i);
            self::assertSame("quote'd", $probe->s);
        } finally {
            $connection->getPdo()->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
    }

    #[Test]
    public function returningStatementsRunNothingWhilePretending(): void
    {
        TestModelFactory::times(2)->create(['enabled' => true]);

        $queries = DB::pretend(
            static function (): void {
                TestModel::toBase()->updateAndReturn(['enabled' => false], 'id');
            }
        );

        self::assertNotEmpty($queries, 'the statement must still be logged');
        self::assertStringContainsString('returning', strtolower($queries[0]['query']));

        // Nothing was actually written.
        self::assertSame(2, TestModel::where('enabled', true)->count());
    }
}
