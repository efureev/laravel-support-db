<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Tests\Functional\Schemas;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\ExtendedPostgresProvider;
use Php\Support\Laravel\Schemas\Blueprints\ExtendedBlueprint;
use Php\Support\Laravel\Schemas\Helpers\ColumnAssertions;
use Php\Support\Laravel\Schemas\Helpers\TableAssertions;
use Php\Support\Laravel\Tests\Functional\AbstractFunctionalTestCase;

class CreateTableTest extends AbstractFunctionalTestCase
{
    use ColumnAssertions;
    use TableAssertions;

    /**
     * @test
     */
    public function createSimple(): void
    {
        Schema::create(
            'test_table',
            static function (ExtendedBlueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('field_comment')
                    ->comment('test');
                $table->integer('field_default')
                    ->default(123);
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));
    }

    /**
     * @test
     */
    public function columnAssertions(): void
    {
        Schema::create(
            'test_table',
            static function (ExtendedBlueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('field_comment')
                    ->comment('test');
                $table->integer('field_default')
                    ->default(123);
            }
        );

        $this->assertSameTable(['id', 'name', 'field_comment', 'field_default'], 'test_table');

        $this->assertPostgresTypeColumn('test_table', 'id', 'integer');
        $this->assertLaravelTypeColumn('test_table', 'name', 'string');
        $this->assertPostgresTypeColumn('test_table', 'name', 'character varying');

        $this->assertDefaultOnColumn('test_table', 'field_default', '123');
        $this->assertCommentOnColumn('test_table', 'field_comment', 'test');

        $this->assertDefaultOnColumn('test_table', 'name');
        $this->assertCommentOnColumn('test_table', 'name');
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Facade::clearResolvedInstances();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ExtendedPostgresProvider::class,
        ];
    }


}
