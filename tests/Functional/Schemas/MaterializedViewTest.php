<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ViewAssertions;

/**
 * Materialized views used to be create-only: `dropView()` emitted `DROP VIEW`, which PostgreSQL
 * rejects for them, and `hasView()` looked only at `information_schema.views`, which does not
 * list them at all. See AUDIT.md, D9.
 */
class MaterializedViewTest extends AbstractTestCase
{
    use ViewAssertions;

    // refreshMaterializedView(concurrently: true) cannot run inside a transaction block.
    protected bool $transactional = false;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
            }
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExistsCascade('test_table');

        parent::tearDown();
    }

    #[Test]
    public function createFindAndDropAMaterializedView(): void
    {
        Schema::createView('test_mat_view', 'select * from test_table where name is not null', true);

        // Fails against `information_schema.views`, which excludes materialized views.
        $this->seeView('test_mat_view');
        $this->assertSameView(
            'select id, name from test_table where (name is not null);',
            'test_mat_view'
        );

        Schema::dropView('test_mat_view', true);
        $this->notSeeView('test_mat_view');
    }

    #[Test]
    public function aMaterializedViewHoldsItsOwnCopyOfTheData(): void
    {
        DB::table('test_table')->insert(['name' => 'first']);

        Schema::createView('test_mat_view', 'select * from test_table', true);

        DB::table('test_table')->insert(['name' => 'second']);

        // Still the snapshot taken at creation time.
        static::assertSame(1, DB::table('test_mat_view')->count());

        Schema::refreshMaterializedView('test_mat_view');

        static::assertSame(2, DB::table('test_mat_view')->count());

        Schema::dropView('test_mat_view', true);
    }

    #[Test]
    public function refreshConcurrentlyRequiresAUniqueIndex(): void
    {
        DB::table('test_table')->insert(['name' => 'first']);

        Schema::createView('test_mat_view', 'select id, name from test_table', true);
        DB::statement('create unique index test_mat_view_id_idx on test_mat_view (id)');

        DB::table('test_table')->insert(['name' => 'second']);

        Schema::refreshMaterializedView('test_mat_view', true);

        static::assertSame(2, DB::table('test_mat_view')->count());

        Schema::dropView('test_mat_view', true);
    }

    #[Test]
    public function dropViewIfExistsToleratesAMissingView(): void
    {
        Schema::dropViewIfExists('test_mat_view', true);
        Schema::dropViewIfExists('test_view');

        $this->notSeeView('test_mat_view');
    }

    #[Test]
    public function orReplaceIsRejectedForMaterializedViews(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('CREATE OR REPLACE for materialized views');

        Schema::createViewOrReplace('test_mat_view', 'select * from test_table', true);
    }

    #[Test]
    public function plainViewsAreStillFoundAfterTheCatalogSwitch(): void
    {
        Schema::createView('test_view', 'select * from test_table where name is not null');

        $this->seeView('test_view');
        $this->assertSameView(
            'select id, name from test_table where (name is not null);',
            'test_view'
        );

        Schema::dropView('test_view');
        $this->notSeeView('test_view');
    }
}
