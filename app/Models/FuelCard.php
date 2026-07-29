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

    /**
     * Sparsame Darstellung fuer Auswahlfelder und Listen, die die Karte nur
     * benennen, nicht bearbeiten muessen (Fahrzeugformular, Tankkartenliste):
     * Anbieter plus die letzten vier Stellen, z.B. "DKV ····6789". Die volle
     * Nummer bleibt dem Bearbeitungsformular der Tankkarte vorbehalten.
     */
    public function maskedNumber(): string
    {
        return $this->provider->getLabel().' ····'.substr($this->card_number, -4);
    }
}
