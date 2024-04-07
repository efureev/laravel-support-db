<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Helpers;

use Illuminate\Support\Facades\DB;

trait ExtensionsAssertions
{
    protected function assertHasExtension(string $name): void
    {
        static::assertTrue(in_array($name, $this->getExtensionListing(), true));
    }

    protected function assertHasNotExtension(string $name): void
    {
        static::assertFalse(in_array($name, $this->getExtensionListing(), true));
    }


    private function getExtensionListing(): array
    {
        $result = DB::select('SELECT extname FROM pg_catalog.pg_extension');

        return collect($result)->map->extname->toArray();
    }
}
