<?php

namespace App\Models;

use App\Enums\AppointmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleAppointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'type',
        'date',
        'mileage',
        'cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
