<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Contracts\View\View;

/**
 * Öffentliche Startseite (kein Login nötig). Zeigt ausschließlich die beiden
 * vom Auftraggeber freigegebenen aggregierten Kennzahlen - keine Kennzeichen,
 * Marken, Modelle, Mitarbeiternamen, Tankkarten, Leasinggeber, Daten oder
 * Beträge. Ein eigener (wenn auch einfacher) Controller statt einer Closure
 * in routes/web.php, weil er so isoliert per Feature-Test ansprechbar bleibt
 * und routes/web.php frei von Abfragelogik bleibt.
 */
class PublicOverviewController extends Controller
{
    public function __invoke(): View
    {
        return view('public.overview', [
            'activeVehicleCount' => Vehicle::active()->count(),
            'fuelTypeBreakdown' => Vehicle::activeFuelTypeBreakdown(),
        ]);
    }
}
