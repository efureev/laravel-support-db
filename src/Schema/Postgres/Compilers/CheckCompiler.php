<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

/**
 * A `CHECK` constraint, built from the same predicate vocabulary as a partial index.
 *
 * The two are the same kind of thing to PostgreSQL — a boolean expression over a row — so they
 * are written the same way here rather than in two dialects.
 */
class CheckCompiler
{
    use WheresBuilder;

    public static function compile(Grammar $grammar, BaseBlueprint $blueprint, PartialBuilder $command): string
    {
        $wheres = static::build($grammar, $command);

        if ($wheres === []) {
            throw new InvalidArgumentException('A check constraint needs at least one condition.');
        }

        return sprintf(
            'alter table %s add constraint %s check (%s)',
            $grammar->wrapTable($blueprint),
            $grammar->wrap((string)$command->get('constraint')),
            static::removeLeadingBoolean(implode(' ', $wheres))
        );
    }
}
