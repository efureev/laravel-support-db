<?php
// @formatter:off


namespace Illuminate\Support\Facades {

    use Php\Support\Laravel\Database\Schema\Postgres\Builder;

    /**
     * @mixin Builder
     */
    class Schema
    {
    }
}

namespace Illuminate\Database\Schema {

    use Illuminate\Support\Fluent;
    use Php\Support\Laravel\Database\Schema\Definitions\LikeDefinition;
    use Php\Support\Laravel\Database\Schema\Definitions\ViewDefinition;
    use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
    use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;

    /**
     * @method LikeDefinition like(string $table)
     * @method Fluent ifNotExists()
     * @method PartialBuilder partial($columns, ?string $index = null, ?string $algorithm = null)
     * @method UniqueBuilder uniquePartial($columns, ?string $index = null, ?string $algorithm = null)
     * @method ViewDefinition createView(string $view, string $select, bool $materialize = false)
     * @method ViewDefinition createViewOrReplace(string $view, string $select, bool $materialize = false)
     * @method Fluent dropView(string $view, bool $materialize = false)
     * @method Fluent dropViewIfExists(string $view, bool $materialize = false)
     * @method Fluent ginIndex($columns, ?string $name = null)
     *
     * @mixin \Php\Support\Laravel\Database\Schema\Postgres\Blueprint
     */
    class Blueprint
    {
    }
}
//
//namespace Illuminate\Database\Query {
//
//    /**
//     * @mixin \Php\Support\Laravel\Database\Query\Builder
//     */
//    class Builder
//    {
//    }
//}

namespace Illuminate\Database\Eloquent {

    /**
     * @method array updateAndReturn(array $values, string ...$columns) Update records in the database and return
     *     columns of updated records.
     * @method array deleteAndReturn(string ...$columns) Delete records in the database and return columns of deleted
     *     records.
     */
    class Builder
    {
    }
}
