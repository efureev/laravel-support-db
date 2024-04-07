<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

class PartialCompiler
{
    use WheresBuilder;

    public static function compile(
        Grammar $grammar,
        Blueprint $blueprint,
        PartialBuilder $fluent
    ): string {
        $wheres = static::build($grammar, $blueprint, $fluent);
        $cols   = implode(',', (array)$fluent->get('columns'));

        if (count($wheres) === 0) {
            return sprintf(
                "CREATE INDEX %s ON %s (%s)",
                $fluent->get('index'),
                $blueprint->getTable(),
                $cols,
            );
        }

        return sprintf(
            "CREATE INDEX %s ON %s (%s) WHERE %s",
            $fluent->get('index'),
            $blueprint->getTable(),
            $cols,
            static::removeLeadingBoolean(implode(' ', $wheres))
        );
    }
}
