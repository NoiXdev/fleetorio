# Korrekturen nach der ersten Benutzung

Datum: 2026-08-05
Ausgangsstand: v1.0.2

Fünf Änderungen aus der ersten echten Benutzung der Anwendung. Vier davon sind Erweiterungen,
eine ist ein Fehler, den man beim Hochladen eines Bildes sofort merkt.

Die Punkte hängen nur lose zusammen. Sie stehen in einer Spec, weil sie aus einer Rückmeldung
stammen; umgesetzt werden sie auf zwei Branches (siehe „Aufteilung" am Ende).

---

## 1. Archivieren statt Löschen

**Warum:** Stammdaten verschwinden heute endgültig. Ein Mitarbeiter, der die Firma verlässt,
lässt sich entweder gar nicht löschen (solange ein Fahrzeug an ihm hängt) oder ist danach weg —
mitsamt der Information, wer das Fahrzeug gefahren hat. Gewünscht ist ein Mittelweg: Löschen
archiviert, und nur ein bereits archivierter Datensatz lässt sich endgültig entfernen.

### Umfang

SoftDeletes (`deleted_at`) auf: `employees`, `dealerships`, `leasing_companies`, `fuel_cards`,
`fuel_card_providers`, `vehicles`.

Benutzerkonten (`users`) bleiben ausgenommen. Sie sind keine Stammdaten des Fuhrparks, und an
ihnen hängen Import- und Exportzeilen mit `cascadeOnDelete` — das ist ein eigener Fall, der hier
nicht mitentschieden wird.

### Warum SoftDeletes die gewünschte Regel ohne neue Prüfung liefert

Ein Soft Delete ist ein `UPDATE`. Die Fremdschlüssel der Stammdaten stehen auf
`restrictOnDelete` (siehe `create_vehicles_table`), und ein `UPDATE` löst sie nicht aus. Ein
`forceDelete` ist dagegen ein echtes `DELETE` und läuft in genau diese Sperre.

Daraus folgt ohne eine einzige zusätzliche Bedingung im Code:

| Vorgang | Mitarbeiter ohne Fahrzeug | Mitarbeiter mit Fahrzeug |
|---|---|---|
| Archivieren | geht | geht |
| Endgültig löschen | geht (nur wenn archiviert) | Datenbank verweigert |

Das ist die gewünschte Semantik: archivieren immer, endgültig löschen nur, wenn der Datensatz
archiviert ist **und** niemand mehr auf ihn zeigt.

### Oberfläche

- Die Aktion „Löschen" heißt in allen Stammdatenlisten und beim Fahrzeug „Archivieren".
- Bei einem archivierten Datensatz erscheinen „Wiederherstellen" und „Endgültig löschen".
- Ein Filter „Archivierte anzeigen", standardmäßig aus.
- Archivierte Zeilen tragen ein Badge „Archiviert".
- `App\Filament\Actions\GuardedDeleteAction` bewacht künftig das **endgültige** Löschen statt des
  Archivierens. Der Text bleibt inhaltlich gleich („ist noch dem Fahrzeug X zugeordnet"), nur der
  Titel wird zu „Endgültig löschen nicht möglich". Ohne diese Vorabprüfung liefe der Benutzer in
  eine SQL-Fehlermeldung des Fremdschlüssels.

### Zwei Fallstricke, die abgesichert werden müssen

**1. `Vehicle::booted()` löscht beim Archivieren die Dateien.**

`app/Models/Vehicle.php` registriert heute:

```php
static::deleting(function (Vehicle $vehicle): void {
    $vehicle->documents->each->delete();

    if (filled($vehicle->image_path)) {
        Storage::disk('local')->delete($vehicle->image_path);
    }
});
```

Das `deleting`-Event feuert bei einem Soft Delete genauso wie bei einem harten. Ohne Änderung
würde „Archivieren" das Fahrzeugbild und alle Dokumente von der Platte löschen, während der
Datensatz erhalten bleibt — ein Datenverlust unter einer Beschriftung, die das Gegenteil
verspricht.

Der Rumpf muss deshalb auf `$vehicle->isForceDeleting()` eingegrenzt werden. Das ist die
riskanteste Einzelstelle dieser Änderung und braucht einen eigenen Test, der nach dem
Archivieren prüft, dass Datei und Dokumentzeilen noch da sind.

**2. Eine `belongsTo`-Beziehung auf einen archivierten Datensatz liefert `null`.**

Der globale Scope von SoftDeletes wirkt auch auf Beziehungen. Ein Fahrzeug, dessen Fahrer
archiviert wurde, hätte also plötzlich gar keinen Fahrer mehr — nicht „einen ehemaligen", sondern
optisch nie einen gehabt. Dasselbe gilt für Autohaus, Leasinggeber und Tankkarte.

Deshalb:

- Die `belongsTo`-Beziehungen am Fahrzeug bekommen `withTrashed()`, damit der Wert überhaupt
  auflösbar bleibt.
- In der Anzeige (Liste, Detailseite) wird ein archivierter Bezug als solcher gekennzeichnet,
  z. B. „Max Mustermann (archiviert)".
- Das **Auswahlfeld** im Fahrzeugformular zeigt nur nicht-archivierte Einträge **plus den aktuell
  gesetzten Wert**. Ohne diese Ausnahme fände das Formular den gespeicherten Wert nicht in der
  Optionsliste und würde die Zuordnung beim nächsten Speichern stillschweigend leeren.

### Unique-Indizes

`vehicles.license_plate` und `fuel_card_providers.name` sind `unique`. Ein archivierter Datensatz
belegt den Wert weiterhin — ein archiviertes Kennzeichen lässt sich also nicht erneut anlegen.

Das bleibt so. Die Alternative (Unique zusammen mit `deleted_at`) erlaubt dasselbe Kennzeichen
mehrfach in der Tabelle und macht jede spätere Auswertung mehrdeutig. Stattdessen weist die
Fehlermeldung darauf hin, dass der Wert möglicherweise von einem archivierten Eintrag belegt ist.

### Auswertungen

Dashboard-Kacheln, Leasing- und HU-Warnungen, Kostenübersicht und globale Suche dürfen
archivierte Datensätze nicht mitzählen. Der globale Scope erledigt das für alles, was über das
Modell läuft. Zu prüfen ist `Vehicle::sumAnnualCost()`, weil dort mit einem SQL-Aggregat
gearbeitet wird.

---

## 2. 2FA in der Benutzerverwaltung

**Warum:** 2FA lässt sich heute nur im eigenen Profil einschalten. Ein Admin sieht nicht, wer es
aktiv hat, kann es bei einem verlorenen Telefon nicht zurücksetzen und kann es nicht anordnen.

**Was ein Admin ausdrücklich nicht kann:** fremdes 2FA einrichten. Das Geheimnis darf nur in der
Authenticator-App des Benutzers landen; wüsste es ein Zweiter, wäre der zweite Faktor keiner mehr.

### Umfang

- **Statusspalte** in der Benutzerliste: 2FA aktiv oder nicht.
- **Aktion „2FA zurücksetzen"** mit Rückfrage. Leert `app_authentication_secret` und
  `app_authentication_recovery_codes`. Nur für Admins (`UserPolicy`).
- **Neue Spalte `users.requires_two_factor`** (boolean, Standard `false`), im Benutzerformular als
  Schalter.

### Warum die Pflicht eigene Middleware braucht

Filaments `multiFactorAuthentication(..., isRequired:)` akzeptiert zwar einen Closure, wird aber
beim Registrieren der Routen ausgewertet — `Filament\Pages\Concerns\HasRoutes::getRouteMiddleware()`
entscheidet dort, ob die Middleware überhaupt an die Route gehängt wird. Zu diesem Zeitpunkt gibt
es keinen angemeldeten Benutzer, eine benutzerabhängige Entscheidung ist dort also nicht möglich.

Stattdessen eine eigene Middleware in der `authMiddleware` des Panels: Wer
`requires_two_factor` gesetzt hat und noch kein `app_authentication_secret` besitzt, wird auf die
Einrichtungsseite umgeleitet.

**Fallstrick:** Die Middleware muss die Einrichtungsseite selbst, deren Formular-Requests und den
Logout durchlassen. Sonst leitet sie den Benutzer auf eine Seite um, die sie erneut umleitet — eine
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
| `damage` | Schaden | Orange, in `AdminPanelProvider::panel()` unter `->colors()` neu registriert |

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

- Archivieren eines Fahrzeugs lässt Bild und Dokumente auf der Platte und in der Datenbank
  bestehen (der Fallstrick oben).
- Endgültiges Löschen eines Fahrzeugs entfernt Bild und Dokumente weiterhin.
- Ein Mitarbeiter mit zugeordnetem Fahrzeug lässt sich archivieren; die Zuordnung bleibt.
- Endgültiges Löschen eines zugeordneten Mitarbeiters wird verweigert — gegen MariaDB, damit der
  echte Fremdschlüssel geprüft wird und nicht eine Nachbildung.
- Ein Fahrzeug mit archiviertem Fahrer zeigt ihn weiterhin an und verliert die Zuordnung beim
  Speichern nicht.
- Archivierte erscheinen nicht in Auswahlfeldern, Listen, globaler Suche, Dashboard-Kacheln und
  Kostensumme; mit gesetztem Filter erscheinen sie.
- Wiederherstellen macht den Datensatz wieder sichtbar.

Zu Punkt 2:

- Die Statusspalte zeigt aktiv/inaktiv korrekt.
- Zurücksetzen leert Geheimnis und Recovery-Codes; ein Nicht-Admin kann die Aktion weder sehen
  noch direkt aufrufen.
- Ein Benutzer mit `requires_two_factor` und ohne Geheimnis wird umgeleitet; die
  Einrichtungsseite selbst und der Logout bleiben erreichbar.
- Ein Benutzer ohne die Pflicht wird nicht umgeleitet.

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
