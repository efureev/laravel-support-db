<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory as ConnectionFactoryBase;

class ConnectionFactory extends ConnectionFactoryBase
{
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        if ($resolver = Connection::getResolver($driver)) {
            return $resolver($connection, $database, $prefix, $config);
        }

        if ($driver === 'pgsql') {
            return new Postgres\Connection($connection, $database, $prefix, $config);
        }

        return parent::createConnection($driver, $connection, $database, $prefix, $config);
    }
}
