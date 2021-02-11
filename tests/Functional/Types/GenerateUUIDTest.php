<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Helpers\ColumnAssertions;
use Php\Support\Laravel\Database\Schema\Helpers\IndexAssertions;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

class GenerateUUIDTest extends AbstractTestCase
{
    use ColumnAssertions;
    use IndexAssertions;

    /**
     * @test
     */
    public function base(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->generateUUID();
                $table->string('title');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

        $this->assertLaravelTypeColumn('test_table', 'id', 'guid');
        $this->assertPostgresTypeColumn('test_table', 'id', 'uuid');
    }

    /**
     * @test
     */
    public function uuidWithoutIndex(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->generateUUID('custom_name', false);
                $table->string('title');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));
        $this->notSeeIndex('test_table_pkey');
        $this->assertLaravelTypeColumn('test_table', 'custom_name', 'guid');
        $this->assertPostgresTypeColumn('test_table', 'custom_name', 'uuid');
    }

    /**
     * @test
     */
    public function uuidWithNull(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->generateUUID('fk_id', null);
                $table->string('title');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));
        $this->notSeeIndex('test_table_pkey');
        $this->assertLaravelTypeColumn('test_table', 'fk_id', 'guid');
        $this->assertPostgresTypeColumn('test_table', 'fk_id', 'uuid');
    }

    /**
     * @test
     */
    public function uuidIndexedWithNull(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->generateUUID('fk_id', null)->index();
                $table->string('title');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));
        $this->notSeeIndex('test_table_pkey');
        $this->seeIndex('test_table_fk_id_index');

        $this->assertLaravelTypeColumn('test_table', 'fk_id', 'guid');
        $this->assertPostgresTypeColumn('test_table', 'fk_id', 'uuid');
    }

    /**
     * @test
     */
    public function primaryUUID(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->primaryUUID();
                $table->string('title');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

        $this->assertLaravelTypeColumn('test_table', 'id', 'guid');
        $this->assertPostgresTypeColumn('test_table', 'id', 'uuid');
    }

    /**
     * @test
     */
    public function primaryUUIDCustomName(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->primaryUUID('custom_name');
                $table->string('title');
            }
        );

        $this->assertLaravelTypeColumn('test_table', 'custom_name', 'guid');
        $this->assertPostgresTypeColumn('test_table', 'custom_name', 'uuid');

        $this->seeIndex('test_table_pkey');
    }

}
