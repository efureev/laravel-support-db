<?php

namespace Php\Support\Laravel\Database\Tests\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $enabled
 * @mixin Builder
 */
class TestModel extends Model
{
    protected $keyType = 'string';

    protected $table = 'tests';
}
