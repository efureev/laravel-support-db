<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniquePartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\PartialCompiler;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\UniqueCompiler;

trait GrammarIndexes
{
    public function compileUniquePartial(Blueprint $blueprint, UniqueBuilder $command): string|array
    {
        $constraints = $command->get('constraints');

        // Without a predicate there is nothing partial about the index, so fall back to the
        // framework's unconditional unique constraint rather than emitting a dangling `WHERE`.
        if ($constraints instanceof UniquePartialBuilder && !empty($constraints->get('wheres'))) {
            return UniqueCompiler::compile($this, $blueprint, $command, $constraints);
        }

        // Since Laravel 13 `compileUnique()` returns an array of statements.
        return $this->compileUnique($blueprint, $command);
    }

    public function compilePartial(Blueprint $blueprint, PartialBuilder $command): string
    {
        return PartialCompiler::compile($this, $blueprint, $command);
    }
}
