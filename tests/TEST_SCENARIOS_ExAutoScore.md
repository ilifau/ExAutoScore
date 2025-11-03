# Test-Szenarien: ExAutoScore Plugin (Einzelnutzer & Team)

**Erstellt:** 2025-11-03
**Feature:** Automatisches Scoring mit Deadline-Schutz und Auto-Publish

---

## Übersicht

Diese Test-Matrix deckt alle kritischen Szenarien für ExAutoScore-Übungseinheiten ab.

**Wichtige Variablen:**
- **Übungs-Deadline:** Kann gesetzt sein oder nicht
- **Persönliche Deadline:** Kann individuell pro User gesetzt sein
- **Benutzer-Rolle:** Student vs. Tutor/Admin
- **Team vs. Einzeluser**
- **Instant Feedback:** Status/Message die sofort sichtbar sind
- **Feedback-Dateien:** Werden erst nach Deadline sichtbar

**Besonderheiten ExAutoScore:**
- ✅ Instant Feedback (immer sofort sichtbar, auch vor Deadline)
- ✅ Bewertung (Points/Mark) nur nach Deadline sichtbar
- ✅ Feedback-Dateien nur nach Deadline sichtbar
- ✅ SCRUB löscht Bewertung UND Feedback-Dateien bei Deadline-Verlängerung
- ✅ Auto-Publish und SCRUB lösen Redirect aus (seit 2025-11-03)

---

## A. Grundlegende Deadline-Szenarien (Einzelnutzer)

### A1: Übungs-Deadline gesetzt, VOR Deadline
**Setup:**
- Übungseinheit: Deadline 15.12.2024 23:59
- Zeit: 10.12.2024 14:00 (VOR Deadline)
- Instant Feedback aktiviert

**Aktionen:**
1. Student reicht Code ein → Bewertung kommt zurück (z.B. 8/10 Punkte)
2. Student öffnet Übungseinheit-Detail

**Erwartetes Verhalten:**
- ✅ instant_status wird sofort angezeigt (z.B. "success")
- ✅ instant_message wird sofort angezeigt
- ❌ Bewertung (Points/Mark) NICHT angezeigt
- ❌ Feedback-Dateien NICHT sichtbar
- ❌ Button "Erweitertes Feedback anzeigen" NICHT sichtbar
- ❌ "Evaluation by Tutor" Sektion NICHT sichtbar
- ✅ Tutor/Admin sieht Bewertung (für Kontrolle)

**Datenbank-Check:**
```sql
SELECT status, mark, notice FROM exc_mem_ass_status WHERE ass_id=X AND usr_id=Y;
-- Erwartung: status='notgraded', mark='', notice=''
```

---

### A2: Übungs-Deadline gesetzt, NACH Deadline (Auto-Publish)
**Setup:**
- Übungseinheit: Deadline 15.12.2024 23:59
- Zeit: 16.12.2024 09:00 (NACH Deadline)
- Student hat bereits am 10.12. eingereicht

**Aktionen:**
1. Student öffnet Übungseinheit-Detail (ERSTER Load nach Deadline)

**Erwartetes Verhalten (1. Load):**
- ✅ Auto-Publish wird ausgelöst
- ✅ **Redirect wird automatisch ausgelöst** (NEU seit 2025-11-03)
- ✅ Nach Redirect: Bewertung ist SOFORT sichtbar (KEIN 2. Reload nötig!)
- ✅ Status = "passed", Mark = "8", Notice = Feedback-Text
- ✅ Feedback-Dateien sind sichtbar
- ✅ Button "Erweitertes Feedback anzeigen" sichtbar
- ✅ "Evaluation by Tutor" Sektion sichtbar

**Datenbank-Check:**
```sql
SELECT status, mark, notice FROM exc_mem_ass_status WHERE ass_id=X AND usr_id=Y;
-- Erwartung: status='passed', mark='8', notice='Feedback-Text'
```

**Technischer Hintergrund:**
- Lines 1255-1305 in ilExAssTypeAutoScoreBaseGUI.php
- `buildSubmissionPropertiesAndActions()` ruft Auto-Publish auf
- Gibt `$did_publish = true` zurück
- Redirect via `redirectByClass()` (Lines 1300-1305)

---

### A3: Keine Deadline gesetzt
**Setup:**
- Übungseinheit: KEINE Deadline
- Zeit: beliebig

