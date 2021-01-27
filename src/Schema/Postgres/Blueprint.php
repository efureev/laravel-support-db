<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Exception;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

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
     * @return Fluent
     */
    public function numeric(string $column, ?int $precision = null, ?int $scale = null): Fluent
    {
        return $this->addColumn('numeric', $column, compact('precision', 'scale'));
    }

    /**
     * @param string $column
     * @param bool|callable|Expression $generate
     *
     * @return Fluent
     * @throws Exception
     */
    public function generateUUID(string $column = 'id', $generate = true): Fluent
    {
        if (!$defCol = $this->addColumn('uuid', $column)) {
            return $defCol;
        }

        switch ($driverName = DB::getDriverName()) {
            case 'pgsql':
                DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
                $expression = 'uuid_generate_v4()';
                break;
            case 'mysql':
                $expression = 'UUID()';
                break;
            default:
                throw new Exception('Your DB driver [' . $driverName . '] does not supported');
        }

        if ($generate === true) {
            $defaultExpression = new Expression($expression);
        } elseif (is_callable($generate)) {
            $defaultExpression = new Expression($generate($driverName));
        } elseif ($generate instanceof Expression) {
            $defaultExpression = $generate;
        } else {
            $defaultExpression = new Expression($generate);
        }

        return $defCol->default($defaultExpression);
    }

    /**
     * @param string $column
     *
     * @return Fluent
     */
    public function tsRange(string $column): Fluent
    {
        return $this->addColumn('tsrange', $column);
    }

    /**
     * @param string $view
     * @param string $select
     * @param bool $materialize
     *
     * @return Fluent
     */
    public function createView(string $view, string $select, bool $materialize = false): Fluent
    {
        return $this->addCommand('createView', compact('view', 'select', 'materialize'));
    }

    public function dropView(string $view): Fluent
    {
        return $this->addCommand('dropView', compact('view'));
    }

    public function ifNotExists(): Fluent
    {
        return $this->addCommand('ifNotExists');
    }
}
