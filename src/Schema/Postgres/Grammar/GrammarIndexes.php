<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\PartialCompiler;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\UniqueCompiler;

trait GrammarIndexes
{
    /** @return string|list<string> */
    public function compileUniquePartial(BaseBlueprint $blueprint, UniqueBuilder $command): string|array
    {
        $constraints = $command->get('constraints');

        // Without a predicate there is nothing partial about the index, so fall back to the
        // framework's unconditional unique constraint rather than emitting a dangling `WHERE`.
        if ($constraints instanceof PartialBuilder && !empty($constraints->get('wheres'))) {
            return UniqueCompiler::compile($this, $blueprint, $command, $constraints);
        }

        // Since Laravel 13 `compileUnique()` returns an array of statements.
        return $this->compileUnique($blueprint, $command);
    }

    public function compilePartial(BaseBlueprint $blueprint, PartialBuilder $command): string
    {
        return PartialCompiler::compile($this, $blueprint, $command);
    }

    /**
     * The framework honours an operator class only for spatial and vector indexes; a plain
     * `create index` drops it on the floor. GIN is where operator classes earn their keep —
     * `jsonb_path_ops` on a jsonb column, `gin_trgm_ops` for trigram search — so route a command
     * that carries one through the framework's own compiler for it.
     *
     * @param Fluent<string, mixed> $command
     */
    #[\Override]
    public function compileIndex(BaseBlueprint $blueprint, Fluent $command): string
    {
        if ($command->get('operatorClass') !== null) {
            return $this->compileIndexWithOperatorClass($blueprint, $command);
        }

        return parent::compileIndex($blueprint, $command);
    }
}
