<?php

declare(strict_types=1);

namespace Database\Factories\Engelsystem\Models;

use Engelsystem\Models\SystemConfig;
use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemConfigFactory extends Factory
{
    /** @var string */
    protected $model = SystemConfig::class; // phpcs:ignore

    public function definition(): array
    {
        return [
            'created_at'     => $this->faker->dateTimeThisMonth(),
            'created_by'     => User::factory()
        ];
    }
}
