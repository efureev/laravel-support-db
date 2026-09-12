<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * A type is only a type once the server knows it, so these create one and use it as a column.
 */
class UserTypeTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::createEnumType('test_state', ['new', 'paid', 'shipped']);
        Schema::createDomain(
            'test_positive',
            'integer',
            static fn(PartialBuilder $check) => $check->where('value', '>', 0)
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');
        Schema::dropTypeIfExistsCascade('test_state');
        Schema::dropDomainIfExists('test_positive');

        parent::tearDown();
    }

    #[Test]
    public function anEnumTypeBecomesAUsableColumn(): void
    {
        $this->table();

        DB::table('test_table')->insert(['state' => 'paid', 'qty' => 5]);

        self::assertSame('paid', DB::table('test_table')->value('state'));
    }

    #[Test]
    public function theEnumRefusesALabelItDoesNotKnow(): void
    {
        $this->table();

        $this->assertRefused(
            static fn() => DB::table('test_table')->insert(['state' => 'bogus', 'qty' => 1]),
            'invalid input value for enum'
        );
    }

    #[Test]
    public function theDomainEnforcesItsCheck(): void
    {
        $this->table();

        $this->assertRefused(
            static fn() => DB::table('test_table')->insert(['state' => 'new', 'qty' => -1]),
            'violates check constraint'
        );
    }

    /**
     * PostgreSQL keeps enum labels in the order they were added, and `before`/`after` place a new
     * one rather than appending it — which matters, because comparisons use that order.
     */
    #[Test]
    public function valuesAreAddedInThePlaceTheyAreGiven(): void
    {
        Schema::addEnumValue('test_state', 'refunded');
        Schema::addEnumValue('test_state', 'pending', 'new');

        self::assertSame(
            [
                'pending', 'new', 'paid', 'shipped', 'refunded',
            ],
            array_map(
                static fn(object $row): string => $row->enumlabel,
                DB::select(
                    'select enumlabel from pg_enum e join pg_type t on t.oid = e.enumtypid
                     where t.typname = ? order by e.enumsortorder',
                    ['test_state']
                )
            )
        );
    }

    #[Test]
    public function aCompositeTypeHoldsItsFields(): void
    {
        Schema::createCompositeType('test_name', ['first' => 'text', 'last' => 'varchar(30)']);

        DB::statement('create table test_table (id serial primary key, name test_name)');
        DB::statement('insert into test_table (name) values (row(?, ?))', ['Ada', 'Lovelace']);

        $row = DB::selectOne('select (name).first as f, (name).last as l from test_table');

        self::assertSame('Ada', $row->f);
        self::assertSame('Lovelace', $row->l);

        DB::statement('drop table test_table');
        Schema::dropTypeIfExists('test_name');
    }

    #[Test]
    public function theDomainCheckReachesTheCatalogueAsPostgresWritesIt(): void
    {
        self::assertSame(
            'CHECK ((VALUE > 0))',
            DB::selectOne(
                'select pg_get_constraintdef(oid) as def from pg_constraint where conname = ?',
                ['test_positive_check']
            )->def,
            'a quoted "value" is resolved to the VALUE placeholder'
        );
    }

    #[Test]
    public function cascadeTakesTheColumnsWithTheType(): void
    {
        $this->table();

        Schema::dropTypeIfExistsCascade('test_state');

        self::assertNull(DB::selectOne('select 1 as x from pg_type where typname = ?', ['test_state']));
    }

    private function table(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->rawColumn('state', 'test_state');
                $table->rawColumn('qty', 'test_positive');
            }
        );
    }

    private function assertRefused(callable $write, string $message): void
    {
        try {
            DB::transaction(static function () use ($write): void {
                $write();
            });
            self::fail('the write was expected to be refused');
        } catch (QueryException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
    }
}
