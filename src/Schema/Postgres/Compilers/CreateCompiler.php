<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Partitions\PartitionBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

class CreateCompiler
{
    /**
     * @param list<string>                      $columns
     * @param array<string, Fluent<string, mixed>|PartitionBuilder|null> $commands
     */
    public static function compile(
        Grammar $grammar,
        BaseBlueprint $blueprint,
        array $columns,
        array $commands = []
    ): string {
        $postCompile = match (true) {
            // a child table carries no column list of its own: it inherits the parent's
            ($commands['partitionOf'] ?? null) instanceof PartitionBuilder
                => PartitionCompiler::of($grammar, $commands['partitionOf']),
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
                    self::createVerb($blueprint),
                    ($commands['ifNotExists'] ?? null) ? 'if not exists' : '',
                    $grammar->wrapTable($blueprint),
                    $postCompile,
                    ($commands['partitionBy'] ?? null)
                        ? PartitionCompiler::by($grammar, $blueprint, $commands['partitionBy'])
                        : '',
                ],
                static fn(string $part): bool => $part !== ''
            )
        );
    }

    /**
     * `UNLOGGED` skips the write-ahead log: faster writes, and the table is emptied after a crash
     * and never replicated. `TEMPORARY` and `UNLOGGED` are mutually exclusive.
     */
    private static function createVerb(BaseBlueprint $blueprint): string
    {
        if ($blueprint->temporary) {
            return 'create temporary table';
        }

        return ($blueprint->unlogged ?? false) ? 'create unlogged table' : 'create table';
    }

    /** @param Fluent<string, mixed> $command */
    private static function compileLike(Grammar $grammar, Fluent $command): string
    {
        $table        = $command->get('table');
        $includingAll = $command->get('includingAll') ? ' including all' : '';
        return "(like {$grammar->wrapTable($table)}$includingAll)";
    }

    /** @param Fluent<string, mixed> $command */
    private static function compileFromSelect(Fluent $command): string
    {
        $sql = $command->get('fromSelect');

        return "as ($sql)";
    }

    /** @param Fluent<string, mixed> $command */
    private static function compileFromTable(Grammar $grammar, Fluent $command): string
    {
        $table = $command->get('fromTable');

        return "as table {$grammar->wrapTable($table)}";
    }

    /** @param list<string> $columns */
    private static function compileColumns(array $columns): string
    {
        return '(' . implode(', ', $columns) . ')';
    }
}
