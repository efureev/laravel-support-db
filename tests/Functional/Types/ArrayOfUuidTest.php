<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Types\UuidArrayType;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ColumnAssertions;
use Php\Support\Laravel\Database\Tests\Helpers\IndexAssertions;
use PHPUnit\Framework\Attributes\Test;

class ArrayOfUuidTest extends AbstractTestCase
{
    use ColumnAssertions;
    use IndexAssertions;

    #[Test]
    public function base(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
//                $table->string('data')->compression('lz4');
                $table->uuidArray('test_col');
                $table->ginIndex('test_col');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));
        $this->seeIndex('test_table_test_col_index');

        $definition = DB::selectOne('SELECT * FROM pg_indexes WHERE indexname = ?', ['test_table_test_col_index']);

        self::assertEquals(
            "CREATE INDEX test_table_test_col_index ON public.test_table USING gin (test_col)",
            $definition->indexdef
        );

        $this->assertTypeColumn('test_table', 'test_col', UuidArrayType::class);
    }

}
