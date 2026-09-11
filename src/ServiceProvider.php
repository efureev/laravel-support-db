<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Php\Support\Laravel\Database\Schema\Postgres\Connection as PostgresConnection;

class ServiceProvider extends DatabaseServiceProvider
{
    public function register()
    {
        parent::register();

        $this->registerConnectionResolver();
    }

    public function boot()
    {
        parent::boot();

        $this->registerMacros();
    }

    /**
     * Route `pgsql` connections to this package's Connection.
     *
     * `Connection::getResolver()` is consulted first by the framework's own connection factory,
     * so there is nothing to subclass or rebind. The closure must not capture the container:
     * `Connection::$resolvers` is static and is never cleared, so a captured application would
     * outlive the request (or the test case) that registered it.
     */
    protected function registerConnectionResolver(): void
    {
        Connection::resolverFor(
            'pgsql',
            static fn($connection, $database, $prefix, $config): PostgresConnection
                => new PostgresConnection($connection, $database, $prefix, $config)
        );
    }

    protected function registerMacros(): void
    {
        Builder::macro(
            'updateAndReturn',
            function ($values, string ...$columns) {
                return $this->toBase()->updateAndReturn($this->addUpdatedAtColumn($values), ...$columns);
            }
        );

        Builder::macro(
            'deleteAndReturn',
            function (string ...$columns) {
                return $this->toBase()->deleteAndReturn(...$columns);
            }
        );
    }
}