**Aktionen:**
1. Student reicht Code ein → 6/10 Punkte
2. Student öffnet Übungseinheit-Detail

**Erwartetes Verhalten:**
- ✅ Bewertung wird SOFORT angezeigt (kein Schutz nötig)
- ✅ Status = "failed", Mark = "6"
- ✅ Feedback-Dateien sofort sichtbar
- ✅ instant_status und instant_message sofort sichtbar

---

### A4: Persönliche Deadline überschreibt allgemeine
**Setup:**
- Übungseinheit: Allgemeine Deadline 15.12.2024 23:59
- Student A: Persönliche Deadline 20.12.2024 23:59 (Verlängerung)
- Eingereicht: 10.12.2024
- Zeit: 16.12.2024 (nach allgemeiner, VOR persönlicher Deadline)

**Aktionen:**
1. Student A öffnet Übungseinheit-Detail

**Erwartetes Verhalten:**
- ✅ instant_status/message sichtbar (waren schon seit Einreichung da)
- ❌ Bewertung NICHT angezeigt (persönliche Deadline noch nicht erreicht)
- ❌ Feedback-Dateien NICHT sichtbar
- ✅ Nach 20.12. → Bewertung und Feedback-Dateien sichtbar

---

## B. Instant Feedback (IMMER sofort sichtbar)

### B1: Instant Status vor Deadline
**Setup:**
- Deadline in 5 Minuten
- instant_status aktiviert

**Aktionen:**
1. Student reicht Code ein

**Erwartetes Verhalten:**
- ✅ instant_status sofort sichtbar (z.B. "success", "failed", "error")
- ✅ Funktioniert VOR Deadline
- ✅ Unabhängig von Bewertungs-Sichtbarkeit

**Code-Referenz:**
- Lines 1308-1325 in ilExAssTypeAutoScoreBaseGUI.php
- Instant-Info wird IMMER in "Einreichung" Sektion angezeigt

---

### B2: Instant Message vor Deadline
**Setup:**
- Deadline in 5 Minuten
- instant_message aktiviert

**Aktionen:**
1. Student reicht Code ein mit Message "Alle Tests bestanden!"

**Erwartetes Verhalten:**
- ✅ instant_message sofort sichtbar
- ✅ Funktioniert VOR Deadline
- ✅ HTML-Formatierung wird korrekt angezeigt

---

### B3: Teams - Instant Feedback für alle Member
**Setup:**
- Team-Übungseinheit mit Deadline
- Team: User A, User B, User C

**Aktionen:**
1. User A reicht für Team ein
2. User B öffnet Übungseinheit
3. User C öffnet Übungseinheit

**Erwartetes Verhalten:**
- ✅ Alle Team-Member sehen instant_status und instant_message sofort
- ❌ Bewertung für KEINEN sichtbar (vor Deadline)
- ✅ Nach Deadline: Alle sehen Bewertung

---

## C. Team-Szenarien

### C1: Team, VOR Deadline
**Setup:**
- Team-Übungseinheit: Deadline 15.12.2024 23:59
- Team: User A, User B, User C
- User A reicht ein: 8/10 Punkte

**Aktionen:**
1. User A öffnet Übungseinheit (VOR Deadline)
2. User B öffnet Übungseinheit (VOR Deadline)

**Erwartetes Verhalten:**
- ✅ User A: instant_status/message sichtbar
- ✅ User B: instant_status/message sichtbar
- ❌ User A: Keine Bewertung sichtbar
- ❌ User B: Keine Bewertung sichtbar
- ✅ Tutor sieht: Team hat 8/10 Punkte

---

### C2: Team, NACH Deadline, alle Member (Auto-Publish)
**Setup:**
- Team: User A, User B, User C (alle gleiche Deadline)
- Team hat eingereicht: 8/10 Punkte
- Zeit: NACH Deadline

**Aktionen:**
1. User A öffnet Detail (erster Load nach Deadline)
2. User B öffnet Detail

**Erwartetes Verhalten:**
- ✅ Auto-Publish wird beim ersten Load ausgelöst
- ✅ **Redirect wird ausgelöst** (NEU seit 2025-11-03)
- ✅ Alle Team-Member sehen: 8/10 Punkte, passed
- ✅ Alle sehen Feedback-Dateien
- ✅ Datenbank: 3 Einträge (User A, B, C) mit gleichen Werten

