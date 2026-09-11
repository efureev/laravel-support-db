<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Definitions;

use Illuminate\Support\Fluent;

/**
 * @extends Fluent<string, mixed>
 *
 * `INCLUDING ALL` is shorthand for INCLUDING DEFAULTS, CONSTRAINTS, INDEXES, STORAGE and COMMENTS.
 *
 * @method Fluent<string, mixed> includingAll()
 */
class LikeDefinition extends Fluent
{
}
