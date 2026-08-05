# Korrekturen nach der ersten Benutzung

Datum: 2026-08-05
Ausgangsstand: v1.0.2

Fünf Änderungen aus der ersten echten Benutzung der Anwendung. Vier davon sind Erweiterungen,
eine ist ein Fehler, den man beim Hochladen eines Bildes sofort merkt.

Die Punkte hängen nur lose zusammen. Sie stehen in einer Spec, weil sie aus einer Rückmeldung
stammen; umgesetzt werden sie auf zwei Branches (siehe „Aufteilung" am Ende).

---

## 1. Deaktivieren statt Löschen

**Warum:** Stammdaten verschwinden heute endgültig. Ein Mitarbeiter, der die Firma verlässt,
lässt sich entweder gar nicht löschen (solange ein Fahrzeug an ihm hängt) oder ist danach weg —
mitsamt der Information, wer das Fahrzeug gefahren hat. Gewünscht ist ein Mittelweg: Löschen
deaktiviert, und nur ein bereits deaktivierter Datensatz lässt sich endgültig entfernen.

### Der Zustand ist teilweise schon vorhanden

Beim Planen hat sich gezeigt, dass die Anwendung das Konzept bereits kennt:

- **Fahrzeuge** haben `status` mit `Aktiv` und `Ausgemustert` (`App\Enums\VehicleStatus`). Die
  Fahrzeugliste filtert schon heute standardmäßig auf `Aktiv`, ausgemusterte sind zuschaltbar
  (`VehiclesTable`, `SelectFilter::make('status')->default(...)`). Dashboard-Kacheln sowie
  Leasing- und HU-Warnungen zählen ausgemusterte über `Vehicle::scopeActive()` nicht mit.
- **Mitarbeiter** haben ein Feld `active`, sichtbar in Formular, Liste und Detailseite. Es filtert
  aber nirgends etwas — es ist heute reine Dekoration.

Deshalb **kein** SoftDeletes. Ein zusätzliches `deleted_at` ergäbe beim Fahrzeug drei Zustände
(aktiv, ausgemustert, archiviert) und beim Mitarbeiter zwei gleich klingende nebeneinander. Statt
dessen bekommt der vorhandene Zustand endlich eine Wirkung, und die übrigen Stammdaten bekommen
dasselbe Feld.

Nebeneffekt, der Arbeit spart: Ohne Soft Delete feuert `Vehicle::deleting` weiterhin nur beim
echten Löschen. Der dort hinterlegte Code, der Bild und Dokumente von der Platte räumt, bleibt
unverändert richtig.

### Die Regel

> Löschen ist nur möglich, wenn der Datensatz inaktiv ist (beim Fahrzeug: ausgemustert).
> Solange er aktiv ist, bietet die Zeile kein Löschen an, sondern Deaktivieren.

Deaktivieren ist dagegen **immer** erlaubt, auch bei einem Mitarbeiter, dem noch ein Fahrzeug
zugeordnet ist. Die Zuordnung bleibt bestehen — jemand verlässt die Firma, das Auto bleibt ihm
zugeordnet, bis ein Nachfolger da ist.

Die Fremdschlüssel bleiben unangetastet: Ein inaktiver Mitarbeiter mit Fahrzeug lässt sich
weiterhin nicht löschen, weil `restrictOnDelete` greift. `App\Filament\Actions\GuardedDeleteAction`
fängt das wie bisher vorher ab und nennt das Fahrzeug, das im Weg steht.

### Umfang

Neues Feld `active` (boolean, Standard `true`) auf: `dealerships`, `leasing_companies`,
`fuel_cards`, `fuel_card_providers`.

Mitarbeiter (`employees.active`) und Fahrzeug (`vehicles.status`) haben ihren Zustand bereits.

Benutzerkonten (`users`) bleiben ausgenommen. Sie sind keine Stammdaten des Fuhrparks, und an
ihnen hängen Import- und Exportzeilen mit `cascadeOnDelete` — ein eigener Fall, der hier nicht
mitentschieden wird.

### Oberfläche

Je Zeile in den Stammdatenlisten und beim Fahrzeug:

| Aktion | sichtbar wenn | Wirkung |
|---|---|---|
| Deaktivieren (Fahrzeug: Ausmustern) | Datensatz ist aktiv | setzt `active = false` bzw. `status = retired`, mit Rückfrage |
| Aktivieren | Datensatz ist inaktiv | setzt den Zustand zurück |
| Löschen | Datensatz ist inaktiv | wie heute über `GuardedDeleteAction` |

Zusätzlich:

- Jede Stammdatenliste bekommt einen Filter „Status" mit Standard „Aktiv" — dasselbe Muster wie in
  der Fahrzeugliste. Inaktive sind zuschaltbar, aber nicht im Weg.
- Inaktive Zeilen tragen ein Badge „Inaktiv".
- **Auswahlfelder** (Fahrer, Autohaus, Leasinggeber, Tankkarte am Fahrzeug; Anbieter an der
  Tankkarte) zeigen nur aktive Einträge **plus den aktuell gesetzten Wert**. Ohne diese Ausnahme
  fände das Formular den gespeicherten Wert nicht in der Optionsliste und würde die Zuordnung beim
  nächsten Speichern stillschweigend leeren.
- Wird ein inaktiver Bezug angezeigt (etwa der Fahrer eines Fahrzeugs), steht dahinter „(inaktiv)".
- Die globale Suche liefert keine inaktiven Datensätze.

### Unique-Indizes

`vehicles.license_plate` und `fuel_card_providers.name` sind `unique`. Ein inaktiver Datensatz
belegt den Wert weiterhin — ein Kennzeichen eines ausgemusterten Fahrzeugs lässt sich also nicht
erneut vergeben.

Das bleibt so; alles andere erlaubt dasselbe Kennzeichen mehrfach in der Tabelle und macht jede
spätere Auswertung mehrdeutig. Die Fehlermeldung weist darauf hin, dass der Wert möglicherweise
von einem inaktiven Eintrag belegt ist.

---

## 2. 2FA in der Benutzerverwaltung

**Warum:** 2FA lässt sich heute nur im eigenen Profil einschalten. Ein Admin kann es bei einem
verlorenen Telefon nicht zurücksetzen und kann es nicht anordnen.

**Was bereits existiert:** Die Spalte „2FA aktiv" in der Benutzerliste (`UsersTable`, `IconColumn`
auf `app_authentication_secret`). Der Status ist also schon sichtbar.

