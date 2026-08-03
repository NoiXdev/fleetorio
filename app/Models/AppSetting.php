<?php

namespace App\Models;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Singleton-Einstellungen fuer das Erscheinungsbild (Name, Logo,
 * Primaerfarbe), ueber App\Filament\Pages\AppearanceSettings pflegbar. Es
 * gibt genau eine Zeile (SINGLETON_ID) - ein zusaetzliches Settings-Paket
 * waere fuer dieses eine Formular unbegruendeter Ballast. Ein schlankes
 * Model mit statischen Zugriffsmethoden buendelt Persistenz und
 * Fallback-Logik an einer Stelle, im selben Stil wie andere statische
 * Domain-Methoden in diesem Projekt (Vehicle::leaseUrgencyColor(),
 * User::canViewCosts()).
 *
 * WICHTIG - der Grund fuer den Zwischenspeicher: Filament wertet
 * AdminPanelProvider::panel() nicht einmalig beim Start aus, sondern bei
 * jedem Boot-Zyklus der Anwendung, also praktisch bei jedem Request (auch
 * bei jedem artisan-Aufruf). Ohne Cache wuerde das eine Datenbankabfrage pro
 * Request bedeuten, selbst fuer Anfragen, die sonst gar keine braeuchten.
 * cached() liest deshalb genau einmal und danach aus dem Cache
 * (Cache::rememberForever); AppearanceSettings::save() leert ihn wieder,
 * sonst wirkte eine Aenderung erst nach einem Neustart.
 *
 * WICHTIG - der Ausfallschutz auf ZWEI Ebenen: AdminPanelProvider::panel()
 * wird bei jedem Boot ausgewertet - einschliesslich der allerersten
 * `migrate` auf einem frischen System, die die Tabellen ueberhaupt erst
 * anlegt. Auf einem solchen System fehlen aber gleich zwei Tabellen:
 *   1. app_settings selbst - resolveSettings() prueft ihre Existenz per
 *      Schema::hasTable() und liefert sonst Standardwerte.
 *   2. die 'cache'-Tabelle - denn CACHE_STORE=database (der Standard der
 *      .env) legt den Cache ebenfalls in die Datenbank. Cache::rememberForever
 *      greift dann selbst auf eine noch fehlende Tabelle zu, bevor die Closure
 *      und damit der Schutz aus (1) ueberhaupt laufen kann. Deshalb umschliesst
 *      cached() den GESAMTEN Cache-Aufruf mit try/catch und faellt auf die
 *      ungecachte resolveSettings() zurueck.
 * Ohne beide Ebenen waere ein frischer Klon dieses Projekts nicht migrierbar,
 * weil jeder artisan-Aufruf mit einer QueryException abbraeche.
 */
