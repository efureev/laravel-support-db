<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Definitions;

use Illuminate\Support\Fluent;

/**
 * @method self where(string $column, $operator, $value, $boolean = 'and')
 * @method self whereRaw($sql, $bindings = [], $boolean = 'and')
 * @method self whereColumn($first, $operator, $second, $boolean = 'and')
 * @method self whereIn(string $column, $values = [], $boolean = 'and', $not = false)
 * @method self whereNotIn(string $column, $values = [], $boolean = 'and')
 * @method self whereBetween(string $column, $values, $boolean = 'and', $not = false)
 * @method self whereNotBetween(string $column, $values, $boolean = 'and')
 * @method self whereNull(string $column, $boolean = 'and', $not = false)
 * @method self whereNotNull(string $column, $boolean = 'and')
 * @method self whereBool(string $column, bool $value, $boolean = 'and')
 * @method self whereTrue(string $column, $boolean = 'and')
 * @method self whereFalse(string $column, $boolean = 'and')
 */
class UniqueDefinition extends Fluent
{
}
