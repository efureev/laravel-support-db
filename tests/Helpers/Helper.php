<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Helpers;

class Helper
{
    public static function instance($instance, ...$params): mixed
    {
        if (is_object($instance)) {
            return $instance;
        }

        if (is_string($instance) && class_exists($instance)) {
            return new $instance(...$params);
        }

        return null;
    }
}
