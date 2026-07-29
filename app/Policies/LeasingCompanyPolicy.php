<?php

namespace App\Policies;

use App\Models\LeasingCompany;
use App\Models\User;

class LeasingCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeasingCompany $leasingCompany): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LeasingCompany $leasingCompany): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, LeasingCompany $leasingCompany): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
