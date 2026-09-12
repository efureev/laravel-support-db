<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use PDO;
use Php\Support\Laravel\Database\Query\Builder as QueryBuilder;
use Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar as QueryPostgresGrammar;

class Connection extends BasePostgresConnection
{
    #[\Override]
    protected function getDefaultSchemaGrammar()
    {
        return (new Grammar($this))->addModifier('Compression');
    }


    #[\Override]
    public function getSchemaBuilder()
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }
        return new Builder($this);
    }

    #[\Override]
    public function query()
    {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    #[\Override]
    protected function getDefaultQueryGrammar()
    {
        return new QueryPostgresGrammar($this);
    }

    /** @param array<array-key, mixed> $bindings */
    #[\Override]
    public function bindValues($statement, $bindings): void
    {
        if ($this->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            foreach ($bindings as $key => $value) {
                $parameter = is_string($key) ? $key : $key + 1;

                $dataType = match (true) {
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_resource($value) => PDO::PARAM_LOB,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };

                $statement->bindValue($parameter, $value, $dataType);
            }
        } else {
            parent::bindValues($statement, $bindings);
        }
    }

    /**
     * @param array<array-key, mixed> $bindings
     *
     * @return list<mixed>
     */
    public function insertAndReturn(string $query, array $bindings = []): array
    {
        return $this->affectingStatementArray($query, $bindings);
    }

    /**
     * @param array<array-key, mixed> $bindings
     *
     * @return list<mixed>
     */
    public function updateAndReturn(string $query, array $bindings = []): array
    {
        return $this->affectingStatementArray($query, $bindings);
    }

    /**
     * @param array<array-key, mixed> $bindings
     *
     * @return list<mixed>
     */
    public function deleteAndReturn(string $query, array $bindings = []): array
    {
        return $this->affectingStatementArray($query, $bindings);
    }

    /**
     * @param array<array-key, mixed> $bindings
     *
     * @return list<mixed>
     */
    public function affectingStatementArray(string $query, array $bindings = []): array
    {
        return $this->run(
            $query,
            $bindings,
            function ($query, $bindings) {
                if ($this->pretending()) {
                    return [];
                }

                // Going through `prepared()` is what makes RETURNING rows look like every other
                // result set: it applies the configured fetch mode and dispatches
                // `StatementPrepared`, which packages hook to change that mode.
                $statement = $this->prepared($this->getPdo()->prepare($query));

                $this->bindValues($statement, $this->prepareBindings($bindings));

                $statement->execute();

                // `Connection::$recordsModified` is a bool and is stored verbatim, so passing the
                // row list would leave an array in it and skew the sticky-connection check.
                $this->recordsHaveBeenModified(
                    ($list = $statement->fetchAll()) !== []
                );

                return $list;
            }
        );
    }
}
