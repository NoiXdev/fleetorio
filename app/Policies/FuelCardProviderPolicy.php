<?php

namespace App\Policies;

use App\Models\FuelCardProvider;
use App\Models\User;

class FuelCardProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FuelCardProvider $fuelCardProvider): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, FuelCardProvider $fuelCardProvider): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, FuelCardProvider $fuelCardProvider): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
