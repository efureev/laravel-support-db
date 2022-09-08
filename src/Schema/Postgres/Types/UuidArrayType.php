<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

class UuidArrayType extends AbstractType
{
    public const TYPE_NAME = 'uuidArray';

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['uuid[]', static::TYPE_NAME];
    }
}
