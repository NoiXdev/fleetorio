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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('model');
            $table->string('license_plate')->unique();
            $table->string('fuel_type');

            // restrictOnDelete: Der Löschschutz aus der Spec wird auf Datenbankebene
            // erzwungen, nicht nur in der Oberfläche. Auch ein direkter Zugriff kann
            // damit keine verwaisten Fahrzeuge erzeugen.
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fuel_card_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('leasing_company_id')->nullable()->constrained()->restrictOnDelete();

            $table->date('lease_start');
            $table->date('lease_end');
            $table->decimal('monthly_lease_rate', 10, 2)->nullable();
            $table->decimal('annual_vehicle_tax', 10, 2)->nullable();
            $table->decimal('annual_insurance', 10, 2)->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
