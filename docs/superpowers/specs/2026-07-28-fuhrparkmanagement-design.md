# Fuhrparkmanagement — Design

**Datum:** 2026-07-28
**Status:** Entwurf zur Umsetzung freigegeben

## Zweck

Eine interne Web-App, die den Fahrzeugbestand der Firma dokumentiert: welches Fahrzeug
fährt wer, mit welcher Tankkarte, und wann läuft das Leasing aus. Sie ersetzt die
Verwaltung per Tabellenkalkulation und beantwortet zwei Fragen ohne Suchen: *Was steht an?*
und *Was kostet der Fuhrpark?*

## Rahmenbedingungen

- **Umfang:** bis 20 Fahrzeuge, ein Standort
- **Nutzer:** kleines Team (Fuhrparkverantwortlicher, Buchhaltung), 3–5 Logins
- **Betrieb:** Entwicklung lokal in DDEV, späteres Deployment auf Firmen-Cloud-Hosting
- **Vertraulichkeit:** Kostendaten, Kennzeichen, Mitarbeiterzuordnung und Tankkartennummern
  sind vertraulich. Sicherheit hat Vorrang vor Bequemlichkeit.

## Technischer Stack

| Bereich | Wahl | Begründung |
|---|---|---|
| Sprache/Framework | PHP, Laravel | Entspricht der Erfahrung des Entwicklers (PHP/TYPO3) |
| Oberfläche | Filament | Listen, Formulare, Filter, Auth und Dashboard-Widgets sind vorgesehene Bausteine — es wird Fachlogik geschrieben, keine Infrastruktur |
| Datenbank | MariaDB | Identisch lokal (DDEV) und in der Cloud |
| Lokale Umgebung | DDEV | Vertraut aus TYPO3-Projekten, HTTPS und DB ohne Konfiguration |
| Tests | Pest | Laravel-Standard |

Die Konfiguration läuft vollständig über `.env`, damit lokale und Cloud-Umgebung sich nur in
den Zugangsdaten unterscheiden.

## Datenmodell

Fünf Tabellen. Fremdschlüssel auf Stammdaten sind bewusst optional (`nullable`), damit ein
Fahrzeug angelegt werden kann, bevor Fahrer oder Tankkarte feststehen.

### `vehicles`

| Feld | Typ | Regeln |
|---|---|---|
| `brand` | string | Pflicht |
| `model` | string | Pflicht |
| `license_plate` | string | Pflicht, eindeutig |
| `fuel_type` | enum | Pflicht: Benzin, Diesel, Hybrid, Plug-in-Hybrid, Elektro, sonstige |
| `employee_id` | FK → `employees` | optional |
| `fuel_card_id` | FK → `fuel_cards` | optional |
| `lessor_id` | FK → `lessors` | optional |
| `lease_start` | date | Pflicht |
| `lease_end` | date | Pflicht, muss nach `lease_start` liegen |
| `monthly_lease_rate` | decimal(10,2) | optional, ≥ 0 |
| `annual_vehicle_tax` | decimal(10,2) | optional, ≥ 0 |
| `annual_insurance` | decimal(10,2) | optional, ≥ 0 |
| `status` | enum | `active` / `retired`, Standard `active` |
| `notes` | text | optional |

### `employees`

Vorname, Nachname, Abteilung, E-Mail, `active` (bool).

Bewusst getrennt von `users`: ein Mitarbeiter mit Dienstwagen braucht keinen Zugang zur
Software, und ein Buchhaltungs-Login fährt kein Auto.

### `fuel_cards`

Anbieter (enum: DKV, Elli, sonstige), Kartennummer, gültig bis (date), Notizen.

Die Kartennummer wird über Laravels `encrypted`-Cast verschlüsselt in der Datenbank
abgelegt. Konsequenz: keine SQL-Suche über dieses Feld — bei dieser Datenmenge kein Verlust.

### `lessors`

Name, Ansprechpartner, Telefon, E-Mail, Notizen.

### `users`

Name, E-Mail, Passwort-Hash, `role` (enum: `admin` / `viewer`), `can_view_costs` (bool),
TOTP-Secret für 2FA.

### Lebenszyklus

Ausgemusterte Fahrzeuge werden auf `status = retired` gesetzt, nicht gelöscht. Sie
verschwinden aus der Standardansicht, bleiben aber für Rückfragen erhalten. Endgültiges
Löschen ist Admins vorbehalten.

## Berechtigungen

Zwei Felder am Benutzer statt eines Rechte-Pakets mit eigenen Tabellen — bei 3–5 Nutzern
wäre eine Rollen-Rechte-Matrix Ballast, der dauerhaft gepflegt werden muss.

- **`role = admin`** — darf alles, inklusive Benutzerverwaltung und endgültigem Löschen
- **`role = viewer`** — darf lesen und exportieren, keine Schreibvorgänge
- **`can_view_costs`** — unabhängig von der Rolle: ohne dieses Recht sind der Menüpunkt
  „Kosten" und die Betragsfelder am Fahrzeug nicht sichtbar

