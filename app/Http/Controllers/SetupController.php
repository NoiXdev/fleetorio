<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Ersteinrichtung: legt den ersten (und einzigen ueber diesen Weg
 * erreichbaren) Administrator an, wenn noch kein Benutzer existiert.
 *
 * Absicherung: der Zustand der Datenbank ist der einzige Schalter. Es gibt
 * keine Konfiguration, kein Flag, kein Token, das nach der Einrichtung von
 * Hand deaktiviert werden muesste - solche Dinge werden vergessen. Sobald ein
 * Benutzer existiert, antwortet diese Route mit 404, sowohl fuer GET als auch
 * fuer POST. Die Pruefung steht bewusst in beiden Methoden einzeln, nicht nur
 * an einer Stelle: ein Angreifer, der store() direkt per POST anspricht, darf
 * sich nicht auf eine Pruefung verlassen koennen, die nur beim Anzeigen der
 * Seite (show()) lief.
 */
class SetupController extends Controller
{
    public function show(): View
    {
        $this->abortIfAlreadySetUp();

        return view('setup.show');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->abortIfAlreadySetUp();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // Gleiche Untergrenze wie in der Benutzerverwaltung
            // (App\Filament\Resources\Users\Schemas\UserForm).
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], attributes: [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
            'password' => 'Passwort',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            // Wettrennen zweier gleichzeitiger Anfragen: "SELECT ... FOR
            // UPDATE" ohne einschraenkendes WHERE sperrt bei InnoDB/MariaDB
            // unter REPEATABLE READ (Standard-Isolationsstufe) die gesamte
            // Tabelle inklusive der Luecke bis zu ihrem Ende (Next-Key-Lock
            // auf den "Supremum"-Datensatz). Eine zweite, parallel laufende
            // Transaktion blockiert dadurch an genau dieser Stelle, bis die
            // erste committet oder zurückrollt - und sieht danach den bereits
            // angelegten Benutzer, statt selbst einen zweiten anzulegen.
            if (User::query()->lockForUpdate()->exists()) {
                abort(404);
            }

            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                // Kein Hash::make() hier: Am User-Model steht der Cast
                // 'password' => 'hashed'. Er hasht beim Speichern automatisch;
                // ein zusaetzliches Hash::make() wuerde doppelt hashen und den
                // neuen Benutzer aussperren.
                'password' => $validated['password'],
                'role' => UserRole::Admin,
                'can_view_costs' => true,
            ]);
        });

        Auth::guard('web')->login($user);

        // Session-Fixation vermeiden: nach dem Anmelden eine neue Session-ID
        // ausstellen.
        $request->session()->regenerate();

        return redirect()->route('filament.admin.pages.dashboard');
    }

    private function abortIfAlreadySetUp(): void
    {
        abort_if(User::query()->exists(), 404);
    }
}
