<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ColumnAssertions;
use Php\Support\Laravel\Database\Tests\Helpers\IndexAssertions;
use PHPUnit\Framework\Attributes\Test;

class GenerateUUIDTest extends AbstractTestCase
{
    use ColumnAssertions;
    use IndexAssertions;

    #[Test]
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

        $this->assertLaravelTypeColumn('test_table', 'id', 'uuid');
        $this->assertPostgresTypeColumn('test_table', 'id', 'uuid');
    }

    #[Test]
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
        $this->assertLaravelTypeColumn('test_table', 'custom_name', 'uuid');
        $this->assertPostgresTypeColumn('test_table', 'custom_name', 'uuid');
    }

    #[Test]
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
        $this->assertLaravelTypeColumn('test_table', 'fk_id', 'uuid');
        $this->assertPostgresTypeColumn('test_table', 'fk_id', 'uuid');
    }

    #[Test]
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

        $this->assertLaravelTypeColumn('test_table', 'fk_id', 'uuid');
        $this->assertPostgresTypeColumn('test_table', 'fk_id', 'uuid');
    }

    #[Test]
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

        $this->assertLaravelTypeColumn('test_table', 'id', 'uuid');
        $this->assertPostgresTypeColumn('test_table', 'id', 'uuid');
    }

    #[Test]
    public function primaryUUIDCustomName(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->primaryUUID('custom_name');
                $table->string('title');
            }
        );

        $this->assertLaravelTypeColumn('test_table', 'custom_name', 'uuid');
        $this->assertPostgresTypeColumn('test_table', 'custom_name', 'uuid');

        $this->seeIndex('test_table_pkey');
    }

    #[Test]
    public function generateCb(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->generateUUID(default: function (string $column) {
                    return 'uuid_generate_v4()';
                });

                $table->string('title');
            }
        );

        $this->assertLaravelTypeColumn('test_table', 'id', 'uuid');
        $this->assertPostgresTypeColumn('test_table', 'id', 'uuid');
    }

    #[Test]
    public function expression(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->generateUUID(default: new Expression('uuid_generate_v4()'));

                $table->string('title');
            }
        );

        $this->assertLaravelTypeColumn('test_table', 'id', 'uuid');
        $this->assertPostgresTypeColumn('test_table', 'id', 'uuid');
    }
}
