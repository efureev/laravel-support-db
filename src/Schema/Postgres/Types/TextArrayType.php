<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

class TextArrayType extends AbstractType
{
    public const TYPE_NAME = 'text[]';

    public function phpType(): string
    {
        return 'text[]';
    }

    public function postgresType(): string
    {
        return 'ARRAY';
    }
}
