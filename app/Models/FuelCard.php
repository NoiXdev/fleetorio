<?php

namespace App\Models;

use App\Enums\FuelCardProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'card_number',
        'valid_until',
        'notes',
    ];

    protected $hidden = [
        'card_number',
    ];

    protected function casts(): array
    {
        return [
            'provider' => FuelCardProvider::class,
            'card_number' => 'encrypted',
            'valid_until' => 'date',
        ];
    }
}
