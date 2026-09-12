<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Query\Grammars;

use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;
use Php\Support\Laravel\Database\Query\Builder;
use Php\Support\Laravel\Database\Query\Compilers\ConflictTargetCompiler;
use RuntimeException;

class PostgresGrammar extends BasePostgresGrammar
{
    /** @param array<array-key, string> $cols */
    public function compileReturns(array $cols): string
    {
        $cols = array_unique(array_filter($cols));

        $returns = implode(',', array_map($this->wrap(...), $cols));

        return $returns ? " returning $returns" : '';
    }

    /**
     * `ON CONFLICT` needs the index predicate spelled out before PostgreSQL will match a
     * *partial* unique index — `on conflict (email)` alone raises
     * `there is no unique or exclusion constraint matching the ON CONFLICT specification`
     * against an index created `where deleted_at is null`.
     *
     * The framework compiles the rest of the statement; this only inserts the predicate the
     * builder was given, so a future change to `compileUpsert()` is inherited rather than
     * re-implemented.
     *
     * @param array<array-key, mixed> $values
     * @param array<array-key, string> $uniqueBy
     * @param array<array-key, mixed> $update
     */
    #[\Override]
    public function compileUpsert(BaseQueryBuilder $query, array $values, array $uniqueBy, array $update)
    {
        $sql = parent::compileUpsert($query, $values, $uniqueBy, $update);

        $target = $query instanceof Builder ? $query->getConflictTarget() : null;
        $predicate = $target === null ? '' : ConflictTargetCompiler::compile($this, $target);

        return $predicate === '' ? $sql : $this->spliceConflictPredicate($sql, $uniqueBy, $predicate);
    }

    /**
     * Put the predicate between the conflict target and the update.
     *
     * The needle is rebuilt from the same input the parent compiled from, so it matches whatever
     * the parent emitted rather than guessing at its shape. If it stops matching, the framework
     * has changed how it writes an upsert, and saying so beats quietly emitting SQL that targets
     * the wrong index.
     *
     * @param array<array-key, string> $uniqueBy
     */
    protected function spliceConflictPredicate(string $sql, array $uniqueBy, string $predicate): string
    {
        $target = ' on conflict (' . $this->columnize($uniqueBy) . ')';

        if (!str_contains($sql, "$target do update set ")) {
            throw new RuntimeException(
                'Cannot place the ON CONFLICT predicate: the framework no longer compiles an '
                . 'upsert the way this grammar expects. Reporting it beats emitting wrong SQL.'
            );
        }

        return str_replace("$target do update set ", "$target where $predicate do update set ", $sql);
    }
}
