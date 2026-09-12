<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Partitions\PartitionBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

/**
 * Declarative partitioning: a parent table that holds no rows, and children that do.
 *
 * Laravel has no form for any of it.
 */
class PartitionCompiler
{
    use WheresBuilder;

    /** The strategies PostgreSQL partitions by. */
    private const STRATEGIES = [
        'range', 'list', 'hash',
    ];

    /**
     * ` partition by range ("at")` — appended to a `CREATE TABLE`.
     *
     * @param Fluent<string, mixed> $command
     */
    public static function by(Grammar $grammar, BaseBlueprint $blueprint, Fluent $command): string
    {
        $strategy = (string)$command->get('strategy');

        if (!in_array($strategy, self::STRATEGIES, true)) {
            throw new InvalidArgumentException(
                sprintf('Unknown partition strategy [%s]; expected range, list or hash.', $strategy)
            );
        }

        $columns = (array)$command->get('columns');

        if ($columns === []) {
            throw new InvalidArgumentException('A partitioned table needs at least one partition key.');
        }

        self::assertKeysCoverTheOwnPrimaryKey($blueprint, $columns);

        return sprintf('partition by %s (%s)', $strategy, $grammar->columnize($columns));
    }

    /**
     * PostgreSQL requires every unique constraint on a partitioned table — the primary key
     * included — to contain all the partitioning columns, and says so in a way that takes a while
     * to read: *unique constraint on partitioned table must include all partitioning columns*.
     *
     * The commonest way to hit it is `bigIncrements('id')` next to `partitionBy('range', 'at')`,
     * which is a perfectly ordinary-looking pair. Saying it here, before the statement is sent,
     * costs nothing and names the actual problem.
     *
     * @param list<string> $partitionKeys
     */
    private static function assertKeysCoverTheOwnPrimaryKey(BaseBlueprint $blueprint, array $partitionKeys): void
    {
        $primary = null;

        foreach ($blueprint->getColumns() as $column) {
            if ($column->get('autoIncrement')) {
                $primary = [(string)$column->get('name')];
            }
        }

        foreach ($blueprint->getCommands() as $command) {
            if ($command->get('name') === 'primary') {
                $primary = array_map(strval(...), (array)$command->get('columns'));
            }
        }

        if ($primary === null) {
            return;
        }

        $missing = array_diff($partitionKeys, $primary);

        if ($missing === []) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The primary key (%s) must contain every partitioning column; %s missing. '
            . 'PostgreSQL requires it, so partition by a column the key already has, or widen '
            . 'the key — a composite primary key over (%s) is the usual answer.',
            implode(', ', $primary),
            implode(', ', $missing),
            implode(', ', array_unique([...$primary, ...$partitionKeys]))
        ));
    }

    /**
     * `partition of "events" for values from (…) to (…)` — the body of a child table's
     * `CREATE TABLE`, which carries no column list of its own.
     */
    public static function of(Grammar $grammar, PartitionBuilder $command): string
    {
        return sprintf(
            'partition of %s %s',
            $grammar->wrapTable((string)$command->get('parent')),
            self::forValues($command)
        );
    }

    /** `alter table "events" attach partition "events_2026" for values …`. */
    public static function attach(Grammar $grammar, BaseBlueprint $blueprint, PartitionBuilder $command): string
    {
        return sprintf(
            'alter table %s attach partition %s %s',
            $grammar->wrapTable($blueprint),
            $grammar->wrapTable((string)$command->get('partition')),
            self::forValues($command)
        );
    }

    /**
     * `alter table "events" detach partition "events_2025"`.
     *
     * `concurrently` avoids the access-exclusive lock, and PostgreSQL forbids it inside a
     * transaction block — the same rule `CREATE INDEX CONCURRENTLY` follows.
     *
     * @param Fluent<string, mixed> $command
     */
    public static function detach(Grammar $grammar, BaseBlueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s detach partition %s%s',
            $grammar->wrapTable($blueprint),
            $grammar->wrapTable((string)$command->get('partition')),
            $command->get('concurrently') ? ' concurrently' : ''
        );
    }

    private static function forValues(PartitionBuilder $command): string
    {
        $strategy = $command->get('strategy');
        $bounds = (array)$command->get('bounds');

        return match ($strategy) {
            'default' => 'default',
            'range' => sprintf(
                'for values from (%s) to (%s)',
                self::literal($bounds['from']),
                self::literal($bounds['to'])
            ),
            'list' => sprintf(
                'for values in (%s)',
                implode(', ', array_map(self::literal(...), $bounds['values']))
            ),
            'hash' => sprintf(
                'for values with (modulus %d, remainder %d)',
                $bounds['modulus'],
                $bounds['remainder']
            ),
            default => throw new InvalidArgumentException(
                'This partition has no bounds; call fromTo(), in(), hash() or asDefault().'
            ),
        };
    }

    /** A bound is interpolated into DDL, where PostgreSQL takes no parameter. */
    private static function literal(string|int|float $value): string
    {
        return is_string($value) ? static::quoteLiteral($value) : (string)$value;
    }
}
