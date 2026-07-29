# Hinweise für die Arbeit an diesem Projekt

## Die Entwicklungsdatenbank enthält echte Arbeitsdaten

Die DDEV-Datenbank `db` ist **kein** Wegwerf-Zustand. Dort liegen die Benutzerkonten und der
erfasste Fuhrpark.

**Niemals ausführen:** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `db:seed`.

Das ist schon einmal passiert: Ein `migrate:fresh` — gedacht als Prüfung, ob eine neue Migration
von Null durchläuft — hat die Benutzerkonten gelöscht, und der Zugang zur Anwendung war weg.

`ddev artisan migrate` (nur vorwärts) ist unbedenklich.

**Wenn du die Migrationskette von Null prüfen willst:** Die Testsuite tut das bei jedem Lauf.
Sie arbeitet auf der eigenen Datenbank `test` und baut sie selbst auf:

    ddev exec vendor/bin/pest

## Alle Befehle laufen über DDEV

PHP und Composer sind auf dem Host nicht installiert. Also `ddev artisan …`, `ddev composer …`,
`ddev exec vendor/bin/pest`. Meldet `ddev` „unknown command", ist das Arbeitsverzeichnis falsch.

## Sprache

Code, Klassennamen, Spaltennamen und Commit-Messages englisch. Oberflächentexte deutsch —
Labels immer explizit setzen (`->label('Kennzeichen')`), sonst leitet Filament englische
Feldnamen ab.

## Was beim Ändern leicht übersehen wird

- **Kostendaten** sind an das Gate `viewCosts` gebunden, unabhängig von der Rolle. Der Schutz im
  Fahrzeugformular wirkt über die versteckte `Section` — sie verwirft beim Speichern die Werte
  aller ihrer Felder. Wer die Kostenfelder aus dieser Section herausholt, hebt den Schutz auf.
- **Leasingdaten sind optional.** Jede neue Stelle, die `lease_start` oder `lease_end` liest,
  muss `null` vertragen.
- **Die Tankkartennummer** ist in der Datenbank verschlüsselt und deshalb nicht durchsuchbar.
  Kein `->searchable()` auf dieser Spalte — die Suche liefe gegen das Chiffrat und fände nichts.
- **Das Warnfenster** ist `Vehicle::LEASE_WARNING_DAYS`, die Dringlichkeitsschwelle
  `Vehicle::LEASE_URGENT_DAYS`, die Farbe kommt aus `Vehicle::leaseUrgencyColor()`. Diese Werte
  nicht erneut hinschreiben.
- **`->searchable()` auf einer Relations-Spalte** darf keinen Tabellennamen als Präfix tragen.
  Filament deutet den Punkt sonst als JSON-Pfad und erzeugt ungültiges SQL.

## Dokumentation

Fachliche Entscheidungen stehen in `docs/superpowers/specs/`, der Umsetzungsweg in
`docs/superpowers/plans/`. Betrieb und Einrichtung: `README.md`.
