<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Contracts\Database\LostConnectionDetector;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;
use Php\Support\Laravel\Database\ServiceProvider;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Generator;
use ReflectionMethod;

class ConnectionTest extends AbstractTestCase
{
    /**
     * The package registers a driver resolver instead of subclassing the connection factory;
     * the framework's own factory consults it before its own `match`.
     */
    #[Test]
    public function pgsqlResolverIsRegistered(): void
    {
        $resolver = BaseConnection::getResolver('pgsql');

        static::assertNotNull($resolver);
        static::assertInstanceOf(
            Connection::class,
            $resolver(static fn() => null, 'testing', '', ['driver' => 'pgsql'])
        );
    }

    /**
     * The package used to carry a hand-copied `registerConnectionServices()` that had drifted from
     * the framework's: it bound five services where Laravel 13 binds seven, silently dropping
     * `ConcurrencyErrorDetector` and `LostConnectionDetector`.
     *
     * A binding assertion cannot catch that regression — in a real application Laravel's own
     * `DatabaseServiceProvider` registers those contracts anyway, so they are bound either way.
     * What must be asserted is the structural rule: this provider must not re-copy the method.
     */
    #[Test]
    public function connectionServicesAreNotReimplemented(): void
    {
        $method = new ReflectionMethod(ServiceProvider::class, 'registerConnectionServices');

        static::assertSame(
            DatabaseServiceProvider::class,
            $method->getDeclaringClass()->getName(),
            'registerConnectionServices() must stay inherited: a copy drifts from the framework.'
        );
    }

    #[Test]
    public function everyFrameworkConnectionServiceIsBound(): void
    {
        static::assertTrue($this->app->bound(ConcurrencyErrorDetector::class));
        static::assertTrue($this->app->bound(LostConnectionDetector::class));

        foreach (['db.factory', 'db', 'db.connection', 'db.schema', 'db.transactions'] as $binding) {
            static::assertTrue($this->app->bound($binding), "[$binding] must stay bound");
        }
    }

    #[Test]
    public function checkConnection(): void
    {
        static::assertInstanceOf(Connection::class, $this->app['db.connection']);
    }

    #[Test]
    #[DataProvider('boolDataProvider')]
    public function boolTrueBindingsWorks(bool $value): void
    {
        $table = 'test_table';
        $data  = ['field' => $value];

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
    public function intBindingsWorks(int $value): void
    {
        $table = 'test_table';
        $data  = ['field' => $value];
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
        $data  = ['field' => 'string'];
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
        $data  = ['field' => null];
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
    public function dateTimeBindingsWorks(mixed $value): void
    {
        $table = 'test_table';
        $data  = ['field' => $value];
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


    public static function boolDataProvider(): Generator
    {
        yield 'true' => [true];
        yield 'false' => [false];
    }

    public static function intDataProvider(): Generator
    {
        yield 'zero' => [0];
        yield 'non-zero' => [10];
    }

    public static function dateDataProvider(): Generator
    {
        yield 'as string' => ['2019-01-01 13:12:22'];
        yield 'as Carbon object' => [new Carbon('2019-01-01 13:12:22')];
    }
}
