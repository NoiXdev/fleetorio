<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bruttolistenpreis: Listenpreis bei Erstzulassung, Grundlage etwa fuer die
 * 1-Prozent-Regel. Fliesst NICHT in die Jahreskosten ein - ein Listenpreis
 * ist keine laufende Position. Selbe Genauigkeit wie die uebrigen Geldfelder
 * in create_vehicles_table (decimal(10, 2)). Nullable, weil der Wert bei
 * Bestandsfahrzeugen oft nicht mehr auffindbar ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->decimal('gross_list_price', 10, 2)->nullable()->after('annual_insurance');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn('gross_list_price');
        });
    }
};
