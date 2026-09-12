<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Partitions\PartitionBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Security\PolicyBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\PartitionCompiler;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Statistics\StatisticsBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\PolicyCompiler;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\StatisticsCompiler;

trait GrammarPartitions
{
    /**
     * The planner assumes columns are independent; extended statistics tell it where they are not.
     */
    public function compileStatistics(BaseBlueprint $blueprint, StatisticsBuilder $command): string
    {
        return StatisticsCompiler::create($this, $blueprint, $command);
    }

    /** @param Fluent<string, mixed> $command */
    public function compileDropStatistics(BaseBlueprint $blueprint, Fluent $command): string
    {
        /** @var list<string> $names */
        $names = (array)$command->get('statistics');

        return StatisticsCompiler::drop($this, $names);
    }

    public function compilePolicy(BaseBlueprint $blueprint, PolicyBuilder $command): string
    {
        return PolicyCompiler::create($this, $blueprint, $command);
    }

    /** @param Fluent<string, mixed> $command */
    public function compileDropPolicy(BaseBlueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'drop policy if exists %s on %s',
            $this->wrap((string)$command->get('policy')),
            $this->wrapTable($blueprint)
        );
    }

    /**
     * Row-level security is off until it is switched on, and even then the table owner bypasses
     * it — `force` is what makes the owner obey the policies too.
     *
     * @param Fluent<string, mixed> $command
     */
    public function compileRowLevelSecurity(BaseBlueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s %s row level security',
            $this->wrapTable($blueprint),
            (string)$command->get('action')
        );
    }

    public function compileAttachPartition(BaseBlueprint $blueprint, PartitionBuilder $command): string
    {
        return PartitionCompiler::attach($this, $blueprint, $command);
    }

    /** @param Fluent<string, mixed> $command */
    public function compileDetachPartition(BaseBlueprint $blueprint, Fluent $command): string
    {
        return PartitionCompiler::detach($this, $blueprint, $command);
    }

    /**
     * `alter table … set (fillfactor = 70, …)`.
     *
     * Emitted as its own statement rather than as `WITH (…)` on the create, so the same call works
     * on a new table and on one that already exists.
     *
     * @param Fluent<string, mixed> $command
     */
    public function compileStorageParameters(BaseBlueprint $blueprint, Fluent $command): string
    {
        /** @var array<string, scalar> $parameters */
        $parameters = (array)$command->get('parameters');

        if ($parameters === []) {
            throw new InvalidArgumentException('Name at least one storage parameter to set.');
        }

        $pairs = [];

        foreach ($parameters as $name => $value) {
            if (preg_match('/^[a-z_][a-z0-9_.]*$/i', (string)$name) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid storage parameter [%s].', $name));
            }

            $pairs[] = sprintf('%s = %s', $name, $this->storageValue($value));
        }

        return sprintf('alter table %s set (%s)', $this->wrapTable($blueprint), implode(', ', $pairs));
    }

    /** @param Fluent<string, mixed> $command */
    public function compileResetStorageParameters(BaseBlueprint $blueprint, Fluent $command): string
    {
        /** @var list<string> $names */
        $names = (array)$command->get('parameters');

        if ($names === []) {
            throw new InvalidArgumentException('Name at least one storage parameter to reset.');
        }

        foreach ($names as $name) {
            if (preg_match('/^[a-z_][a-z0-9_.]*$/i', $name) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid storage parameter [%s].', $name));
            }
        }

        return sprintf('alter table %s reset (%s)', $this->wrapTable($blueprint), implode(', ', $names));
    }

    /** A storage value is interpolated into DDL; only a number or a bare word can be one. */
    private function storageValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_string($value) && preg_match('/^[A-Za-z0-9_.]+$/', $value) === 1) {
            return $value;
        }

        throw new InvalidArgumentException('A storage parameter value must be a number or a bare word.');
    }
}
