<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bisheriger Enum-Wert (App\Enums\FuelCardProvider) => Name in
     * fuel_card_providers. Die vorherige Migration
     * (2026_07_30_180000_create_fuel_card_providers_table) legt Zeilen mit
     * genau diesen Namen an - hier wird nur nachgeschlagen, nichts neu
     * angelegt, damit kein Duplikat entsteht.
     *
     * @return array<string, string>
     */
    private function legacyValueToProviderName(): array
    {
        return [
            'dkv' => 'DKV',
            'elli' => 'Elli',
            'other' => 'Sonstige',
        ];
    }

    /**
     * Run the migrations.
     *
     * Der Anbieter war bislang ein festes Enum (fuel_cards.provider). Der
     * Auftraggeber moechte pflegbare Stammdaten wie bei Autohaus und
     * Leasinggeber. Diese Migration darf keinen vorhandenen Anbieterwert
     * verlieren: jede Tankkarte wird auf den passenden, bereits angelegten
     * Datensatz in fuel_card_providers umgehaengt, bevor die alte Spalte
     * verschwindet.
     */
    public function up(): void
    {
        Schema::table('fuel_cards', function (Blueprint $table) {
            $table->foreignId('fuel_card_provider_id')
                ->nullable()
                ->after('provider')
                ->constrained()
                ->restrictOnDelete();
        });

        $providerIdsByName = DB::table('fuel_card_providers')->pluck('id', 'name');

        foreach ($this->legacyValueToProviderName() as $legacyValue => $providerName) {
            $providerId = $providerIdsByName[$providerName] ?? null;

            if ($providerId === null) {
                // Sollte nach der vorherigen Migration nicht vorkommen - lieber
                // hart abbrechen, als Tankkarten stillschweigend ohne Anbieter
                // zurueckzulassen.
                throw new RuntimeException("Tankkartenanbieter '{$providerName}' wurde nicht gefunden, Datenüberführung abgebrochen.");
            }

            DB::table('fuel_cards')
                ->where('provider', $legacyValue)
                ->update(['fuel_card_provider_id' => $providerId]);
        }

        $missing = DB::table('fuel_cards')->whereNull('fuel_card_provider_id')->count();

        if ($missing > 0) {
            throw new RuntimeException("{$missing} Tankkarte(n) konnten keinem Anbieter zugeordnet werden, Datenüberführung abgebrochen.");
        }

        DB::statement('ALTER TABLE fuel_cards MODIFY fuel_card_provider_id BIGINT UNSIGNED NOT NULL');

        Schema::table('fuel_cards', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Fuer Anbieter, die erst nach dieser Migration ueber die neue Oberflaeche
     * angelegt wurden (nicht Teil des urspruenglichen Enums), gibt es keinen
     * Enum-Wert zum Zurueckfallen - solche Karten werden beim Rollback auf
     * 'other' abgebildet. Das ist ein bewusster, dokumentierter
     * Informationsverlust beim RUECKWAERTS-Migrieren, nicht beim Vorwaertsweg.
     */
    public function down(): void
    {
        Schema::table('fuel_cards', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('fuel_card_provider_id');
        });

        $providerNameToLegacyValue = array_flip($this->legacyValueToProviderName());

        $cards = DB::table('fuel_cards')
            ->join('fuel_card_providers', 'fuel_cards.fuel_card_provider_id', '=', 'fuel_card_providers.id')
            ->select('fuel_cards.id', 'fuel_card_providers.name')
            ->get();

        foreach ($cards as $card) {
            $legacyValue = $providerNameToLegacyValue[$card->name] ?? 'other';

            DB::table('fuel_cards')->where('id', $card->id)->update(['provider' => $legacyValue]);
        }

        DB::statement('ALTER TABLE fuel_cards MODIFY provider VARCHAR(255) NOT NULL');

        Schema::table('fuel_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fuel_card_provider_id');
        });
    }
};
