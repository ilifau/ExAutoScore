<?php
declare(strict_types=1);

// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Prueft ein Dockerfile darauf, ob es unnoetig Plattenplatz auf dem Korrekturserver kostet.
 *
 * Hintergrund (Messung 07.08.2026): 62 Korrektur-Images belegten 79,8 GB von 99 GB.
 * 21 davon brachten je rund 2,4 GB mit, die sie mit KEINEM anderen Image teilten —
 * obwohl sie fast identisch waren. Ursache war die Reihenfolge im Dockerfile:
 *
 *     ADD  . ./                                  <- 844 kB Aufgabendateien
 *     RUN  pip install torch ...                 <- 1,11 GB
 *     RUN  pip install -r requirements.txt       <-  611 MB
 *
 * Docker bildet fuer jeden Schritt einen Layer, dessen Identitaet vom Ergebnis aller
 * vorherigen Schritte abhaengt. Weil die kopierten Aufgabendateien sich von Aufgabe zu
 * Aufgabe unterscheiden, bekommt ab dem ADD jeder Folgeschritt einen eigenen Layer —
 * PyTorch wird also fuer jede Aufgabe erneut gebaut UND erneut gespeichert. Stuenden
 * die pip-Zeilen ueber dem ADD, teilten sich alle Aufgaben denselben Layer, und aus
 * 21 x 2,4 GB wuerden 2,4 GB plus kleine Deltas.
 *
 * Die Klasse kennt bewusst weder ILIAS noch Docker: sie bekommt den Dateiinhalt als
 * String und gibt Befunde als Datenstruktur zurueck. Das Formulieren und Anzeigen
 * uebernimmt ilExAutoScoreProvidedFilesGUI, das Uebersetzen die Sprachdateien.
 *
 * @see ilExAutoScoreProvidedFilesGUI::showDockerfileHints()
 */
class ilExAutoScoreDockerfileAnalyzer
{
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_HINT = 'hint';

    /**
     * Schritte, die den geteilten Cache-Strang beenden: sie holen Dateien aus dem
     * Build-Kontext, und der ist bei jeder Aufgabe ein anderer.
     */
    protected const BARRIER_INSTRUCTIONS = ['COPY', 'ADD'];

    /**
     * Teure RUN-Schritte. Muster => Kurzname fuer die Meldung. Die Liste muss nicht
     * vollstaendig sein: sie soll die grossen Faelle treffen, ohne bei harmlosen
     * Zeilen (mkdir, chmod, echo) Laerm zu machen.
     */
    protected const EXPENSIVE_PATTERNS = [
        '/\bpip3?\s+install\b/i' => 'pip install',
        '/\bpython3?\s+-m\s+pip\s+install\b/i' => 'pip install',
        '/\bapt(-get)?\s+install\b/i' => 'apt-get install',
        '/\bapk\s+add\b/i' => 'apk add',
        '/\b(dnf|yum)\s+install\b/i' => 'dnf install',
        '/\bnpm\s+(install|ci)\b/i' => 'npm install',
        '/\b(yarn|pnpm)\s+install\b/i' => 'yarn install',
        '/\b(conda|mamba)\s+install\b/i' => 'conda install',
        '/\bgem\s+install\b/i' => 'gem install',
        '/\bcargo\s+install\b/i' => 'cargo install',
        '/\bgo\s+(install|get)\b/i' => 'go install',
        '/\b(mvn|gradle)\s/i' => 'Maven/Gradle',
        '/\bwget\s/i' => 'wget',
        '/\bcurl\s/i' => 'curl',
    ];

    /**
     * Basis-Images, von denen es eine deutlich kleinere Variante gibt. Der Vorschlag
     * bleibt ein Hinweis und keine Warnung: ob die schlanke Variante reicht, haengt an
     * der Aufgabe (kompilierende Tests brauchen oft mehr als eine Laufzeitumgebung).
     */
    protected const SLIMMABLE_BASES = ['python', 'node'];

    /** @var array<int, array{level: string, line: int, key: string, args: array}> */
    protected array $findings = [];

    /** Wurde PIP_NO_CACHE_DIR per ENV global gesetzt? Dann ist --no-cache-dir entbehrlich. */
    protected bool $pipCacheDisabledGlobally = false;

