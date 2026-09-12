<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * A policy is only a policy if the server hides rows because of it, so this one switches role and
 * counts what is left.
 */
class RowLevelSecurityTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('drop role if exists test_tenant_role');
        DB::statement('create role test_tenant_role');

        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('tenant');
                $table->string('body');

                $table->enableRowLevelSecurity();
                $table->forceRowLevelSecurity();

                $table->policy('test_tenant_read')
                    ->for('select')
                    ->to('test_tenant_role')
                    ->using(static fn(PartialBuilder $where) => $where->whereRaw(
                        'tenant = current_setting(?, true)',
                        ['app.tenant']
                    ));
            }
        );

        DB::statement('grant select on test_table to test_tenant_role');
        DB::table('test_table')->insert([
            [
                'tenant' => 'a',
                'body'   => 'one',
            ],
            [
                'tenant' => 'b',
                'body'   => 'two',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('reset role');
        Schema::dropIfExists('test_table');
        DB::statement('drop role if exists test_tenant_role');

        parent::tearDown();
    }

    #[Test]
    public function theTableRecordsThatSecurityIsOnAndForced(): void
    {
        $row = DB::selectOne(
            'select relrowsecurity, relforcerowsecurity from pg_class where relname = ?',
            ['test_table']
        );

        self::assertTrue($row->relrowsecurity);
        self::assertTrue($row->relforcerowsecurity, 'the owner obeys the policies too');
    }

    /** The point of all of it. */
    #[Test]
    public function thePolicyHidesTheRowsItDoesNotPermit(): void
    {
        DB::statement('set role test_tenant_role');
        DB::statement('select set_config(?, ?, false)', ['app.tenant', 'a']);

        $visible = DB::table('test_table')->pluck('body')->all();

        DB::statement('reset role');

        self::assertSame(['one'], $visible);
    }

    #[Test]
    public function adifferentTenantSeesADifferentRow(): void
    {
        DB::statement('set role test_tenant_role');
        DB::statement('select set_config(?, ?, false)', ['app.tenant', 'b']);

        $visible = DB::table('test_table')->pluck('body')->all();

        DB::statement('reset role');

        self::assertSame(['two'], $visible);
    }

    #[Test]
    public function thePolicyIsInTheCatalogue(): void
    {
        self::assertSame(
            'r',
            DB::selectOne(
                'select polcmd from pg_policy p join pg_class c on c.oid = p.polrelid
                 where c.relname = ? and p.polname = ?',
                [
                    'test_table', 'test_tenant_read',
                ]
            )->polcmd,
            'r is what PostgreSQL calls a SELECT policy'
        );
    }

    #[Test]
    public function droppingItLetsTheRowsBackOut(): void
    {
        Schema::table('test_table', static fn(Blueprint $t) => $t->dropPolicy('test_tenant_read'));

        self::assertNull(DB::selectOne(
            'select 1 as x from pg_policy p join pg_class c on c.oid = p.polrelid
             where c.relname = ? and p.polname = ?',
            [
                'test_table', 'test_tenant_read',
            ]
        ));
    }

    #[Test]
    public function securityCanBeSwitchedOffAgain(): void
    {
        Schema::table('test_table', static function (Blueprint $t) {
            $t->noForceRowLevelSecurity();
            $t->disableRowLevelSecurity();
        });

        $row = DB::selectOne(
            'select relrowsecurity, relforcerowsecurity from pg_class where relname = ?',
            ['test_table']
        );

        self::assertFalse($row->relrowsecurity);
        self::assertFalse($row->relforcerowsecurity);
    }
}
