<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use LogicException;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

class UniqueCompiler
{
    use CompilesIndexAlgorithm;
    use WheresBuilder;

    public static function compile(
        Grammar $grammar,
        BaseBlueprint $blueprint,
        UniqueBuilder $fluent,
        PartialBuilder $command
    ): string {
        $wheres = static::build($grammar, $blueprint, $command);

        if ($wheres === []) {
            // Callers are expected to fall back to the plain unique index when there is no
            // predicate; reaching this point would produce a dangling `WHERE`.
            throw new LogicException(
                'A partial unique index requires at least one where clause; use unique() instead.'
            );
        }

        return sprintf(
            'create unique index %s on %s%s (%s) where %s',
            $grammar->wrap($fluent->get('index')),
            $grammar->wrapTable($blueprint),
            static::algorithmClause($fluent),
            $grammar->columnize((array)$fluent->get('columns')),
            static::removeLeadingBoolean(implode(' ', $wheres)),
        );
    }
}