**Datenbank-Check:**
```sql
SELECT usr_id, status, mark FROM exc_mem_ass_status WHERE ass_id=X AND usr_id IN (A,B,C);
-- Erwartung: 3 Zeilen, alle mit status='passed', mark='8'
```

**Technischer Hintergrund:**
- Lines 1258-1260: `$affected_users = $task->getAffectedUserIds()`
- Lines 1263-1269: Prüft ALLE Team-Member Deadlines
- Lines 1286: `$task->updateMemberStatus($affected_users)` schreibt für ALLE Member

---

### C3: Team mit unterschiedlichen persönlichen Deadlines
**Setup:**
- Team: User A (Deadline 15.12.), User B (Deadline 20.12.)
- Eingereicht: 10.12. → 8/10 Punkte
- Zeit: 16.12. (nach A's Deadline, VOR B's Deadline)

**Aktionen:**
1. User A öffnet Detail (16.12.)
2. User B öffnet Detail (16.12.)

**Erwartetes Verhalten - KRITISCH!**
- ❌ **KEINER sieht Bewertung** (weil nicht ALLE Deadlines erreicht)
- ✅ instant_status/message sichtbar für beide
- ✅ Nach 20.12.: Beide sehen Bewertung

**Technischer Hintergrund:**
- Lines 1263-1269: Loop über alle Team-Member
- `$all_deadlines_reached = true` nur wenn ALLE Member Deadline erreicht
- **Team ist Einheit** - Publishing erst wenn ALLE Deadlines erreicht

---

## D. Deadline-Verlängerung (SCRUB)

### D1: Deadline wird verlängert - SCRUB mit Redirect (NEU 2025-11-03)
**Setup:**
- Ursprüngliche Deadline: 15.12.2024 23:59
- Student hat eingereicht: 10.12. → 8/10 Punkte, Feedback-Dateien vorhanden
- Zeit: 16.12. → Bewertung ist sichtbar, Feedback-Dateien sichtbar
- Admin verlängert Deadline auf: 20.12.2024 23:59

**Aktionen:**
1. Student öffnet Übungseinheit (nach Verlängerung, Zeit: 17.12.)

**Erwartetes Verhalten:**
- ✅ SCRUB wird ausgeführt (prüft: vor neuer Deadline?)
- ✅ **Redirect wird automatisch ausgelöst** (NEU seit 2025-11-03)
- ✅ Nach Redirect: Bewertung verschwindet
- ✅ Nach Redirect: Feedback-Dateien verschwinden
- ✅ Nach Redirect: "Evaluation by Tutor" Sektion verschwindet
- ✅ instant_status/message bleiben sichtbar (werden nicht gelöscht)
- ✅ Nach 20.12. → Bewertung erscheint wieder

**Datenbank-Check:**
```sql
SELECT status, mark, notice FROM exc_mem_ass_status WHERE ass_id=X AND usr_id=Y;
-- Nach SCRUB: status='notgraded', mark='', notice=''
```

**Technischer Hintergrund:**
- Lines 1202-1252 in ilExAssTypeAutoScoreBaseGUI.php
- SCRUB prüft: `$hide = !$this->canShowAssessmentNow($ass, $sub)`
- Wenn vor Deadline UND nicht Tutor: Status löschen
- `$task->deleteFeedbackFiles()` löscht alle Feedback-Dateien
- Lines 1247-1252: Redirect via `redirectByClass()`

**Code-Referenz:**
```php
// Lines 1223-1237: SCRUB-Logik
if ($current_status->getStatus() !== 'notgraded' || !empty($current_status->getMark())) {
    foreach ($affected_user_ids as $uid) {
        $ms = new ilExAssignmentMemberStatus($ass->getId(), $uid);
        $ms->setStatus('notgraded');
        $ms->setMark('');
        $ms->setReturned(false);
        $ms->update();
    }

    if ($task) {
        $task->deleteFeedbackFiles();  // WICHTIG: Feedback-Dateien löschen!
    }

    $did_scrub = true;
}
```

---

### D2: Deadline wird verkürzt - Auto-Publish
**Setup:**
- Ursprüngliche Deadline: 20.12.2024 23:59
- Eingereicht: 10.12. → 8/10 Punkte
- Zeit: 16.12. (VOR Deadline, keine Bewertung sichtbar)
- Admin ändert Deadline auf: 15.12.2024 23:59 (schon vorbei)