    /**
     * Untersucht den Inhalt eines Dockerfiles.
     *
     * @param string $content roher Dateiinhalt
     * @return array<int, array{level: string, line: int, key: string, args: array}>
     *         Befunde, nach Zeilennummer sortiert. Leeres Array = nichts gefunden.
     */
    public function analyze(string $content): array
    {
        $this->findings = [];
        $this->pipCacheDisabledGlobally = false;

        $instructions = $this->parse($content);

        // Zeile des ersten COPY/ADD der aktuellen Build-Stufe — ab hier kostet jeder
        // teure Schritt Platz pro Aufgabe. null = noch alles im geteilten Bereich.
        $barrierLine = null;
        $barrierKeyword = '';

        foreach ($instructions as $instruction) {
            $keyword = $instruction['keyword'];
            $args = $instruction['args'];
            $line = $instruction['line'];

            if ($keyword === 'ENV' && preg_match('/\bPIP_NO_CACHE_DIR\s*[= ]\s*["\']?(1|true|on|yes)\b/i', $args)) {
                $this->pipCacheDisabledGlobally = true;
                continue;
            }

            if ($keyword === 'FROM') {
                // Neue Build-Stufe: eigener Cache-Strang, die Barriere gilt nicht weiter.
                $barrierLine = null;
                $barrierKeyword = '';
                $this->checkBaseImage($line, $args);
                continue;
            }

            if (in_array($keyword, self::BARRIER_INSTRUCTIONS, true)) {
                // "COPY --from=<stufe>" holt aus einer anderen Build-Stufe statt aus dem
                // aufgabenspezifischen Kontext und bricht den geteilten Cache deshalb nicht.
                if (preg_match('/(^|\s)--from=/i', $args)) {
                    continue;
                }
                if ($barrierLine === null) {
                    $barrierLine = $line;
                    $barrierKeyword = $keyword;
                }
                continue;
            }

            if ($keyword !== 'RUN') {
                continue;
            }

            $expensive = $this->expensiveLabel($args);

            if ($expensive !== null && $barrierLine !== null) {
                $this->add(self::LEVEL_WARNING, $line, 'dockerfile_check_after_copy',
                    [$line, $expensive, $barrierKeyword, $barrierLine]);
            }

            $this->checkPipCache($line, $args);
            $this->checkAptLists($line, $args);
        }

        usort($this->findings, function (array $a, array $b): int {
            // Warnungen zuerst, darin nach Zeile — der Dozent soll oben das lesen,
            // was tatsaechlich Gigabyte kostet.
            if ($a['level'] !== $b['level']) {
                return $a['level'] === self::LEVEL_WARNING ? -1 : 1;
            }
            return $a['line'] <=> $b['line'];
        });

        return $this->findings;
    }

    /**
     * Zerlegt das Dockerfile in logische Anweisungen.
     *
     * Beruecksichtigt Zeilenfortsetzungen mit "\", denn genau dort steht das Teure:
     * ein "RUN pip install \" ueber fuenf Zeilen ist eine Anweisung, und gemeldet
     * werden muss ihre ERSTE Zeile — die sucht der Dozent im Editor.
     *
     * @return array<int, array{keyword: string, args: string, line: int}>
     */
    protected function parse(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);

        $instructions = [];
        $buffer = '';
        $startLine = 0;

        foreach ($lines as $index => $raw) {
            $lineNumber = $index + 1;
            $trimmed = trim($raw);

            if ($buffer === '') {
                // Leerzeilen und Kommentare ausserhalb einer Anweisung ueberspringen.
                if ($trimmed === '' || $trimmed[0] === '#') {
                    continue;
                }
                $startLine = $lineNumber;
            } elseif ($trimmed !== '' && $trimmed[0] === '#') {
                // Kommentar mitten in einer Fortsetzung — Docker ignoriert ihn ebenfalls.
                continue;
            }

            if (substr($trimmed, -1) === '\\') {
                $buffer .= rtrim(substr($trimmed, 0, -1)) . ' ';
                continue;
            }

            $buffer .= $trimmed;
            $this->pushInstruction($instructions, $buffer, $startLine);
            $buffer = '';
        }

