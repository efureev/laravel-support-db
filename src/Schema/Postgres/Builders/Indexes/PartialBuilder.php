<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes;

use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\WhereBuilderTrait;

/**
 * @extends Fluent<string, mixed>
 */
class PartialBuilder extends Fluent
{
    use WhereBuilderTrait;
}
