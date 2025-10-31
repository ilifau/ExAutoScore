# ExAutoScore Tests

Dieses Verzeichnis enthält automatisierte Tests für das ExAutoScore Plugin.

## Test-Typen

### Smoke Tests (`tests/smoke/`)
Schnelle Tests die grundlegende Funktionalität prüfen:
- Klassen können geladen werden
- Methoden existieren
- Basis-Logik funktioniert ohne DB-Zugriff

**Laufzeit:** <30 Sekunden
**Wann ausführen:** Nach jedem Code-Change, vor jedem Commit

### Unit Tests (`tests/unit/`)
Tests für spezifische Business-Logik (noch nicht implementiert)

### Integration Tests (`tests/integration/`)
End-to-End Tests mit ILIAS Integration (noch nicht implementiert)

## Test-Ausführung

### Voraussetzungen
```bash
composer require --dev phpunit/phpunit
```

### Smoke Tests ausführen
```bash
cd /var/www/StudOn/Customizing/global/plugins/Modules/Exercise/AssignmentHook/ExAutoScore
./vendor/bin/phpunit tests/smoke
```

### Alle Tests ausführen
```bash
./vendor/bin/phpunit tests/
```

### Einzelnen Test ausführen
```bash
./vendor/bin/phpunit tests/smoke/BasicFunctionalityTest.php
```

### Mit detailliertem Output
```bash
./vendor/bin/phpunit --testdox tests/smoke
```

## Test-Entwicklung

### Neue Smoke Tests hinzufügen
1. Datei in `tests/smoke/` erstellen
2. Von `PHPUnit\Framework\TestCase` erben
3. Methoden mit `test` Prefix schreiben
4. Keine DB-Zugriffe (nur Mocks)

### Best Practices
- **Smoke Tests:** Schnell, keine externen Abhängigkeiten
- **Unit Tests:** Isoliert, mit Mocks für ILIAS Core
- **Integration Tests:** Mit echter DB, langsamer

## Manuelle Tests

Für manuelle Tests siehe: `../ki_infos/05_testing_guide.md`

Diese enthalten kritische User-Flow Tests die nicht automatisiert werden können.

## CI/CD Integration

Diese Tests können in CI/CD Pipeline integriert werden:
```bash
# In GitLab CI oder GitHub Actions:
./vendor/bin/phpunit tests/smoke --log-junit test-results.xml
```

## Probleme?

Bei Problemen mit Tests:
1. Prüfe dass alle Klassen korrekt eingebunden sind
2. Prüfe PHPUnit Version: `./vendor/bin/phpunit --version`
3. Schaue in Logs: `--debug` Flag verwenden
