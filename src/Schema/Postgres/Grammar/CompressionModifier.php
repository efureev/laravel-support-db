<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use InvalidArgumentException;

trait CompressionModifier
{
    /**
     * Default compression method used when `compression()` is called without an argument.
     */
    public const DEFAULT_COMPRESSION = 'pglz';

    /**
     * Compile a column change command, appending `SET COMPRESSION` when requested.
     *
     * Laravel creates one `change` command per changed column, so this runs once per column —
     * the changed column is `$command->column`. Iterating over every changed column here would
     * emit each statement N times.
     */
    #[\Override]
    public function compileChange(BaseBlueprint $blueprint, Fluent $command)
    {
        $queries = (array)parent::compileChange($blueprint, $command);

        $column = $command->get('column');

        if ($column instanceof Fluent && ($compression = static::compressionValue($column)) !== null) {
            $queries[] = sprintf(
                'alter table %s alter column %s set compression %s',
                $this->wrapTable($blueprint),
                $this->wrap($column->get('name')),
                $compression,
            );
        }

        return $queries;
    }

    /**
     * Get the SQL for a compression column modifier.
     *
     * Only applies while creating or adding a column: on the change path PostgreSQL requires a
     * standalone `ALTER COLUMN ... SET COMPRESSION` statement (emitted by `compileChange()`
     * above), and the inline fragment the parent grammar would build is not valid syntax.
     */
    protected function modifyCompression(BaseBlueprint $blueprint, Fluent $column): ?string
    {
        if ($column->get('change')) {
            return null;
        }

        $compression = static::compressionValue($column);

        return $compression === null ? null : " compression $compression";
    }

    /**
     * Resolve the compression method configured on a column, if any.
     */
    protected static function compressionValue(Fluent $column): ?string
    {
        $value = $column->get('compression');

        $compression = match (true) {
            $value === null, $value === false => null,
            // `Fluent::__call()` assigns `true` when the method is called without arguments.
            $value === true                   => static::DEFAULT_COMPRESSION,
            default                           => (string)$value,
        };

        if ($compression === null) {
            return null;
        }

        // The value is interpolated into DDL, so it must be a bare identifier.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $compression) !== 1) {
            throw new InvalidArgumentException(
                sprintf(
                    "Invalid column compression method [%s]. Expected an identifier, e.g. 'pglz' or 'lz4'.",
                    $compression
                )
            );
        }

        return $compression;
    }
}
