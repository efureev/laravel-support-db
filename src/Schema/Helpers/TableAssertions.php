<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Helpers;

use Illuminate\Support\Facades\Schema;

trait TableAssertions
{
    abstract public static function assertSame($expected, $actual, string $message = ''): void;

    abstract public static function assertTrue($condition, string $message = ''): void;

    protected function assertCompareTables(string $sourceTable, string $destinationTable): void
    {
        static::assertSame($this->getTableDefinition($sourceTable), $this->getTableDefinition($destinationTable));
    }

    protected function assertSameTable(array $expectedDef, string $table): void
    {
        $definition = $this->getTableDefinition($table);

        static::assertSame($expectedDef, $definition);
    }

    protected function seeTable(string $table): void
    {
        static::assertTrue(Schema::hasTable($table));
    }

    private function getTableDefinition(string $table): array
    {
        return Schema::getColumnListing($table);
    }
}
