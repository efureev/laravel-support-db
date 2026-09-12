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
 * @mixin Fluent<string, mixed>
 */
trait WhereBuilderTrait
{
    /** @param array<array-key, mixed> $bindings */
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

    /** @param array<array-key, mixed> $values */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        return $this->compileWhere($not ? 'NotIn' : 'In', $boolean, compact('column', 'values'));
    }

    /** @param array<array-key, mixed> $values */
    public function whereNotIn(string $column, array $values = [], string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        return $this->compileWhere($not ? 'NotNull' : 'Null', $boolean, compact('column'));
    }

    /** @param array<array-key, mixed> $values */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        return $this->compileWhere('Between', $boolean, compact('column', 'values', 'not'));
    }

    /** @param array<array-key, mixed> $values */
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    // ---------------------------------------------------------------- or

    /**
     * Every predicate above takes a `$boolean` argument; these are the `or` spellings of it, so
     * that a disjunction reads the way it does on the query builder rather than ending in a
     * stray `'or'` argument.
     *
     * @param array<array-key, mixed> $bindings
     */
    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    public function orWhere(
        string $column,
        string $operator,
        string|int|float|bool|BackedEnum|DateTimeInterface|Stringable|null $value
    ): static {
        return $this->where($column, $operator, $value, 'or');
    }

    public function orWhereBool(string $column, bool $value): static
    {
        return $this->whereBool($column, $value, 'or');
    }

    public function orWhereTrue(string $column): static
    {
        return $this->whereTrue($column, 'or');
    }

    public function orWhereFalse(string $column): static
    {
        return $this->whereFalse($column, 'or');
    }

    public function orWhereColumn(string $first, string $operator, string $second): static
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /** @param array<array-key, mixed> $values */
    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    /** @param array<array-key, mixed> $values */
    public function orWhereNotIn(string $column, array $values = []): static
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    public function orWhereNotNull(string $column): static
    {
        return $this->whereNotNull($column, 'or');
    }

    /** @param array<array-key, mixed> $values */
    public function orWhereBetween(string $column, array $values): static
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /** @param array<array-key, mixed> $values */
    public function orWhereNotBetween(string $column, array $values): static
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /** @param array<string, mixed> $parameters */
    protected function compileWhere(string $type, string $boolean, array $parameters = []): static
    {
        $this->attributes['wheres'][] = array_merge(compact('type', 'boolean'), $parameters);

        return $this;
    }
}
