<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(DocumentType::cases()),
            'title' => fake()->sentence(3),
            'file_path' => 'vehicle-documents/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'file_size' => fake()->numberBetween(1_000, 5_000_000),
            'mime_type' => 'application/pdf',
            'notes' => null,
        ];
    }
}
