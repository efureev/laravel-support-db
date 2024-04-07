<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

abstract class AbstractType
{
    public const TYPE_NAME = 'unknown';

    public function phpType(): string
    {
        return static::TYPE_NAME;
    }

    public function postgresType(): string
    {
        return static::TYPE_NAME;
    }
}
