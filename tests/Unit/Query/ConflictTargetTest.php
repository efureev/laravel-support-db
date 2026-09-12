<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Query;

use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Query\Builder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * PostgreSQL matches an `ON CONFLICT` target against a partial unique index by repeating the
 * index predicate — the conflict columns alone raise
 * `there is no unique or exclusion constraint matching the ON CONFLICT specification`.
 */
final class ConflictTargetTest extends UnitTestCase
{
    private const VALUES = [
        [
            'email' => 'a@x.io',
            'name'  => 'B',
        ],
    ];

    #[Test]
    public function withoutAPredicateTheFrameworksOwnStatementIsUnchanged(): void
    {
        self::assertSame(
            'insert into "users" ("email", "name") values (?, ?) '
            . 'on conflict ("email") do update set "name" = "excluded"."name"',
            $this->upsert(null)
        );
    }

    #[Test]
    public function thePredicateSitsBetweenTheTargetAndTheUpdate(): void
    {
        self::assertSame(
            'insert into "users" ("email", "name") values (?, ?) '
            . 'on conflict ("email") where ("deleted_at" is null) '
            . 'do update set "name" = "excluded"."name"',
            $this->upsert(static fn(PartialBuilder $w) => $w->whereNull('deleted_at'))
        );
    }

    #[Test]
    public function severalPredicatesAreJoined(): void
    {
        self::assertStringContainsString(
            'on conflict ("email") where ("deleted_at" is null) and ("is_active" is true) do update',
            $this->upsert(
                static fn(PartialBuilder $w) => $w->whereNull('deleted_at')->whereTrue('is_active')
            )
        );
    }

    #[Test]
    public function severalConflictColumnsKeepTheirOrder(): void
    {
        self::assertStringContainsString(
            'on conflict ("tenant_id", "email") where ("deleted_at" is null) do update',
            $this->upsert(
                static fn(PartialBuilder $w) => $w->whereNull('deleted_at'),
                [
                    'tenant_id', 'email',
                ]
            )
        );
    }

    /**
     * The whole vocabulary is the one the index was declared with, not a second dialect.
     */
    #[Test]
    public function thePredicateSpeaksTheIndexVocabulary(): void
    {
        $sql = $this->upsert(
            static fn(PartialBuilder $w) => $w
                ->whereIn('state', ['new', 'paid'])
                ->orWhereNotNull('archived_at')
        );

        self::assertStringContainsString(
            'where ("state" in (\'new\',\'paid\')) or ("archived_at" is not null)',
            $sql
        );
    }

    /**
     * A leading `or` is stripped here for the same reason it is in an index predicate: the clause
     * would otherwise open with a dangling boolean.
     */
    #[Test]
    public function aLeadingOrIsRemoved(): void
    {
        self::assertStringContainsString(
            'on conflict ("email") where ("deleted_at" is null) do update',
            $this->upsert(static fn(PartialBuilder $w) => $w->orWhereNull('deleted_at'))
        );
    }

    /**
     * Calling it twice accumulates rather than replacing, the way the index builder does.
     */
    #[Test]
    public function repeatedCallsAccumulate(): void
    {
        $query = $this->query()->onConflictWhere(
            static fn(PartialBuilder $w) => $w->whereNull('deleted_at')
        );
        $query->onConflictWhere(static fn(PartialBuilder $w) => $w->whereTrue('is_active'));

        self::assertStringContainsString(
            'where ("deleted_at" is null) and ("is_active" is true)',
            $this->grammarFor($query)->compileUpsert($query, self::VALUES, ['email'], ['name'])
        );
    }

    #[Test]
    public function anEmptyPredicateChangesNothing(): void
    {
        self::assertSame(
            $this->upsert(null),
            $this->upsert(static fn(PartialBuilder $w) => $w)
        );
    }

    /**
     * The splice is the one place this grammar depends on the shape of framework output. If that
     * shape changes it must say so rather than return a statement pointing at the wrong index.
     */
    #[Test]
    public function itRefusesToGuessWhenTheFrameworkOutputChangesShape(): void
    {
        $grammar = $this->grammarFor($this->query());

        $splice = new \ReflectionMethod($grammar, 'spliceConflictPredicate');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no longer compiles an upsert the way this grammar expects');

        $splice->invoke(
            $grammar,
            'insert into "users" ON CONFLICT (email) DO UPDATE SET x = 1',
            ['email'],
            '(a is null)'
        );
    }

    // ------------------------------------------------------------------ helpers

    /** @param array<array-key, string> $uniqueBy */
    private function upsert(?callable $predicate, array $uniqueBy = ['email']): string
    {
        $query = $this->query();

        if ($predicate !== null) {
            $query->onConflictWhere($predicate);
        }

        return $this->grammarFor($query)->compileUpsert($query, self::VALUES, $uniqueBy, ['name']);
    }

    private function query(): Builder
    {
        $connection = new Connection(
            static fn(): PDO => throw new LogicException('A unit test must not open a connection.'),
            'testing',
            '',
            ['driver' => 'pgsql']
        );

        /** @var Builder $query */
        $query = $connection->query();

        return $query->from('users');
    }

    private function grammarFor(Builder $query): \Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar
    {
        /** @var \Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar $grammar */
        $grammar = $query->getGrammar();

        return $grammar;
    }
}
