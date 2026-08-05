<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der Admin kann pro Benutzer verlangen, dass 2FA eingerichtet ist. Wer den
 * Schalter gesetzt hat und noch kein Geheimnis besitzt, landet beim naechsten
 * Aufruf des Panels auf der Profilseite (siehe EnsureTwoFactorWhenRequired).
 *
 * Standard false: Die Pflicht ist eine bewusste Anordnung, kein Nebeneffekt
 * einer Migration. Andernfalls sperrte das naechste Deployment alle
 * bestehenden Benutzer aus dem Panel aus, bis sie 2FA neu eingerichtet
 * haetten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('requires_two_factor')->default(false)->after('can_view_costs');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('requires_two_factor');
        });
    }
};
