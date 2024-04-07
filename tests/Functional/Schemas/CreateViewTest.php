<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ViewAssertions;
use PHPUnit\Framework\Attributes\Test;


class CreateViewTest extends AbstractTestCase
{
    use ViewAssertions;

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    #[Test]
    public function createFacadeView(): void
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

    #[Test]
    public function createViewOrReplace(): void
    {
        Schema::createViewOrReplace('test_view', 'select * from test_table where name is not null');

        $this->seeView('test_view');
        $this->assertSameView(
            'select id, name from test_table where (name is not null);',
            'test_view'
        );

        Schema::createViewOrReplace('test_view', 'select * from test_table where name is null');

        $this->seeView('test_view');
        $this->assertSameView(
            'select id, name from test_table where (name is null);',
            'test_view'
        );

        Schema::dropView('test_view');
        $this->notSeeView('test_view');
    }

    #[Test]
    public function createBlueprintView(): void
    {
        Schema::table(
            'test_table',
            static function (Blueprint $table) {
                $table->createView('test_view', 'select * from test_table where name is not null');
            }
        );

        $this->seeView('test_view');
        $this->assertSameView(
            'select id, name from test_table where (name is not null);',
            'test_view'
        );

        Schema::table(
            'users',
            static function (Blueprint $table) {
                $table->dropView('test_view');
            }
        );

        $this->notSeeView('test_view');
    }

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
        //        Facade::clearResolvedInstances();
    }


}
