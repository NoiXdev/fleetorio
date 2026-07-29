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

Die Testsuite läuft gegen eine eigene MariaDB-Datenbank (konfiguriert in `phpunit.xml`
als `DB_DATABASE=test`), nicht gegen SQLite. DDEV legt diese Datenbank `test` beim Start
automatisch mit an, zusätzlich zur Projektdatenbank `db` — das ist DDEV-Standardverhalten
für dieses Setup, kein projektspezifisches Skript. Ein neuer Rechner braucht dafür keine
zusätzliche Konfiguration, solange er per `ddev start` läuft.

## Benutzer und Rechte

Benutzer werden in der App unter *Verwaltung → Benutzer* gepflegt. Zwei Angaben steuern die
Rechte:

- **Rolle** — *Administrator* darf alles, *Nur-Lesen* darf ausschließlich lesen und exportieren.
- **Darf Kosten sehen** — unabhängig von der Rolle. Ohne dieses Recht bleiben alle Beträge
  verborgen, auch der Menüpunkt *Kosten*.

Der erste Benutzer wird über `ddev artisan make:filament-user` angelegt; dieser Befehl erzeugt
immer einen Benutzer mit der Rolle *Nur-Lesen*, und `/admin/users` ist für diese Rolle gesperrt
— er kann sich also nicht selbst zum Administrator befördern. Dafür einmalig per Tinker:

    ddev artisan tinker
    App\Models\User::where('email', '…')->firstOrFail()->update(['role' => 'admin', 'can_view_costs' => true]);

Auf der Anmeldeseite steht ein Passwort-Reset-Link zur Verfügung (`->passwordReset()`).
Anfragen sind auf 2 Versuche pro Minute begrenzt (Filaments eingebautes Rate-Limiting).
Lokal landen die Reset-Mails in Mailpit (`ddev launch :8025`, siehe `ddev describe`) — dafür
ist `.env` bereits auf `MAIL_MAILER=smtp` mit Mailpits Host und Port eingerichtet.

Zwei-Faktor-Authentisierung per Authenticator-App ist derzeit freiwillig (Wunsch des
Auftraggebers) und lässt sich über die Profilseite einrichten; Recovery-Codes werden dabei
einmalig angezeigt — sicher aufbewahren. Um 2FA später wieder verpflichtend zu machen,
`isRequired: true` in `app/Providers/Filament/AdminPanelProvider.php`
(`->multiFactorAuthentication(...)`) setzen.

## Betrieb in der Cloud

- `APP_ENV=production` und `APP_DEBUG=false` setzen
- `APP_KEY` muss gesetzt sein und darf sich nie ändern: Tankkartennummern und 2FA-Secrets sind
  damit verschlüsselt. Ein Verlust des Schlüssels macht diese Daten unlesbar.
- Die Anwendung erzeugt außerhalb der lokalen Umgebung ausschließlich `https`-URLs
  (`URL::forceHttps(...)` in `AppServiceProvider`). Das betrifft nur die von Laravel selbst
  generierten Links und Formularziele — eingehende `http://`-Anfragen werden dadurch weder
  abgewiesen noch umgeleitet, und es wird kein HSTS-Header gesetzt. Die Umleitung von http auf
  https sowie HSTS müssen am Reverse-Proxy des Hosters konfiguriert werden.
- `SESSION_SECURE_COOKIE=true` setzen, damit das Session-Cookie nur noch über https gesendet
  wird.
- Sessions laufen nach 60 Minuten Inaktivität ab und enden zusätzlich beim Schließen des
  Browsers (`SESSION_LIFETIME=60`, `SESSION_EXPIRE_ON_CLOSE=true`).
- Login-Versuche werden von Filament serverseitig gedrosselt (5 Fehlversuche, 60 Sekunden
  Sperre) — fest im Vendor-Code, keine Konfiguration nötig. Passwort-Reset-Anfragen sind
  ebenso gedrosselt (2 Versuche, 60 Sekunden Sperre).
- **Echten Mailversand konfigurieren.** `MAIL_MAILER` und die zugehörigen Werte zeigen lokal
  auf Mailpit; ohne einen echten SMTP-Versand (oder z. B. SES/Postmark) in der Cloud läuft der
  Passwort-Reset-Link auf der Anmeldeseite ins Leere — die Mail wird zwar "erfolgreich"
  verarbeitet, kommt aber nirgends an.
- Datenbank-Backups einrichten.
- Queue: siehe Notiz unten zum Export.

## Export

Der CSV-/Excel-Export läuft über Laravels Job-Batches mit `QUEUE_CONNECTION=sync`. Das ist
Absicht: bei dieser Datenmenge ist der Export sofort fertig, und es braucht keinen
Worker-Prozess auf dem Server. Der Export wurde in den Aufgaben zur Fahrzeug- und
Kostenübersicht gegen diese Konfiguration verifiziert (echte Dateien, korrekte Spalten).

Sollte künftig auf `database` umgestellt werden, muss ein Worker laufen
(`php artisan queue:work`, überwacht durch systemd oder Supervisor).

## Bekannte Lücken

**Keine Detailansicht für Nur-Lese-Nutzer.** Ein einzelnes Fahrzeug lässt sich derzeit nur über
das Bearbeitungsformular ansehen, und das ist Administratoren vorbehalten. Für einen Nutzer mit
der Rolle *Nur-Lesen* sind Leasinggeber, Tankkarte, Leasingstart und Notizen damit nirgends
sichtbar — die Fahrzeugliste zeigt sie nicht, die Kostenseite nur Kennzeichen und Beträge.

Die Spezifikation sieht vor, dass *Nur-Lesen* alles sehen darf. Solange ausschließlich
Administratoren mit der Anwendung arbeiten, fällt die Lücke nicht auf; sobald die Buchhaltung
einen eigenen Zugang bekommt, sollte eine Leseansicht (`ViewRecord` mit Infolist, dieselbe
Abschnittsstruktur wie das Formular inklusive der Sichtbarkeitsbedingung auf dem Kostenblock)
nachgezogen werden. `VehiclePolicy::view()` gibt bereits `true` zurück und ist bis dahin
ungenutzt.

## Bekannte Altlasten

Die Migrationen `create_imports_table` und `create_failed_import_rows_table` stammen aus dem
Filament-Paket und werden nicht genutzt (CSV-Import ist laut Spezifikation zurückgestellt).
Sie zu entfernen wäre riskanter als sie zu behalten, solange nicht sicher ist, dass kein
Filament-Feature stillschweigend davon abhängt — daher bleiben sie bestehen.

## Nicht enthalten (bewusst)

Audit-Log, E-Mail-Benachrichtigungen, Fahrer-Historie, Kraftstoffkosten via DKV/Elli.
Siehe `docs/superpowers/specs/2026-07-28-fuhrparkmanagement-design.md`.
