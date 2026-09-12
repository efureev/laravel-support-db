<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Definitions\ColumnDefinition;
use Php\Support\Laravel\Database\Schema\Definitions\LikeDefinition;
use Php\Support\Laravel\Database\Schema\Definitions\ViewDefinition;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Constraints\ExclusionBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Partitions\PartitionBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Security\PolicyBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Statistics\StatisticsBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;

class Blueprint extends BaseBlueprint
{
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

    /** @param bool|callable(string): string|Expression<literal-string>|null $default */
    public function generateUUID(string $column = 'id', bool|callable|Expression|null $default = true): ColumnDefinition
    {
        $defCol = $this->addColumn('uuid', $column);
        if ($default === false) {
            return $defCol;
        }

        if ($default === null) {
            return $defCol->nullable()->default(null);
        }

        $defaultExpression = match (true) {
            // Native, extension-less UUID generation (PostgreSQL >= 13).
            $default === true => new Expression('gen_random_uuid()'),
            $default instanceof Expression => $default,
            default => new Expression($default($column)),
        };

        return $defCol->default($defaultExpression);
    }


    /** @param bool|callable(string): string|Expression<literal-string>|null $generate */
    public function primaryUUID(
        string $column = 'id',
        bool|callable|Expression|null $generate = true
    ): ColumnDefinition {
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
     */
    public function uuidArray(string $column): ColumnDefinition
    {
        return $this->addColumn('uuidArray', $column);
    }

    /**
     * Create a new text[] column
     */
    public function textArray(string $column): ColumnDefinition
    {
        return $this->addColumn('textArray', $column);
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
     * @param array<string, mixed> $parameters
     */
    #[\Override]
    public function addColumn($type, $name, array $parameters = []): ColumnDefinition
    {
        /** @var ColumnDefinition $definition the parent returns whatever definition it is given */
        $definition = $this->addColumnDefinition(
            new ColumnDefinition(
                array_merge(compact('type', 'name'), $parameters)
            )
        );

        return $definition;
    }

    public function createView(string $view, string $select, bool $materialize = false): ViewDefinition
    {
        return $this->addExtendedCommand(
            ViewDefinition::class,
            'createView',
            compact('view', 'select', 'materialize')
        );
    }

    public function createViewOrReplace(
        string $view,
        string $select,
        bool $materialize = false
    ): ViewDefinition {
        return $this->addExtendedCommand(
            ViewDefinition::class,
            'createViewOrReplace',
            compact('view', 'select', 'materialize')
        );
    }

    /**
     * Drop a view. Materialized views require `$materialize: true` — PostgreSQL rejects
     * `DROP VIEW` on them.
     *
     * @return Fluent<string, mixed>
     */
    public function dropView(string $view, bool $materialize = false): Fluent
    {
        return $this->addCommand('dropView', compact('view', 'materialize'));
    }

    /** @return Fluent<string, mixed> */
    public function dropViewIfExists(string $view, bool $materialize = false): Fluent
    {
        return $this->addCommand('dropView', compact('view', 'materialize') + ['ifExists' => true]);
    }

    /** @return Fluent<string, mixed> */
    public function ifNotExists(): Fluent
    {
        return $this->addCommand('ifNotExists');
    }

    public function like(string $table): LikeDefinition
    {
        return $this->addExtendedCommand(LikeDefinition::class, 'like', compact('table'));
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
     *
     * @return Fluent<string, mixed>
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
     *
     * @return Fluent<string, mixed>
     */
    public function fromTable(string $fromTable): Fluent
    {
        return $this->addCommand('fromTable', compact('fromTable'));
    }

    /**
     * @param array<array-key, string>|string $columns
     */
    public function uniquePartial($columns, ?string $index = null, ?string $algorithm = null): UniqueBuilder
    {
        return $this->addPartialIndex(UniqueBuilder::class, 'uniquePartial', 'unique', $columns, $index, $algorithm);
    }

    /**
     * @param array|string $columns
     * @param string|null $index
     * @param string|null $algorithm
     *
     */
    /** @param array<array-key, string>|string $columns */
    public function partial($columns, ?string $index = null, ?string $algorithm = null): PartialBuilder
    {
        return $this->addPartialIndex(PartialBuilder::class, 'partial', 'partial', $columns, $index, $algorithm);
    }

    /**
     * @template T of Fluent
     *
     * @param class-string<T>                 $builder
     * @param array<array-key, string>|string $columns
     *
     * @return T
     */
    private function addPartialIndex(
        string $builder,
        string $command,
        string $nameType,
        $columns,
        ?string $index,
        ?string $algorithm
    ): Fluent {
        $columns = (array)$columns;

        if ($columns === []) {
            throw new InvalidArgumentException('A partial index needs at least one column.');
        }

        $index = $index ?: $this->createIndexName($nameType, $columns);

        return $this->addExtendedCommand($builder, $command, compact('columns', 'index', 'algorithm'));
    }

    /**
     * A GIN index, optionally with an operator class — `jsonb_path_ops` for containment queries
     * on jsonb, `gin_trgm_ops` for trigram search. The framework's `index()` accepts no operator
     * class, and its `compileIndex()` would drop one anyway.
     *
     * @param array<array-key, string>|string $columns
     *
     * @return Fluent<string, mixed>
     */
    public function ginIndex(array|string $columns, ?string $name = null, ?string $operatorClass = null): Fluent
    {
        return $this->indexCommand('index', $columns, $name, 'gin', $operatorClass);
    }

    /**
     * A `CHECK` constraint, written with the same predicate vocabulary as a partial index —
     * PostgreSQL treats both as a boolean expression over a row, so they read alike:
     *
     * ```php
     * $table->check('price_positive')->where('price', '>', 0);
     * ```
     *
     * Laravel's schema builder has no `CHECK` in any grammar.
     */
    public function check(string $name): PartialBuilder
    {
        // the key is `constraint`, not `name`: `createCommand()` merges parameters over the
        // command name, so a parameter called `name` would replace it
        return $this->addExtendedCommand(PartialBuilder::class, 'check', ['constraint' => $name]);
    }

    /**
     * `UNLOGGED`: no write-ahead log, so writes are faster — and the table is emptied after a
     * crash and never replicated. For data you can rebuild.
     */
    public bool $unlogged = false;

    public function unlogged(bool $value = true): static
    {
        $this->unlogged = $value;

        return $this;
    }

    /**
     * Make this a partitioned table. It holds no rows itself; its partitions do.
     *
     * ```php
     * Schema::create('events', function (Blueprint $table) {
     *     $table->bigIncrements('id');
     *     $table->timestamp('at');
     *     $table->partitionBy('range', 'at');
     * });
     * ```
     *
     * @param array<array-key, string>|string $columns
     *
     * @return Fluent<string, mixed>
     */
    public function partitionBy(string $strategy, array|string $columns): Fluent
    {
        return $this->addCommand('partitionBy', [
            'strategy' => $strategy,
            'columns'  => (array)$columns,
        ]);
    }

    /**
     * Make this table a partition of another. It takes no column list — it inherits the parent's.
     *
     * ```php
     * Schema::create('events_2026', function (Blueprint $table) {
     *     $table->partitionOf('events')->fromTo('2026-01-01', '2027-01-01');
     * });
     * ```
     */
    public function partitionOf(string $parent): PartitionBuilder
    {
        return $this->addExtendedCommand(PartitionBuilder::class, 'partitionOf', compact('parent'));
    }

    /** Adopt an existing table as a partition of this one. */
    public function attachPartition(string $partition): PartitionBuilder
    {
        return $this->addExtendedCommand(
            PartitionBuilder::class,
            'attachPartition',
            compact('partition')
        );
    }

    /**
     * Release a partition back into a table of its own.
     *
     * `concurrently` avoids the access-exclusive lock and, like every other `CONCURRENTLY` in
     * PostgreSQL, cannot run inside a transaction block.
     *
     * @return Fluent<string, mixed>
     */
    public function detachPartition(string $partition, bool $concurrently = false): Fluent
    {
        return $this->addCommand('detachPartition', compact('partition', 'concurrently'));
    }

    /**
     * Extended statistics: tell the planner that these columns are not independent.
     *
     * ```php
     * $table->statistics('events_kind_region')->on('kind', 'region');
     * ```
     *
     * Without them the planner multiplies selectivities as though a city did not imply its
     * country, and a plan chosen on an estimate that is wrong by orders of magnitude is usually
     * the wrong plan.
     */
    public function statistics(string $name): StatisticsBuilder
    {
        return $this->addExtendedCommand(
            StatisticsBuilder::class,
            'statistics',
            ['statistics' => $name]
        );
    }

    /**
     * Drop one or more statistics objects.
     *
     * @return Fluent<string, mixed>
     */
    public function dropStatistics(string ...$statistics): Fluent
    {
        return $this->addCommand('dropStatistics', compact('statistics'));
    }

    /**
     * Row-level security: per-row authorisation the database enforces itself.
     *
     * Switching it on hides every row until a policy permits one. The table owner bypasses the
     * policies unless `force` is used as well.
     *
     * @return Fluent<string, mixed>
     */
    public function enableRowLevelSecurity(): Fluent
    {
        return $this->addCommand('rowLevelSecurity', ['action' => 'enable']);
    }

    /** @return Fluent<string, mixed> */
    public function disableRowLevelSecurity(): Fluent
    {
        return $this->addCommand('rowLevelSecurity', ['action' => 'disable']);
    }

    /**
     * Make the table owner obey the policies too.
     *
     * @return Fluent<string, mixed>
     */
    public function forceRowLevelSecurity(): Fluent
    {
        return $this->addCommand('rowLevelSecurity', ['action' => 'force']);
    }

    /** @return Fluent<string, mixed> */
    public function noForceRowLevelSecurity(): Fluent
    {
        return $this->addCommand('rowLevelSecurity', ['action' => 'no force']);
    }

    /**
     * A row-level security policy.
     *
     * ```php
     * $table->policy('tenant_read')
     *     ->for('select')
     *     ->using(fn (PartialBuilder $w) => $w->whereRaw('tenant = current_setting(?)', ['app.tenant']));
     * ```
     */
    public function policy(string $name): PolicyBuilder
    {
        return $this->addExtendedCommand(PolicyBuilder::class, 'policy', ['policy' => $name]);
    }

    /** @return Fluent<string, mixed> */
    public function dropPolicy(string $name): Fluent
    {
        return $this->addCommand('dropPolicy', ['policy' => $name]);
    }

    /**
     * Per-table storage parameters — `fillfactor`, autovacuum tuning, and the rest.
     *
     * @param array<string, scalar> $parameters
     *
     * @return Fluent<string, mixed>
     */
    public function storageParameters(array $parameters): Fluent
    {
        return $this->addCommand('storageParameters', compact('parameters'));
    }

    /**
     * Put storage parameters back to the server default.
     *
     * @return Fluent<string, mixed>
     */
    public function resetStorageParameters(string ...$parameters): Fluent
    {
        return $this->addCommand('resetStorageParameters', compact('parameters'));
    }

    /**
     * An exclusion constraint: no two rows may satisfy every one of the given operators at once.
     *
     * ```php
     * $table->exclusion('bookings_no_overlap')
     *     ->using('gist')
     *     ->with('room_id', '=')
     *     ->with('during', '&&');
     * ```
     *
     * A range operator such as `&&` needs `gist`, and mixing a scalar column into the same
     * constraint needs the `btree_gist` extension — `Schema::createExtensionIfNotExists()`.
     */
    public function exclusion(string $name): ExclusionBuilder
    {
        return $this->addExtendedCommand(ExclusionBuilder::class, 'exclusion', ['constraint' => $name]);
    }

    /**
     * Drop any named constraint — a check, an exclusion, a unique.
     *
     * @return Fluent<string, mixed>
     */
    public function dropConstraint(string $name): Fluent
    {
        return $this->addCommand('dropConstraint', ['constraint' => $name]);
    }

    /**
     * The same statement as `dropConstraint()`, named for what it usually drops.
     *
     * @return Fluent<string, mixed>
     */
    public function dropCheck(string $name): Fluent
    {
        return $this->dropConstraint($name);
    }

    /**
     * @param array<array-key, string>|string $index
     *
     * @return Fluent<string, mixed>
     */
    public function dropUniquePartial(array|string $index): Fluent
    {
        return $this->dropIndexCommand('dropIndex', 'unique', $index);
    }

    /**
     * @param array<array-key, string>|string $index
     *
     * @return Fluent<string, mixed>
     */
    public function dropPartial(array|string $index): Fluent
    {
        return $this->dropIndexCommand('dropIndex', 'partial', $index);
    }

    /**
     * @template T of Fluent
     *
     * @param class-string<T>      $fluent
     * @param array<string, mixed> $parameters
     *
     * @return T
     */
    private function addExtendedCommand(string $fluent, string $name, array $parameters = []): Fluent
    {
        $command          = new $fluent(array_merge(compact('name'), $parameters));
        $this->commands[] = $command;

        return $command;
    }
}
