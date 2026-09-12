<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Constraints\ExclusionBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

/**
 * `EXCLUDE USING <method> (<column> WITH <operator>, …) [WHERE (<predicate>)]`.
 *
 * The constraint range columns exist for: a unique index says two rows must not be equal, and this
 * says they must not overlap, intersect, or whatever else an operator can express.
 */
class ExclusionCompiler
{
    use CompilesIndexAlgorithm;
    use WheresBuilder;

    /**
     * PostgreSQL operator names are built from this character set, and nothing else.
     *
     * The operator is interpolated into DDL — it cannot be bound — so it is checked rather than
     * trusted, the same way the access method is.
     */
    private const OPERATOR = '/^[+\-*\/<>=~!@#%^&|`?]{1,63}$/';

    public static function compile(
        Grammar $grammar,
        BaseBlueprint $blueprint,
        ExclusionBuilder $command
    ): string {
        $elements = (array)$command->get('elements');

        if ($elements === []) {
            throw new InvalidArgumentException(
                'An exclusion constraint needs at least one column and operator; call with().'
            );
        }

        $wheres = static::build($grammar, $command);

        return sprintf(
            'alter table %s add constraint %s exclude%s (%s)%s',
            $grammar->wrapTable($blueprint),
            $grammar->wrap((string)$command->get('constraint')),
            static::algorithmClause($command),
            implode(', ', array_map(
                static fn(array $element): string => sprintf(
                    '%s with %s',
                    $grammar->wrap($element['column']),
                    self::operator($element['operator'])
                ),
                $elements
            )),
            $wheres === [] ? '' : ' where ' . static::removeLeadingBoolean(implode(' ', $wheres))
        );
    }

    private static function operator(string $operator): string
    {
        if (preg_match(self::OPERATOR, $operator) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Invalid exclusion operator [%s].', $operator)
            );
        }

        return $operator;
    }
}
