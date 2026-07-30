# Fuhrparkmanagement

Interne Web-App zur Dokumentation des Firmenfuhrparks: Fahrzeuge, Fahrer, Tankkarten,
Leasingzeiträume und Kosten.

## Stack

Laravel 13, Filament 5, MariaDB, DDEV für die lokale Entwicklung.

**PHP 8.4 ist Mindestanforderung** — nicht 8.3. Die gelockte Version von
`symfony/console` verlangt PHP ≥ 8.4.1; ein Build auf 8.3 scheitert beim
`composer install`. Beim Hosting also PHP 8.4 verlangen, zusammen mit der
Erweiterung `intl` (die fordert Filament).

## Frisch aufsetzen (nach dem Klonen)

`vendor/` und `.env` liegen nicht im Repository, ein Klon braucht deshalb einmalig:

    ddev start
    ddev composer install
    ddev artisan key:generate
    ddev artisan migrate

DDEV legt die `.env` beim ersten Start mit den Datenbankzugängen selbst an. `key:generate`
schreibt den `APP_KEY` hinein — **ohne ihn startet die Anwendung nicht**, und er darf danach
nicht mehr wechseln (siehe *Betrieb in der Cloud*).

Danach im Browser `/setup` aufrufen — der empfohlene Weg zum ersten Zugang, siehe
*Ersteinrichtung* weiter unten.

## Ersteinrichtung

Solange in der Datenbank kein einziger Benutzer existiert, ist unter `/setup` ein
Einrichtungsassistent erreichbar (Name, E-Mail, Passwort mit Bestätigung). Das Absenden legt
direkt einen Administrator mit Kostenrecht an, meldet ihn an und leitet zum Dashboard weiter.

Sobald ein Benutzer existiert, antwortet `/setup` nur noch mit 404 — sowohl beim Anzeigen als
auch beim Absenden. Das ist Absicht: eine dauerhaft erreichbare Einrichtungsseite wäre eine
Hintertür zum Administratorkonto, deshalb hängt die Sperre am Benutzerbestand selbst statt an
einem Schalter, den man hinterher von Hand abstellen müsste. Sollte je kein Benutzer mehr
existieren, öffnet sich `/setup` genau deswegen von selbst wieder — das ist der Rettungsweg,
falls man sich einmal ausgesperrt hat.

Alternativ bleibt der bisherige Weg über die Konsole:

    ddev artisan make:filament-user

Der so erzeugte Benutzer hat die Rolle *Nur-Lesen*; wie er Administrator wird, steht unter
*Benutzer und Rechte*.

## Lokal starten

    ddev start
    ddev launch /admin

E-Mails (z.B. Passwort-Reset) gehen lokal nicht nach draußen, sondern in das Mailpit, das DDEV
mitbringt:

    ddev mailpit

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

Der erste Benutzer entsteht am einfachsten über den Setup-Assistenten unter `/setup` (siehe
*Ersteinrichtung*) — der legt direkt einen Administrator mit Kostenrecht an. Alternativ über die
Konsole: `ddev artisan make:filament-user` erzeugt immer einen Benutzer mit der Rolle
*Nur-Lesen*, und `/admin/users` ist für diese Rolle gesperrt — er kann sich also nicht selbst zum
Administrator befördern. Dafür einmalig per Tinker:

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

## Deployment als Docker-Image

