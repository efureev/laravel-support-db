<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Query;

use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Php\Support\Laravel\Database\Query\Builder;
use Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Database\Factories\TestModelFactory;
use Php\Support\Laravel\Database\Tests\Models\TestModel;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class QueryBuilderTest extends AbstractTestCase
{
    protected array $migrations = ['2021_11_15_000000_create_test_table.php'];

    #[Test]
    public function createCustomQuery(): void
    {
        /** @var Connection $connection */
        $connection = $this->app['db.connection'];

        self::assertInstanceOf(Connection::class, $connection);
        self::assertInstanceOf(Builder::class, $connection->query());
        self::assertInstanceOf(PostgresGrammar::class, $connection->getQueryGrammar());
        self::assertInstanceOf(PostgresGrammar::class, $connection->query()->getGrammar());
    }

    /**
     * `recordsHaveBeenModified()` used to receive the row list instead of a bool, leaving an
     * array in `Connection::$recordsModified` and skewing the sticky-connection check
     * (AUDIT.md D11).
     */
    #[Test]
    public function modificationStateStaysBoolean(): void
    {
        $connection = DB::connection();
        $connection->forgetRecordModificationState();

        TestModelFactory::times(2)->create(['enabled' => true]);
        TestModel::toBase()->updateAndReturn(['enabled' => false], 'id');

        self::assertTrue($connection->hasModifiedRecords());

        $connection->forgetRecordModificationState();
        self::assertFalse($connection->hasModifiedRecords());

        // An update that matches nothing must not flag the connection either.
        TestModel::toBase()->where('name', '__no_such_row__')->updateAndReturn(['enabled' => true], 'id');

        self::assertFalse($connection->hasModifiedRecords());
    }

    /**
     * RETURNING rows used to be fetched with a hardcoded `PDO::FETCH_ASSOC`, bypassing
     * `Connection::prepared()`. That made them the only result set in the connection shaped as
     * arrays, ignored a configured fetch mode, and skipped the `StatementPrepared` event that
     * packages hook to change it. See AUDIT.md §7.
     */
    #[Test]
    public function returnedRowsHaveTheSameShapeAsASelect(): void
    {
        TestModelFactory::times(1)->create(['enabled' => true]);

        $selected = DB::table('tests')->first();
        $returned = TestModel::toBase()->updateAndReturn(['enabled' => false], 'id')[0];

        self::assertInstanceOf(stdClass::class, $selected);
        self::assertInstanceOf(stdClass::class, $returned);
    }

    #[Test]
    public function preparingTheStatementIsObservable(): void
    {
        TestModelFactory::times(1)->create(['enabled' => true]);

        $seen = [];
        Event::listen(
            StatementPrepared::class,
            static function (StatementPrepared $event) use (&$seen): void {
                $seen[] = $event;
            }
        );

        TestModel::toBase()->updateAndReturn(['enabled' => false], 'id');

        self::assertNotEmpty($seen, 'StatementPrepared must fire for RETURNING statements too');
    }

    #[Test]
    public function returnColsOnUpdateFromBaseQuery(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);
        $list = TestModel::toBase()->updateAndReturn(['enabled' => false], 'id', 'name');

        self::assertCount(5, $list);

        foreach ($list as $item) {
            self::assertCount(2, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectHasProperty('name', $item);
        }

        $list = TestModel::toBase()->deleteAndReturn('id', 'name');

        self::assertCount(5, $list);

        foreach ($list as $item) {
            self::assertCount(2, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectHasProperty('name', $item);
        }
    }

    #[Test]
    public function returnColsOnUpdateFromQuery(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);

        $list = TestModel::query()->updateAndReturn(['enabled' => false], 'id', 'name');

        self::assertCount(5, $list);
        foreach ($list as $item) {
            self::assertCount(2, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectHasProperty('name', $item);
        }
    }

    #[Test]
    public function returnColsOnUpdateFromWhere(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);
        $list = TestModel::where(['enabled' => true])->updateAndReturn(['enabled' => false], 'id', 'name');

        self::assertCount(5, $list);
        foreach ($list as $item) {
            self::assertCount(2, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectHasProperty('name', $item);
        }
    }

    #[Test]
    public function returnColsOnUpdateFromModel(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);

        /** @var TestModel $model */
        $model = TestModel::first();

        $list = $model->newQuery()->updateAndReturn(['enabled' => false], 'id');

        self::assertCount(5, $list);
        foreach ($list as $item) {
            self::assertCount(1, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectNotHasProperty('name', $item);
        }
    }


    #[Test]
    public function returnColsOnDeleteFromBaseQuery(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);
        $list = TestModel::toBase()->deleteAndReturn('id', 'name');

        self::assertCount(5, $list);

        foreach ($list as $item) {
            self::assertCount(2, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectHasProperty('name', $item);
        }
    }

    #[Test]
    public function returnColsOnDeleteFromQuery(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);

        $list = TestModel::query()->deleteAndReturn('id', 'name');

        self::assertCount(5, $list);
        foreach ($list as $item) {
            self::assertCount(2, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectHasProperty('name', $item);
        }
    }

    #[Test]
    public function returnColsOnDeleteFromWhere(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);
        TestModelFactory::times(2)->create(['enabled' => false]);
        $list = TestModel::where(['enabled' => false])->deleteAndReturn('id', 'name');

        self::assertCount(2, $list);
        foreach ($list as $item) {
            self::assertCount(2, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectHasProperty('name', $item);
        }
    }

    #[Test]
    public function returnColsOnDeleteFromModel(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);

        /** @var TestModel $model */
        $model = TestModel::first();

        $list = $model->newQuery()->deleteAndReturn('id');

        self::assertCount(5, $list);
        foreach ($list as $item) {
            self::assertCount(1, (array)$item);
            self::assertObjectHasProperty('id', $item);
            self::assertObjectNotHasProperty('name', $item);
        }
    }
}
