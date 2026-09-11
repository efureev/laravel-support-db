<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;
use Stringable;

trait WheresBuilder
{
    /**
     * Inline the bindings into a raw where expression.
     *
     * Bindings are substituted one placeholder at a time instead of through `sprintf()`, so a
     * literal `%` in the SQL (`like 'a%b'`) is not mistaken for a format specifier. The offset
     * advances past each replacement so a value containing `?` is not re-scanned.
     */
    protected static function whereRaw(Grammar $grammar, Blueprint $blueprint, array $where = []): string
    {
        $sql    = (string)($where['sql'] ?? '');
        $offset = 0;

        foreach ((array)($where['bindings'] ?? []) as $binding) {
            if (($position = strpos($sql, '?', $offset)) === false) {
                break;
            }

            $value  = static::wrapValue($binding);
            $sql    = substr_replace($sql, $value, $position, 1);
            $offset = $position + strlen($value);
        }

        return $sql;
    }

    protected static function whereBasic(Grammar $grammar, Blueprint $blueprint, array $where): string
    {
        return implode(
            ' ',
            [
                $grammar->wrap($where['column']),
                $where['operator'],
                static::wrapValue($where['value']),
            ]
        );
    }

    protected static function whereColumn(Grammar $grammar, Blueprint $blueprint, array $where): string
    {
        return implode(
            ' ',
            [
                $grammar->wrap($where['first']),
                $where['operator'],
                $grammar->wrap($where['second']),
            ]
        );
    }

    protected static function whereIn(Grammar $grammar, Blueprint $blueprint, array $where = []): string
    {
        if (!empty($where['values'])) {
            return implode(
                ' ',
                [
                    $grammar->wrap($where['column']),
                    'in',
                    '(' . implode(',', static::wrapValues($where['values'])) . ')',
                ]
            );
        }
        return '0 = 1';
    }

    protected static function whereNotIn(Grammar $grammar, Blueprint $blueprint, array $where = []): string
    {
        if (!empty($where['values'])) {
            return implode(
                ' ',
                [
                    $grammar->wrap($where['column']),
                    'not in',
                    '(' . implode(',', static::wrapValues($where['values'])) . ')',
                ]
            );
        }
        return '1 = 1';
    }

    protected static function whereBoolean(Grammar $grammar, Blueprint $blueprint, array $where): string
    {
        return implode(' ', [$grammar->wrap($where['column']), 'is ' . static::wrapValueForBool($where['value'])]);
    }

    protected static function whereNull(Grammar $grammar, Blueprint $blueprint, array $where): string
    {
        return implode(' ', [$grammar->wrap($where['column']), 'is null']);
    }

    protected static function whereNotNull(Grammar $grammar, Blueprint $blueprint, array $where): string
    {
        return implode(' ', [$grammar->wrap($where['column']), 'is not null']);
    }

    protected static function whereBetween(Grammar $grammar, Blueprint $blueprint, array $where): string
    {
        return implode(
            ' ',
            [
                $grammar->wrap($where['column']),
                $where['not'] ? 'not between' : 'between',
                static::wrapValue(reset($where['values'])),
                'and',
                static::wrapValue(end($where['values'])),
            ]
        );
    }

    protected static function wrapValues(array $values = []): array
    {
        return array_map(static::wrapValue(...), $values);
    }

    /**
     * Render a value as a SQL literal safe to embed in an index predicate.
     *
     * Strings are escaped by doubling the apostrophe — the same approach Laravel itself uses in
     * `Illuminate\Database\Schema\Grammars\Grammar::getDefaultValue()`. That keeps the compiler
     * usable without a live PDO connection (migrations run with `--pretend`, unit tests), which
     * `PDO::quote()` would not. It is correct for PostgreSQL because `standard_conforming_strings`
     * has been on by default since 9.1, so backslashes carry no special meaning.
     */
    protected static function wrapValue(mixed $value): string
    {
        return match (true) {
            $value === null                      => 'null',
            is_bool($value)                      => static::wrapValueForBool($value),
            is_int($value), is_float($value)     => (string)$value,
            $value instanceof BackedEnum         => static::quoteLiteral((string)$value->value),
            $value instanceof DateTimeInterface  => static::quoteLiteral($value->format('Y-m-d H:i:s')),
            $value instanceof Stringable          => static::quoteLiteral((string)$value),
            is_string($value)                    => static::quoteLiteral($value),
            default                              => throw new InvalidArgumentException(
                'Unsupported value of type [' . get_debug_type($value) . '] in an index predicate.'
            ),
        };
    }

    protected static function quoteLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    protected static function wrapValueForBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    protected static function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/^(and|or)\s+/i', '', $value, 1);
    }

    private static function build(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        return array_map(
            static function (array $where) use ($grammar, $blueprint): string {
                $method = "where{$where['type']}";

                if (!method_exists(static::class, $method)) {
                    throw new InvalidArgumentException("Unsupported index predicate type [{$where['type']}].");
                }

                return $where['boolean'] . ' (' . static::$method($grammar, $blueprint, $where) . ')';
            },
            (array)$command->get('wheres')
        );
    }
}
