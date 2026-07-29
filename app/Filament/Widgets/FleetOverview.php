<?php

namespace App\Filament\Widgets;

use App\Enums\FuelType;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FleetOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            $this->fleetSizeStat(),
            $this->nextLeaseEndStat(),
            $this->expiringCountStat(),
        ];
    }

    private function fleetSizeStat(): Stat
    {
        $byFuelType = Vehicle::active()
            ->selectRaw('fuel_type, COUNT(*) AS total')
            ->groupBy('fuel_type')
            ->pluck('total', 'fuel_type');

        // groupBy() liefert keine garantierte Reihenfolge. Für die Anzeige
        // sortieren wir absteigend nach Häufigkeit (die dominierende
        // Antriebsart zuerst ist die interessantere Information), mit der
        // Deklarationsreihenfolge der Enum als stabilem Kriterium bei
        // Gleichstand - damit ändert sich die Reihenfolge nicht willkürlich
        // zwischen Aufrufen, wenn zwei Antriebsarten gleich häufig sind.
        $enumOrder = array_flip(array_column(FuelType::cases(), 'value'));

        $breakdown = $byFuelType
            ->sortBy(
                fn (int $total, string $fuelType): array => [-$total, $enumOrder[$fuelType]],
            )
            ->map(fn (int $total, string $fuelType): string => $total.' '.FuelType::from($fuelType)->getLabel())
            ->implode(' · ');

        return Stat::make('Aktive Fahrzeuge', (string) Vehicle::active()->count())
            ->description($breakdown !== '' ? $breakdown : 'Noch keine Fahrzeuge erfasst')
            ->descriptionIcon('heroicon-m-truck')
            ->color('primary');
    }

    private function nextLeaseEndStat(): Stat
    {
        $next = Vehicle::active()
            ->where('lease_end', '>=', today())
            ->orderBy('lease_end')
            ->first();

        if ($next === null) {
            return Stat::make('Nächstes Leasingende', 'Keine')
                ->description('Kein aktiver Vertrag mit künftigem Ende')
                ->color('gray');
        }

        $days = (int) today()->diffInDays($next->lease_end, false);

        return Stat::make('Nächstes Leasingende', $next->license_plate)
            ->description("{$next->brand} {$next->model} · in {$days} Tagen")
            ->descriptionIcon('heroicon-m-calendar-days')
            // Gleiche vierstufige Skala wie VehiclesTable::configure() und
            // ExpiringLeases: unter 0 grau, bis 90 Tage dringend, bis
            // LEASE_WARNING_DAYS eine Warnung, danach unauffällig. Die
            // Karte zeigt sonst noch bei einem 300-Tage-Termin Orange, obwohl
            // kein Handlungsbedarf besteht. "gray" ist hier strukturell nicht
            // erreichbar - die Abfrage filtert bereits auf lease_end >= heute
            // - bleibt aber aus Konsistenzgründen Teil derselben Skala statt
            // einer eigenen, engeren Variante.
            ->color(match (true) {
                $days < 0 => 'gray',
                $days <= 90 => 'danger',
                $days <= Vehicle::LEASE_WARNING_DAYS => 'warning',
                default => 'success',
            });
    }

    private function expiringCountStat(): Stat
    {
        $count = Vehicle::leaseEndingWithin(Vehicle::LEASE_WARNING_DAYS)->count();

        return Stat::make('Verträge mit Handlungsbedarf', (string) $count)
            ->description('Leasingende in den nächsten '.Vehicle::LEASE_WARNING_DAYS.' Tagen')
            ->color($count > 0 ? 'warning' : 'success');
    }
}
