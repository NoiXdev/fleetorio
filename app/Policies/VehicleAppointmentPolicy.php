<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleAppointment;

class VehicleAppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VehicleAppointment $vehicleAppointment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, VehicleAppointment $vehicleAppointment): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, VehicleAppointment $vehicleAppointment): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
