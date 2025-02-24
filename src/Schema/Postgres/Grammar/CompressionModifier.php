<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

trait CompressionModifier
{
    public function compileChange(BaseBlueprint $blueprint, Fluent $command)
    {
        $queries = (array)parent::compileChange($blueprint, $command);

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
        $compression = $column->value('compression');

        if ($compression !== null) {
            return " compression $compression";
        }

        return null;
    }
}
