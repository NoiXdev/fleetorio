# Fuhrparkmanagement

Interne Web-App zur Dokumentation des Firmenfuhrparks: Fahrzeuge, Fahrer, Tankkarten,
Leasingzeiträume und Kosten.

## Stack

Laravel 13, Filament 5, MariaDB, DDEV für die lokale Entwicklung.

## Lokal starten

    ddev start
    ddev launch /admin

## Tests

    ddev exec vendor/bin/pest

Die Testsuite läuft gegen eine eigene MariaDB-Datenbank `fuhrpark_test` (konfiguriert in
`phpunit.xml`), nicht gegen SQLite. DDEV richtet diese Datenbank ein; ein neuer Rechner
braucht dafür keine zusätzliche Konfiguration.

## Benutzer und Rechte

Benutzer werden in der App unter *Verwaltung → Benutzer* gepflegt. Zwei Angaben steuern die
Rechte:

- **Rolle** — *Administrator* darf alles, *Nur-Lesen* darf ausschließlich lesen und exportieren.
- **Darf Kosten sehen** — unabhängig von der Rolle. Ohne dieses Recht bleiben alle Beträge
  verborgen, auch der Menüpunkt *Kosten*.

Der erste Benutzer wird über `ddev artisan make:filament-user` angelegt und anschließend zum
Administrator gemacht.

Zwei-Faktor-Authentisierung per Authenticator-App ist derzeit freiwillig (Wunsch des
Auftraggebers) und lässt sich über die Profilseite einrichten; Recovery-Codes werden dabei
einmalig angezeigt — sicher aufbewahren. Um 2FA später wieder verpflichtend zu machen,
`isRequired: true` in `app/Providers/Filament/AdminPanelProvider.php`
(`->multiFactorAuthentication(...)`) setzen.

## Betrieb in der Cloud

- `APP_ENV=production` und `APP_DEBUG=false` setzen
- `APP_KEY` muss gesetzt sein und darf sich nie ändern: Tankkartennummern und 2FA-Secrets sind
  damit verschlüsselt. Ein Verlust des Schlüssels macht diese Daten unlesbar.
- HTTPS wird außerhalb der lokalen Umgebung automatisch erzwungen.
- Sessions laufen nach 60 Minuten Inaktivität ab und enden zusätzlich beim Schließen des
  Browsers (`SESSION_LIFETIME=60`, `SESSION_EXPIRE_ON_CLOSE=true`).
- Login-Versuche werden von Filament serverseitig gedrosselt (5 Fehlversuche, 60 Sekunden
  Sperre) — fest im Vendor-Code, keine Konfiguration nötig.
- Datenbank-Backups einrichten.
- Queue: siehe Notiz unten zum Export.

## Export

Der CSV-/Excel-Export läuft über Laravels Job-Batches mit `QUEUE_CONNECTION=sync`. Das ist
Absicht: bei dieser Datenmenge ist der Export sofort fertig, und es braucht keinen
Worker-Prozess auf dem Server. Der Export wurde in den Aufgaben zur Fahrzeug- und
Kostenübersicht gegen diese Konfiguration verifiziert (echte Dateien, korrekte Spalten).

Sollte künftig auf `database` umgestellt werden, muss ein Worker laufen
(`php artisan queue:work`, überwacht durch systemd oder Supervisor).

## Nicht enthalten (bewusst)

Audit-Log, E-Mail-Benachrichtigungen, Fahrer-Historie, Kraftstoffkosten via DKV/Elli.
Siehe `docs/superpowers/specs/2026-07-28-fuhrparkmanagement-design.md`.
