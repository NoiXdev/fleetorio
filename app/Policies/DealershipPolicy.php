<?php

namespace App\Policies;

use App\Models\Dealership;
use App\Models\User;

class DealershipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Dealership $dealership): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Dealership $dealership): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Dealership $dealership): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
