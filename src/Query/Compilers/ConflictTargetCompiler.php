<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Query\Compilers;

use Illuminate\Database\Grammar as BaseGrammar;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\WheresBuilder;

/**
 * The predicate of an `ON CONFLICT` target.
 *
 * It is the same predicate the partial unique index was declared with — PostgreSQL matches the
 * two textually rather than semantically — so it is compiled by the same vocabulary, in a class
 * of its own rather than a trait on the grammar: `Query\Grammars\PostgresGrammar` already has
 * non-static `whereBasic()`, `whereNull()` and friends of its own for ordinary `WHERE` clauses,
 * and a trait would collide with every one of them.
 */
class ConflictTargetCompiler
{
    use WheresBuilder;

    public static function compile(BaseGrammar $grammar, PartialBuilder $target): string
    {
        $wheres = static::build($grammar, $target);

        if ($wheres === []) {
            return '';
        }

        return static::removeLeadingBoolean(implode(' ', $wheres));
    }
}
