<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasFactory;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'can_view_costs',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Spiegelt die Migrations-Defaults, damit frisch erzeugte Modelle im
     * schwächsten Zustand (viewer, kein Kostenrecht) starten, auch ohne
     * erneutes Laden aus der Datenbank.
     */
    protected $attributes = [
        'role' => 'viewer',
        'can_view_costs' => false,
    ];

    /**
     * Casts für die 2FA-Spalten setzen die Filament-Traits selbst.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'can_view_costs' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Abkuerzung fuer den Aufruf des viewCosts-Gates, kein Ersatz dafuer: das
     * Gate (siehe AppServiceProvider::boot()) bleibt die Autorisierungsgrenze.
     * Zentralisiert nur den wiederholten Aufruf can('viewCosts'), der zuvor an
     * fuenf Stellen wortgleich stand.
     */
    public function canViewCosts(): bool
    {
        return $this->can('viewCosts');
    }

    /**
     * Wer sich am Panel anmelden darf. Ohne dieses Interface sperrt Filament
     * den Zugang in allen Umgebungen außer local.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
