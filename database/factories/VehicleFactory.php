<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        $leaseStart = fake()->dateTimeBetween('-3 years', '-1 month');

        return [
            'brand' => fake()->randomElement(['VW', 'Audi', 'Skoda', 'BMW', 'Tesla']),
            'model' => fake()->randomElement(['Passat', 'A4', 'Octavia', '320d', 'Model 3']),
            'license_plate' => strtoupper(fake()->unique()->bothify('M-??###')),
            'fuel_type' => fake()->randomElement(FuelType::cases()),
            'employee_id' => null,
            'fuel_card_id' => null,
            'leasing_company_id' => null,
            'lease_start' => $leaseStart,
            'lease_end' => fake()->dateTimeBetween('+1 month', '+3 years'),
            'monthly_lease_rate' => fake()->randomFloat(2, 250, 800),
            'annual_vehicle_tax' => fake()->randomFloat(2, 50, 400),
            'annual_insurance' => fake()->randomFloat(2, 300, 1200),
            'status' => VehicleStatus::Active,
            'notes' => null,
        ];
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VehicleStatus::Retired,
        ]);
    }

    /**
     * Fahrzeug ohne erfassten Leasingvertrag: lease_start und lease_end sind
     * seit diesem Wunsch optional (Migration 2026_07_29_090000).
     */
    public function withoutLeaseDates(): static
    {
        return $this->state(fn (array $attributes): array => [
            'lease_start' => null,
            'lease_end' => null,
        ]);
    }
}
