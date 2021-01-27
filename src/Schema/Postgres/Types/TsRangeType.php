<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Class TsRangeType
 * @package Php\Support\Laravel
 */
class TsRangeType extends Type
{
    public const TYPE_NAME = 'tsrange';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return static::TYPE_NAME;
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }
}
