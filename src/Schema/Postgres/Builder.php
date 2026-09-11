<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\PostgresBuilder;

class Builder extends PostgresBuilder
{
    #[\Override]
    protected function createBlueprint($table, ?Closure $callback = null)
    {
        $connection = $this->connection;

        if (isset($this->resolver)) {
            return ($this->resolver)($connection, $table, $callback);
        }

        return Container::getInstance()->make(Blueprint::class, compact('connection', 'table', 'callback'));
    }

    /**
     * Drop a table from the schema if it exists.
     *
     * @param string $table
     */
    public function dropIfExistsCascade(string $table): void
    {
        $this->build(
            tap(
                $this->createBlueprint($table),
                static function ($blueprint) {
                    $blueprint->dropIfExists()->cascade();
                }
            )
        );
    }

    public function createView(string $view, string $select, bool $materialize = false): void
    {
        $blueprint = $this->createBlueprint($view);
        $blueprint->createView($view, $select, $materialize);
        $this->build($blueprint);
    }

    public function createViewOrReplace(string $view, string $select, bool $materialize = false): void
    {
        $blueprint = $this->createBlueprint($view);
        $blueprint->createViewOrReplace($view, $select, $materialize);
        $this->build($blueprint);
    }

    public function dropView(string $view, bool $materialize = false): void
    {
        $blueprint = $this->createBlueprint($view);
        $blueprint->dropView($view, $materialize);
        $this->build($blueprint);
    }

    public function dropViewIfExists(string $view, bool $materialize = false): void
    {
        $blueprint = $this->createBlueprint($view);
        $blueprint->dropViewIfExists($view, $materialize);
        $this->build($blueprint);
    }

    /**
     * Refresh a materialized view. `CONCURRENTLY` requires the view to carry a unique index
     * and to have been populated at least once.
     */
    public function refreshMaterializedView(string $view, bool $concurrently = false): void
    {
        $this->getConnection()->statement(
            $this->getConnection()->getSchemaGrammar()->compileRefreshMaterializedView($view, $concurrently)
        );
    }

    /**
     * Unlike the framework's `hasView()`, this also finds materialized views — neither
     * `pg_views` (used by Laravel) nor `information_schema.views` lists them.
     */
    #[\Override]
    public function hasView($view): bool
    {
        return count(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileViewExists(),
                $this->viewBindings($view)
            )
        ) > 0;
    }

    public function getViewDefinition($view): string
    {
        $results = $this->connection->selectFromWriteConnection(
            $this->grammar->compileViewDefinition(),
            $this->viewBindings($view)
        );

        return count($results) > 0 ? (string)$results[0]->definition : '';
    }

    /**
     * Both view queries union `pg_views` with `pg_matviews`, so schema and name are bound twice.
     */
    private function viewBindings(string $view): array
    {
        $schema = $this->getCurrentSchemaName();
        $name   = $this->connection->getTablePrefix() . $view;

        return [
            $schema,
            $name,
            $schema,
            $name,
        ];
    }

    public function createExtension(string $name): void
    {
        $name = $this->getConnection()->getSchemaGrammar()->wrap($name);
        $this->getConnection()->statement("create extension $name");
    }

    public function createExtensionIfNotExists(string $name): void
    {
        $name = $this->getConnection()->getSchemaGrammar()->wrap($name);
        $this->getConnection()->statement("create extension if not exists $name");
    }

    public function dropExtensionIfExists(string ...$name): void
    {
        $names = $this->getConnection()->getSchemaGrammar()->naming($name);
        $this->getConnection()->statement("drop extension if exists $names");
    }
}
