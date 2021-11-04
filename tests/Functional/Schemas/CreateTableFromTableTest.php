<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Helpers\IndexAssertions;
use Php\Support\Laravel\Database\Schema\Helpers\TableAssertions;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;


class CreateTableFromTableTest extends AbstractTestCase
{
    use TableAssertions;
    use IndexAssertions;

    private const SRC_TABLE = 'source_table';
    private const TGT_TABLE = 'target_table';

    /**
     * @test
     */
    public function createTableFromTable(): void
    {
        Schema::create(self::TGT_TABLE, function (Blueprint $table) {
            $table->fromTable(self::SRC_TABLE);
            $table->ifNotExists();
        });

        $this->seeTable(self::TGT_TABLE);

        $list = $this->getTableDefinition(self::TGT_TABLE);

        static::assertEquals(['id', 'name', 'enum', 'field_comment', 'field_default'], $list);
        static::assertEmpty($this->getIndexListByTable(self::TGT_TABLE));

        $this->assertDatabaseCount(self::TGT_TABLE, 2);
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
