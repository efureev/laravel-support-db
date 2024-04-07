<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

class IntArrayType extends AbstractType
{
    public const TYPE_NAME = 'integer[]';

    public function phpType(): string
    {
        return 'integer[]';
    }

    public function postgresType(): string
    {
        return 'ARRAY';
    }
}
