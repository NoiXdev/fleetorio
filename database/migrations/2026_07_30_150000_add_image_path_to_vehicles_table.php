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
            // Zufaellig vergebener Speicherpfad auf dem privaten Disk (wie
            // file_path bei vehicle_documents) - nullable, weil ein
            // Fahrzeugfoto optional bleibt. Die Datei selbst wird
            // ausschliesslich ueber App\Http\Controllers\VehicleImageController
            // ausgeliefert, siehe Kommentar dort.
            $table->string('image_path')->nullable()->after('license_plate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
