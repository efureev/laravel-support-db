<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Tests\Functional\Schemas;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\ExtendedPostgresProvider;
use Php\Support\Laravel\Schemas\Blueprints\ExtendedBlueprint;
use Php\Support\Laravel\Schemas\Helpers\ViewAssertions;
use Php\Support\Laravel\Tests\Functional\AbstractFunctionalTestCase;


class CreateViewTest extends AbstractFunctionalTestCase
{
    use ViewAssertions;

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    /**
     * @test
     */
    public function createFacadeView(): void
    {
        Schema::createView('test_view', 'select * from test_table where name is not null');

        $this->seeView('test_view');
        $this->assertSameView(
            'select test_table.id, test_table.name from test_table where (test_table.name is not null);',
            'test_view'
        );

        Schema::dropView('test_view');
        $this->notSeeView('test_view');
    }

    /**
     * @test
     */
    public function createBlueprintView(): void
    {
        Schema::table(
            'test_table',
            static function (ExtendedBlueprint $table) {
                $table->createView('test_view', 'select * from test_table where name is not null');
            }
        );

        $this->seeView('test_view');
        $this->assertSameView(
            'select test_table.id, test_table.name from test_table where (test_table.name is not null);',
            'test_view'
        );

        Schema::table(
            'users',
            static function (ExtendedBlueprint $table) {
                $table->dropView('test_view');
            }
        );

        $this->notSeeView('test_view');
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            'test_table',
            static function (ExtendedBlueprint $table) {
                $table->increments('id');
                $table->string('name');
            }
        );

        Facade::clearResolvedInstances();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ExtendedPostgresProvider::class,
        ];
    }

}
