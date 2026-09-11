<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Regression tests for D6 — `CreateCompiler` collapsed every double space in the statement.
 */
final class CreateTableTest extends UnitTestCase
{
    #[Test]
    public function doubleSpacesInsideADefaultValueAredPreserved(): void
    {
        $sql = $this->sqlForCreate('t', static function (Blueprint $table): void {
            $table->string('greeting')->default('hello  world');
        });

        self::assertStringContainsString("default 'hello  world'", $sql[0]);
    }

    #[Test]
    public function doubleSpacesInsideFromSelectAreaPreserved(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->create();
            $table->fromSelect("select 'a  b' as label from src");
        });

        self::assertStringContainsString("select 'a  b' as label from src", $sql[0]);
    }

    /**
     * The statement layout must not drift while `str_replace('  ', ' ')` is removed.
     */
    #[Test]
    #[DataProvider('tableVariants')]
    public function tableStatementLayoutIsUnchanged(bool $temporary, bool $ifNotExists, string $expected): void
    {
        $sql = $this->sqlForCreate('t', static function (Blueprint $table) use ($temporary, $ifNotExists): void {
            if ($temporary) {
                $table->temporary();
            }

            if ($ifNotExists) {
                $table->ifNotExists();
            }

            $table->string('c');
        });

        self::assertSame($expected, $sql[0]);
    }

    public static function tableVariants(): iterable
    {
        yield 'plain' => [false, false, 'create table "t" ("c" varchar(255) not null)'];
        yield 'if not exists' => [false, true, 'create table if not exists "t" ("c" varchar(255) not null)'];
        yield 'temporary' => [true, false, 'create temporary table "t" ("c" varchar(255) not null)'];
        yield 'both' => [
            true,
            true,
            'create temporary table if not exists "t" ("c" varchar(255) not null)',
        ];
    }

    #[Test]
    public function fromTableAppliesThePrefixAndQuotesTheSource(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->create();
            $table->fromTable('src');
        }, 'pref_');

        self::assertSame('create table "pref_t" as table "pref_src"', $sql[0]);
    }

    #[Test]
    public function likeAppliesThePrefixAndQuotesTheSource(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->create();
            $table->like('src');
        }, 'pref_');

        self::assertSame('create table "pref_t" (like "pref_src")', $sql[0]);
    }
}
