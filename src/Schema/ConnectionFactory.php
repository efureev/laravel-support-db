<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Schemas;

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
            return new PostgresConnection($connection, $database, $prefix, $config);
        }

        return parent::createConnection($driver, $connection, $database, $prefix, $config);
    }
}
