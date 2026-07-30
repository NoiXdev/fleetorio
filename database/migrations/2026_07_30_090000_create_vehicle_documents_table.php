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
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete wie bei vehicle_appointments: Dokumente gehoeren
            // zum Fahrzeug und haben ohne es keinen Sinn. Das entfernt beim
            // Loeschen eines Fahrzeugs nur die Datenbankzeilen - die
            // zugehoerigen Dateien raeumt VehicleDocument::booted() ab, siehe
            // Vehicle::booted().
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->string('title');

            // Zufaellig vergebener Speicherpfad (Filaments FileUpload
            // generiert den Dateinamen) - nicht erratbar und getrennt vom
            // urspruenglichen Namen, der unten separat steht.
            $table->string('file_path');
            $table->string('original_filename');
            $table->unsignedInteger('file_size');
            $table->string('mime_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};
