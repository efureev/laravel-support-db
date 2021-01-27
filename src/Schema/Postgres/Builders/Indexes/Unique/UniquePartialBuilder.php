<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique;

use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\WhereBuilderTrait;

class UniquePartialBuilder extends Fluent
{
    use WhereBuilderTrait;
}
