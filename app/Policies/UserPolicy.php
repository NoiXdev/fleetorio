<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        // Sich selbst zu löschen sperrt im Zweifel den letzten Admin aus.
        return $user->isAdmin() && $user->isNot($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Ein Admin kann fremdes 2FA zuruecksetzen, nie neu einrichten - das
     * Geheimnis darf ausschliesslich in der Authenticator-App des Benutzers
     * entstehen, sonst waere der zweite Faktor keiner mehr.
     */
    public function resetTwoFactor(User $user, User $model): bool
    {
        return $user->isAdmin();
    }
}
