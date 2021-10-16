<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

trait CompressionModifier
{
    public function compileChange(BaseBlueprint $blueprint, Fluent $command, Connection $connection)
    {
        $queries = parent::compileChange($blueprint, $command, $connection);

        foreach ($blueprint->getChangedColumns() as $changedColumn) {
            if ($changedColumn->compression !== null) {
                $queries[] = sprintf(
                    'ALTER TABLE %s ALTER %s SET COMPRESSION %s',
                    $this->wrapTable($blueprint->getTable()),
                    $this->wrap($changedColumn->name),
                    $this->wrap($changedColumn->compression),
                );
            }
        }

        return $queries;
    }

    /**
     * Get the SQL for a default column modifier.
     */
    protected function modifyCompression(Blueprint $blueprint, Fluent $column): ?string
    {
        if ($column->compression !== null) {
            return " compression $column->compression";
        }

        return null;
    }
}
