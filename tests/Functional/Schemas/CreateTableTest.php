<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ColumnAssertions;
use Php\Support\Laravel\Database\Tests\Helpers\TableAssertions;
use PHPUnit\Framework\Attributes\Test;

class CreateTableTest extends AbstractTestCase
{
    use ColumnAssertions;
    use TableAssertions;

    #[Test]
    public function createSimple(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
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

    #[Test]
    public function columnAssertions(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
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
        $this->assertLaravelTypeColumn('test_table', 'name', 'character varying(255)');
        $this->assertPostgresTypeColumn('test_table', 'name', 'character varying');

        $this->assertDefaultOnColumn('test_table', 'field_default', '123');
        $this->assertCommentOnColumn('test_table', 'field_comment', 'test');

        $this->assertDefaultOnColumn('test_table', 'name');
        $this->assertCommentOnColumn('test_table', 'name');
    }

}
