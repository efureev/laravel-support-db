<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Helpers;

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
        return preg_replace(
            "#\s+#",
            ' ',
            strtolower(trim(str_replace("\n", ' ', Schema::getViewDefinition($view))))
        );
    }
}