**Was ein Admin ausdrücklich nicht kann:** fremdes 2FA einrichten. Das Geheimnis darf nur in der
Authenticator-App des Benutzers landen; wüsste es ein Zweiter, wäre der zweite Faktor keiner mehr.

### Umfang

- **Aktion „2FA zurücksetzen"** mit Rückfrage. Leert `app_authentication_secret` und
  `app_authentication_recovery_codes`. Nur für Admins (`UserPolicy`). Nur sichtbar, wenn der
  Benutzer überhaupt 2FA eingerichtet hat.
- **Neue Spalte `users.requires_two_factor`** (boolean, Standard `false`), im Benutzerformular als
  Schalter, in der Liste als Symbol.

### Warum die Pflicht eigene Middleware braucht

Filaments `multiFactorAuthentication(..., isRequired:)` akzeptiert zwar einen Closure, wird aber
beim Registrieren der Routen ausgewertet — `Filament\Pages\Concerns\HasRoutes` entscheidet dort,
ob die Middleware überhaupt an die Route gehängt wird. Zu diesem Zeitpunkt gibt es keinen
angemeldeten Benutzer, eine benutzerabhängige Entscheidung ist dort also nicht möglich.

Stattdessen eine eigene Middleware in der `authMiddleware` des Panels: Wer `requires_two_factor`
gesetzt hat und noch kein `app_authentication_secret` besitzt, wird auf die Profilseite
umgeleitet, auf der 2FA eingerichtet wird.

**Fallstrick:** Die Middleware muss die Zielseite selbst, deren Livewire-Requests und den Logout
durchlassen. Sonst leitet sie den Benutzer auf eine Seite um, die sie erneut umleitet — eine
Schleife, aus der er sich nicht einmal abmelden kann.

---

## 3. Bruttolistenpreis

Neue Spalte `vehicles.gross_list_price`, `decimal(10, 2)`, nullable — wie die übrigen Geldfelder
in `create_vehicles_table`.

Sie gehört in die Section **„Kosten"** des Fahrzeugformulars, nicht in die Stammdaten-Section.
Der Schutz durch `viewCosts` entsteht dadurch, dass die versteckte Section beim Speichern die
Werte aller ihrer Felder verwirft (siehe `CLAUDE.md`). Ein Kostenfeld außerhalb dieser Section
wäre ungeschützt.

Zusätzlich als Spalte in der Kostenübersicht, sortierbar. **Nicht** Teil der Jahreskosten-Summe:
ein Listenpreis ist keine laufende Position. Eine Rechnung nach der 1-Prozent-Regel ist nicht Teil
dieser Änderung.

---

## 4. „Termine" heißt künftig „Historie"

**Warum:** Der Bereich soll auch Unfälle und Schäden aufnehmen. „Termin" passt dafür nicht — ein
Unfall ist keiner.

Geändert werden **nur die Beschriftungen**: Navigation, Titel der Box am Fahrzeug, Schaltflächen
(„Eintrag hinzufügen" statt „Termin hinzufügen"), Meldungstexte. Klassen- und Tabellennamen
(`VehicleAppointment`, `vehicle_appointments`, `AppointmentType`) bleiben unverändert — eine
Umbenennung kostet eine Migration und ändert nichts Sichtbares.

Zwei neue Werte in `AppointmentType`:

| Wert | Beschriftung | Farbe |
|---|---|---|
| `accident` | Unfall | `danger` (das Rose des Panels) |
| `damage` | Schaden | `orange`, in `AdminPanelProvider::panel()` unter `->colors()` neu registriert |

