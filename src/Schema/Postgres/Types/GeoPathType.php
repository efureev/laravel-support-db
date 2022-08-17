<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Class GeoPathType
 * @package Php\Support\Laravel
 */
class GeoPathType extends Type
{
    public const TYPE_NAME = 'path';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return static::TYPE_NAME;
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }
}
