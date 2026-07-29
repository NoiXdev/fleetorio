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
        Schema::create('vehicle_appointments', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete statt restrictOnDelete wie bei den Stammdaten:
            // Termine gehoeren zum Fahrzeug und haben ohne es keinen Sinn -
            // wird ein Fahrzeug geloescht, sollen seine Termine mitgehen.
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->date('date');
            $table->unsignedInteger('mileage')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_appointments');
    }
};
