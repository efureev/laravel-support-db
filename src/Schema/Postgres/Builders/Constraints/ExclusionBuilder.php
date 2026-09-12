<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Builders\Constraints;

use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\WhereBuilderTrait;

/**
 * An exclusion constraint: "no two rows may relate to each other this way".
 *
 * ```php
 * $table->exclusion('bookings_no_overlap')
 *     ->using('gist')
 *     ->with('room_id', '=')
 *     ->with('during', '&&')
 *     ->whereNull('cancelled_at');
 * ```
 *
 * @extends Fluent<string, mixed>
 */
class ExclusionBuilder extends Fluent
{
    use WhereBuilderTrait;

    /**
     * The index access method the constraint is built on.
     *
     * Stored as `algorithm`, the attribute every access method in this package uses, so one
     * validated code path covers them all. A range operator such as `&&` needs `gist`; the
     * default, `btree`, supports only `=`.
     */
    public function using(string $method): static
    {
        $this->attributes['algorithm'] = $method;

        return $this;
    }

    /**
     * One column and the operator two rows must not satisfy together.
     *
     * Calls accumulate: every pair given becomes part of the same constraint.
     */
    public function with(string $column, string $operator): static
    {
        $this->attributes['elements'][] = compact('column', 'operator');

        return $this;
    }
}
