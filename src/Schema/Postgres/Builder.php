<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\Schema\PostgresBuilder;

/**
 * @property Grammar $grammar
 */
class Builder extends PostgresBuilder
{
    private ?string $currentSchema = null;

    /**
     * Point the parent's `createBlueprint()` at this package's Blueprint.
     *
     * Registering a resolver rather than overriding `createBlueprint()` keeps the framework's
     * own implementation in play — the override was a verbatim copy of it.
     */
    public function __construct(BaseConnection $connection)
    {
        parent::__construct($connection);

        $this->blueprintResolver(
            static fn(BaseConnection $conn, string $table, ?Closure $callback = null): Blueprint
                => Container::getInstance()->make(
                    Blueprint::class,
                    [
                        'connection' => $conn,
                        'table'      => $table,
                        'callback'   => $callback,
                    ]
                )
        );
    }

    /**
     * Drop a table from the schema if it exists.
     *
     * @param string $table
     */
    public function dropIfExistsCascade(string $table): void
    {
        /** @var Blueprint $blueprint */
        $blueprint = $this->createBlueprint($table);

        $blueprint->dropIfExists()['cascade'] = true;

        $this->build($blueprint);
    }

    public function createView(string $view, string $select, bool $materialize = false): void
    {
        $this->buildView('createView', $view, $select, $materialize);
    }

    public function createViewOrReplace(string $view, string $select, bool $materialize = false): void
    {
        $this->buildView('createViewOrReplace', $view, $select, $materialize);
    }

    private function buildView(string $method, string $view, string $select, bool $materialize): void
    {
        /** @var Blueprint $blueprint */
        $blueprint = $this->createBlueprint($view);

        $blueprint->{$method}($view, $select, $materialize);

        $this->build($blueprint);
    }

    public function dropView(string $view, bool $materialize = false): void
    {
        /** @var Blueprint $blueprint */
        $blueprint = $this->createBlueprint($view);
        $blueprint->dropView($view, $materialize);
        $this->build($blueprint);
    }

    public function dropViewIfExists(string $view, bool $materialize = false): void
    {
        /** @var Blueprint $blueprint */
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
            $this->grammar->compileRefreshMaterializedView($view, $concurrently)
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

    public function getViewDefinition(string $view): string
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
    /** @return list<string|null> */
    private function viewBindings(string $view): array
    {
        // `getCurrentSchemaName()` issues a `show search_path` every time; the schema cannot
        // change under a single builder instance.
        $schema = $this->currentSchema ??= $this->getCurrentSchemaName();
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
        $name = $this->grammar->wrap($name);
        $this->getConnection()->statement("create extension $name");
    }

    public function createExtensionIfNotExists(string $name): void
    {
        $name = $this->grammar->wrap($name);
        $this->getConnection()->statement("create extension if not exists $name");
    }

    public function dropExtensionIfExists(string ...$name): void
    {
        $names = $this->grammar->naming($name);
        $this->getConnection()->statement("drop extension if exists $names");
    }
}
