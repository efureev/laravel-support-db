<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Per-row authorisation the database enforces itself, which no amount of application code can be
 * talked out of. Laravel has no form for it.
 */
final class RowLevelSecurityTest extends UnitTestCase
{
    /** @return iterable<string, array{callable(Blueprint): mixed, string}> */
    public static function switches(): iterable
    {
        yield 'enable' => [
            static fn(Blueprint $t) => $t->enableRowLevelSecurity(),
            'alter table "docs" enable row level security',
        ];
        yield 'disable' => [
            static fn(Blueprint $t) => $t->disableRowLevelSecurity(),
            'alter table "docs" disable row level security',
        ];
        yield 'force' => [
            static fn(Blueprint $t) => $t->forceRowLevelSecurity(),
            'alter table "docs" force row level security',
        ];
        yield 'no force' => [
            static fn(Blueprint $t) => $t->noForceRowLevelSecurity(),
            'alter table "docs" no force row level security',
        ];
    }

    #[Test]
    #[DataProvider('switches')]
    public function theSwitchesSayWhatTheyDo(callable $define, string $expected): void
    {
        self::assertSame($expected, $this->sqlFor('docs', static fn(Blueprint $t) => $define($t))[0]);
    }

    #[Test]
    public function aPolicyCarriesItsUsingPredicate(): void
    {
        self::assertSame(
            'create policy "tenant_read" on "docs" for select '
            . 'using ((tenant = current_setting(\'app.tenant\')))',
            $this->policy(static fn(Blueprint $t) => $t->policy('tenant_read')
                ->for('select')
                ->using(static fn(PartialBuilder $w) => $w->whereRaw(
                    'tenant = current_setting(?)',
                    ['app.tenant']
                )))
        );
    }

    #[Test]
    public function withCheckGovernsWhatMayBeLeftBehind(): void
    {
        self::assertStringContainsString(
            'for insert with check (("tenant" = \'a\'))',
            $this->policy(static fn(Blueprint $t) => $t->policy('w')
                ->for('insert')
                ->withCheck(static fn(PartialBuilder $c) => $c->where('tenant', '=', 'a')))
        );
    }

    #[Test]
    public function bothPredicatesMayBeGiven(): void
    {
        $sql = $this->policy(static fn(Blueprint $t) => $t->policy('rw')
            ->for('update')
            ->using(static fn(PartialBuilder $w) => $w->whereNull('deleted_at'))
            ->withCheck(static fn(PartialBuilder $c) => $c->whereNull('deleted_at')));

        self::assertStringContainsString('using (("deleted_at" is null))', $sql);
        self::assertStringContainsString('with check (("deleted_at" is null))', $sql);
    }

    #[Test]
    public function rolesAreNamedAndQuoted(): void
    {
        self::assertStringContainsString(
            'to "app_user", "reporting"',
            $this->policy(static fn(Blueprint $t) => $t->policy('p')
                ->to('app_user', 'reporting')
                ->using(static fn(PartialBuilder $w) => $w->whereTrue('live')))
        );
    }

    /** Without `for`, PostgreSQL applies the policy to every command. */
    #[Test]
    public function theCommandIsOptional(): void
    {
        $sql = $this->policy(static fn(Blueprint $t) => $t->policy('p')
            ->using(static fn(PartialBuilder $w) => $w->whereTrue('live')));

        self::assertStringNotContainsString(' for ', $sql);
    }

    #[Test]
    public function anUnknownCommandIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A policy applies to one of');

        $this->sqlFor('docs', static fn(Blueprint $t) => $t->policy('p')->for('truncate'));
    }

    /**
     * A policy with neither predicate permits nothing, which is a mistake far more often than it
     * is an intention.
     */
    #[Test]
    public function aPolicyWithoutAPredicateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a using() or a withCheck()');

        $this->sqlFor('docs', static fn(Blueprint $t) => $t->policy('p')->for('select'));
    }

    #[Test]
    public function droppingIsIdempotent(): void
    {
        self::assertSame(
            'drop policy if exists "tenant_read" on "docs"',
            $this->sqlFor('docs', static fn(Blueprint $t) => $t->dropPolicy('tenant_read'))[0]
        );
    }

    #[Test]
    public function everyStatementCarriesTheTablePrefix(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static function (Blueprint $t): void {
                $t->enableRowLevelSecurity();
                $t->dropPolicy('p');
            },
            'pref_'
        );

        self::assertStringContainsString('"pref_docs"', $sql[0]);
        self::assertStringContainsString('"pref_docs"', $sql[1]);
    }

    private function policy(callable $define): string
    {
        return $this->sqlFor('docs', static fn(Blueprint $t) => $define($t))[0];
    }
}
