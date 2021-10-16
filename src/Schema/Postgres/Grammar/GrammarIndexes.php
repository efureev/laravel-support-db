<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniquePartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\UniqueCompiler;

trait GrammarIndexes
{
    public function compileUniquePartial(Blueprint $blueprint, UniqueBuilder $command): string
    {
        $constraints = $command->get('constraints');
        if ($constraints instanceof UniquePartialBuilder) {
            return UniqueCompiler::compile($this, $blueprint, $command, $constraints);
        }
        return $this->compileUnique($blueprint, $command);
    }
}
