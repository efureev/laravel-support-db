<?php

namespace Php\Support\Laravel\Database\Query;

use Illuminate\Database\Query\Builder as BaseQuery;

/**
 * @method array|int update(array $values)
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
    public function updateAndReturn(array $values, string ...$columns)
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
    public function deleteAndReturn(string ...$columns)
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