**Aktionen:**
1. Student öffnet Übungseinheit (16.12., nach neuer Deadline)

**Erwartetes Verhalten:**
- ✅ Auto-Publish wird ausgelöst (Deadline erreicht)
- ✅ **Redirect wird ausgelöst**
- ✅ Bewertung wird sofort angezeigt
- ✅ Feedback-Dateien werden sichtbar

---

## E. Feedback-Dateien und "Erweitertes Feedback"

### E1: Feedback-Dateien VOR Deadline nicht sichtbar
**Setup:**
- Übungseinheit mit Deadline in 5 Minuten
- Grading-Server erstellt Feedback-Dateien (z.B. `result.txt`, `coverage.html`)

**Aktionen:**
1. Student reicht ein
2. Student öffnet Übungseinheit-Detail (VOR Deadline)

**Erwartetes Verhalten:**
- ✅ instant_status/message sichtbar
- ❌ Feedback-Dateien NICHT sichtbar
- ❌ "Evaluation by Tutor" Sektion NICHT sichtbar
- ❌ Button "Erweitertes Feedback anzeigen" NICHT sichtbar

**Technischer Hintergrund:**
- Lines 1167-1197 in ilExAssTypeAutoScoreBaseGUI.php
- `canShowAssessmentNow()` prüft Deadline
- Nur wenn `true`: Feedback-Button wird angezeigt

---

### E2: Feedback-Dateien NACH Deadline sichtbar
**Setup:**
- Übungseinheit mit Deadline
- Zeit: NACH Deadline
- Feedback-Dateien vorhanden

**Aktionen:**
1. Student öffnet Übungseinheit-Detail

**Erwartetes Verhalten:**
- ✅ "Evaluation by Tutor" Sektion erscheint
- ✅ Button "Erweitertes Feedback anzeigen" sichtbar
- ✅ Klick öffnet Modal mit Feedback-Dateien
- ✅ Feedback-Dateien können heruntergeladen werden

---

### E3: Erweitertes Feedback Modal
**Setup:**
- Nach Deadline, Feedback vorhanden

**Aktionen:**
1. Student klickt "Erweitertes Feedback anzeigen"

**Erwartetes Verhalten:**
- ✅ Modal öffnet sich
- ✅ Zeigt protected_feedback_text (falls vorhanden)
- ✅ Zeigt protected_feedback_html (falls vorhanden)
- ✅ Listet alle Feedback-Dateien mit Download-Links
- ✅ Modal kann geschlossen werden

**Code-Referenz:**
- Lines 1327-1440 in ilExAssTypeAutoScoreBaseGUI.php
- `showExtendedFeedback()` Methode

---

### E4: Team - Feedback für alle Member
**Setup:**
- Team-Übungseinheit, nach Deadline
- Feedback-Dateien vorhanden

**Aktionen:**
1. Jedes Team-Mitglied öffnet Übungseinheit

**Erwartetes Verhalten:**
- ✅ Alle Member sehen Button "Erweitertes Feedback anzeigen"
- ✅ Alle können Feedback-Dateien herunterladen
- ✅ Feedback-Dateien sind identisch für alle Member

---

## F. Submission-Verwaltung

### F1: Submission löschen (Einzeluser)
**Setup:**
- Student hat eingereicht
- Bewertung vorhanden

**Aktionen:**
1. Student löscht Submission

**Erwartetes Verhalten:**
- ✅ Task wird gelöscht
- ✅ Status zurückgesetzt auf "notgraded"
- ✅ Feedback-Dateien werden gelöscht
- ✅ instant_status/message verschwinden

---

### F2: Submission löschen (Team)
**Setup:**
- Team hat eingereicht
- Bewertung vorhanden für alle Member

**Aktionen:**
1. Team-Mitglied löscht Submission

**Erwartetes Verhalten:**
- ✅ Task gelöscht
- ✅ Status für ALLE Mitglieder zurückgesetzt
- ✅ Feedback-Dateien gelöscht

---

### F3: Erneute Submission nach Löschung
**Setup:**
- Eingereicht, gelöscht

**Aktionen:**
1. Erneut einreichen

**Erwartetes Verhalten:**
- ✅ Neue Submission wird korrekt verarbeitet
- ✅ Neue Bewertung wird gespeichert
- ✅ Alte Bewertung komplett überschrieben

---

