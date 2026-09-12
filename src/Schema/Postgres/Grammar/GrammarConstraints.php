<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\CheckCompiler;

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

    /** @param Fluent<string, mixed> $command */
    public function compileDropCheck(BaseBlueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s drop constraint %s',
            $this->wrapTable($blueprint),
            $this->wrap((string)$command->get('constraint'))
        );
    }
}
