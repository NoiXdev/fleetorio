<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewCosts', fn (User $user): bool => $user->can_view_costs);

        // Erzwingt https-URLs, sobald die App nicht lokal laeuft. Verhindert, dass
        // Links und Formularziele auf http zeigen, wenn ein Proxy TLS terminiert.
        URL::forceHttps(! $this->app->isLocal());
    }
}
