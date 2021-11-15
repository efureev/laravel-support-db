<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Php\Support\Laravel\Database\Tests\Models\TestModel;

class TestModelFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = TestModel::class;

    public function definition(): array
    {
        return [
            'name'    => $this->faker->name,
            'enabled' => $this->faker->boolean,
        ];
    }
}
