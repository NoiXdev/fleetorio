<?php

namespace App\Models;

use App\Enums\FuelCardProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