Für das Deployment gibt es ein eigenständiges, mehrstufiges `Dockerfile` auf Basis von
[FrankenPHP](https://frankenphp.dev). Es ist ausschließlich fürs Deployment gedacht — **DDEV
bleibt die lokale Entwicklungsumgebung** und wird dadurch nicht ersetzt oder verändert; beide
funktionieren unabhängig voneinander nebeneinander.

Die Anwendung läuft darin im klassischen FrankenPHP-Modus, ausdrücklich **ohne** Laravel Octane
und ohne FrankenPHPs Worker-Modus. Bei 3–5 Nutzern bringt Octane keinen messbaren
Geschwindigkeitsvorteil, holt aber eine Fehlerklasse ins Haus, die mit Livewire und Filament
besonders unangenehm ist: Zustand, der versehentlich zwischen Anfragen überlebt (statische
Eigenschaften, Singletons, alles, was in einem einmal gebooteten Container hängen bleibt). Diese
Begründung steht auch als Kommentar im `Dockerfile`, damit sie niemandem verloren geht, der es
später "optimiert".

Kein Node, kein npm, kein Asset-Build im Image: Filament liefert seine Assets bereits fertig
unter `public/css/filament/` und `public/js/filament/` aus, und keine Blade-View im Projekt
bindet `@vite` ein.

### Bauen und lokal ausprobieren

Zum Ausprobieren ohne eigenes Hosting liegt eine `docker-compose.yml` bei (Anwendung + eigene
MariaDB, mit Volumes für die Datenbank und für `storage/app`). Sie belegt bewusst **nicht** die
Ports 80/443 auf dem Host, da DDEV diese bereits verwendet — stattdessen Port **8080**:

    APP_KEY=base64:… docker compose up --build

Der `APP_KEY` ist Pflicht (siehe unten) und hat hier bewusst keinen Default; ein frisch mit
`openssl rand -base64 32` erzeugter Schlüssel genügt für einen reinen Lokaltest mit der
Wegwerf-Datenbank dieses Compose-Stacks. Anschließend:

    docker compose exec app php artisan migrate   # einmalig, siehe "Erstes Deployment" unten
    curl -I http://localhost:8080/

Aufräumen (entfernt auch die Volumes der Testdatenbank):

    docker compose down -v

Nur das Image selbst bauen:

    docker build -t fuhrpark:deploy .

### Reihenfolge beim ersten Deployment

Der Container migriert **nicht automatisch** beim Start — das ist Absicht. Der Auftraggeber
bringt einen Datenbank-Dump aus der bestehenden Installation mit, und automatisches Migrieren
beim Containerstart ist unsicher, sobald mehr als eine Instanz läuft. Reihenfolge:

1. Image bauen: `docker build -t fuhrpark:deploy .`
2. Umgebungsvariablen setzen (siehe `.env.docker.example` als kommentierte Vorlage) — insbesondere
   `APP_KEY`, Datenbankzugang, `APP_URL`, `SERVER_NAME`.
3. **Datenbank-Dump importieren** (empfohlen, da echte Arbeitsdaten mitkommen) **oder**, bei
   einer leeren Datenbank, einmalig migrieren:

       docker compose exec app php artisan migrate --force

   Das ist ein bewusster, eigener Schritt — der Container tut das nicht von selbst.
4. Container starten.
5. Bei einer leeren, frisch migrierten Datenbank (kein Dump importiert): im Browser `/setup`
   aufrufen und dort den ersten Benutzer anlegen — keine Konsole am Zielsystem nötig, siehe
   *Ersteinrichtung* weiter oben. Bei einem importierten Dump existiert bereits ein Benutzer,
   `/setup` bleibt dann von selbst gesperrt (404).

### `APP_KEY` — nicht neu erzeugen

Der `APP_KEY` verschlüsselt in der Datenbank unter anderem die Tankkartennummern. Für ein
Deployment mit einem mitgebrachten Datenbank-Dump muss der **bestehende** Schlüssel aus der
aktuellen Installation übernommen werden (aus deren `.env`) — niemals ein neuer per
`artisan key:generate`. Ein anderer Schlüssel macht die vorhandenen Tankkartennummern
unwiderruflich unlesbar. Siehe die Hinweise dazu in `.env.docker.example`.

Das Image selbst enthält keinen `APP_KEY` und keine `.env` — beides kommt ausschließlich aus der
Container-Umgebung. `config:cache`, `route:cache` und `view:cache` laufen deshalb bewusst **erst
beim Containerstart** (im Entrypoint `docker/entrypoint.sh`), nicht beim `docker build`: Zum
Build-Zeitpunkt ist die echte Umgebung (Datenbankzugang, `APP_URL`, …) noch nicht bekannt. Ein
beim Build erzeugter Konfigurations-Cache würde diese leeren Platzhalterwerte einfrieren, und
jeder gestartete Container würde die tatsächlich übergebene Umgebung stillschweigend ignorieren —
der klassische Fehler bei containerisierten Laravel-Apps. Der Entrypoint bricht außerdem sofort
mit einer klaren Fehlermeldung ab, wenn `APP_KEY` fehlt, statt später mit einem unverständlichen
Verschlüsselungsfehler zu scheitern.

### HTTPS

Zwei Wege, je nachdem, was der Hoster hergibt:

- **FrankenPHP macht HTTPS selbst.** `SERVER_NAME` auf die echte Domain setzen (z. B.
  `SERVER_NAME=fuhrpark.example.com`). Das in FrankenPHP eingebaute Caddy besorgt sich dann
  automatisch ein Let's-Encrypt-Zertifikat und erneuert es selbständig. Voraussetzung: Port 80
  und 443 des Containers sind direkt aus dem Internet erreichbar (für die HTTP-01-Challenge).
  Für dauerhaft gültige Zertifikate über Neustarts hinweg sollte `/data/caddy` dann als Volume
  eingebunden werden, sonst fordert Caddy bei jedem Neustart ein neues Zertifikat an.
- **Ein vorgeschalteter Reverse-Proxy** (nginx, Traefik, die Load-Balancer-Lösung des Hosters, …)
  terminiert HTTPS bereits selbst. Dann `SERVER_NAME=":80"` setzen — Caddy serviert dann nur noch
  reines HTTP auf Port 80 innerhalb des Containers, ohne selbst ein Zertifikat anzufordern, und
  überlässt TLS vollständig dem Proxy davor.

In beiden Fällen gilt weiterhin, was im Abschnitt *Betrieb in der Cloud* zu HTTPS steht: Laravel
selbst erzeugt außerhalb der lokalen Umgebung nur noch `https`-URLs, setzt aber keine
HTTP→HTTPS-Weiterleitung und kein HSTS gegenüber eingehenden Anfragen — im Reverse-Proxy-Fall ist
das weiterhin Aufgabe des Proxys.

### Sonstiges zum Image

- Basis-Image: `dunglas/frankenphp`, fest gepinnt auf `1.12.6-php8.4.23-bookworm` (kein
  `latest`, kein wandernder Tag). PHP 8.4, nicht 8.3: `composer.json` nennt zwar `"php": "^8.3"`,
  aber `composer.lock` sperrt tatsächlich `symfony/console` & Co. auf `v8.1.x`, was PHP ≥8.4.1
  voraussetzt — die Testsuite lief nie unter PHP 8.3, DDEV selbst nutzt Version 8.4
  (`.ddev/config.yaml`). Das Image folgt damit der tatsächlich geprüften Version, nicht der
  veralteten Angabe in `composer.json`.
- Die Anwendung läuft im Container als `www-data` (uid 33), nicht als root. Der `frankenphp`-
  Prozess kann trotzdem an Port 80/443 binden, weil das Basis-Image ihm bereits die Capability
  `cap_net_bind_service` mitgibt.
- Persistent zu haltende Pfade: `storage/app` (dort landen erzeugte Exportdateien) und die
  MariaDB-Daten. `storage/logs` braucht in der Praxis kein eigenes Volume, es sei denn, Logs
  sollen einen Containerneustart überleben.
- Healthcheck des Containers ruft `/` auf (keine Anmeldung nötig) und prüft auf eine echte
  Antwort der Anwendung.

## Export

Der CSV-/Excel-Export läuft über Laravels Job-Batches mit `QUEUE_CONNECTION=sync`. Das ist
Absicht: bei dieser Datenmenge ist der Export sofort fertig, und es braucht keinen
Worker-Prozess auf dem Server. Der Export wurde in den Aufgaben zur Fahrzeug- und
Kostenübersicht gegen diese Konfiguration verifiziert (echte Dateien, korrekte Spalten).

Sollte künftig auf `database` umgestellt werden, muss ein Worker laufen
(`php artisan queue:work`, überwacht durch systemd oder Supervisor).

## Fahrzeug-Detailseite

Ein Klick auf eine Zeile in der Fahrzeugliste führt zu einer Anzeigeseite (dieselbe
Abschnittsstruktur wie das Bearbeitungsformular, Kostenblock ebenso an `viewCosts` gebunden),
nicht direkt in den Bearbeitungsmodus. Von dort führt die Schaltfläche *Daten bearbeiten* zur
Bearbeitungsseite — sichtbar nur für Administratoren (`VehiclePolicy::update`). Ein Nutzer mit
der Rolle *Nur-Lesen* erreicht die Anzeigeseite und sieht dort auch die Terminhistorie, jedoch
ohne Möglichkeit, etwas anzulegen oder zu ändern.

## Bekannte Altlasten

Die Migrationen `create_imports_table` und `create_failed_import_rows_table` stammen aus dem
Filament-Paket und werden nicht genutzt (CSV-Import ist laut Spezifikation zurückgestellt).
Sie zu entfernen wäre riskanter als sie zu behalten, solange nicht sicher ist, dass kein
Filament-Feature stillschweigend davon abhängt — daher bleiben sie bestehen.

## Nicht enthalten (bewusst)

Audit-Log, E-Mail-Benachrichtigungen, Fahrer-Historie, Kraftstoffkosten via DKV/Elli.
Siehe `docs/superpowers/specs/2026-07-28-fuhrparkmanagement-design.md`.
