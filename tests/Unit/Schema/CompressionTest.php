<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Regression tests for D2, D3 and D14 — the column compression modifier.
 */
final class CompressionTest extends UnitTestCase
{
    #[Test]
    public function compressionIsInlinedWhenCreatingAColumn(): void
    {
        $sql = $this->sqlForCreate('t', static function (Blueprint $table): void {
            $table->string('data')->compression('lz4');
        });

        self::assertStringContainsString('compression lz4', $sql[0]);
    }

    #[Test]
    public function compressionWithoutAnArgumentFallsBackToPglz(): void
    {
        // `Fluent::__call()` assigns `true`, which used to be interpolated as `compression 1`.
        $sql = $this->sqlForCreate('t', static function (Blueprint $table): void {
            $table->string('data')->compression();
        });

        self::assertStringContainsString('compression pglz', $sql[0]);
        self::assertStringNotContainsString('compression 1', $sql[0]);
    }

    #[Test]
    public function changingAColumnEmitsASingleStandaloneStatement(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->text('a')->compression('lz4')->change();
        });

        $setCompression = $this->statementsContaining('set compression', $sql);

        self::assertCount(1, $setCompression);
        self::assertSame('alter table "t" alter column "a" set compression lz4', $setCompression[0]);
    }

    #[Test]
    public function changingAColumnDoesNotEmitTheInlineModifier(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->text('a')->compression('lz4')->change();
        });

        // `alter column "a"  compression lz4` is not valid PostgreSQL.
        $this->assertNoStatementContains('alter column "a"  compression', $sql);
    }

    #[Test]
    public function eachChangedColumnIsCompiledExactlyOnce(): void
    {
        $sql = $this->sqlFor('t', static function (Blueprint $table): void {
            $table->text('a')->compression('lz4')->change();
            $table->text('b')->compression('pglz')->change();
        });

        self::assertCount(2, $this->statementsContaining('set compression', $sql));
    }

    #[Test]
    public function anInvalidCompressionMethodIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column compression method');

        $this->sqlForCreate('t', static function (Blueprint $table): void {
            $table->string('data')->compression("lz4; drop table users; --");
        });
    }

    /**
     * @param list<string> $statements
     *
     * @return list<string>
     */
    private function statementsContaining(string $needle, array $statements): array
    {
        return array_values(array_filter(
            $statements,
            static fn(string $statement): bool => str_contains($statement, $needle)
        ));
    }
}
