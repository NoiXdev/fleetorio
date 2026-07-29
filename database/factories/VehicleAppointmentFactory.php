<?php

namespace Database\Factories;

use App\Enums\AppointmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleAppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(AppointmentType::cases()),
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'mileage' => fake()->numberBetween(5_000, 150_000),
            'cost' => fake()->randomFloat(2, 50, 900),
            'notes' => null,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AppointmentType::Service,
        ]);
    }

    public function workshop(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AppointmentType::Workshop,
        ]);
    }

    public function inspection(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AppointmentType::Inspection,
        ]);
    }
}
