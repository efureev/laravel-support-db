<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Builders;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Fluent;
use Stringable;

/**
 * Collects the predicate of a partial index.
 *
 * Values accepted by these methods are rendered by
 * {@see \Php\Support\Laravel\Database\Schema\Postgres\Compilers\WheresBuilder::wrapValue()}:
 * `string|int|float|bool|BackedEnum|DateTimeInterface|Stringable|null`.
 *
 * @mixin Fluent
 */
trait WhereBuilderTrait
{
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        return $this->compileWhere('Raw', $boolean, compact('sql', 'bindings'));
    }

    public function where(
        string $column,
        string $operator,
        string|int|float|bool|BackedEnum|DateTimeInterface|Stringable|null $value,
        string $boolean = 'and'
    ): static {
        return $this->compileWhere('Basic', $boolean, compact('column', 'operator', 'value'));
    }

    public function whereBool(string $column, bool $value, string $boolean = 'and'): static
    {
        return $this->compileWhere('Boolean', $boolean, compact('column', 'value'));
    }

    public function whereTrue(string $column, string $boolean = 'and'): static
    {
        return $this->whereBool($column, true, $boolean);
    }

    public function whereFalse(string $column, string $boolean = 'and'): static
    {
        return $this->whereBool($column, false, $boolean);
    }

    public function whereColumn(string $first, string $operator, string $second, string $boolean = 'and'): static
    {
        return $this->compileWhere('Column', $boolean, compact('first', 'operator', 'second'));
    }

    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        return $this->compileWhere($not ? 'NotIn' : 'In', $boolean, compact('column', 'values'));
    }

    public function whereNotIn(string $column, array $values = [], string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        return $this->compileWhere($not ? 'NotNull' : 'Null', $boolean, compact('column'));
    }

    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        return $this->compileWhere('Between', $boolean, compact('column', 'values', 'not'));
    }

    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    protected function compileWhere(string $type, string $boolean, array $parameters = []): static
    {
        $this->attributes['wheres'][] = array_merge(compact('type', 'boolean'), $parameters);

        return $this;
    }
}