### F4: Auto-Publish prüft Submission-Existenz (NEU 2025-11-03)
**Setup:**
- Übungseinheit mit Deadline
- Student reicht ein, bekommt Bewertung
- Student löscht Submission NACH Deadline

**Aktionen:**
1. Student öffnet Übungseinheit nach Submission-Löschung

**Erwartetes Verhalten:**
- ❌ Auto-Publish läuft NICHT (keine Submission vorhanden)
- ✅ Status bleibt "notgraded"
- ✅ Keine Bewertung sichtbar

**Technischer Hintergrund:**
- Line 1278: `$still_has_submission = (count($sub->getFiles()) > 0) || ($task->getSubmitSuccess() === true)`
- Verhindert Publishing wenn Submission gelöscht wurde

---

## G. Tutor/Admin-Sicht

### G1: Tutor öffnet Detail VOR Deadline
**Setup:**
- Deadline: 15.12.2024 23:59
- Zeit: 10.12.2024
- Student hat eingereicht: 8/10 Punkte

**Aktionen:**
1. Tutor öffnet Übungseinheit-Detail des Students

**Erwartetes Verhalten:**
- ✅ Tutor sieht Bewertung (für Kontrolle)
- ✅ Tutor sieht Feedback-Dateien
- ✅ Status, Mark, Notice angezeigt
- ✅ SCRUB löscht NICHT (weil Tutor)

**Technischer Hintergrund:**
- Line 1206: `$is_tutor = $this->plugin->canDefine()`
- Line 1209: `if ($hide && !$is_tutor)` - Tutor ist ausgenommen

---

### G2: Admin-Funktion "Neu bewerten"
**Setup:**
- Student hat eingereicht
- Bewertung vorhanden

**Aktionen:**
1. Admin klickt "Neu bewerten" Button

**Erwartetes Verhalten:**
- ✅ Task wird an Grading-Server gesendet
- ✅ Neue Bewertung kommt zurück
- ✅ Status wird aktualisiert
- ✅ Unabhängig von Deadline

---

## H. Edge Cases

### H1: Deadline genau beim Einreichen
**Setup:**
- Deadline in 1 Sekunde

**Aktionen:**
1. Genau bei Ablauf einreichen

**Erwartetes Verhalten:**
- ✅ Entweder sofort Bewertung ODER nach Reload
- ✅ Kein Crash, graceful handling

---

### H2: Kein Grading-Server verfügbar
**Setup:**
- Grading-Server ist offline

**Aktionen:**
1. Student reicht ein

**Erwartetes Verhalten:**
- ✅ Submission wird akzeptiert
- ❌ Keine Bewertung (timeout)
- ✅ Error-Handling, kein Crash
- ✅ Admin kann später manuell "Neu bewerten"

---

### H3: Sehr großes Team (>20 Mitglieder)
**Setup:**
- Team mit 25 Mitgliedern

**Aktionen:**
1. Team reicht ein
2. Deadline läuft ab

**Erwartetes Verhalten:**
- ✅ Auto-Publish schreibt 25 DB-Einträge
- ✅ Performance akzeptabel
- ✅ Alle Member bekommen Bewertung

---

### H4: Submission ohne Bewertung (submit_success = false)
**Setup:**
- Student reicht ein
- Grading-Server antwortet mit submit_success = false

**Aktionen:**
1. Student öffnet Übungseinheit nach Deadline

**Erwartetes Verhalten:**
- ❌ Auto-Publish läuft NICHT (kein publishable result)
- ✅ Status bleibt "notgraded"

**Technischer Hintergrund:**
- Lines 1272-1276: Prüft ob publishable result vorhanden
- `$has_publishable_result` muss true sein

---

## I. Redirect-Verhalten (NEU 2025-11-03)

### I1: Auto-Publish löst Redirect aus
**Setup:**
- Übungseinheit mit Deadline in 2 Minuten
- Student reicht ein

**Aktionen:**
1. Student öffnet Übungseinheit VOR Deadline (instant_status sichtbar)
2. 2+ Minuten warten
3. Student öffnet Übungseinheit NACH Deadline

**Erwartetes Verhalten:**
- ✅ Auto-Publish wird ausgelöst
- ✅ **Redirect wird automatisch ausgelöst**
- ✅ Nach Redirect: Bewertung ist sofort sichtbar (KEIN manueller Reload!)
- ✅ Nach Redirect: Feedback-Dateien sichtbar
- ✅ Status = "passed", Mark = "8"

