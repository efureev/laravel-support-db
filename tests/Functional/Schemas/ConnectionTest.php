<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Tests\Functional\Schemas;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\ExtendedPostgresProvider;
use Php\Support\Laravel\Schemas\Blueprints\ExtendedBlueprint;
use Php\Support\Laravel\Schemas\ConnectionFactory;
use Php\Support\Laravel\Tests\Functional\AbstractFunctionalTestCase;

class ConnectionTest extends AbstractFunctionalTestCase
{
    /**
     * @test
     */
    public function connectionFactory(): void
    {
        $factory = new ConnectionFactory($this->app);

        static::assertInstanceOf(SQLiteConnection::class, $factory->make(config('database.connections.sqlite')));
    }


    /**
     * @test
     */
    public function checkConnection(): void
    {
        static::assertInstanceOf(\Php\Support\Laravel\Schemas\PostgresConnection::class, $this->app['db.connection']);
    }


    /**
     * @test
     * @dataProvider boolDataProvider
     *
     * @param $value
     */
    public function boolTrueBindingsWorks($value): void
    {
        $table = 'test_table';
        $data  = [
            'field' => $value,
        ];

        Schema::create(
            $table,
            static function (ExtendedBlueprint $table) {
                $table->increments('id');
                $table->boolean('field');
            }
        );
        DB::table($table)->insert($data);

        $result = DB::table($table)->select($data);

        static::assertSame(1, $result->count());
    }

    /**
     * @test
     * @dataProvider intDataProvider
     *
     * @param $value
     */
    public function intBindingsWorks($value): void
    {
        $table = 'test_table';
        $data  = [
            'field' => $value,
        ];
        Schema::create(
            $table,
            static function (ExtendedBlueprint $table) {
                $table->increments('id');
                $table->integer('field');
            }
        );
        DB::table($table)->insert($data);
        $result = DB::table($table)->select($data);
        static::assertSame(1, $result->count());
    }


    /**
     * @test
     */
    public function stringBindingsWorks(): void
    {
        $table = 'test_table';
        $data  = [
            'field' => 'string',
        ];
        Schema::create(
            $table,
            function (ExtendedBlueprint $table) {
                $table->increments('id');
                $table->string('field');
            }
        );
        DB::table($table)->insert($data);
        $result = DB::table($table)->select($data);
        static::assertSame(1, $result->count());
    }

    /**
     * @test
     */
    public function nullBindingsWorks(): void
    {
        $table = 'test_table';
        $data  = [
            'field' => null,
        ];
        Schema::create(
            $table,
            static function (ExtendedBlueprint $table) {
                $table->increments('id');
                $table->string('field')
                    ->nullable();
            }
        );
        DB::table($table)->insert($data);
        $result = DB::table($table)->whereNull('field')->get();
        static::assertSame(1, $result->count());
    }

    /**
     * @test
     * @dataProvider dateDataProvider
     *
     * @param $value
     */
    public function dateTimeBindingsWorks($value): void
    {
        $table = 'test_table';
        $data  = [
            'field' => $value,
        ];
        Schema::create(
            $table,
            static function (ExtendedBlueprint $table) {
                $table->increments('id');
                $table->dateTime('field');
            }
        );
        DB::table($table)->insert($data);
        $result = DB::table($table)->select($data);
        static::assertSame(1, $result->count());
    }


    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Facade::clearResolvedInstances();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ExtendedPostgresProvider::class,
        ];
    }


    public function boolDataProvider(): ?\Generator
    {
        yield 'true' => [true];
        yield 'false' => [false];
    }

    public function intDataProvider(): ?\Generator
    {
        yield 'zero' => [0];
        yield 'non-zero' => [10];
    }

    public function dateDataProvider(): ?\Generator
    {
        yield 'as string' => ['2019-01-01 13:12:22'];
        yield 'as Carbon object' => [new Carbon('2019-01-01 13:12:22')];
    }
}
