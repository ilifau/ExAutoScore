# Browser-Tests ExAutoScore (Playwright)

Laufen kopflos (z. B. auf dem Testserver) gegen ilias11 (die Firma springt von StudOn 9 direkt auf 11).
Die Ergebnisse kommen vom echten Bewertungsdienst (Testaufgabe in
`fixtures/sample-task/`, Laufzeit etwa 2 Minuten); die Attrappe in `mock/`
ist fuer Faelle gedacht, die der echte Dienst nicht gezielt liefert.

Voraussetzungen in der ILIAS-Instanz: vier Testkonten und ein Kurs mit Uebung
(siehe `.env.example`), die Kursadmin-Rolle als Erzeuger-Rolle in der
Plugin-Konfiguration, `py` als erlaubte Dateiendung (Administration →
Datei-Services) kein Passwortwechsel bei der ersten Anmeldung und fuer `single-teams.spec.ts`
der StudOn-Core-Patch `exTeamSingles` samt seiner Sprachtexte (lokal installiert).

```bash
cd <Plugin-Checkout>/tests/e2e
npm ci                    # einmalig: Abhaengigkeiten
npx playwright install --with-deps chromium   # einmalig: Browser
cp .env.example .env      # einmalig, Passwoerter und Ref-IDs eintragen
npm run mock &            # Attrappe des Bewertungsdienstes auf 127.0.0.1:8099
npm test                  # alle Tests
npm run test:11           # nur ilias11
npx playwright test --ui --ui-host=127.0.0.1 --ui-port=9323   # UI-Modus, nur lokal (kein Passwort!), per SSH-Tunnel öffnen
npm run report            # HTML-Bericht des letzten Laufs
```

Attrappe: in der Plugin-Konfiguration der Testinstanz
`service_assignment_url = http://127.0.0.1:8099/assignment` und
`service_task_url = http://127.0.0.1:8099/task`. Das naechste Ergebnis
steuern die Tests ueber `POST /_control/next` (z. B. `{"points": 7}`).

Neue Tests aufnehmen (auf einem Rechner mit Bildschirm):
`npx playwright codegen http://131.188.192.30/ilias11/`