`danger` war bisher bewusst gemieden, damit Badges nicht mit dem Löschen verwechselt werden. Für
einen Unfall ist das Hervorstechen aber genau richtig. Orange muss neu registriert werden, weil
`warning` bereits die Hauptuntersuchung belegt.

---

## 5. Fehler: Bildupload bei kleinen Bildern

**Symptom:** Der Upload verhält sich unberechenbar, sobald ein Bild kleiner als 2000 Pixel ist.

**Zwei Ursachen, beide in `VehicleForm`:**

1. `automaticallyResizeImagesToWidth('2000')` läuft mit Filaments Standardwert
   `shouldAutomaticallyUpscaleImagesWhenResizing = true`. Ein Bild mit 800 Pixel Breite wird
   dadurch auf 2000 Pixel **hochgerechnet** — es wird unscharf und deutlich größer, statt so zu
   bleiben, wie es war.
2. `imageAspectRatio('4:3')` ist nicht nur eine Vorgabe für den Zuschneide-Editor, sondern erzeugt
   serverseitig `Rule::dimensions()->ratio()`. Weicht das Ergebnis des Zuschnitts um wenige Pixel
   ab, weist der Server das Bild mit der Meldung zum Seitenverhältnis zurück.

**Gewünschtes Verhalten:** Die Bildgröße ist egal. Es gilt nur die Grenze von 10 MB.

**Änderung:**

- `automaticallyUpscaleImagesWhenResizing(false)` — kleinere Bilder bleiben unverändert.
- `imageAspectRatio('4:3')` und `automaticallyOpenImageEditorForAspectRatio()` entfallen; damit
  fällt auch die serverseitige `dimensions`-Regel und ihre Fehlermeldung weg.
- `imageEditor()` bleibt, der Zuschnitt ist also weiter möglich, nur nicht mehr erzwungen.
- Das Verkleinern oberhalb von 2000 Pixel bleibt: es spart Speicher und verschlechtert nichts.
- `maxSize(10 MB)` bleibt unverändert.

**Folge für die Darstellung:** Bilder haben künftig beliebige Seitenverhältnisse. Damit die
Kacheln in Liste und Detailansicht gleich hoch bleiben, brauchen sie `object-fit: cover` mit
festem Rahmen.

---

## Tests

Zu Punkt 1:

- Ein aktiver Datensatz lässt sich nicht löschen — die Aktion ist nicht vorhanden, und der direkte
  Aufruf greift ebenfalls nicht.
- Deaktivieren funktioniert auch bei einem Mitarbeiter mit zugeordnetem Fahrzeug; die Zuordnung
  bleibt.
- Löschen eines inaktiven, aber noch zugeordneten Mitarbeiters wird verweigert — gegen MariaDB,
  damit der echte Fremdschlüssel geprüft wird und nicht eine Nachbildung.
- Löschen eines inaktiven, nicht mehr zugeordneten Mitarbeiters funktioniert.
- Ein Fahrzeug mit inaktivem Fahrer zeigt ihn weiterhin an und verliert die Zuordnung beim
  Speichern nicht.
- Inaktive erscheinen nicht in Auswahlfeldern, nicht in der Standardansicht der Listen und nicht
  in der globalen Suche; mit gesetztem Filter erscheinen sie in der Liste.
- Aktivieren macht den Datensatz wieder normal nutzbar.

Zu Punkt 2:

- Zurücksetzen leert Geheimnis und Recovery-Codes; ein Nicht-Admin kann die Aktion weder sehen
  noch direkt aufrufen.
- Ein Benutzer mit `requires_two_factor` und ohne Geheimnis wird umgeleitet; die Zielseite selbst
  und der Logout bleiben erreichbar.
- Ein Benutzer ohne die Pflicht wird nicht umgeleitet.
- Ein Benutzer mit der Pflicht und eingerichtetem 2FA wird nicht umgeleitet.

Zu Punkt 3:

- Ohne `viewCosts` wird der Bruttolistenpreis weder angezeigt noch beim Speichern übernommen —
  auch nicht, wenn er im Formular mitgeschickt wird.

Zu Punkt 4:

- Die neuen Typen lassen sich anlegen und erscheinen mit ihrer Beschriftung und Farbe.

Zu Punkt 5:

- Ein Bild mit 800 Pixel Breite wird angenommen und in seiner Größe gespeichert.
- Ein Bild mit einem anderen Seitenverhältnis als 4:3 wird angenommen.
- Ein Bild über 10 MB wird weiterhin abgelehnt.

---

## Aufteilung auf Branches

- `fix/vehicle-image-upload` — Punkt 5 allein. Kleiner Diff, behebt einen Fehler, den man im
  Alltag sofort trifft; soll unabhängig von den größeren Änderungen mergebar sein.
- `feature/archiving-and-admin-controls` — Punkte 1 bis 4, in getrennten Commits.

Beide Branches fassen `VehicleForm` an, aber an verschiedenen Stellen (Bildfeld gegenüber
Kosten-Section).
