<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

class PartialCompiler
{
    use CompilesIndexAlgorithm;
    use WheresBuilder;

    public static function compile(
        Grammar $grammar,
        Blueprint $blueprint,
        PartialBuilder $fluent
    ): string {
        $wheres = static::build($grammar, $blueprint, $fluent);

        // The table must go through `wrapTable()` so the connection's table prefix is applied:
        // `createIndexName()` already prefixes the index name, so an unprefixed table here meant
        // the index targeted a relation that does not exist.
        return sprintf(
            'create index %s on %s%s (%s)%s',
            $grammar->wrap($fluent->get('index')),
            $grammar->wrapTable($blueprint),
            static::algorithmClause($fluent),
            $grammar->columnize((array)$fluent->get('columns')),
            $wheres === [] ? '' : ' where ' . static::removeLeadingBoolean(implode(' ', $wheres)),
        );
    }
}
