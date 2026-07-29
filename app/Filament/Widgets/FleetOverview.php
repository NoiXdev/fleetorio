<?php

namespace App\Filament\Widgets;

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
        // Gruppierung und Sortierung nach Antriebsart leben zentral auf dem
        // Vehicle-Model (Vehicle::activeCountsByFuelType()), weil die
        // öffentliche Startseite dieselbe Aufteilung zeigt.
        $breakdown = Vehicle::activeFuelTypeBreakdown();

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
            // Gleiche Skala wie VehiclesTable und ExpiringLeases, zentral in
            // Vehicle::leaseUrgencyColor(): unter 0 grau, bis LEASE_URGENT_DAYS
            // dringend, bis LEASE_WARNING_DAYS eine Warnung, danach unauffaellig.
            ->color(Vehicle::leaseUrgencyColor($days));
    }

    private function expiringCountStat(): Stat
    {
        $count = Vehicle::leaseEndingWithin(Vehicle::LEASE_WARNING_DAYS)->count();

        return Stat::make('Verträge mit Handlungsbedarf', (string) $count)
            ->description('Leasingende in den nächsten '.Vehicle::LEASE_WARNING_DAYS.' Tagen')
            ->color($count > 0 ? 'warning' : 'success');
    }
}
