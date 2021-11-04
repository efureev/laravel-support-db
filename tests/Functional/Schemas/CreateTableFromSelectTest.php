<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Helpers\IndexAssertions;
use Php\Support\Laravel\Database\Schema\Helpers\TableAssertions;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;


class CreateTableFromSelectTest extends AbstractTestCase
{
    use TableAssertions;
    use IndexAssertions;

    private const SRC_TABLE = 'source_table';
    private const TGT_TABLE = 'target_table';

    /**
     * @test
     */
    public function createTableFromSelect(): void
    {
        Schema::create(self::TGT_TABLE, function (Blueprint $table) {
            $table->fromSelect('select t1.id, t1.name from ' . self::SRC_TABLE . ' t1');
        });

        $this->seeTable(self::TGT_TABLE);

        $list = $this->getTableDefinition(self::TGT_TABLE);

        static::assertEquals(['id', 'name'], $list);
        static::assertEmpty($this->getIndexListByTable(self::TGT_TABLE));
    }

    /**
     * @test
     */
    public function createTableFromSelectWithJoin(): void
    {
        $tbl = self::SRC_TABLE . '_2';
        Schema::create(
            $tbl,
            static function (Blueprint $table) {
                $table->text('extra');
                $table->integer('src_id');
                $table->boolean('enabled');
                $table->foreign('src_id')->references('id')->on(self::SRC_TABLE);
            }
        );

        DB::table($tbl)
            ->insert(
                [
                    [
                        'extra'   => 'extra text',
                        'src_id'  => 1,
                        'enabled' => true,
                    ],
                    [
                        'extra'   => 'dis text',
                        'src_id'  => 2,
                        'enabled' => false,
                    ],
                ]
            );


        Schema::create(self::TGT_TABLE, function (Blueprint $table) use ($tbl) {
            $table->fromSelect(
                'select t1.id, t2.enabled, t2.extra from ' . self::SRC_TABLE . ' t1 ' .
                'join ' . $tbl . ' t2 on t1.id = t2.src_id ' .
                'where t2.enabled = true'
            );
        });

        $this->seeTable(self::TGT_TABLE);

        $list = $this->getTableDefinition(self::TGT_TABLE);
        static::assertEquals(['id', 'enabled', 'extra'], $list);
        static::assertEmpty($this->getIndexListByTable(self::TGT_TABLE));

        $this->assertDatabaseCount(self::TGT_TABLE, 1);
    }

    /**
     * @test
     */
    public function createTableFromSelectExtended(): void
    {
        $tbl = 'src_table';

        Schema::create(
            $tbl,
            static function (Blueprint $table) {
                $table->string('key', 16)->primary();
                $table->string('title');
                $table->integer('sort')->index();
            }
        );

        DB::table($tbl)
            ->insert(
                [
                    [
                        'key'   => 'ru',
                        'title' => 'RU',
                        'sort'  => 1,
                    ],
                    [
                        'key'   => 'en',
                        'title' => 'EN',
                        'sort'  => 2,
                    ],
                ]
            );

        Schema::create(self::TGT_TABLE, function (Blueprint $table) use ($tbl) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

            $table->fromSelect(
                'select uuid_generate_v4() as id, key, title, sort from ' . $tbl
            );
        });

        $this->seeTable(self::TGT_TABLE);

        $list = $this->getTableDefinition(self::TGT_TABLE);

        static::assertEquals(['id', 'key', 'title', 'sort'], $list);
        static::assertEmpty($this->getIndexListByTable(self::TGT_TABLE));

        $this->assertDatabaseCount(self::TGT_TABLE, 2);


        Schema::create(self::TGT_TABLE.'_1', function (Blueprint $table) use ($tbl) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

            $table->fromSelect(
                'select uuid_generate_v4() as id, * from ' . $tbl
            );
        });

        $this->seeTable(self::TGT_TABLE.'_1');

        $list = $this->getTableDefinition(self::TGT_TABLE.'_1');

        static::assertEquals(['id', 'key', 'title', 'sort'], $list);
        static::assertEmpty($this->getIndexListByTable(self::TGT_TABLE.'_1'));

        $this->assertDatabaseCount(self::TGT_TABLE.'_1', 2);
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            self::SRC_TABLE,
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->index();
                $table->enum('enum', ['true', 'false'])->index();
                $table->string('field_comment')->nullable();
                $table->integer('field_default')->default(123);
            }
        );

        DB::table(self::SRC_TABLE)
            ->insert(
                [
                    [
                        'name' => 'test',
                        'enum' => 'false',
                    ],
                    [
                        'name' => 'test disabled',
                        'enum' => 'true',
                    ],
                ]
            );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExistsCascade(self::TGT_TABLE);
        Schema::dropIfExistsCascade(self::SRC_TABLE);

        parent::tearDown();
    }
}
