<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

class CreateCompiler
{
    public static function compile(
        Grammar $grammar,
        Blueprint $blueprint,
        array $columns,
        array $commands = []
    ): string {
        if ($commands['like']) {
            $postCompile = self::compileLike($grammar, $commands['like']);
        } elseif ($commands['fromSelect']) {
            $postCompile = self::compileFromSelect($grammar, $commands['fromSelect']);
        } elseif ($commands['fromTable']) {
            $postCompile = self::compileFromTable($grammar, $commands['fromTable']);
        } else {
            $postCompile = self::compileColumns($columns);
        }


        $compiledCommand = sprintf(
            'create%s table%s %s %s',
            $blueprint->temporary ? ' temporary' : '',
            self::beforeTable($commands['ifNotExists']),
            $grammar->wrapTable($blueprint),
            $postCompile
        );

        return str_replace('  ', ' ', trim($compiledCommand));
    }

    private static function beforeTable(?Fluent $command = null): string
    {
        return $command ? ' if not exists' : '';
    }

    private static function compileLike(Grammar $grammar, Fluent $command): string
    {
        $table        = $command->get('table');
        $includingAll = $command->get('includingAll') ? ' including all' : '';
        return "(like {$grammar->wrapTable($table)}$includingAll)";
    }

    private static function compileFromSelect(Grammar $grammar, Fluent $command): string
    {
        $sql = $command->get('fromSelect');

        return "as ($sql)";
    }

    private static function compileFromTable(Grammar $grammar, Fluent $command): string
    {
        $table = $command->get('fromTable');

        return "as TABLE $table";
    }

    private static function compileColumns(array $columns): string
    {
        return '(' . implode(', ', $columns) . ')';
    }
}
