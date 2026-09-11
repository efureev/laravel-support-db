<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Query;

use Illuminate\Database\Query\Builder as BaseQuery;
use Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar;
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
     * Update records in the database and return columns of updated records.
     *
     * @param array $values
     * @param string ...$columns
     *
     * @return array
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
