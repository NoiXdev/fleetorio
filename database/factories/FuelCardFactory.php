<?php

namespace Database\Factories;

use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelCard>
 */
class FuelCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fuel_card_provider_id' => FuelCardProvider::factory(),
            'card_number' => (string) fake()->numerify('708#########'),
            'valid_until' => fake()->dateTimeBetween('+1 year', '+4 years'),
            'notes' => null,
        ];
    }
}
