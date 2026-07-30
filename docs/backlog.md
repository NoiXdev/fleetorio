# Backlog

Ideen, die besprochen aber zurückgestellt wurden. Sortiert nach dem, was echtes Geld oder
Risiko betrifft — nicht nach Aufwand.

Was hier steht, ist bewusst nicht gebaut. Die Anwendung ist ohne diese Punkte vollständig
benutzbar.

---

## Fahrer-Zugänge mit Einreichungen

**Warum:** Wer ein Fahrzeug fährt, merkt einen Schaden oder einen Werkstattbesuch zuerst. Bisher
muss er es dem Verwalter sagen, der es dann einträgt. Fahrer sollen selbst melden können, und der
Verwalter prüft, übernimmt, bearbeitet oder lehnt ab.

**Was dazugehört:**

- Fahrer melden Ereignisse zu **ihrem** Fahrzeug: Schäden, Werkstattbesuche, Service — also das,
  was heute nur ein Verwalter als Termin einträgt.
- Eingereichte Meldungen sind zunächst Vorschläge, keine Termine. Der Verwalter sieht eine Liste
  offener Einreichungen und entscheidet je Eintrag.
- Fahrer sehen ausschließlich die Historie ihres eigenen Fahrzeugs.

**Vier Entscheidungen, die die Umsetzung völlig verschieden ausfallen lassen** — vor dem Bauen
klären:

1. **Mitarbeiter und Benutzerkonten sind in dieser Anwendung bewusst getrennt** (siehe die
   Begründung in der Spezifikation: ein Mitarbeiter mit Dienstwagen brauchte keinen Zugang, ein
   Buchhaltungskonto fährt kein Auto). Für Fahrer-Zugänge müssen beide zusammenfinden — als
   Verknüpfung (`employees.user_id`) oder durch Zusammenlegen. Die Verknüpfung ist der kleinere
   Eingriff und erhält die Trennung für alle, die keinen Zugang brauchen.
2. **Eigener Bereich oder dasselbe Panel mit eingeschränkter Sicht?** Ein Fahrer, der nur Schäden
   meldet, braucht keine Navigation mit sechs Menüpunkten. Ein zweites, sehr einfaches Panel ist
   mehr Arbeit, aber für die Zielgruppe deutlich besser. Filament kann mehrere Panels.
3. **Was genau darf eingereicht werden?** Nur Termine und Schäden, oder auch Kilometerstände und
   Dateien (Foto vom Schaden)? Dateien bedeuten Upload-Rechte für Fahrer — dann gilt für sie
   dasselbe wie für Dokumente: privater Speicher, autorisierte Auslieferung.
4. **Wie kommen Fahrer an ihren Zugang?** Der Verwalter legt Passwörter an, oder es gibt eine
   Einladung per E-Mail. Letzteres ist angenehmer, setzt aber echten Mailversand voraus (siehe
   `README.md`, Abschnitt zum Betrieb).

**Der Fallstrick, an dem so etwas scheitert:** „Fahrer sieht nur sein Fahrzeug" ist eine
Autorisierungsanforderung, keine Anzeigefrage. Es genügt **nicht**, die Liste zu filtern — sonst
erreicht ein Fahrer ein fremdes Fahrzeug, indem er die Adresse abändert. Die Prüfung muss in der
Policy sitzen und für jeden Zugriff gelten, auch für Dokumente, Bilder und Termine des Fahrzeugs.
Diese Anwendung hat für genau dieses Muster schon Vorbilder: die Kostensichtbarkeit und den
Dokument-Download. Und sie braucht Tests, die den direkten Zugriff prüfen, nicht nur die
Sichtbarkeit.

**Zweiter Fallstrick:** Ein Fahrer wechselt das Fahrzeug. Dann darf er die Historie seines alten
Fahrzeugs vermutlich nicht mehr sehen — oder doch, für den Zeitraum, in dem er es gefahren hat?
Das hängt an der zurückgestellten Fahrer-Historie weiter unten und sollte zusammen entschieden
werden.

---

## Kilometerstände und Leasing-Limit

**Warum:** Leasingverträge haben Kilometerbegrenzungen, und Überschreitung kostet pro Kilometer.
Ohne Nachverfolgung sieht man das erst bei der Rückgabe, wenn es zu spät ist.

**Was dazugehört:**

- Neue Spalte am Fahrzeug: `lease_mileage_limit` (vertragliches Limit) und ein Preis pro
  Mehrkilometer für die Geldaussage.
- Eine Liste von Kilometerständen am Fahrzeug (Datum, Stand) — als Relation Manager, wie
  Termine und Dokumente.
