<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dealership extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'street',
        'postcode',
        'city',
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

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
