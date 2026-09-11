<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique;

use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;

class UniqueBuilder extends Fluent
{
    /**
     * Route where-clause calls to the partial-index constraint builder.
     *
     * Only methods actually declared on `PartialBuilder` are rerouted — `method_exists()`
     * is false for Fluent's magic attribute setters, so calls such as `->algorithm('btree')`
     * keep their normal Fluent behaviour and the index stays a plain unique index.
     *
     * The constraint builder is created once and reused, so repeated calls on the same instance
     * accumulate their predicates instead of discarding the previous ones.
     */
    #[\Override]
    public function __call($method, $parameters): Fluent
    {
        if (!method_exists(PartialBuilder::class, $method)) {
            return parent::__call($method, $parameters);
        }

        $constraints = $this->attributes['constraints'] ??= new PartialBuilder();

        $constraints->$method(...$parameters);

        return $constraints;
    }
}
