<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

abstract class AbstractType extends Type
{
    public const TYPE_NAME = 'point';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return static::TYPE_NAME;
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return [static::TYPE_NAME];
    }
}
