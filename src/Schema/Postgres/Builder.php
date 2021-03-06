<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Illuminate\Database\Schema\PostgresBuilder;

class Builder extends PostgresBuilder
{
    protected function createBlueprint($table, \Closure $callback = null)
    {
        return new Blueprint($table, $callback);
    }

    public function createView(string $view, string $select, $materialize = false): void
    {
        $blueprint = $this->createBlueprint($view);
        $blueprint->createView($view, $select, $materialize);
        $this->build($blueprint);
    }

    public function dropView(string $view): void
    {
        $blueprint = $this->createBlueprint($view);
        $blueprint->dropView($view);
        $this->build($blueprint);
    }

    public function hasView(string $view): bool
    {
        return count(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileViewExists(),
                [
                    $this->connection->getConfig()['schema'],
                    $this->connection->getTablePrefix() . $view,
                ]
            )
        ) > 0;
    }

    public function getViewDefinition($view): string
    {
        $results = $this->connection->selectFromWriteConnection(
            $this->grammar->compileViewDefinition(),
            [
                $this->connection->getConfig()['schema'],
                $this->connection->getTablePrefix() . $view,
            ]
        );
        return count($results) > 0 ? $results[0]->view_definition : '';
    }
}
