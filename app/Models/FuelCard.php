<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'fuel_card_provider_id',
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
            'card_number' => 'encrypted',
            'valid_until' => 'date',
        ];
    }

    public function fuelCardProvider(): BelongsTo
    {
        return $this->belongsTo(FuelCardProvider::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
