<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Support\Fluent;
use LogicException;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

trait GrammarViews
{
    public function compileCreateView(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileView('create', $command);
    }

    public function compileCreateViewOrReplace(Blueprint $blueprint, Fluent $command): string
    {
        if ($command->get('materialize')) {
            throw new LogicException(
                'PostgreSQL has no CREATE OR REPLACE for materialized views; drop and recreate it.'
            );
        }

        return $this->compileView('create or replace', $command);
    }

    public function compileDropView(Blueprint $blueprint, Fluent $command): string
    {
        return implode(
            ' ',
            array_filter(
                [
                    'drop',
                    $command->get('materialize') ? 'materialized' : '',
                    'view',
                    $command->get('ifExists') ? 'if exists' : '',
                    $this->wrapTable($command->get('view')),
                ]
            )
        );
    }

    public function compileRefreshMaterializedView(string $view, bool $concurrently = false): string
    {
        return implode(
            ' ',
            array_filter(
                [
                    'refresh materialized view',
                    $concurrently ? 'concurrently' : '',
                    $this->wrapTable($view),
                ]
            )
        );
    }

    /**
     * Look a view up in both catalogs: `pg_views` holds ordinary views only, materialized views
     * live in `pg_matviews`. `information_schema.views` covers neither materialized views nor
     * views the current user does not own.
     */
    public function compileViewExists(): string
    {
        return <<<'SQL'
            select 1 from pg_views where schemaname = ? and viewname = ?
            union all
            select 1 from pg_matviews where schemaname = ? and matviewname = ?
            SQL;
    }

    public function compileViewDefinition(): string
    {
        return <<<'SQL'
            select definition from pg_views where schemaname = ? and viewname = ?
            union all
            select definition from pg_matviews where schemaname = ? and matviewname = ?
            SQL;
    }

    private function compileView(string $verb, Fluent $command): string
    {
        return implode(
            ' ',
            array_filter(
                [
                    $verb,
                    $command->get('materialize') ? 'materialized' : '',
                    'view',
                    $this->wrapTable($command->get('view')),
                    'as',
                    $command->get('select'),
                ]
            )
        );
    }
}
