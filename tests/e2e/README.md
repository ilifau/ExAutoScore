# Browser-Tests ExAutoScore (Playwright)

Laufen kopflos (z. B. auf dem Testserver) gegen ilias10 und ilias11.
Die Ergebnisse kommen vom echten Bewertungsdienst; die Attrappe in
`mock/` ist fuer Faelle gedacht, die der echte Dienst nicht gezielt liefert.

```bash
cd <Plugin-Checkout>/tests/e2e
npm ci                    # einmalig: Abhaengigkeiten
npx playwright install --with-deps chromium   # einmalig: Browser
cp .env.example .env      # einmalig, Passwoerter und Ref-IDs eintragen
npm run mock &            # Attrappe des Bewertungsdienstes auf 127.0.0.1:8099
npm test                  # alle Tests, beide Instanzen
npm run test:11           # nur ilias11
npx playwright test --ui-port=9323 --ui-host=0.0.0.0   # UI-Modus im Browser
npm run report            # HTML-Bericht des letzten Laufs
```

Attrappe: in der Plugin-Konfiguration der Testinstanz
`service_assignment_url = http://127.0.0.1:8099/assignment` und
`service_task_url = http://127.0.0.1:8099/task`. Das naechste Ergebnis
steuern die Tests ueber `POST /_control/next` (z. B. `{"points": 7}`).

Neue Tests aufnehmen (auf einem Rechner mit Bildschirm):
`npx playwright codegen http://131.188.192.30/ilias11/`
