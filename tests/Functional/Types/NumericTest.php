<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\ColumnType;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ColumnAssertions;
use PHPUnit\Framework\Attributes\Test;

class NumericTest extends AbstractTestCase
{
    use ColumnAssertions;

    #[Test]
    public function base(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->numeric('num');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

        $this->assertTypeColumn('test_table', 'num', ColumnType::Numeric);
    }

    #[Test]
    public function precision(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->numeric('num', 10);
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

        $this->assertLaravelTypeColumn('test_table', 'num', ColumnType::Numeric->value . '(10,0)');
        $this->assertPostgresTypeColumn('test_table', 'num', ColumnType::Numeric->value);
    }

    #[Test]
    public function scale(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->numeric('num', 10, 2);
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));

        $this->assertLaravelTypeColumn('test_table', 'num', ColumnType::Numeric->value . '(10,2)');
        $this->assertPostgresTypeColumn('test_table', 'num', ColumnType::Numeric->value);
    }
}
