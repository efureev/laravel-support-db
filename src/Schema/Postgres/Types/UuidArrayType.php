<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

class UuidArrayType extends AbstractType
{
    public const TYPE_NAME = 'uuid[]';

    public function phpType(): string
    {
        return 'uuid[]';
    }

    public function postgresType(): string
    {
        return 'ARRAY';
    }
}
