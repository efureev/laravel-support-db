<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Definitions;

use Illuminate\Database\Schema\ColumnDefinition as CD;

/**
 * @method $this compression(string $name='pglz')
 * @method $this ginIndex(string $indexName = null) Add an index with GIN algo
 * @method $this algorithm(string $algo) Add an index with custom algorithm
 */
class ColumnDefinition extends CD
{
}
