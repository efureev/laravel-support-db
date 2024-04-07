<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\IndexAssertions;
use Php\Support\Laravel\Database\Tests\Helpers\TableAssertions;
use PHPUnit\Framework\Attributes\Test;


class CreateTableLikeTest extends AbstractTestCase
{
    use TableAssertions;
    use IndexAssertions;

    private const SRC_TABLE = 'source_table';
    private const TGT_TABLE = 'target_table';

    #[Test]
    public function createTableLikeOtherTable(): void
    {
        Schema::create(self::TGT_TABLE, function (Blueprint $table) {
            $table->like(self::SRC_TABLE);
            $table->ifNotExists();
        });

        $this->assertCompareTables(self::SRC_TABLE, self::TGT_TABLE);

        self::assertCount(3, $this->getIndexListByTable(self::SRC_TABLE));
        self::assertEmpty($this->getIndexListByTable(self::TGT_TABLE));

        $this->assertDatabaseCount(self::SRC_TABLE, 1);
        $this->assertDatabaseCount(self::TGT_TABLE, 0);
    }

    #[Test]
    public function createTableLikeOtherTableIncludeAll(): void
    {
        Schema::create(self::TGT_TABLE, function (Blueprint $table) {
            $table->like(self::SRC_TABLE)->includingAll();
            $table->ifNotExists();
        });

        $this->assertCompareTables(self::SRC_TABLE, self::TGT_TABLE);

        $srcList = $this->getIndexListByTable(self::SRC_TABLE);
        self::assertCount(3, $srcList);
        $tgtList = $this->getIndexListByTable(self::TGT_TABLE);
        self::assertCount(3, $tgtList);

        self::assertEquals(self::SRC_TABLE . '_pkey', $srcList[0]->indexname);
        self::assertEquals(self::TGT_TABLE . '_pkey', $tgtList[0]->indexname);

        self::assertEquals(self::SRC_TABLE . '_name_index', $srcList[1]->indexname);
        self::assertEquals(self::TGT_TABLE . '_name_idx', $tgtList[1]->indexname);

        self::assertEquals(self::SRC_TABLE . '_enum_index', $srcList[2]->indexname);
        self::assertEquals(self::TGT_TABLE . '_enum_idx', $tgtList[2]->indexname);

        $this->assertDatabaseCount(self::SRC_TABLE, 1);
        $this->assertDatabaseCount(self::TGT_TABLE, 0);
    }


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
                    'name' => 'test',
                    'enum' => 'false',
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
