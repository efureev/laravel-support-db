<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use BadMethodCallException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Regression tests for D12 — column modifiers that were advertised in phpdoc but silently
 * did nothing, because Laravel only turns a fixed set of column attributes into index commands.
 */
final class ColumnDefinitionTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('removedModifiers')]
    public function aRemovedColumnModifierFailsLoudly(string $modifier, string $hint): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage($hint);

        $this->blueprint('t')->string('c')->{$modifier}('whatever');
    }

    /** @return iterable<string, array{string, string}> */
    public static function removedModifiers(): iterable
    {
        yield 'ginIndex' => [
            'ginIndex',
            'Use $table->ginIndex($columns)',
        ];
        yield 'algorithm' => [
            'algorithm',
            'Use $table->index($columns, $name, $algorithm)',
        ];
    }

    #[Test]
    public function supportedModifiersStillWork(): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table): void {
                $table->string('c')->compression('lz4')->nullable()->comment('hi');
            }
        );

        self::assertStringContainsString('compression lz4', $sql[0]);
        self::assertStringContainsString('null', $sql[0]);
    }

    #[Test]
    public function unknownModifiersKeepTheDefaultFluentBehaviour(): void
    {
        // Fluent accepts any attribute; only the two removed names are rejected.
        $column = $this->blueprint('t')->string('c')->somethingCustom('x');

        self::assertSame('x', $column->get('somethingCustom'));
    }

    /**
     * The table-level helper is a real method and is unaffected by the column-level removal.
     */
    #[Test]
    public function theTableLevelGinIndexStillWorks(): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table): void {
                $table->textArray('tags');
                $table->ginIndex('tags');
            }
        );

        self::assertStringContainsString('create index "t_tags_index" on "t" using gin ("tags")', $sql[1]);
    }
}
