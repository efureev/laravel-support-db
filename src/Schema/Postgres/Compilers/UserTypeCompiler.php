<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

/**
 * Types of one's own: enums, domains and composites.
 *
 * Laravel can read these back and drop them all at once, but has no way to create one.
 *
 * A class rather than a trait on the grammar: `WheresBuilder` declares a static `wrapValue()`,
 * and `Illuminate\Database\Grammar` declares a non-static one, so mixing it in is a fatal error.
 * The same reason `ConflictTargetCompiler` exists.
 */
class UserTypeCompiler
{
    use WheresBuilder;

    /** @param list<string> $values */
    public static function enumType(Grammar $grammar, string $name, array $values): string
    {
        if ($values === []) {
            throw new InvalidArgumentException('An enum type needs at least one value.');
        }

        return sprintf(
            'create type %s as enum (%s)',
            $grammar->wrapTable($name),
            implode(', ', array_map(static::quoteLiteral(...), $values))
        );
    }

    public static function addEnumValue(
        Grammar $grammar,
        string $type,
        string $value,
        ?string $before,
        ?string $after
    ): string {
        if ($before !== null && $after !== null) {
            throw new InvalidArgumentException(
                'An enum value goes either before or after another, not both.'
            );
        }

        $position = match (true) {
            $before !== null => ' before ' . static::quoteLiteral($before),
            $after !== null => ' after ' . static::quoteLiteral($after),
            default => '',
        };

        return sprintf(
            'alter type %s add value %s%s',
            $grammar->wrapTable($type),
            static::quoteLiteral($value),
            $position
        );
    }

    /** @param array<string, string> $fields */
    public static function compositeType(Grammar $grammar, string $name, array $fields): string
    {
        if ($fields === []) {
            throw new InvalidArgumentException('A composite type needs at least one field.');
        }

        $columns = [];

        foreach ($fields as $field => $type) {
            $columns[] = $grammar->wrap($field) . ' ' . self::typeName($type);
        }

        return sprintf('create type %s as (%s)', $grammar->wrapTable($name), implode(', ', $columns));
    }

    /**
     * The check predicate refers to the value being tested. PostgreSQL spells that `VALUE` and
     * resolves a quoted `"value"` to it as well, so the vocabulary used everywhere else in this
     * package works here unchanged: `->where('value', '>', 0)`.
     */
    public static function domain(
        Grammar $grammar,
        string $name,
        string $type,
        ?PartialBuilder $check
    ): string {
        $wheres = $check === null ? [] : static::build($grammar, $check);

        return sprintf(
            'create domain %s as %s%s',
            $grammar->wrapTable($name),
            self::typeName($type),
            $wheres === []
                ? ''
                : ' check (' . static::removeLeadingBoolean(implode(' ', $wheres)) . ')'
        );
    }

    /** @param list<string> $names */
    public static function dropIfExists(Grammar $grammar, string $kind, array $names, bool $cascade): string
    {
        if ($names === []) {
            throw new InvalidArgumentException("Name at least one $kind to drop.");
        }

        return sprintf(
            'drop %s if exists %s%s',
            $kind,
            implode(', ', array_map($grammar->wrapTable(...), $names)),
            $cascade ? ' cascade' : ''
        );
    }

    /**
     * A base type is interpolated into DDL, so it is checked rather than trusted. `varchar(30)`
     * and `numeric(10, 2)` are types too, hence the optional parenthesised part.
     */
    private static function typeName(string $type): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_ ]*(\(\s*\d+\s*(,\s*\d+\s*)?\))?(\[\])?$/', $type) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid type name [%s].', $type));
        }

        return $type;
    }
}
