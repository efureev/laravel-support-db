<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Helpers\ColumnAssertions;
use Php\Support\Laravel\Database\Schema\Helpers\IndexAssertions;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

class ArrayOfUuidTest extends AbstractTestCase
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
                $table->increments('id');
//                $table->string('data')->compression('lz4');
                $table->uuidArray('uuids');
                $table->ginIndex('uuids');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));
        static::seeIndex('test_table_uuids_index');

        $definition = DB::selectOne('SELECT * FROM pg_indexes WHERE indexname = ?', ['test_table_uuids_index']);

        self::assertEquals(
            "CREATE INDEX test_table_uuids_index ON public.test_table USING gin (uuids)",
            $definition->indexdef
        );

        //        $this->assertLaravelTypeColumn('test_table', 'uuids', 'uuidArray');
        //        $this->assertPostgresTypeColumn('test_table', 'uuids', 'uuidArray');
    }

}
