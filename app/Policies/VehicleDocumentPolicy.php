<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleDocument;

/**
 * Lesen/Herunterladen: jeder angemeldete Benutzer (konsistent mit den
 * Fahrzeugdaten selbst, die ein Nur-Lese-Benutzer ebenfalls sehen darf).
 * Hochladen/Aendern/Loeschen: nur Administratoren - Fahrzeugscheine enthalten
 * Fahrgestellnummer und Halterdaten.
 */
class VehicleDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VehicleDocument $vehicleDocument): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, VehicleDocument $vehicleDocument): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, VehicleDocument $vehicleDocument): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
