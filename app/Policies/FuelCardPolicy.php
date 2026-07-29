<?php

namespace App\Policies;

use App\Models\FuelCard;
use App\Models\User;

class FuelCardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FuelCard $fuelCard): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, FuelCard $fuelCard): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, FuelCard $fuelCard): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