**Technischer Hintergrund:**
- Lines 1255-1305 in ilExAssTypeAutoScoreBaseGUI.php
- `buildSubmissionPropertiesAndActions()` prüft Deadline
- Gibt `$did_publish = true` zurück wenn publiziert
- Lines 1300-1305: Redirect via `redirectByClass()`

**Code-Referenz:**
```php
if ($did_publish) {
    $current_class = $DIC->ctrl()->getCmdClass();
    $DIC->ctrl()->redirectByClass($current_class, $DIC->ctrl()->getCmd());
}
```

---

### I2: SCRUB löst Redirect aus
**Setup:**
- Deadline am 15.12., Student hat eingereicht
- Zeit: 16.12. → Bewertung sichtbar
- Admin verlängert Deadline auf 20.12.

**Aktionen:**
1. Student öffnet Übungseinheit (17.12.)

**Erwartetes Verhalten:**
- ✅ SCRUB wird ausgelöst (vor neuer Deadline)
- ✅ **Redirect wird automatisch ausgelöst**
- ✅ Nach Redirect: Bewertung verschwindet
- ✅ Nach Redirect: Feedback-Dateien verschwinden
- ✅ Status = "notgraded"

**Technischer Hintergrund:**
- Lines 1202-1252 in ilExAssTypeAutoScoreBaseGUI.php
- SCRUB prüft `$current_status->getStatus() !== 'notgraded'`
- Gibt `$did_scrub = true` zurück
- Lines 1247-1252: Redirect via `redirectByClass()`

---

### I3: Kein Redirect wenn keine Änderung
**Setup:**
- Übungseinheit NACH Deadline
- Student hat bereits veröffentlichte Bewertung

**Aktionen:**
1. Student öffnet Übungseinheit mehrfach

**Erwartetes Verhalten:**
- ❌ KEIN Redirect (Bewertung bereits publiziert)
- ✅ Seite lädt normal
- ✅ Performance gut (kein unnötiger Redirect)

**Technischer Hintergrund:**
- Line 1285: Prüft `$current_status->getStatus() === 'notgraded' || empty($current_status->getMark())`
- Nur wenn Status noch nicht gesetzt: Auto-Publish
- Verhindert Redirect bei jedem Seitenaufruf

---

### I4: Redirect verwendet ilCtrl-API korrekt
**Setup:**
- Beliebige Übungseinheit

**Aktionen:**
1. Auto-Publish oder SCRUB wird ausgelöst

**Erwartetes Verhalten:**
- ✅ Redirect verwendet `getCmdClass()` und `redirectByClass()`
- ✅ NICHT `$_SERVER['REQUEST_URI']` (Sicherheit!)
- ✅ Redirect zur aktuellen Controller-Klasse
- ✅ Kommando wird beibehalten via `getCmd()`

**Code-Referenz:**
```php
$current_class = $DIC->ctrl()->getCmdClass();
$DIC->ctrl()->redirectByClass($current_class, $DIC->ctrl()->getCmd());
```

---

## Zusammenfassung: Kritische Testfälle

**Must-Test (vor Deployment):**
1. ✅ A1: VOR Deadline → instant_status/message JA, Bewertung NEIN
2. ✅ A2: NACH Deadline → Bewertung SOFORT (1. Load, mit Redirect)
3. ✅ B1/B2: Instant Feedback funktioniert immer
4. ✅ C2: Team → alle Member sehen Bewertung
5. ✅ C3: Team mit unterschiedlichen Deadlines → KEINE Bewertung bis alle Deadlines erreicht
6. ✅ D1: Deadline-Verlängerung → Bewertung + Feedback-Dateien verschwinden (mit Redirect)
7. ✅ E1: Feedback-Dateien VOR Deadline nicht sichtbar
8. ✅ G1: Tutor → immer sichtbar
9. ✅ **I1: Auto-Publish löst Redirect aus (NEU)**
10. ✅ **I2: SCRUB löst Redirect aus (NEU)**

**Nice-to-Test:**
11. A4: Persönliche Deadline
12. E3: Erweitertes Feedback Modal
13. F4: Auto-Publish prüft Submission-Existenz
14. H2: Grading-Server offline

---

## Test-Checkliste (für manuelle Tests)

