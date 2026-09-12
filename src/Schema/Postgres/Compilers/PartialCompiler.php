<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use LogicException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

class PartialCompiler
{
    use CompilesIndexAlgorithm;
    use WheresBuilder;

    public static function compile(
        Grammar $grammar,
        BaseBlueprint $blueprint,
        PartialBuilder $fluent
    ): string {
        $wheres = static::build($grammar, $fluent);

        // PostgreSQL parses `NULLS NOT DISTINCT` on a plain index and then ignores it; saying so
        // beats emitting a clause that does nothing.
        if ($fluent->get('nullsNotDistinct') !== null) {
            throw new LogicException(
                'nullsNotDistinct() only means something for a unique index; use uniquePartial().'
            );
        }

        // The table must go through `wrapTable()` so the connection's table prefix is applied:
        // `createIndexName()` already prefixes the index name, so an unprefixed table here meant
        // the index targeted a relation that does not exist.
        return sprintf(
            'create index %s%s on %s%s (%s)%s',
            static::concurrentlyClause($fluent),
            $grammar->wrap($fluent->get('index')),
            $grammar->wrapTable($blueprint),
            static::algorithmClause($fluent),
            $grammar->columnize((array)$fluent->get('columns')),
            $wheres === [] ? '' : ' where ' . static::removeLeadingBoolean(implode(' ', $wheres)),
        );
    }
}
