<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use Illuminate\Database\Grammar as BaseGrammar;
use Stringable;

/**
 * The shape `WhereBuilderTrait` records and this trait renders.
 *
 * @phpstan-type WhereClause array{
 *     type: string,
 *     boolean: string,
 *     column?: string,
 *     operator?: string,
 *     value?: mixed,
 *     values?: array<array-key, mixed>,
 *     first?: string,
 *     second?: string,
 *     not?: bool,
 *     sql?: string,
 *     bindings?: array<array-key, mixed>,
 * }
 */
trait WheresBuilder
{
    /**
     * Inline the bindings into a raw where expression.
     *
     * Bindings are substituted one placeholder at a time instead of through `sprintf()`, so a
     * literal `%` in the SQL (`like 'a%b'`) is not mistaken for a format specifier. The offset
     * advances past each replacement so a value containing `?` is not re-scanned.
     *
     * @param WhereClause $where
     */
    protected static function whereRaw(BaseGrammar $grammar, array $where): string
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

    /** @param WhereClause $where */
    protected static function whereBasic(BaseGrammar $grammar, array $where): string
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

    /** @param WhereClause $where */
    protected static function whereColumn(BaseGrammar $grammar, array $where): string
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

    /** @param WhereClause $where */
    protected static function whereIn(BaseGrammar $grammar, array $where): string
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

    /** @param WhereClause $where */
    protected static function whereNotIn(BaseGrammar $grammar, array $where): string
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

    /** @param WhereClause $where */
    protected static function whereBoolean(BaseGrammar $grammar, array $where): string
    {
        return implode(' ', [$grammar->wrap($where['column']), 'is ' . static::wrapValueForBool($where['value'])]);
    }

    /** @param WhereClause $where */
    protected static function whereNull(BaseGrammar $grammar, array $where): string
    {
        return implode(' ', [$grammar->wrap($where['column']), 'is null']);
    }

    /** @param WhereClause $where */
    protected static function whereNotNull(BaseGrammar $grammar, array $where): string
    {
        return implode(' ', [$grammar->wrap($where['column']), 'is not null']);
    }

    /** @param WhereClause $where */
    protected static function whereBetween(BaseGrammar $grammar, array $where): string
    {
        $values = array_values((array)($where['values'] ?? []));

        // Without exactly two bounds this silently produced `between false and false`, or dropped
        // everything between the first and last value.
        if (count($values) !== 2) {
            throw new InvalidArgumentException(
                sprintf('A between predicate needs exactly two values, %d given.', count($values))
            );
        }

        return implode(
            ' ',
            [
                $grammar->wrap($where['column']),
                $where['not'] ? 'not between' : 'between',
                static::wrapValue($values[0]),
                'and',
                static::wrapValue($values[1]),
            ]
        );
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
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

    /**
     * @param Fluent<string, mixed> $command
     *
     * @return list<string>
     */
    protected static function build(BaseGrammar $grammar, Fluent $command): array
    {
        return array_map(
            static function (array $where) use ($grammar): string {
                $method = "where{$where['type']}";

                if (!method_exists(static::class, $method)) {
                    throw new InvalidArgumentException("Unsupported index predicate type [{$where['type']}].");
                }

                return $where['boolean'] . ' (' . static::$method($grammar, $where) . ')';
            },
            (array)$command->get('wheres')
        );
    }
}