class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_name',
        'logo_path',
        'primary_color',
    ];

    /**
     * Es gibt absichtlich nur eine Zeile.
     */
    public const SINGLETON_ID = 1;

    public const DEFAULT_NAME = 'Fleetorio';

    public const DEFAULT_COLOR = '#2563eb';

    /**
     * Relativ zu public/, existiert bereits als Datei im Projekt.
     */
    public const DEFAULT_LOGO_ASSET_PATH = 'images/fleetorio-logo.svg';

    private const CACHE_KEY = 'app-settings.current';

    /**
     * Filaments eigene Palette-Kurve (siehe
     * Filament\Support\Colors\Color::generatePalette()): fuer jede Stufe ein
     * festes [Helligkeit, Chroma]-Paar, jeweils relativ zur eigenen Stufe
     * 600. buildPalette() unten skaliert diese Kurve so, dass die eigene
     * Stufe 600 exakt auf der gewaehlten Farbe landet - siehe dortiger
     * Kommentar fuer die vollstaendige Herleitung.
     */
    private const DEFAULT_CURVE = [
        50 => [0.97717647058824, 0.01395454545455],
        100 => [0.95035294117647, 0.03272727272727],
        200 => [0.90547058823529, 0.06318181818182],
        300 => [0.84047058823529, 0.10604545454546],
        400 => [0.75352941176471, 0.15027272727273],
        500 => [0.68270588235294, 0.17009090909091],
        600 => [0.59782352941176, 0.16913636363636],
        700 => [0.51494117647059, 0.14940909090909],
        800 => [0.44611764705882, 0.12331818181818],
        900 => [0.39458823529412, 0.09963636363636],
        950 => [0.27788235294118, 0.07136363636364],
    ];

    public static function name(): string
    {
        return static::cached()['brand_name'] ?? static::DEFAULT_NAME;
    }

    public static function color(): string
    {
        return static::cached()['primary_color'] ?? static::DEFAULT_COLOR;
    }

    public static function hasCustomLogo(): bool
    {
        return filled(static::logoStoragePath());
    }

    /**
     * Pfad auf dem privaten Disk 'local', wie ihn BrandLogoController
     * braucht. null, solange kein eigenes Logo hochgeladen wurde.
     */
    public static function logoStoragePath(): ?string
    {
        return static::cached()['logo_path'] ?? null;
    }

    /**
     * Oeffentliche URL fuers Logo. Fuehrt bei einem eigenen Logo ueber den
     * anmeldefreien BrandLogoController (privater Disk), sonst direkt auf
     * die bereits oeffentliche Standarddatei - letztere braucht keinen
     * Umweg ueber PHP, da sie ohnehin unter public/ liegt. Beide Faelle
     * bewusst hinter dieser einen Methode versteckt, damit
     * AdminPanelProvider und die oeffentlichen Blade-Views nicht selbst
     * zwischen ihnen unterscheiden muessen.
     */
    public static function logoUrl(): string
    {
        return static::hasCustomLogo()
            ? route('branding.logo')
            : asset(static::DEFAULT_LOGO_ASSET_PATH);
    }

    /**
     * @return array<int, string> 11-stufige oklch-Palette fuer Filaments
     *                            ->colors(['primary' => ...]).
     */
    public static function palette(): array
    {
        return static::buildPalette(static::color());
    }

    /**
     * Baut aus einem Hex-Wert eine Filament-Farbpalette, bei der Stufe 600
     * exakt der Eingabefarbe entspricht.
     *
     * Hintergrund (siehe historischer Kommentar, vormals fest in
     * AdminPanelProvider fuer #bb0000 verdrahtet): Filaments eigenes
     * Color::hex() uebernimmt aus der Eingabefarbe nur den Farbwinkel (Hue)
     * und ersetzt Helligkeit und Chroma durch eine feste Kurve - Stufe 600
     * trifft die Eingabefarbe dadurch praktisch nie exakt.
     *
     * Verfahren hier: DEFAULT_CURVE beschreibt fuer jede Stufe ein festes
     * Verhaeltnis von Helligkeit und Chroma zur jeweils eigenen Stufe 600.
     * Dieses Verhaeltnis wird uebernommen, aber auf die tatsaechliche
     * Helligkeit/Chroma der Eingabefarbe skaliert:
     *
     *     Helligkeit(Stufe) = Helligkeit_Eingabe(600) * Kurve(Stufe) / Kurve(600)
     *
     * und entsprechend fuer Chroma. Der Farbwinkel bleibt fuer alle Stufen
     * der der Eingabefarbe. Bei Stufe 600 ist der Skalierungsfaktor damit
     * rechnerisch exakt 1 - das Ergebnis ist dort exakt die Eingabefarbe,
     * ohne Rundungsfehler (die Fallunterscheidung unten setzt sie zusaetzlich
     * direkt ein, statt sie ueber die Formel mit Faktor 1 zu berechnen). Die
     * uebrigen Stufen behalten die relative Form von Filaments eigener
     * Kurve: hellere Stufen bleiben hell (fuer Hintergruende/Abzeichen),
     * dunklere fuehren angemessen in Richtung Schwarz - ohne dass fuer jede
     * moegliche Eingabefarbe von Hand nachjustiert werden muss, wie es beim
     * alten, auf #bb0000 zugeschnittenen Kommentar noch der Fall war.
     *
     * Sehr entsaettigte Eingabefarben (Chroma < 0.03, z. B. Grau) werden wie
     * bei Filaments eigenem generatePalette() komplett entsaettigt
     * ausgegeben, damit kein Farbstich durch Rundungsfehler entsteht.
     *
     * @return array<int, string>
     */
    /**
     * Bringt gaengige Schreibweisen einer Hex-Farbe auf die Form "#rrggbb"
     * oder gibt null zurueck, wenn daraus keine Farbe zu machen ist.
     *
     * Absicht: Wer "bb0000" ohne Raute, "#FFF" als Kurzform oder mit einem
     * versehentlichen Leerzeichen eintraegt, meint offensichtlich eine Farbe.
     * Das abzulehnen ist unfreundlich, wenn die Absicht eindeutig ist - und
     * war die Ursache der Fehlermeldung beim Speichern der Primaerfarbe.
     */
    public static function normalizeColor(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        $value = ltrim($value, '#');

        // Kurzform verdoppeln: "abc" -> "aabbcc" (so wie CSS es auch liest).
        if (preg_match('/^[0-9a-f]{3}$/', $value)) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        return preg_match('/^[0-9a-f]{6}$/', $value) ? '#'.$value : null;
    }

    public static function buildPalette(string $hex): array
    {
        // Unbrauchbare Eingaben nicht stillschweigend durchlassen: Filaments
        // convertToOklch() wirft dabei keine Ausnahme, sondern liefert falsche
        // Werte - "#fff" ergibt ein Rot, "bb0000" ohne Raute ein Schwarz. Ohne
        // diesen Rueckfall stellte man sich damit unbemerkt eine falsche Farbe
        // ein. normalizeColor() faengt die ueblichen Schreibweisen ab; bleibt
        // danach etwas Ungueltiges uebrig, gilt der Standardwert.
        $hex = static::normalizeColor($hex) ?? static::DEFAULT_COLOR;

        $inputOklch = Color::convertToOklch($hex);
        [$inputLightness, $inputChroma, $hue] = sscanf($inputOklch, 'oklch(%f %f %f)');

        [$defaultLightness600, $defaultChroma600] = static::DEFAULT_CURVE[600];

        $isAchromatic = $inputChroma < 0.03;

        $palette = [];

        foreach (static::DEFAULT_CURVE as $shade => [$defaultLightness, $defaultChroma]) {
            if ($shade === 600) {
                $lightness = $inputLightness;
                $chroma = $inputChroma;
            } else {
                $lightness = $inputLightness * ($defaultLightness / $defaultLightness600);
                $chroma = $inputChroma * ($defaultChroma / $defaultChroma600);
            }

            $palette[$shade] = sprintf(
                'oklch(%s %s %s)',
                round($lightness, 3),
                $isAchromatic ? 0 : round($chroma, 3),
                round($hue, 3),
            );
        }

        return $palette;
    }

    public static function forgetCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * @return array{brand_name?: ?string, logo_path?: ?string, primary_color?: ?string}
     */
    private static function cached(): array
    {
        try {
            return Cache::rememberForever(static::CACHE_KEY, static::resolveSettings(...));
        } catch (Throwable $exception) {
            // Der Cache-Store selbst kann fehlschlagen: liegt er auf der
            // Datenbank (CACHE_STORE=database, der Standard der .env), greift
            // Cache::rememberForever auf die Tabelle 'cache' zu - die beim
            // allerersten `migrate` auf einem frischen System noch gar nicht
            // existiert. Dann die Werte ungecacht direkt aufloesen, statt den
            // gesamten Boot (und damit `migrate` selbst) abzubrechen. Der
            // Schema::hasTable('app_settings')-Schutz in resolveSettings()
            // greift erst *innerhalb* der Closure und liefe zu spaet.
            return static::resolveSettings();
        }
    }

    /**
     * @return array{brand_name?: ?string, logo_path?: ?string, primary_color?: ?string}
     */
    private static function resolveSettings(): array
    {
        try {
            // Tabellen-Existenz statt Exception-Handling als primaere
            // Pruefung: so bricht auch dann nichts, wenn die Tabelle
            // schlicht noch nicht existiert (frisches System, laufende
            // Migration) - der Normalfall beim allerersten Deployment.
            if (! Schema::hasTable('app_settings')) {
                return [];
            }

            $settings = static::query()->find(static::SINGLETON_ID);

            return $settings?->only(['brand_name', 'logo_path', 'primary_color']) ?? [];
        } catch (Throwable $exception) {
            // Eng gefasster Ausfallschutz: Schema::hasTable() selbst kann
            // ebenfalls fehlschlagen, wenn die Datenbank ueberhaupt nicht
            // erreichbar ist (nicht nur wenn die Tabelle fehlt). In
            // beiden Faellen sollen Standardwerte greifen, statt die
            // gesamte Anwendung - inklusive jedem artisan-Aufruf - mit
            // dieser Exception abzubrechen.
            return [];
        }
    }
}
