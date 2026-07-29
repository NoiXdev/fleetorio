<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;

it('creates no user with a known default password', function () {
    // Der Laravel-Skeleton-Seeder legte hier standardmäßig test@example.com
    // mit dem UserFactory-Standardpasswort "password" an - ein
    // Sicherheitsrisiko, falls db:seed auf einem Cloud-Host ausgeführt wird.
    // Der Seeder muss daher keine Benutzer mehr anlegen.
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(User::count())->toBe(0);
});
