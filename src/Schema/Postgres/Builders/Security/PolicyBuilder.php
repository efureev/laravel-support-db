<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Builders\Security;

use Illuminate\Support\Fluent;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;

/**
 * A row-level security policy.
 *
 * `using` decides which rows a statement may see or touch; `withCheck` decides which rows it may
 * leave behind. They are separate predicates, so each is given its own builder.
 *
 * @extends Fluent<string, mixed>
 */
class PolicyBuilder extends Fluent
{
    /** The commands a policy may apply to. */
    private const COMMANDS = [
        'all', 'select', 'insert', 'update', 'delete',
    ];

    public function for(string $command): static
    {
        $command = strtolower($command);

        if (!in_array($command, self::COMMANDS, true)) {
            throw new InvalidArgumentException(sprintf(
                'A policy applies to one of %s, not [%s].',
                implode(', ', self::COMMANDS),
                $command
            ));
        }

        $this->attributes['for'] = $command;

        return $this;
    }

    /** The roles the policy applies to. Without one, PostgreSQL applies it to `public`. */
    public function to(string ...$roles): static
    {
        $this->attributes['roles'] = $roles;

        return $this;
    }

    /**
     * Which rows the statement may see.
     *
     * @param callable(PartialBuilder): mixed $predicate
     */
    public function using(callable $predicate): static
    {
        $predicate($this->attributes['using'] ??= new PartialBuilder());

        return $this;
    }

    /**
     * Which rows the statement may leave behind — `INSERT` and `UPDATE` only.
     *
     * @param callable(PartialBuilder): mixed $predicate
     */
    public function withCheck(callable $predicate): static
    {
        $predicate($this->attributes['withCheck'] ??= new PartialBuilder());

        return $this;
    }
}
