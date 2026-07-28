<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * Vorlaufzeit, ab der ein auslaufender Leasingvertrag auf dem Dashboard
     * gemeldet wird. Ein halbes Jahr reicht, um ein Nachfolgefahrzeug
     * auszuschreiben. Bewusst eine Konstante und keine Konfiguration.
     */
    public const LEASE_WARNING_DAYS = 180;

    protected $fillable = [
        'brand',
        'model',
        'license_plate',
        'fuel_type',
        'employee_id',
        'fuel_card_id',
        'leasing_company_id',
        'lease_start',
        'lease_end',
        'monthly_lease_rate',
        'annual_vehicle_tax',
        'annual_insurance',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fuel_type' => FuelType::class,
            'status' => VehicleStatus::class,
            'lease_start' => 'date',
            'lease_end' => 'date',
            'monthly_lease_rate' => 'decimal:2',
            'annual_vehicle_tax' => 'decimal:2',
            'annual_insurance' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fuelCard(): BelongsTo
    {
        return $this->belongsTo(FuelCard::class);
    }

    public function leasingCompany(): BelongsTo
    {
        return $this->belongsTo(LeasingCompany::class);
    }

    /**
     * Der decimal-Cast liefert Strings, daher die explizite float-Umwandlung.
     */
    protected function annualTotalCost(): Attribute
    {
        return Attribute::get(fn (): float => ((float) $this->monthly_lease_rate) * 12
            + (float) $this->annual_vehicle_tax
            + (float) $this->annual_insurance);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VehicleStatus::Active);
    }

    public function scopeLeaseEndingWithin(Builder $query, int $days): Builder
    {
        return $query->active()
            ->whereBetween('lease_end', [today(), today()->addDays($days)]);
    }
}
