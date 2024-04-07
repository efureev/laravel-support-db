<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Query;

use Php\Support\Laravel\Database\Query\Builder;
use Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Database\Factories\TestModelFactory;
use Php\Support\Laravel\Database\Tests\Models\TestModel;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderTest extends AbstractTestCase
{
    protected array $migrations = [
        '2021_11_15_000000_create_test_table.php',
    ];

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

    #[Test]
    public function returnColsOnUpdateFromBaseQuery(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);
        $list = TestModel::toBase()->updateAndReturn(['enabled' => false], 'id', 'name');

        self::assertCount(5, $list);

        foreach ($list as $item) {
            self::assertCount(2, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('name', $item);
        }

        $list = TestModel::toBase()->deleteAndReturn('id', 'name');

        self::assertCount(5, $list);

        foreach ($list as $item) {
            self::assertCount(2, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('name', $item);
        }
    }

    #[Test]
    public function returnColsOnUpdateFromQuery(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);

        $list = TestModel::query()->updateAndReturn(['enabled' => false], 'id', 'name');

        self::assertCount(5, $list);
        foreach ($list as $item) {
            self::assertCount(2, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('name', $item);
        }
    }

    #[Test]
    public function returnColsOnUpdateFromWhere(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);
        $list = TestModel::where(['enabled' => true])->updateAndReturn(['enabled' => false], 'id', 'name');

        self::assertCount(5, $list);
        foreach ($list as $item) {
            self::assertCount(2, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('name', $item);
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
            self::assertCount(1, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayNotHasKey('name', $item);
        }
    }


    #[Test]
    public function returnColsOnDeleteFromBaseQuery(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);
        $list = TestModel::toBase()->deleteAndReturn('id', 'name');

        self::assertCount(5, $list);

        foreach ($list as $item) {
            self::assertCount(2, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('name', $item);
        }
    }

    #[Test]
    public function returnColsOnDeleteFromQuery(): void
    {
        TestModelFactory::times(5)->create(['enabled' => true]);

        $list = TestModel::query()->deleteAndReturn('id', 'name');

        self::assertCount(5, $list);
        foreach ($list as $item) {
            self::assertCount(2, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('name', $item);
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
            self::assertCount(2, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('name', $item);
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
            self::assertCount(1, $item);
            self::assertArrayHasKey('id', $item);
            self::assertArrayNotHasKey('name', $item);
        }
    }
}
