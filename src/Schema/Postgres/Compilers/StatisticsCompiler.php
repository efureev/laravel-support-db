<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Statistics\StatisticsBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

/**
 * `CREATE STATISTICS name (kind, …) ON column, … FROM table`.
 *
 * The last thing on the roadmap, and the only one aimed at the planner rather than at what the
 * database will accept.
 */
class StatisticsCompiler
{
    public static function create(
        Grammar $grammar,
        BaseBlueprint $blueprint,
        StatisticsBuilder $command
    ): string {
        $columns = (array)$command->get('columns');

        if ($columns === []) {
            throw new InvalidArgumentException(
                'Statistics need columns; call on() with at least two.'
            );
        }

        $kinds = (array)($command->get('kinds') ?? []);

        return implode(' ', array_filter([
            'create statistics',
            $command->get('ifNotExists') ? 'if not exists' : '',
            $grammar->wrapTable((string)$command->get('statistics')),
            $kinds === [] ? '' : '(' . implode(', ', $kinds) . ')',
            'on ' . $grammar->columnize($columns),
            'from ' . $grammar->wrapTable($blueprint),
        ], static fn(string $part): bool => $part !== ''));
    }

    /** @param list<string> $names */
    public static function drop(Grammar $grammar, array $names): string
    {
        if ($names === []) {
            throw new InvalidArgumentException('Name at least one statistics object to drop.');
        }

        return 'drop statistics if exists ' . implode(', ', array_map($grammar->wrapTable(...), $names));
    }
}