```
□ A1: Einzelnutzer VOR Deadline → instant JA, Bewertung NEIN
□ A2: Einzelnutzer NACH Deadline → sofort sichtbar (KEIN 2. Reload!)
□ A3: Keine Deadline → sofort sichtbar
□ A4: Persönliche Deadline überschreibt allgemeine
□ B1: Instant Status funktioniert vor Deadline
□ B2: Instant Message funktioniert vor Deadline
□ B3: Team - Instant Feedback für alle Member
□ C1: Team VOR Deadline → instant JA, Bewertung NEIN
□ C2: Team NACH Deadline → alle sehen Bewertung
□ C3: Team mit unterschiedlichen Deadlines (kritisch!)
□ D1: Deadline-Verlängerung → Bewertung + Feedback verschwinden
□ D2: Deadline-Verkürzung → Bewertung erscheint
□ E1: Feedback-Dateien VOR Deadline nicht sichtbar
□ E2: Feedback-Dateien NACH Deadline sichtbar
□ E3: Erweitertes Feedback Modal funktioniert
□ E4: Team - Feedback für alle Member
□ F1: Submission löschen (Einzeluser)
□ F2: Submission löschen (Team)
□ F4: Auto-Publish prüft Submission-Existenz
□ G1: Tutor VOR Deadline → sieht Bewertung
□ G2: Admin "Neu bewerten" funktioniert
□ H2: Grading-Server offline → graceful handling
□ H3: Sehr großes Team → Performance ok
□ I1: Auto-Publish löst Redirect aus
□ I2: SCRUB löst Redirect aus
□ I3: Kein Redirect wenn Bewertung bereits publiziert
□ I4: Redirect verwendet ilCtrl-API korrekt
```

---

## Performance-Tests

### P1: Große Anzahl Teilnehmer
**Setup:**
- 200 Studenten
- Alle haben eingereicht
- Deadline erreicht

**Aktionen:**
1. Alle 200 Studenten öffnen gleichzeitig Übungseinheit

**Erwartetes Verhalten:**
- ✅ Auto-Publish läuft 200x (jeweils nur für einen User)
- ✅ Keine Race Conditions
- ✅ Performance akzeptabel (< 2s pro User)

---

### P2: Große Teams
**Setup:**
- Team mit 50 Mitgliedern
- Eingereicht, Deadline erreicht

**Erwartetes Verhalten:**
- ✅ updateMemberStatus() schreibt 50 DB-Einträge
- ✅ Performance akzeptabel (< 5s)

---

## Vergleich TestResult vs. ExAutoScore

| Feature | TestResult | ExAutoScore |
|---------|-----------|-------------|
| **Instant Feedback** | ❌ Nein | ✅ Ja (instant_status/message) |
| **Feedback-Dateien** | ❌ Nein | ✅ Ja (nach Deadline) |
| **SCRUB Redirect** | ❌ Nein | ✅ Ja (seit 2025-11-03) |
| **Auto-Publish Redirect** | ✅ Ja | ✅ Ja (seit 2025-11-03) |
| **Feedback löschen bei SCRUB** | - | ✅ Ja via `deleteFeedbackFiles()` |
| **Submission-Existenz-Prüfung** | ❌ Nein | ✅ Ja (Line 1278) |
| **Erweitertes Feedback Modal** | ❌ Nein | ✅ Ja |

---

## Automatisierte Tests

**Existierende Smoke Tests:**
- ✅ `tests/smoke/BasicFunctionalityTest.php` (23 Tests)
- ✅ `tests/smoke/TaskMethodsTest.php` (6 Tests)

**Neue Tests (TODO):**
```php
// tests/smoke/RedirectBehaviorTest.php

class RedirectBehaviorTest extends PHPUnit\Framework\TestCase {
    public function testAutoPublishRedirectCodeExists() { }
    public function testScrubRedirectCodeExists() { }
    public function testDeleteFeedbackFilesMethodExists() { }
}
```

---

**Letzte Aktualisierung:** 2025-11-03
**Wichtige Änderungen:**
- ✅ Redirect-Verhalten für Auto-Publish hinzugefügt (I1)
- ✅ Redirect-Verhalten für SCRUB hinzugefügt (I2)
- ✅ Submission-Existenz-Prüfung dokumentiert (F4)
- ✅ Vergleichstabelle TestResult vs. ExAutoScore hinzugefügt
