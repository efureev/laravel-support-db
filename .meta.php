<?php

/**
 * IDE helper. Never autoloaded — it declares framework class names on purpose so that editors
 * resolve this package's additions on the classes you actually type against.
 */

// @formatter:off

namespace Illuminate\Support\Facades {

    /**
     * @mixin \Php\Support\Laravel\Database\Schema\Postgres\Builder
     */
    class Schema
    {
    }
}

namespace Illuminate\Database\Schema {

    /**
     * @mixin \Php\Support\Laravel\Database\Schema\Postgres\Blueprint
     */
    class Blueprint
    {
    }

    /**
     * `Blueprint::addColumn()` returns this package's definition, so every column carries the
     * extra modifiers — but the framework's own signatures are typed against this class.
     *
     * @mixin \Php\Support\Laravel\Database\Schema\Definitions\ColumnDefinition
     */
    class ColumnDefinition
    {
    }
}

namespace Illuminate\Database\Query {

    /**
     * `Postgres\Connection::query()` builds this package's query builder, which is what
     * `Model::toBase()` and `DB::table()` hand back on a pgsql connection.
     *
     * @mixin \Php\Support\Laravel\Database\Query\Builder
     */
    class Builder
    {
    }
}

namespace Illuminate\Database\Eloquent {

    /**
     * Registered as macros by the package's ServiceProvider, so there is no class to mix in.
     *
     * @method array updateAndReturn(array $values, string ...$columns) Update records and return
     *     the given columns of the updated rows.
     * @method array deleteAndReturn(string ...$columns) Delete records and return the given
     *     columns of the deleted rows.
     */
    class Builder
    {
    }
}
