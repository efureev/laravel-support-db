<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Exception;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Definitions\ColumnDefinition;
use Php\Support\Laravel\Database\Schema\Definitions\LikeDefinition;
use Php\Support\Laravel\Database\Schema\Definitions\UniqueDefinition;
use Php\Support\Laravel\Database\Schema\Definitions\ViewDefinition;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;

class Blueprint extends BaseBlueprint
{
    /**
     * @param string $column
     * @param int $length
     *
     * @return ColumnDefinition
     */
    public function bit(string $column, int $length): ColumnDefinition
    {
        return $this->addColumn('bit', $column, compact('length'));
    }

    /**
     * Almost like 'decimal' type, but can be with variable precision (by default)
     *
     * @param string $column
     * @param int|null $precision
     * @param int|null $scale
     *
     * @return ColumnDefinition
     */
    public function numeric(string $column, ?int $precision = null, ?int $scale = null): Fluent
    {
        return $this->addColumn('numeric', $column, compact('precision', 'scale'));
    }

    /**
     * @param string $column
     * @param bool|callable|Expression|null $generate
     *
     * @return ColumnDefinition
     * @throws Exception
     */
    public function generateUUID(string $column = 'id', $generate = true): ColumnDefinition
    {
        $defCol = $this->addColumn('uuid', $column);
        if ($generate === false) {
            return $defCol;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

        switch (true) {
            case is_bool($generate):
                $defaultExpression = new Expression('uuid_generate_v4()');
                break;

            case is_callable($generate):
                $defaultExpression = new Expression($generate($column));
                break;

            case $generate === null:
                $defaultExpression = $generate;
                $defCol->nullable();
                break;
            case $generate instanceof Expression:
                $defaultExpression = $generate;
                break;
            default:
                $defaultExpression = new Expression($generate);
        }


        return $defCol->default($defaultExpression);
    }


    public function primaryUUID(string $column = 'id', $generate = true): ColumnDefinition
    {
        return $this->generateUUID($column, $generate)->primary();
    }

    /**
     * Create a new date range column on the table.
     */
    public function dateRange(string $column): ColumnDefinition
    {
        return $this->addColumn('dateRange', $column);
    }

    public function tsRange(string $column): ColumnDefinition
    {
        return $this->addColumn('tsrange', $column);
    }

    public function timestampRange(string $column): ColumnDefinition
    {
        return $this->tsRange($column);
    }

    /**
     * Create a new ip network column on the table.
     */
    public function ipNetwork(string $column): ColumnDefinition
    {
        return $this->addColumn('ipNetwork', $column);
    }

    /**
     * Create a new POINT type column
     *
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function geoPoint(string $column): ColumnDefinition
    {
        return $this->addColumn('geoPoint', $column);
    }

    /**
     * Create a new PATH type column
     *
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function geoPath(string $column): ColumnDefinition
    {
        return $this->addColumn('geoPath', $column);
    }

    /**
     * Create a new xml column on the table.
     */
    public function xml(string $column): ColumnDefinition
    {
        return $this->addColumn('xml', $column);
    }

    /**
     * Create a new uuid[] column
     *
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function uuidArray(string $column): ColumnDefinition
    {
        return $this->addColumn('uuidArray', $column);
    }

    /**
     * Create a new int[] column
     *
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function intArray(string $column): ColumnDefinition
    {
        return $this->addColumn('intArray', $column);
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param string $type
     * @param string $name
     * @param array $parameters
     *
     * @return ColumnDefinition
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        return $this->addColumnDefinition(
            new ColumnDefinition(
                array_merge(compact('type', 'name'), $parameters)
            )
        );
    }

    /**
     * @param string $view
     * @param string $select
     * @param bool $materialize
     *
     * @return ViewDefinition|Fluent
     */
    public function createView(string $view, string $select, bool $materialize = false): Fluent
    {
        return $this->addCommand('createView', compact('view', 'select', 'materialize'));
    }

    public function createViewOrReplace(string $view, string $select, bool $materialize = false): Fluent
    {
        return $this->addCommand('createViewOrReplace', compact('view', 'select', 'materialize'));
    }

    public function dropView(string $view): Fluent
    {
        return $this->addCommand('dropView', compact('view'));
    }

    public function ifNotExists(): Fluent
    {
        return $this->addCommand('ifNotExists');
    }

    public function hasIndex($index, bool $unique = false): bool
    {
        if (is_array($index)) {
            $index = $this->createIndexName($unique === false ? 'index' : 'unique', $index);
        }

        return array_key_exists($index, $this->getSchemaManager()->listTableIndexes($this->getTable()));
    }

    /**
     * @return LikeDefinition
     */
    public function like(string $table): Fluent
    {
        return $this->addCommand('like', compact('table'));
    }

    /**
     * Create a new table from a source-table and fill it with a data from SELECT-query from the source-table
     * and without dependencies (Indexes, etc.)
     *
     * @param string $fromSelect
     *
     * @return Fluent
     *
     * @example `$table->fromSelect('select t1.id, t1.name from src_table t1');`
     */
    public function fromSelect(string $fromSelect): Fluent
    {
        return $this->addCommand('fromSelect', compact('fromSelect'));
    }

    /**
     * Create a new table coping from a source-table with a data and without dependencies (Indexes, etc.)
     *
     * @param string $fromTable
     *
     * @return Fluent
     *
     * @example `$table->fromTable('source_table');`
     */
    public function fromTable(string $fromTable): Fluent
    {
        return $this->addCommand('fromTable', compact('fromTable'));
    }

    /**
     * @param array|string $columns
     * @param string|null $index
     * @param string|null $algorithm
     *
     * @return UniqueDefinition|UniqueBuilder
     */
    public function uniquePartial($columns, ?string $index = null, ?string $algorithm = null): Fluent
    {
        $columns = (array)$columns;

        $index = $index ?: $this->createIndexName('unique', $columns);

        return $this->addExtendedCommand(
            UniqueBuilder::class,
            'uniquePartial',
            compact('columns', 'index', 'algorithm')
        );
    }

    public function ginIndex($columns, ?string $name = null): Fluent
    {
        return $this->indexCommand('index', $columns, $name, 'gin');
    }

    public function dropUniquePartial($index): Fluent
    {
        return $this->dropIndexCommand('dropIndex', 'unique', $index);
    }

    protected function getSchemaManager()
    {
        return Schema::getConnection()->getDoctrineSchemaManager();
    }

    private function addExtendedCommand(string $fluent, string $name, array $parameters = []): Fluent
    {
        $command          = new $fluent(array_merge(compact('name'), $parameters));
        $this->commands[] = $command;

        return $command;
    }
}
