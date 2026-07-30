<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelCardProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'notes',
    ];

    public function fuelCards(): HasMany
    {
        return $this->hasMany(FuelCard::class);
    }
}
