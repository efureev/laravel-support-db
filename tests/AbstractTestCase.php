<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Facade;
use Orchestra\Testbench\TestCase;
use Php\Support\Laravel\Database\ServiceProvider;

/**
 * Class AbstractTestCase
 */
abstract class AbstractTestCase extends TestCase
{
    use InteractsWithDatabase;
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginTransactionForTest;
    }

    /** @var list<string> */
    protected array $migrations = [];

    /**
     * Each test runs inside a transaction that is rolled back afterwards, which is both faster
     * than wiping the schema and immune to the order tests happen to run in.
     *
     * Statements PostgreSQL forbids inside a transaction block — `REFRESH MATERIALIZED VIEW
     * CONCURRENTLY`, `CREATE INDEX CONCURRENTLY` — cannot use it; such a test opts out and gets
     * the wipe instead.
     */
    protected bool $transactional = true;

    /** Wiping once per process is enough; each test is isolated by its own transaction. */
    private static bool $databaseWiped = false;

    /**
     * Define environment setup.
     *
     * @param Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set(
            'database.connections.pgsql',
            [
                'driver'         => 'pgsql',
                'url'            => env('DATABASE_URL'),
                'host'           => env('DB_HOST', 'localhost'),
                'port'           => env('DB_PORT', '5432'),
                'database'       => env('DB_DATABASE', 'forge'),
                'username'       => env('DB_USERNAME', 'forge'),
                'password'       => env('DB_PASSWORD', 'forge'),
                'charset'        => 'utf8',
                'prefix'         => '',
                'prefix_indexes' => true,
                'search_path'    => 'public',
                'sslmode'        => 'prefer',
            ]
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }


    protected static function databasePath(?string $path = null): string
    {
        return __DIR__ . '/database' . ($path ? "/$path" : '');
    }

    protected static function migrationsPath(?string $path = null): string
    {
        return self::databasePath('migrations' . ($path ? "/$path" : ''));
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Facade::clearResolvedInstances();

        $this->installMigrations();
    }

    public function beginDatabaseTransaction(): void
    {
        // Rolling back leaves whatever was in the database before the run untouched, so the
        // suite is no longer self-healing on its own. One wipe per process restores that:
        // a crashed earlier run, or a stray table, cannot cascade into 30 confusing failures.
        if (!self::$databaseWiped) {
            $this->artisan('db:wipe')->assertSuccessful();
            self::$databaseWiped = true;
        }

        if ($this->transactional) {
            $this->beginTransactionForTest();

            return;
        }

        $this->artisan('db:wipe')->assertSuccessful();
    }

    protected function installMigrations(): void
    {
        foreach ($this->migrations as $migration) {
            $this->loadMigrationsFrom(self::migrationsPath($migration));
        }
    }
}
