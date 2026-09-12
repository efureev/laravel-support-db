<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;
use InvalidArgumentException;

trait CompilesIndexAlgorithm
{
    /**
     * Build the `using <method>` clause of a CREATE INDEX statement.
     *
     * The attribute is set either by the third argument of `partial()` / `uniquePartial()` or by
     * a fluent `->algorithm()` call — both land in the same Fluent attribute.
     *
     * Placement matches the framework's own `PostgresGrammar::compileIndex()`: right after the
     * table name and before the column list, unquoted.
     *
     * @param Fluent<string, mixed> $fluent
     */
    protected static function algorithmClause(Fluent $fluent): string
    {
        $algorithm = $fluent->get('algorithm');

        if ($algorithm === null || $algorithm === '' || $algorithm === false) {
            return '';
        }

        $algorithm = (string)$algorithm;

        // Interpolated into DDL, so it must be a bare identifier.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $algorithm) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Invalid index algorithm [%s]. Expected an identifier, e.g. btree or gin.', $algorithm)
            );
        }

        return " using $algorithm";
    }

    /**
     * `INCLUDE (…)` carries extra columns in the index leaf without indexing them, so a query
     * reading only those columns never touches the table. PostgreSQL 11 and later.
     *
     * Set by a fluent `->include([...])`. It goes after the column list and before the predicate.
     *
     * @param Fluent<string, mixed> $fluent
     */
    protected static function includeClause(Grammar $grammar, Fluent $fluent): string
    {
        $columns = (array)($fluent->get('include') ?? []);

        if ($columns === []) {
            return '';
        }

        return ' include (' . $grammar->columnize($columns) . ')';
    }

    /**
     * `CREATE INDEX CONCURRENTLY` builds the index without taking a write lock on the table, at
     * the cost of a second pass. Set by a fluent `->online()`, the same name the framework uses.
     *
     * PostgreSQL forbids it inside a transaction block, so a migration that uses it must not run
     * in one.
     *
     * @param Fluent<string, mixed> $fluent
     */
    protected static function concurrentlyClause(Fluent $fluent): string
    {
        return $fluent->get('online') ? 'concurrently ' : '';
    }

    /**
     * `NULLS NOT DISTINCT` makes a unique index treat nulls as equal, so at most one row may hold
     * a null in the indexed column. PostgreSQL 15 and later.
     *
     * It sits after the column list and before the predicate.
     *
     * @param Fluent<string, mixed> $fluent
     */
    protected static function nullsClause(Fluent $fluent): string
    {
        $nullsNotDistinct = $fluent->get('nullsNotDistinct');

        if ($nullsNotDistinct === null) {
            return '';
        }

        return $nullsNotDistinct ? ' nulls not distinct' : ' nulls distinct';
    }
}
