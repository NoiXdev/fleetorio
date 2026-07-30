<?php

namespace App\Filament\Widgets;

use App\Enums\FuelType;
use App\Models\Vehicle;
use Filament\Support\Enums\IconSize;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

use function Filament\Support\generate_icon_html;

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

    /**
     * Antriebsart-Icons in der Beschreibung: Stat::description() akzeptiert
     * laut Filament\Schemas\Components\Concerns\HasDescription auch ein
     * Htmlable, und Blades {{ }} (Illuminate\Support\e()) gibt ein Htmlable
     * unveraendert zurueck statt es zu escapen - deshalb hier eine HtmlString
     * statt eines reinen Textes. ->descriptionIcon() reicht nicht, weil eine
     * Stat-Karte davon nur genau eine besitzt, hier aber je Antriebsart ein
     * eigenes Icon noetig ist. generate_icon_html() ist Filaments eigene
     * Helper-Funktion (auch von den Vendor-Templates selbst verwendet) - kein
     * CDN, keine neue Abhaengigkeit.
     */
    private function fleetSizeStat(): Stat
    {
        // Gruppierung und Sortierung nach Antriebsart lebt zentral auf dem
        // Vehicle-Model (Vehicle::activeCountsByFuelType()).
        $counts = Vehicle::activeCountsByFuelType();

        $description = $counts->isEmpty()
            ? 'Noch keine Fahrzeuge erfasst'
            : new HtmlString(
                $counts->map(function (int $total, string $fuelType): string {
                    $type = FuelType::from($fuelType);
                    $icon = generate_icon_html($type->getIcon(), size: IconSize::Small);

                    return $icon?->toHtml().e("{$total} {$type->getLabel()}");
                })->implode(' · ')
            );

        return Stat::make('Aktive Fahrzeuge', (string) Vehicle::active()->count())
            ->description($description)
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
