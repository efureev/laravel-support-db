<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Types\GeoPointType;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ColumnAssertions;
use PHPUnit\Framework\Attributes\Test;

class GeoPointTest extends AbstractTestCase
{
    use ColumnAssertions;

    #[Test]
    public function base(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->geoPoint('geo');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

        $this->assertTypeColumn('test_table', 'geo', GeoPointType::class);

        $expected = '(58.60374,49.65931)';

        DB::insert('INSERT INTO test_table VALUES (?)', [$expected]);

        $value = DB::selectOne('select "geo" from test_table');

        static::assertEquals($expected, $value->geo);
    }

}
