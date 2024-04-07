<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Types\GeoPathType;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ColumnAssertions;
use PHPUnit\Framework\Attributes\Test;

class GeoPathTest extends AbstractTestCase
{
    use ColumnAssertions;

    #[Test]
    public function base(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->geoPath('path');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

        $expected = '(58.60374,49.65931),(50,40.6),(10,20)';

        DB::insert('INSERT INTO test_table VALUES (?)', [$expected]);

        $value = DB::selectOne('select "path" from test_table');

        static::assertEquals("($expected)", $value->path);

        $this->assertTypeColumn('test_table', 'path', GeoPathType::class);
    }

}
