<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Startwerte, damit eine frische Installation nicht mit einer leeren
     * Liste beginnt. Auswahl gaengiger Tankkarten- bzw. Ladekartenanbieter im
     * deutschsprachigen Raum - eine sachliche, ueberschaubare Grundlage ohne
     * Anspruch auf Vollstaendigkeit, keine abschliessende Marktuebersicht.
     *
     * "DKV", "Elli" und "Sonstige" sind absichtlich exakt so benannt wie die
     * bisherigen Enum-Label (App\Enums\FuelCardProvider): die nachfolgende
     * Migration 2026_07_30_180001 sucht beim Ueberfuehren der vorhandenen
     * Tankkarten genau diese Namen und darf dabei keine Duplikate anlegen.
     *
     * @return list<array{name: string, notes: ?string}>
     */
    private function startingProviders(): array
    {
        return [
            ['name' => 'DKV', 'notes' => null],
            ['name' => 'UTA', 'notes' => null],
            ['name' => 'Aral', 'notes' => null],
            ['name' => 'Shell', 'notes' => null],
            ['name' => 'TotalEnergies', 'notes' => null],
            ['name' => 'Esso', 'notes' => null],
            ['name' => 'Elli', 'notes' => 'Ladekarte für Elektrofahrzeuge (Volkswagen Group Charging).'],
            ['name' => 'EnBW', 'notes' => 'Ladekarte für Elektrofahrzeuge (EnBW mobility+).'],
            ['name' => 'Ionity', 'notes' => 'Ladekarte für Elektrofahrzeuge (Schnellladenetz an Autobahnen).'],
            ['name' => 'Sonstige', 'notes' => null],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fuel_card_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('fuel_card_providers')->insert(
            array_map(
                fn (array $provider): array => [...$provider, 'created_at' => $now, 'updated_at' => $now],
                $this->startingProviders(),
            ),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_card_providers');
    }
};
