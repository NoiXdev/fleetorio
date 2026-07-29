<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Bewusst ein eigenes Feld statt aus dem letzten HU-Termin
            // berechnet: die HU-Intervalle sind nicht einheitlich (Neuwagen
            // drei Jahre, danach zwei), und der Wert steht direkt auf der
            // Plakette und im Fahrzeugschein. Eine Berechnung waere fuer
            // jedes Fahrzeug falsch, das aus der Reihe faellt.
            $table->date('inspection_valid_until')->nullable()->after('lease_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('inspection_valid_until');
        });
    }
};
