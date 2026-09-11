<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Definitions;

use BadMethodCallException;
use Illuminate\Database\Schema\ColumnDefinition as CD;

/**
 * @method $this compression(string $name = 'pglz') Set the column compression method (PostgreSQL >= 14)
 */
class ColumnDefinition extends CD
{
    /**
     * Column modifiers that were once advertised but never had any effect.
     *
     * Laravel only turns a fixed set of column attributes into index commands
     * (`Blueprint::addFluentIndexes()`), and neither of these was ever part of it, so calls
     * silently did nothing. They now fail loudly rather than being quietly dropped.
     */
    private const REMOVED_MODIFIERS = [
        'ginIndex'  => 'Use $table->ginIndex($columns) or $table->index($columns, $name, \'gin\').',
        'algorithm' => 'Use $table->index($columns, $name, $algorithm), or $table->partial(...) for a partial index.',
    ];

    public function __call($method, $parameters)
    {
        if (isset(self::REMOVED_MODIFIERS[$method])) {
            throw new BadMethodCallException(
                sprintf(
                    'Column modifier [%s] has no effect and has been removed. %s',
                    $method,
                    self::REMOVED_MODIFIERS[$method]
                )
            );
        }

        return parent::__call($method, $parameters);
    }
}
