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
            // restrictOnDelete wie bei den anderen Stammdaten-Fremdschluesseln
            // auf dieser Tabelle: kein Autohaus loeschbar, solange ihm noch ein
            // Fahrzeug zugeordnet ist.
            $table->foreignId('dealership_id')->nullable()->after('leasing_company_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dealership_id');
        });
    }
};
