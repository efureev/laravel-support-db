<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Laravel's schema builder has no `CHECK` constraint in any grammar, so a table's columns can be
 * described and most of its invariants cannot. The predicate is the one partial indexes use:
 * PostgreSQL treats both as a boolean expression over a row.
 */
final class CheckConstraintTest extends UnitTestCase
{
    #[Test]
    public function aCheckIsItsOwnAlterStatement(): void
    {
        $sql = $this->sqlFor(
            'products',
            static fn(Blueprint $t) => $t->check('price_positive')->where('price', '>', 0)
        );

        self::assertSame(
            'alter table "products" add constraint "price_positive" check (("price" > 0))',
            $sql[0]
        );
    }

    /**
     * Emitted as `ALTER TABLE` rather than inline, so the same call works on a new table and on
     * one that already exists.
     */
    #[Test]
    public function itIsSeparateFromTheCreateStatement(): void
    {
        $sql = $this->sqlForCreate(
            'products',
            static function (Blueprint $t): void {
                $t->integer('price');
                $t->check('price_positive')->where('price', '>', 0);
            }
        );

        self::assertSame('create table "products" ("price" integer not null)', $sql[0]);
        self::assertSame(
            'alter table "products" add constraint "price_positive" check (("price" > 0))',
            $sql[1]
        );
    }

    #[Test]
    public function conditionsAreJoined(): void
    {
        $sql = $this->sqlFor(
            'products',
            static fn(Blueprint $t) => $t->check('stock_sane')
                ->whereNotNull('sku')
                ->whereBetween('stock', [0, 10000])
        );

        self::assertSame(
            'alter table "products" add constraint "stock_sane" '
            . 'check (("sku" is not null) and ("stock" between 0 and 10000))',
            $sql[0]
        );
    }

    #[Test]
    public function theWholePredicateVocabularyIsAvailable(): void
    {
        $sql = $this->sqlFor(
            'products',
            static fn(Blueprint $t) => $t->check('state_known')
                ->whereIn('state', ['new', 'paid'])
                ->orWhereTrue('archived')
        );

        self::assertStringContainsString(
            'check (("state" in (\'new\',\'paid\')) or ("archived" is true))',
            $sql[0]
        );
    }

    #[Test]
    public function droppingNamesTheConstraint(): void
    {
        $sql = $this->sqlFor('products', static fn(Blueprint $t) => $t->dropCheck('price_positive'));

        self::assertSame('alter table "products" drop constraint "price_positive"', $sql[0]);
    }

    #[Test]
    public function bothStatementsCarryTheTablePrefix(): void
    {
        $sql = $this->sqlFor(
            'products',
            static function (Blueprint $t): void {
                $t->check('price_positive')->where('price', '>', 0);
                $t->dropCheck('old_rule');
            },
            'pref_'
        );

        self::assertStringStartsWith('alter table "pref_products" add constraint', $sql[0]);
        self::assertSame('alter table "pref_products" drop constraint "old_rule"', $sql[1]);
    }

    /**
     * `check (())` is a syntax error, so an empty condition list is refused rather than compiled.
     */
    #[Test]
    public function aCheckWithoutConditionsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one condition');

        $this->sqlFor('products', static fn(Blueprint $t) => $t->check('empty_rule'));
    }

    /**
     * The constraint name travels as `constraint`, not `name`: `createCommand()` merges parameters
     * over the command name, so a parameter called `name` would replace `check` and the command
     * would never dispatch.
     */
    #[Test]
    public function theConstraintNameDoesNotDisplaceTheCommandName(): void
    {
        $blueprint = $this->blueprint('products');
        $blueprint->check('price_positive')->where('price', '>', 0);

        self::assertSame('check', $blueprint->getCommands()[0]->get('name'));
        self::assertSame('price_positive', $blueprint->getCommands()[0]->get('constraint'));
    }
}
