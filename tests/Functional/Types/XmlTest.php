<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Types;

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Types\XmlType;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ColumnAssertions;
use PHPUnit\Framework\Attributes\Test;

class XmlTest extends AbstractTestCase
{
    use ColumnAssertions;

    #[Test]
    public function base(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->xml('xml');
            }
        );

        static::assertTrue(Schema::hasTable('test_table'));
        $this->assertTypeColumn('test_table', 'xml', XmlType::class);
    }

}
