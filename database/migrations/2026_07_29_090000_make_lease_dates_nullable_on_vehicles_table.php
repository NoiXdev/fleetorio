<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * lease_start und lease_end waren bislang NOT NULL (siehe
     * 2026_07_28_104125_create_vehicles_table.php). Der Auftraggeber moechte
     * beide Felder optional: nicht jedes Fahrzeug hat von Anfang an einen
     * Leasingvertrag erfasst.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('lease_start')->nullable()->change();
            $table->date('lease_end')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('lease_start')->nullable(false)->change();
            $table->date('lease_end')->nullable(false)->change();
        });
    }
};
