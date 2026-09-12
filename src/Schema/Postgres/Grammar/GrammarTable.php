<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\CreateCompiler;

trait GrammarTable
{
    /**
     * Compile a create table command.
     *
     * Only takes over when the blueprint actually uses one of this package's extensions.
     * A plain `create table` is left to the parent, so improvements the framework makes there
     * are not silently lost — which is what a full replacement would do.
     *
     * @param Fluent<string, mixed> $command
     */
    #[\Override]
    public function compileCreate(Blueprint $blueprint, Fluent $command): string
    {
        $fromSelect  = $this->getCommandByName($blueprint, 'fromSelect');
        $fromTable   = $this->getCommandByName($blueprint, 'fromTable');
        $like        = $this->getCommandByName($blueprint, 'like');
        $ifNotExists = $this->getCommandByName($blueprint, 'ifNotExists');
        $partitionBy = $this->getCommandByName($blueprint, 'partitionBy');
        $partitionOf = $this->getCommandByName($blueprint, 'partitionOf');

        /** @phpstan-ignore-next-line property.notFound — set by this package's Blueprint */
        $unlogged = (bool)($blueprint->unlogged ?? false);

        if (
            $fromSelect === null && $fromTable === null && $like === null && $ifNotExists === null
            && $partitionBy === null && $partitionOf === null && !$unlogged
        ) {
            return parent::compileCreate($blueprint, $command);
        }

        return CreateCompiler::compile(
            $this,
            $blueprint,
            $this->getColumns($blueprint),
            compact('like', 'ifNotExists', 'fromSelect', 'fromTable', 'partitionBy', 'partitionOf')
        );
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param Fluent<string, mixed> $command
     */
    #[\Override]
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        $baseCompile = parent::compileDropIfExists($blueprint, $command);
        $cascade     = $command->get('cascade') ? ' cascade' : '';

        return "$baseCompile$cascade";
    }
}
