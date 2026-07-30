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
            // Nullable: bei Altbestand ist die Fahrgestellnummer nicht immer
            // erfasst (siehe Spezifikation). 17 Zeichen reichen fuer eine
            // vollstaendige VIN; die genaue Laenge und das Zeichenformat
            // (ohne I, O, Q) prueft das Formular, nicht die Datenbank.
            $table->string('vin')->nullable()->after('license_plate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('vin');
        });
    }
};
