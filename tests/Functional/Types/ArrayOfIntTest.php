<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Helpers\ColumnAssertions;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

class ArrayOfIntTest extends AbstractTestCase
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
                $table->increments('id');
                $table->intArray('numbers');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

        //        $this->assertLaravelTypeColumn('test_table', 'numbers', 'intArray');
        //        $this->assertPostgresTypeColumn('test_table', 'numbers', 'intArray');
    }

}
