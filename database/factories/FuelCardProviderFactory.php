<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FuelCardProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'notes' => null,
        ];
    }
}