- Hochrechnung: gefahrene Strecke gegen verstrichene Vertragslaufzeit, daraus die erwartete
  Gesamtlaufleistung, verglichen mit dem Limit. Warnung bei absehbarer Überschreitung, samt
  geschätzter Kosten.
- Dashboard-Widget für Fahrzeuge, die aus dem Ruder laufen.

**Vorarbeit, die schon existiert:** Die Termine erfassen bereits einen Kilometerstand
(`vehicle_appointments.mileage`). Ob man daraus die Werte zieht oder eine eigene Liste anlegt,
ist die erste Entscheidung. Eine eigene Liste ist sauberer, weil man Stände auch ohne
Werkstattbesuch erfassen will.

**Fallstricke:** Die Hochrechnung braucht `lease_start` und `lease_end` — beide sind seit einer
späteren Änderung optional. Sie darf also fehlen dürfen, ohne abzustürzen. Und die Euro-Aussage
ist eine Kostenangabe und gehört hinter das Recht `viewCosts`; die Kilometer selbst nicht.

---

## Führerscheinkontrolle

**Warum:** Als Halter ist die Firma verpflichtet, regelmäßig zu prüfen, ob die Fahrer einen
gültigen Führerschein haben. Bei Verstoß haftet der Halter mit. Viele Betriebe machen das auf
Zettel oder gar nicht.

**Was dazugehört:** Ein Datum „letzte Kontrolle" am Mitarbeiter, ein Intervall, und eine
Erinnerung wie bei Leasingende und HU. Kleiner Aufwand, deckt ein echtes Haftungsrisiko.

---

## E-Mail-Erinnerungen

**Warum:** Die Warnungen für Leasingende und HU stehen auf dem Dashboard — das sieht nur, wer
sich anmeldet. Schaut zwei Wochen niemand hinein, verpasst man die Frist trotzdem.

**Was dazugehört:** Eine wöchentliche Zusammenfassung per Mail an die Fuhrparkverantwortlichen.

**Der Preis:** Dafür braucht der Server erstmals einen Cronjob. Bisher läuft die Anwendung
bewusst ohne Hintergrundprozesse — kein Cron, kein Queue-Worker — was das Hosting deutlich
einfacher macht (siehe `README.md`, Abschnitt zum Docker-Image). Diese Einfachheit gibt man
damit auf.

---

## Terminkosten in die Kostenauswertung

**Warum:** Die Kostenseite zeigt Leasingrate, Steuer und Versicherung. Werkstatt- und
Servicekosten werden an den Terminen erfasst, laufen dort aber nicht ein. Die Jahreskosten sind
damit unvollständig.

**Was dazugehört:** Die Terminkosten je Fahrzeug summieren und in die Jahreskosten aufnehmen —
entweder als eigene Spalte oder eingerechnet. Die Formel liegt an zwei Stellen
(`Vehicle::annual_total_cost` als Accessor und `Vehicle::sumAnnualCost()` als SQL-Aggregat);
beide müssen zusammen geändert werden, sonst laufen Zeilenwert und Summenzeile auseinander.

---

## Aus der ursprünglichen Spezifikation zurückgestellt

Diese Punkte stehen in `docs/superpowers/specs/2026-07-28-fuhrparkmanagement-design.md` unter
„Nicht in Version 1" und waren von Anfang an bewusst ausgeklammert:

- **Audit-Log** — wer hat wann was geändert
- **Fahrer-Historie mit Zeiträumen** — die Anwendung kennt nur den aktuellen Fahrer. Relevant
  bei Bußgeldern oder Schäden, wenn man wissen muss, wer damals gefahren ist.
- **Kraftstoffkosten via DKV oder Elli** — Abrechnungsdaten je Fahrzeug einlesen
- **Kostenpositionen als eigene Tabelle** statt Felder am Fahrzeug
- **CSV-Import von Bestandsdaten**

---

## Kleinere Beobachtungen aus den Reviews

- **Reifen** (nie beauftragt, nur als Idee genannt): Sommer/Winter, Wechseltermine,
  Einlagerungsort. Passt konzeptionell zu den Terminen.
- **Schäden und Unfälle** dokumentieren — passt ebenfalls zu den Terminen.
- `FuelCardResource` hat kein `$recordTitleAttribute`. Fällt nur auf, wenn man die globale
  Suche aktiviert oder eine Tankkarte in einer Beziehungsauswahl anzeigen lässt.
- Die Stammdatenlisten zeigen bewusst nicht alle Felder — die vollständigen Angaben stehen auf
  den Detailseiten. Falls sich das im Alltag als unpraktisch erweist, wäre die Alternative,
  einzelne Spalten nachzuziehen.
