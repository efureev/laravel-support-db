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
        $postCompile = match (true) {
            (bool)($commands['like'] ?? null)       => self::compileLike($grammar, $commands['like']),
            (bool)($commands['fromSelect'] ?? null) => self::compileFromSelect($commands['fromSelect']),
            (bool)($commands['fromTable'] ?? null)  => self::compileFromTable($grammar, $commands['fromTable']),
            default                                 => self::compileColumns($columns),
        };

        // Built by joining non-empty parts rather than with a `sprintf()` template followed by a
        // `str_replace('  ', ' ')` clean-up: that clean-up also collapsed legitimate double spaces
        // inside default values and user-supplied `fromSelect()` SQL.
        return implode(
            ' ',
            array_filter(
                [
                    $blueprint->temporary ? 'create temporary table' : 'create table',
                    ($commands['ifNotExists'] ?? null) ? 'if not exists' : '',
                    $grammar->wrapTable($blueprint),
                    $postCompile,
                ],
                static fn(string $part): bool => $part !== ''
            )
        );
    }

    private static function compileLike(Grammar $grammar, Fluent $command): string
    {
        $table        = $command->get('table');
        $includingAll = $command->get('includingAll') ? ' including all' : '';
        return "(like {$grammar->wrapTable($table)}$includingAll)";
    }

    private static function compileFromSelect(Fluent $command): string
    {
        $sql = $command->get('fromSelect');

        return "as ($sql)";
    }

    private static function compileFromTable(Grammar $grammar, Fluent $command): string
    {
        $table = $command->get('fromTable');

        return "as table {$grammar->wrapTable($table)}";
    }

    private static function compileColumns(array $columns): string
    {
        return '(' . implode(', ', $columns) . ')';
    }
}
