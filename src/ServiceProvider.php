<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Builder;
use Php\Support\Laravel\Database\Schema\ConnectionFactory;

class ServiceProvider extends DatabaseServiceProvider
{
    public function boot()
    {
        parent::boot();

        $this->registerMacros();
    }

    protected function registerConnectionServices(): void
    {
        $this->app->singleton('db.factory', static fn($app) => new ConnectionFactory($app));

        $this->app->singleton('db', static fn($app) => new DatabaseManager($app, $app['db.factory']));

        $this->app->bind('db.connection', static fn($app) => $app['db']->connection());

        $this->app->bind('db.schema', static fn($app) => $app['db']->connection()->getSchemaBuilder());

        $this->app->singleton('db.transactions', static fn($app) => new DatabaseTransactionsManager());
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
