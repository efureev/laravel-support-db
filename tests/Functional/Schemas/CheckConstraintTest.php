<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * A constraint is only a constraint if the server enforces it, so these write rows and watch what
 * happens rather than reading the statement back.
 */
class CheckConstraintTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->integer('price');
                $table->string('state');

                $table->check('price_positive')->where('price', '>', 0);
            }
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    #[Test]
    public function aRowThatSatisfiesTheCheckIsAccepted(): void
    {
        DB::table('test_table')->insert(['price' => 10, 'state' => 'new']);

        self::assertSame(1, DB::table('test_table')->count());
    }

    #[Test]
    public function aRowThatViolatesItIsRefused(): void
    {
        $this->assertRefused(
            static fn() => DB::table('test_table')->insert(['price' => 0, 'state' => 'new']),
            'price_positive'
        );
    }

    #[Test]
    public function theConstraintExistsInTheCatalogue(): void
    {
        self::assertSame(
            'CHECK ((price > 0))',
            $this->constraintDefinition('price_positive')
        );
    }

    #[Test]
    public function aSecondConstraintCanBeAddedToAnExistingTable(): void
    {
        Schema::table(
            'test_table',
            static function (Blueprint $table) {
                $table->check('state_known')->whereIn('state', ['new', 'paid']);
            }
        );

        DB::table('test_table')->insert(['price' => 10, 'state' => 'paid']);

        $this->assertRefused(
            static fn() => DB::table('test_table')->insert(['price' => 10, 'state' => 'bogus']),
            'state_known'
        );
    }

    #[Test]
    public function droppingItStopsTheEnforcement(): void
    {
        Schema::table('test_table', static fn(Blueprint $table) => $table->dropCheck('price_positive'));

        DB::table('test_table')->insert(['price' => 0, 'state' => 'new']);

        self::assertSame(1, DB::table('test_table')->count());
        self::assertNull($this->constraintDefinition('price_positive'));
    }

    private function constraintDefinition(string $name): ?string
    {
        $row = DB::selectOne(
            'select pg_get_constraintdef(oid) as def from pg_constraint where conname = ?',
            [$name]
        );

        return $row?->def;
    }

    /**
     * A refused write aborts the transaction the suite runs in; a nested one confines that to a
     * savepoint so `tearDown()` still has a usable connection.
     */
    private function assertRefused(callable $write, string $constraint): void
    {
        try {
            DB::transaction(static function () use ($write): void {
                $write();
            });
            self::fail("the [$constraint] constraint was expected to refuse the row");
        } catch (QueryException $e) {
            self::assertStringContainsString($constraint, $e->getMessage());
        }
    }
}
