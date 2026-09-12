<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Query;

use Illuminate\Database\Query\Builder as BaseQuery;
use Illuminate\Support\Arr;
use Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;

/**
 * `RETURNING` is PostgreSQL-only, so this builder is always paired with the package's own
 * grammar and connection — `Postgres\Connection::query()` is what constructs it.
 *
 * @property PostgresGrammar $grammar
 * @property Connection $connection
 */
class Builder extends BaseQuery
{
    /**
     * The predicate an `ON CONFLICT` target carries, when the index it names is a partial one.
     *
     * @var PartialBuilder|null
     */
    private ?PartialBuilder $conflictTarget = null;

    /**
     * Name the predicate of the partial unique index `upsert()` should conflict on.
     *
     * PostgreSQL will not infer a partial index from the conflict columns alone — the predicate
     * has to be repeated, and has to match the index. The vocabulary is the one the index was
     * declared with, so the two read alike:
     *
     * ```php
     * $table->uniquePartial('email')->whereNull('deleted_at');          // the index
     *
     * DB::table('users')
     *     ->onConflictWhere(fn (PartialBuilder $w) => $w->whereNull('deleted_at'))
     *     ->upsert($values, ['email'], ['name']);                       // the upsert
     * ```
     *
     * @param callable(PartialBuilder): mixed $predicate
     */
    public function onConflictWhere(callable $predicate): static
    {
        $predicate($this->conflictTarget ??= new PartialBuilder());

        return $this;
    }

    /** @internal used by the grammar to compile the predicate into the statement */
    public function getConflictTarget(): ?PartialBuilder
    {
        return $this->conflictTarget;
    }

    /**
     * Insert records and return columns of the inserted rows.
     *
     * The framework's `insertGetId()` returns one key from one row; this returns whatever columns
     * you name, for every row inserted, including the ones the database generated.
     *
     * ```php
     * $rows = DB::table('orders')->insertAndReturn($values, 'id', 'created_at');
     * ```
     *
     * @param array<array-key, mixed> $values
     *
     * @return list<mixed>
     */
    public function insertAndReturn(array $values, string ...$columns): array
    {
        if ($values === []) {
            return [];
        }

        // Normalised the way the framework normalises it, so a single row and a batch behave
        // alike and every row lists its columns in the same order.
        if (!is_array(array_first($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        $sql  = $this->grammar->compileInsert($this, $values);
        $sql .= $this->grammar->compileReturns($columns);

        return $this->connection->insertAndReturn($sql, $this->cleanBindings(Arr::flatten($values, 1)));
    }

    /**
     * Update records in the database and return columns of updated records.
     *
     * @param array $values
     * @param string ...$columns
     *
     * @return array
     */
    /**
     * @param array<string, mixed> $values
     *
     * @return list<mixed>
     */
    public function updateAndReturn(array $values, string ...$columns): array
    {
        $this->applyBeforeQueryCallbacks();

        $sql  = $this->grammar->compileUpdate($this, $values);
        $sql .= $this->grammar->compileReturns($columns);

        return $this->connection->updateAndReturn(
            $sql,
            $this->cleanBindings(
                $this->grammar->prepareBindingsForUpdate($this->bindings, $values)
            )
        );
    }

    /**
     * Delete records in the database and return columns of deleted records.
     *
     * @param string ...$columns
     *
     * @return array
     *
     * @return list<mixed>
     */
    public function deleteAndReturn(string ...$columns): array
    {
        $this->applyBeforeQueryCallbacks();

        $sql  = $this->grammar->compileDelete($this);
        $sql .= $this->grammar->compileReturns($columns);

        return $this->connection->deleteAndReturn(
            $sql,
            $this->cleanBindings(
                $this->grammar->prepareBindingsForDelete($this->bindings)
            )
        );
    }
}
