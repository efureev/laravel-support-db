<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class IntArrayType extends AbstractType
{
    public const TYPE_NAME = 'intArray';

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['int[]', static::TYPE_NAME];
    }
}
