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
    use Php\Support\Laravel\Database\Schema\Definitions\UniqueDefinition;
    use Php\Support\Laravel\Database\Schema\Definitions\ViewDefinition;

    /**
     * @method LikeDefinition like(string $table)
     * @method Fluent ifNotExists()
     * @method UniqueDefinition uniquePartial($columns, ?string $index = null, ?string $algorithm = null)
     * @method ViewDefinition createView(string $view, string $select, bool $materialize = false)
     * @method ViewDefinition createViewOrReplace(string $view, string $select, bool $materialize = false)
     * @method Fluent dropView(string $view)
     *
     * @mixin \Php\Support\Laravel\Database\Schema\Postgres\Blueprint
     */
    class Blueprint
    {
    }
}
