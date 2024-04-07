<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\CreateCompiler;

trait GrammarTable
{
    public function compileCreate(Blueprint $blueprint, Fluent $command): string
    {
        $fromSelect  = $this->getCommandByName($blueprint, 'fromSelect');
        $fromTable   = $this->getCommandByName($blueprint, 'fromTable');
        $like        = $this->getCommandByName($blueprint, 'like');
        $ifNotExists = $this->getCommandByName($blueprint, 'ifNotExists');

        return CreateCompiler::compile(
            $this,
            $blueprint,
            $this->getColumns($blueprint),
            compact('like', 'ifNotExists', 'fromSelect', 'fromTable')
        );
    }

    /**
     * Compile a drop table (if exists) command.
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        $baseCompile = parent::compileDropIfExists($blueprint, $command);
        $cascade     = $command->get('cascade') ? ' cascade' : '';

        return "$baseCompile$cascade";
    }
}
