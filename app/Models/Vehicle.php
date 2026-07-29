<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Vorlaufzeit, ab der ein auslaufender Leasingvertrag als dringend gilt
     * (danger statt warning). Ebenfalls bewusst eine Konstante.
     */
    public const LEASE_URGENT_DAYS = 90;

    /**
     * Vorlaufzeit, ab der eine faellige HU auf dem Dashboard gemeldet wird -
     * man kann zwei Monate vor Ablauf zur Pruefung.
     */
    public const INSPECTION_WARNING_DAYS = 60;

    /**
     * Dringlichkeitsschwelle innerhalb des HU-Fensters. LEASE_URGENT_DAYS
     * (90) und LEASE_WARNING_DAYS (180) passen hier nicht: Das HU-Fenster ist
     * nur 60 Tage breit, jeder Resttag darin waere mit der Leasing-Schwelle
     * "danger". Also dieselbe Quote wie beim Leasing (die Haelfte des
     * Fensters gilt als dringend: 90 von 180) auf 60 Tage angewandt.
     */
    public const INSPECTION_URGENT_DAYS = 30;

    protected $fillable = [
        'brand',
        'model',
        'license_plate',
        'fuel_type',
        'employee_id',
        'fuel_card_id',
        'leasing_company_id',
        'dealership_id',
        'lease_start',
        'lease_end',
        'inspection_valid_until',
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
            'inspection_valid_until' => 'date',
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
     * Autohaus und Leasinggeber werden getrennt gefuehrt (Auftraggeber-
     * Entscheidung): der Leasingvertrag laeuft beim Leasinggeber, der Service
     * beim Autohaus - ein Autohauswechsel beruehrt den Leasingvertrag nicht.
     */
    public function dealership(): BelongsTo
    {
        return $this->belongsTo(Dealership::class);
    }

    /**
     * Absteigend nach Datum, damit der juengste Termin oben steht.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(VehicleAppointment::class)->orderByDesc('date');
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
     * Fahrzeuge, deren HU in den naechsten $days Tagen faellig wird (beide
     * Grenzen eingeschlossen). Bewusst gegen inspection_valid_until statt
     * gegen den letzten HU-Termin berechnet - siehe Kommentar an der
     * Migration add_inspection_valid_until_to_vehicles_table: die
     * HU-Intervalle sind nicht einheitlich (Neuwagen drei Jahre, danach
     * zwei), und der Wert steht direkt auf der Plakette und im
     * Fahrzeugschein. Fahrzeuge ohne Eintrag erscheinen nicht (NULL BETWEEN
     * ist NULL, also falsy).
     */
    public function scopeInspectionDueWithin(Builder $query, int $days): Builder
    {
        return $query->active()
            ->whereBetween('inspection_valid_until', [today(), today()->addDays($days)]);
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

    /**
     * Vierstufige Dringlichkeitsfarbe fuer ein Leasingende, ausgehend von den
     * verbleibenden Tagen (negativ = bereits vorbei). Zentral hier, weil
     * dieselbe Skala an drei Stellen gebraucht wird: VehiclesTable (Spalte
     * Leasingende), ExpiringLeases (Spalte Resttage) und FleetOverview
     * (Karte "Naechstes Leasingende").
     *
     * "gray" ist an manchen dieser Stellen strukturell nicht erreichbar (z.B.
     * filtert FleetOverview::nextLeaseEndStat() bereits auf lease_end >= heute,
     * und die Abfrage hinter ExpiringLeases liefert nur 0..LEASE_WARNING_DAYS
     * Resttage) - die Logik bleibt trotzdem an allen drei Stellen identisch,
     * statt je eine eigene, engere Variante zu pflegen.
     */
    public static function leaseUrgencyColor(int $daysLeft): string
    {
        return match (true) {
            $daysLeft < 0 => 'gray',
            $daysLeft <= self::LEASE_URGENT_DAYS => 'danger',
            $daysLeft <= self::LEASE_WARNING_DAYS => 'warning',
            default => 'success',
        };
    }

    /**
     * Dieselbe vierstufige Form wie leaseUrgencyColor(), aber mit eigenen
     * Schwellen (INSPECTION_URGENT_DAYS/INSPECTION_WARNING_DAYS): das
     * 60-Tage-HU-Fenster ist zu schmal fuer die Leasing-Schwellen 90/180 -
     * jeder darin gemeldete Resttag waere sonst immer "danger". Nicht
     * wiederverwendet, sondern bewusst eine eigene Methode fuer eine eigene
     * Skala.
     */
    public static function inspectionUrgencyColor(int $daysLeft): string
    {
        return match (true) {
            $daysLeft < 0 => 'gray',
            $daysLeft <= self::INSPECTION_URGENT_DAYS => 'danger',
            $daysLeft <= self::INSPECTION_WARNING_DAYS => 'warning',
            default => 'success',
        };
    }
}