Durchsetzung erfolgt serverseitig über Laravel-Policies. Ausblenden im Frontend ist
Komfort, nicht Schutz: ein direkter URL-Aufruf ohne Berechtigung muss abgewiesen werden.

## Oberfläche

### Startseite

Bewusst ohne Geldbeträge — sie dient der Orientierung, nicht der Auswertung.

- Anzahl aktiver Fahrzeuge
- Aufteilung nach Antriebsart (z.B. 12 Diesel / 5 Elektro / 3 Hybrid)
- Das nächste anstehende Leasingende namentlich: „Nächstes Leasingende: M-AB 1234,
  VW Passat, in 34 Tagen"
- Liste aller Verträge, die in den nächsten 90 Tagen auslaufen

Alle Kennzahlen und Warnungen berücksichtigen ausschließlich Fahrzeuge mit
`status = active`. Das Fenster von 90 Tagen ist fest im Code hinterlegt, nicht
konfigurierbar.

Keine E-Mail-Benachrichtigung und kein Cronjob: die Warnung erscheint beim Login, wo sie
ohnehin gesehen wird.

### Fahrzeuge

Liste mit Kennzeichen, Marke/Modell, Fahrer, Antriebsart, Leasingende. Sortier- und
durchsuchbar, Filter auf Antriebsart und Status. Näherrückendes Leasingende farblich
hervorgehoben. CSV-Export.

Detailseite in drei Blöcken:

1. **Fahrzeug** — Marke, Modell, Kennzeichen, Antriebsart
2. **Zuordnung** — Mitarbeiter, Tankkarte
3. **Vertrag & Kosten** — Leasinggeber, Laufzeit, Leasingrate, Kfz-Steuer, Versicherung,
   ausgewiesene Jahreskosten (nur mit `can_view_costs`)

### Kosten

Eigener Menüpunkt, nur mit `can_view_costs` erreichbar. Eine Zeile pro aktivem Fahrzeug:
Kennzeichen, Leasingrate monatlich, Kfz-Steuer jährlich, Versicherung jährlich,
Gesamtkosten pro Jahr. Summenzeile und CSV-Export. Ausgemusterte Fahrzeuge sind über den
Status-Filter zuschaltbar, zählen aber nicht in die Standardsumme.

Jahreskosten = `monthly_lease_rate × 12 + annual_vehicle_tax + annual_insurance`.
Fehlende Werte zählen als 0.

Dies ist der Ort, an dem spätere Erweiterungen (Kraftstoffkosten) andocken.

### Stammdaten

Je eine schlichte Liste für Mitarbeiter, Tankkarten und Leasinggeber.

## Fehlerbehandlung

- **Validierung:** Kennzeichen eindeutig, `lease_end` nach `lease_start`, Beträge nicht
  negativ
- **Löschschutz:** Ein Mitarbeiter, eine Tankkarte oder ein Leasinggeber mit noch
  zugeordnetem Fahrzeug lässt sich nicht löschen. Statt eines verwaisten Fahrzeugs
  erscheint eine verständliche Meldung, die das betroffene Fahrzeug benennt. Der Schutz
  gilt unabhängig vom Fahrzeugstatus — auch ein ausgemustertes Fahrzeug behält seine
  Zuordnungen, sonst wäre die Aufbewahrung sinnlos.
- **Produktion:** keine Stacktraces nach außen (`APP_DEBUG=false`)

## Sicherheit im Betrieb

- HTTPS-Zwang in Produktion
- Rate-Limit auf dem Login gegen Durchprobieren
- Automatischer Session-Ablauf
- 2FA-Pflicht per TOTP. Das konkrete Paket wird bei der Umsetzung anhand der dann
  aktuellen Filament-Version gewählt, statt hier eine Version festzuschreiben.
- Tankkartennummer verschlüsselt in der Datenbank

## Tests

Testgetrieben, Schwerpunkt auf Regeln, bei denen ein Fehler weh tut:

- Ein `viewer` kann nicht schreiben — auch nicht per direktem Request
- Ohne `can_view_costs` kommen keine Beträge heraus, auch nicht per direktem URL-Aufruf
- Die Leasingende-Warnung zeigt die richtigen Fahrzeuge im richtigen Zeitfenster
  (Grenzfälle: heute, in 90 Tagen, in 91 Tagen, bereits abgelaufen)
- Der Löschschutz für zugeordnete Stammdaten greift
- Kennzeichen-Eindeutigkeit und Datumsvalidierung
- Jahreskosten-Berechnung inklusive fehlender Werte

Filament-Formulare selbst werden nicht getestet — das ist Framework-Code.

## Nicht in Version 1

Bewusst zurückgestellt, nichts davon wird auf Vorrat gebaut:

- Audit-Log (wer hat wann was geändert)
- E-Mail-Benachrichtigungen zu Leasingenden
- Fahrer-Historie mit Zeiträumen (Version 1 kennt nur den aktuellen Fahrer)
- Kraftstoffkosten via DKV/Elli
- Kostenpositionen als eigene Tabelle statt Felder am Fahrzeug
- CSV-Import von Bestandsdaten (der Fuhrpark wird von Null aufgebaut)
