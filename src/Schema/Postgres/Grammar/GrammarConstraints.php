<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Constraints\ExclusionBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\CheckCompiler;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\ExclusionCompiler;

trait GrammarConstraints
{
    /**
     * Laravel's schema builder has no `CHECK` constraint in any grammar, so a table's columns can
     * be described but most of its invariants cannot.
     *
     * Emitted as its own `ALTER TABLE` rather than inline in `CREATE TABLE`, so the same call
     * works whether the table is being created or already exists.
     */
    public function compileCheck(BaseBlueprint $blueprint, PartialBuilder $command): string
    {
        return CheckCompiler::compile($this, $blueprint, $command);
    }

    /**
     * A unique index says two rows must not be equal; an exclusion constraint says they must not
     * overlap, intersect, or whatever else an operator expresses. It is what a range column is
     * for, and Laravel has no form for it.
     */
    public function compileExclusion(BaseBlueprint $blueprint, ExclusionBuilder $command): string
    {
        return ExclusionCompiler::compile($this, $blueprint, $command);
    }

    /** @param Fluent<string, mixed> $command */
    public function compileDropConstraint(BaseBlueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap((string)$command->get('constraint'))
        );
    }
}
