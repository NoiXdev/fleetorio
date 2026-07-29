<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DealershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Automobile',
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'street' => fake()->streetAddress(),
            'postcode' => fake()->postcode(),
            'city' => fake()->city(),
            'notes' => null,
        ];
    }
}
