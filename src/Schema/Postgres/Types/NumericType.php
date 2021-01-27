<?php

declare(strict_types=1);

namespace Php\Support\Laravel\DB\Schema\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Class NumericType
 * @package Php\Support\Laravel
 */
class NumericType extends Type
{
    public const TYPE_NAME = 'numeric';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return static::TYPE_NAME;
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }
}
