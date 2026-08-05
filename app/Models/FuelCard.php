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
        'active',
    ];

    /**
     * Spiegelt den Migrations-Standard, damit ein frisch erzeugtes Modell
     * denselben Zustand hat wie eine frisch geschriebene Zeile - auch ohne
     * erneutes Laden aus der Datenbank. Gleiches Muster wie in User.
     */
    protected $attributes = [
        'active' => true,
    ];

    protected $hidden = [
        'card_number',
    ];

    protected function casts(): array
    {
        return [
            'card_number' => 'encrypted',
            'valid_until' => 'date',
            'active' => 'boolean',
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
