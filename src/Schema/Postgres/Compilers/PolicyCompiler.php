<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Security\PolicyBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;

/**
 * `CREATE POLICY … ON … [FOR …] [TO …] [USING (…)] [WITH CHECK (…)]`.
 *
 * Row-level security is per-row authorisation the database enforces itself, which no amount of
 * application code can be talked out of. Laravel has no form for it.
 */
class PolicyCompiler
{
    use WheresBuilder;

    public static function create(Grammar $grammar, BaseBlueprint $blueprint, PolicyBuilder $command): string
    {
        $using = self::predicate($grammar, $command->get('using'));
        $check = self::predicate($grammar, $command->get('withCheck'));

        if ($using === '' && $check === '') {
            throw new InvalidArgumentException(
                'A policy needs a using() or a withCheck() predicate; without either it permits '
                . 'nothing and is almost certainly a mistake.'
            );
        }

        $roles = array_map($grammar->wrap(...), (array)($command->get('roles') ?? []));

        return implode(' ', array_filter([
            'create policy',
            $grammar->wrap((string)$command->get('policy')),
            'on ' . $grammar->wrapTable($blueprint),
            $command->get('for') ? 'for ' . $command->get('for') : '',
            $roles === [] ? '' : 'to ' . implode(', ', $roles),
            $using === '' ? '' : "using ($using)",
            $check === '' ? '' : "with check ($check)",
        ], static fn(string $part): bool => $part !== ''));
    }

    private static function predicate(Grammar $grammar, mixed $builder): string
    {
        if (!$builder instanceof PartialBuilder) {
            return '';
        }

        $wheres = static::build($grammar, $builder);

        return $wheres === [] ? '' : static::removeLeadingBoolean(implode(' ', $wheres));
    }
}
