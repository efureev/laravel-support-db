<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

class CompressionTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->serverVersion() < 140000) {
            self::markTestSkipped('Column compression needs PostgreSQL 14.');
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('methods')]
    public function theColumnIsStoredWithTheRequestedMethod(string $method, string $expected): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) use ($method) {
                $table->string('data')->compression($method);
            }
        );

        self::assertSame($expected, $this->compressionOf('test_table', 'data'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function methods(): iterable
    {
        // `pg_attribute.attcompression` stores the method as a single character.
        yield 'pglz' => [
            'pglz',
            'p',
        ];
        yield 'lz4' => [
            'lz4',
            'l',
        ];
    }

    /**
     * Without an argument the modifier used to emit `compression 1`, which PostgreSQL rejects.
     */
    #[Test]
    public function theDefaultMethodIsPglz(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->string('data')->compression();
            }
        );

        self::assertSame('p', $this->compressionOf('test_table', 'data'));
    }

    #[Test]
    public function aColumnWithoutTheModifierKeepsTheServerDefault(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->string('data');
            }
        );

        self::assertSame('', $this->compressionOf('test_table', 'data'));
    }

    private function compressionOf(string $table, string $column): string
    {
        $row = DB::selectOne(
            'select a.attcompression
               from pg_attribute a
               join pg_class c on c.oid = a.attrelid
              where c.relname = ? and a.attname = ?',
            [
                $table,
                $column,
            ]
        );

        return (string)$row->attcompression;
    }
}
