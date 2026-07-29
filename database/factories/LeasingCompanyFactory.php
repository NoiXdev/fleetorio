<?php

namespace Database\Factories;

use App\Models\LeasingCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeasingCompany>
 */
class LeasingCompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['VW Leasing', 'ALD Automotive', 'Athlon', 'Sixt Leasing']),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'notes' => null,
        ];
    }
}
