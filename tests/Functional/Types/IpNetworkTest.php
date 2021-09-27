<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Helpers\ColumnAssertions;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

class IpNetworkTest extends AbstractTestCase
{
    use ColumnAssertions;

    /**
     * @test
     */
    public function base(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->ipNetwork('ip');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

//        $this->assertLaravelTypeColumn('test_table', 'ip', 'cidr');
//        $this->assertPostgresTypeColumn('test_table', 'ip', 'cidr');

        $value = '192.168/24';
        DB::insert(
            'INSERT INTO test_table VALUES (?)',
            [
                $value,
            ]
        );

        $value = DB::selectOne('select "ip" from test_table');

        static::assertEquals("192.168.0.0/24", $value->ip);
    }

}
