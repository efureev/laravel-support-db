<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\ConnectionFactory;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ConnectionTest extends AbstractTestCase
{
    #[Test]
    public function connectionFactory(): void
    {
        $factory = new ConnectionFactory($this->app);

        static::assertInstanceOf(SQLiteConnection::class, $factory->make(config('database.connections.sqlite')));
    }

    #[Test]
    public function checkConnection(): void
    {
        static::assertInstanceOf(Connection::class, $this->app['db.connection']);
    }

    #[Test]
    #[DataProvider('boolDataProvider')]
    public function boolTrueBindingsWorks($value): void
    {
        $table = 'test_table';
        $data = [
            'field' => $value,
        ];

        Schema::create(
            $table,
            static function (Blueprint $table) {
                $table->increments('id');
                $table->boolean('field');
            }
        );
        DB::table($table)->insert($data);

        $result = DB::table($table)->select($data);

        static::assertSame(1, $result->count());
    }

    #[Test]
    #[DataProvider('intDataProvider')]
    public function intBindingsWorks($value): void
    {
        $table = 'test_table';
        $data = [
            'field' => $value,
        ];
        Schema::create(
            $table,
            static function (Blueprint $table) {
                $table->increments('id');
                $table->integer('field');
            }
        );
        DB::table($table)->insert($data);
        $result = DB::table($table)->select($data);
        static::assertSame(1, $result->count());
    }


    #[Test]
    public function stringBindingsWorks(): void
    {
        $table = 'test_table';
        $data = [
            'field' => 'string',
        ];
        Schema::create(
            $table,
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('field');
            }
        );
        DB::table($table)->insert($data);
        $result = DB::table($table)->select($data);
        static::assertSame(1, $result->count());
    }

    #[Test]
    public function nullBindingsWorks(): void
    {
        $table = 'test_table';
        $data = [
            'field' => null,
        ];
        Schema::create(
            $table,
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('field')
                    ->nullable();
            }
        );
        DB::table($table)->insert($data);
        $result = DB::table($table)->whereNull('field')->get();
        static::assertSame(1, $result->count());
    }

    #[Test]
    #[DataProvider('dateDataProvider')]
    public function dateTimeBindingsWorks($value): void
    {
        $table = 'test_table';
        $data = [
            'field' => $value,
        ];
        Schema::create(
            $table,
            static function (Blueprint $table) {
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
    /*protected function setUp(): void
    {
        parent::setUp();

        Facade::clearResolvedInstances();
    }*/


    public static function boolDataProvider(): ?\Generator
    {
        yield 'true' => [true];
        yield 'false' => [false];
    }

    public static function intDataProvider(): ?\Generator
    {
        yield 'zero' => [0];
        yield 'non-zero' => [10];
    }

    public static function dateDataProvider(): ?\Generator
    {
        yield 'as string' => ['2019-01-01 13:12:22'];
        yield 'as Carbon object' => [new Carbon('2019-01-01 13:12:22')];
    }
}
