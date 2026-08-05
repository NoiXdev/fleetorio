<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Den Zustand "aktiv/inaktiv" kannte die Anwendung bisher nur an zwei Stellen:
 * am Mitarbeiter als employees.active (dort ohne jede Wirkung, rein
 * anzeigend) und am Fahrzeug als vehicles.status mit Aktiv/Ausgemustert.
 *
 * Kuenftig haengt das Loeschen an diesem Zustand - geloescht werden darf nur,
 * was vorher deaktiviert wurde. Damit die Regel fuer alle Stammdaten gilt und
 * nicht nur fuer zwei, bekommen die uebrigen vier dasselbe Feld.
 *
 * Bewusst kein SoftDeletes: ein zusaetzliches deleted_at ergaebe beim Fahrzeug
 * drei Zustaende (aktiv, ausgemustert, geloescht) und beim Mitarbeiter zwei
 * gleich klingende nebeneinander. Siehe die Spezifikation vom 2026-08-05.
 *
 * Standard true, damit vorhandene Datensaetze unveraendert nutzbar bleiben.
 */
return new class extends Migration
{
    private const TABLES = [
        'dealerships',
        'leasing_companies',
        'fuel_cards',
        'fuel_card_providers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->boolean('active')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('active');
            });
        }
    }
};
