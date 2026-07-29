<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Bewusst leer: Der Laravel-Skeleton legt hier standardmäßig einen
     * test@example.com-Benutzer mit dem UserFactory-Standardpasswort
     * "password" an. Ein `db:seed --force` auf einem frischen Cloud-Host
     * (ein üblicher erster Schritt beim Deployment) würde damit ein
     * funktionierendes Panel-Login mit bekanntem Passwort erzeugen - der
     * Benutzer sähe zwar keine Kosten, aber Kennzeichen, Fahrer und
     * Mitarbeiterzuordnung, die laut Spezifikation vertraulich sind. Der
     * erste echte Benutzer entsteht laut README über
     * `ddev artisan make:filament-user`.
     */
    public function run(): void {}
}
