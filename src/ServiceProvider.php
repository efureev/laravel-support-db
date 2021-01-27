<?php

declare(strict_types=1);

namespace Php\Support\Laravel;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseServiceProvider;
use Php\Support\Laravel\Schemas\ConnectionFactory;

class ExtendedPostgresProvider extends DatabaseServiceProvider
{
    protected function registerConnectionServices(): void
    {
        $this->app->singleton(
            'db.factory',
            static function ($app) {
                return new ConnectionFactory($app);
            }
        );

        $this->app->singleton(
            'db',
            static function ($app) {
                return new DatabaseManager($app, $app['db.factory']);
            }
        );

        $this->app->bind(
            'db.connection',
            static function ($app) {
                return $app['db']->connection();
            }
        );
    }
}
