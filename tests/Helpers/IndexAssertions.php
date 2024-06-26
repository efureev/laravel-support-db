<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Helpers;

use Illuminate\Support\Facades\DB;

trait IndexAssertions
{
    abstract public static function assertNotNull($actual, string $message = ''): void;

    abstract public static function assertSame($expected, $actual, string $message = ''): void;

    abstract public static function assertNull($actual, string $message = ''): void;

    abstract public static function assertMatchesRegularExpression(
        string $pattern,
        string $string,
        string $message = ''
    ): void;

    abstract public static function assertTrue($condition, string $message = ''): void;

    abstract public static function assertFalse($condition, string $message = ''): void;

    protected function seeIndex(string $index): void
    {
        static::assertNotNull($this->getIndexListing($index));
    }

    protected function notSeeIndex(string $index): void
    {
        static::assertNull($this->getIndexListing($index));
    }

    protected function assertSameIndex(string $index, string $expectedDef): void
    {
        $definition = $this->getIndexListing($index);

        $this->seeIndex($index);
        static::assertSame($expectedDef, $definition);
    }

    protected function assertRegExpIndex(string $index, string $expectedDef): void
    {
        $definition = $this->getIndexListing($index);

        $this->seeIndex($index);
        static::assertMatchesRegularExpression($expectedDef, $definition ?: '');
    }

    protected function dontSeeConstraint(string $table, string $index): void
    {
        static::assertFalse($this->existConstraintOnTable($table, $index));
    }

    protected function seeConstraint(string $table, string $index): void
    {
        static::assertTrue($this->existConstraintOnTable($table, $index));
    }

    private function getIndexListing($index): ?string
    {
        $definition = DB::selectOne('SELECT * FROM pg_indexes WHERE indexname = ?', [$index]);

        return $definition ? $definition->indexdef : null;
    }

    protected function getIndexListByTable(string $table): array
    {
        return DB::select('SELECT * FROM pg_indexes WHERE tablename = ?', [$table]);
    }

    private function existConstraintOnTable(string $table, string $index): bool
    {
        $expression = '
            SELECT c.conname
            FROM pg_constraint c
            LEFT JOIN pg_class t ON c.conrelid  = t.oid
            LEFT JOIN pg_class t2 ON c.confrelid = t2.oid
            WHERE t.relname = ? AND c.conname = ?;
        ';
        $definition = DB::selectOne($expression, [$table, $index]);
        return $definition ? true : false;
    }
}
