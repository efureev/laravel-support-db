<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use PDO;
use Php\Support\Laravel\Database\Query\Builder as QueryBuilder;
use Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar as QueryPostgresGrammar;

class Connection extends BasePostgresConnection
{
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix((new Grammar())->addModifier('Compression'));
    }


    public function getSchemaBuilder()
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }
        return new Builder($this);
    }

    public function query()
    {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    protected function getDefaultQueryGrammar()
    {
        return new QueryPostgresGrammar();
    }

    public function bindValues($statement, $bindings): void
    {
        if ($this->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            foreach ($bindings as $key => $value) {
                $parameter = is_string($key) ? $key : $key + 1;

                $dataType = match (true) {
                    is_bool($value) => PDO::PARAM_BOOL,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };

                $statement->bindValue($parameter, $value, $dataType);
            }
        } else {
            parent::bindValues($statement, $bindings);
        }
    }

    public function updateAndReturn($query, $bindings = []): array
    {
        return $this->affectingStatementArray($query, $bindings);
    }

    public function deleteAndReturn($query, $bindings = []): array
    {
        return $this->affectingStatementArray($query, $bindings);
    }

    public function affectingStatementArray($query, $bindings = []): array
    {
        return $this->run(
            $query,
            $bindings,
            function ($query, $bindings) {
                if ($this->pretending()) {
                    return [];
                }

                $statement = $this->getPdo()->prepare($query);

                $this->bindValues($statement, $this->prepareBindings($bindings));

                $statement->execute();

                $this->recordsHaveBeenModified(
                    ($list = $this->associateStatement($statement))
                );

                return $list;
            }
        );
    }

    public function associateStatement($statement): array
    {
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