        // Datei endet mitten in einer Fortsetzung — trotzdem auswerten.
        if ($buffer !== '') {
            $this->pushInstruction($instructions, $buffer, $startLine);
        }

        return $instructions;
    }

    /**
     * @param array<int, array{keyword: string, args: string, line: int}> $instructions
     */
    protected function pushInstruction(array &$instructions, string $buffer, int $line): void
    {
        if (!preg_match('/^([A-Za-z]+)\s+(.*)$/s', trim($buffer), $matches)) {
            return;
        }
        $instructions[] = [
            'keyword' => strtoupper($matches[1]),
            'args' => trim($matches[2]),
            'line' => $line,
        ];
    }

    /**
     * Kurzname des teuren Schritts, oder null wenn die Zeile harmlos ist.
     */
    protected function expensiveLabel(string $args): ?string
    {
        foreach (self::EXPENSIVE_PATTERNS as $pattern => $label) {
            if (preg_match($pattern, $args)) {
                return $label;
            }
        }
        return null;
    }

    /**
     * pip legt heruntergeladene Pakete unter ~/.cache/pip ab. Ohne --no-cache-dir
     * landet dieser Cache im Layer und verdoppelt bei grossen Paketen beinahe die
     * Groesse — bei PyTorch sind das mehrere hundert MB fuer nichts.
     */
    protected function checkPipCache(int $line, string $args): void
    {
        if (!preg_match('/\bpip3?\s+install\b|\bpython3?\s+-m\s+pip\s+install\b/i', $args)) {
            return;
        }
        if ($this->pipCacheDisabledGlobally) {
            return;
        }
        if (preg_match('/--no-cache-dir\b/i', $args) || preg_match('/\bPIP_NO_CACHE_DIR\b/i', $args)) {
            return;
        }
        $this->add(self::LEVEL_WARNING, $line, 'dockerfile_check_pip_cache', [$line]);
    }

    /**
     * apt-get laedt seine Paketlisten nach /var/lib/apt/lists. Werden sie nicht in
     * DERSELBEN RUN-Zeile geloescht, bleiben sie im Layer liegen (rund 40 MB).
     */
    protected function checkAptLists(int $line, string $args): void
    {
        if (!preg_match('/\bapt(-get)?\s+install\b/i', $args)) {
            return;
        }
        if (preg_match('#rm\s+-rf?\s+[^&|;]*/var/lib/apt/lists#i', $args)) {
            return;
        }
        $this->add(self::LEVEL_HINT, $line, 'dockerfile_check_apt_lists', [$line]);
    }

    /**
     * Weist auf die schlanke Variante gaengiger Basis-Images hin. python:3.12 bringt
     * 1,62 GB mit, python:3.12-slim nur 179 MB.
     */
    protected function checkBaseImage(int $line, string $args): void
    {
        // "FROM <image>[:<tag>] [AS <name>]", Flags wie --platform= ignorieren.
        $args = preg_replace('/(^|\s)--\S+/', ' ', $args) ?? $args;
        if (!preg_match('/^\s*(\S+)/', $args, $matches)) {
            return;
        }
        $reference = $matches[1];

        // Nur einfache Referenzen bewerten; alles mit Registry/Namespace oder Digest
        // (fau.de/…, myorg/python, image@sha256:…) laesst sich so nicht sicher deuten.
        if (strpos($reference, '/') !== false || strpos($reference, '@') !== false) {
            return;
        }

        $parts = explode(':', $reference, 2);
        $image = strtolower($parts[0]);
        $tag = $parts[1] ?? 'latest';

        if (!in_array($image, self::SLIMMABLE_BASES, true)) {
            return;
        }
        if (preg_match('/slim|alpine/i', $tag)) {
            return;
        }

        $this->add(self::LEVEL_HINT, $line, 'dockerfile_check_fat_base',
            [$line, $image . ':' . $tag, $image . ':' . $tag . '-slim']);
    }

    protected function add(string $level, int $line, string $key, array $args): void
    {
        $this->findings[] = [
            'level' => $level,
            'line' => $line,
            'key' => $key,
            'args' => $args,
        ];
    }
}
