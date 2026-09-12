<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Helpers;

use Illuminate\Support\Facades\Schema;

trait ViewAssertions
{
    abstract public static function assertSame($expected, $actual, string $message = ''): void;

    abstract public static function assertTrue($condition, string $message = ''): void;

    abstract public static function assertFalse($condition, string $message = ''): void;

    protected function assertSameView(string $expectedDef, string $view): void
    {
        $definition = $this->getViewDefinition($view);

        static::assertSame($expectedDef, $definition);
    }

    protected function seeView(string $view): void
    {
        static::assertTrue(Schema::hasView($view));
    }

    protected function notSeeView(string $view): void
    {
        static::assertFalse(Schema::hasView($view));
    }

    private function getViewDefinition(string $view): string
    {
        $definition = preg_replace(
            "#\s+#",
            ' ',
            strtolower(trim(str_replace("\n", ' ', Schema::getViewDefinition($view))))
        );

        // PostgreSQL 16 stopped qualifying column names in `pg_get_viewdef()` output:
        // 15 renders `select test_table.id from test_table`, 16+ `select id from test_table`.
        // Strip the qualifier so the assertion is about the view, not the server's rendering.
        return preg_replace('/\b[a-z_][a-z0-9_]*\.(?=[a-z_])/', '', $definition);
    }
}
