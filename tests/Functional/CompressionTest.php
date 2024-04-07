<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional;

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ColumnAssertions;
use PHPUnit\Framework\Attributes\Test;

class CompressionTest extends AbstractTestCase
{
    use ColumnAssertions;

    #[Test]
    public function base(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
//                $table->string('data')->compression('lz4');
                $table->string('data')->compression('pglz');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));
    }

}
