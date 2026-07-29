<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Summiert die Jahreskosten per SQL. Der Accessor annual_total_cost rechnet je
     * Datensatz in PHP; für eine Summenzeile über viele Zeilen wäre das unnötig
     * teuer und für Filament-Summarizer nicht verwendbar.
     *
     * ->select(DB::raw(...)) statt ->selectRaw(...): selectRaw() ERGÄNZT die
     * Spaltenliste, select() ERSETZT sie. Ein künftiger Aufrufer mit einer
     * eigenen Selektion würde sonst "select <spalten>, SUM(...)" erzeugen -
     * unter ONLY_FULL_GROUP_BY ein Fehler. Aktuell nicht auslösbar, aber diese
     * Variante ist robuster gegen künftige Aufrufer.
     */
    public static function sumAnnualCost(Builder $query): float
    {
        $row = $query->clone()
            ->reorder()
            ->select(DB::raw(
                'SUM(COALESCE(monthly_lease_rate, 0) * 12'
                .' + COALESCE(annual_vehicle_tax, 0)'
                .' + COALESCE(annual_insurance, 0)) AS aggregate'
            ))
            ->first();

        return (float) ($row?->aggregate ?? 0);
    }

    /**
     * Aktive Fahrzeuge je Antriebsart, absteigend nach Anzahl sortiert und bei
     * Gleichstand nach der Deklarationsreihenfolge von FuelType (damit die
     * Reihenfolge nicht willkürlich zwischen Aufrufen wechselt, wenn zwei
     * Antriebsarten gleich häufig sind).
     *
     * Wird sowohl vom Dashboard-Widget FleetOverview als auch von der
     * öffentlichen Startseite verwendet - deshalb hier zentral statt an
     * beiden Stellen abgeschrieben.
     *
     * @return Collection<string, int> Antriebsart (Enum-Value) => Anzahl
     */
    public static function activeCountsByFuelType(): Collection
    {
        $counts = static::active()
            ->selectRaw('fuel_type, COUNT(*) AS total')
            ->groupBy('fuel_type')
            ->pluck('total', 'fuel_type');

        $enumOrder = array_flip(array_column(FuelType::cases(), 'value'));

        return $counts->sortBy(
            fn (int $total, string $fuelType): array => [-$total, $enumOrder[$fuelType]],
        );
    }

    /**
     * Formatierte Kurzfassung der obigen Verteilung, z.B. "3 Diesel · 1 Benzin".
     * Zentral hier, damit Widget und Startseite exakt denselben Text zeigen.
     */
    public static function activeFuelTypeBreakdown(): string
    {
        return static::activeCountsByFuelType()
            ->map(fn (int $total, string $fuelType): string => $total.' '.FuelType::from($fuelType)->getLabel())
            ->implode(' · ');
    }
}
