<?php
declare(strict_types=1);

// Copyright (c) 2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Checks a Dockerfile for patterns that waste disk space on the grading server.
 *
 * Copying task files before expensive RUN steps gives every assignment its own copy of
 * those layers (e.g. ~2.4 GB of PyTorch each) instead of one shared cached layer.
 *
 * Deliberately knows neither ILIAS nor Docker: takes the file content as a string and
 * returns findings as data; ilExAutoScoreProvidedFilesGUI formats and displays them.
 *
 * @see ilExAutoScoreProvidedFilesGUI::showDockerfileHints()
 */
class ilExAutoScoreDockerfileAnalyzer
{
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_HINT = 'hint';

    /**
     * Instructions that end the shared cache chain: they take files from the build
     * context, which differs for every assignment.
     */
    protected const BARRIER_INSTRUCTIONS = ['COPY', 'ADD'];

    /**
     * Files whose copying is NOT a barrier: copying a dependency list before installing
     * it is best practice and keeps the expensive install cached.
     */
    protected const DEPENDENCY_MANIFESTS = [
        'requirements.txt', 'requirements-dev.txt', 'constraints.txt',
        'pyproject.toml', 'poetry.lock', 'pipfile', 'pipfile.lock', 'setup.py', 'setup.cfg',
        'environment.yml', 'environment.yaml', 'conda.yaml',
        'package.json', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml',
        'go.mod', 'go.sum', 'gemfile', 'gemfile.lock',
        'pom.xml', 'build.gradle', 'build.gradle.kts', 'cargo.toml', 'cargo.lock',
    ];

    /**
     * Expensive RUN steps, pattern => short label for the message. Not exhaustive; meant
     * to catch the big cases without flagging harmless lines (mkdir, chmod, echo).
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
     * Base images with a much smaller variant. Only a hint: whether the slim variant
     * suffices depends on the assignment.
     */
    protected const SLIMMABLE_BASES = ['python', 'node'];

    /** @var array<int, array{level: string, line: int, key: string, args: array}> */
    protected array $findings = [];

    /** Whether PIP_NO_CACHE_DIR was enabled via ENV; makes --no-cache-dir unnecessary. */
    protected bool $pipCacheDisabledGlobally = false;

    /**
     * Analyzes the content of a Dockerfile.
     *
     * @param string $content raw file content
     * @return array<int, array{level: string, line: int, key: string, args: array}>
     *         Findings, warnings first, then by line; empty array = nothing found.
     */
    public function analyze(string $content): array
    {
        $this->findings = [];
        $this->pipCacheDisabledGlobally = false;

        $instructions = $this->parse($content);

        // Line of the first COPY/ADD in the current build stage; expensive steps after it
        // cost space per assignment. null = still in the shared part.
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
        // New build stage: own cache chain, so reset the barrier and any ENV of the previous stage.
                $barrierLine = null;
                $barrierKeyword = '';
                $this->pipCacheDisabledGlobally = false;
                $this->checkBaseImage($line, $args);
                continue;
            }

            if (in_array($keyword, self::BARRIER_INSTRUCTIONS, true)) {
                // "COPY --from=<stage>" takes files from another build stage, not from the
                // assignment-specific context, so it does not break the shared cache.
                if (preg_match('/(^|\s)--from=/i', $args)) {
                    continue;
                }
                if ($this->copiesOnlyDependencyManifests($args)) {
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
            // Warnings first, then by line, so the lecturer sees the costly issues on top.
            if ($a['level'] !== $b['level']) {
                return $a['level'] === self::LEVEL_WARNING ? -1 : 1;
            }
            return $a['line'] <=> $b['line'];
        });

        return $this->findings;
    }

    /**
     * Splits the Dockerfile into logical instructions.
     *
     * Handles "\" line continuations and reports the FIRST line of a multi-line
     * instruction, since that is what the lecturer looks for in the editor.
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
                // Skip blank lines and comments outside an instruction.
                if ($trimmed === '' || $trimmed[0] === '#') {
                    continue;
                }
                $startLine = $lineNumber;
            } elseif ($trimmed === '' || $trimmed[0] === '#') {
                // Blank line or comment inside a continuation: Docker ignores both and
                // continues the instruction, so it must not be split here.
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

        // File ends inside a continuation; evaluate it anyway.
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
     * Does this step copy only dependency lists? Then it is good practice, not a
     * barrier (see DEPENDENCY_MANIFESTS).
     */
    protected function copiesOnlyDependencyManifests(string $args): bool
    {
        // Strip flags (--chown=, --chmod=, ...), then separate sources from the destination.
        $args = preg_replace('/(^|\s)--\S+/', ' ', $args) ?? $args;
        $parts = preg_split('/\s+/', trim($args), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return false;
        }
        array_pop($parts); // destination

        foreach ($parts as $source) {
            $name = strtolower(basename(trim($source, "\"'")));
            if (!in_array($name, self::DEPENDENCY_MANIFESTS, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Short label of the expensive step, or null if the line is harmless.
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
     * Without --no-cache-dir, pip's download cache (~/.cache/pip) ends up in the layer,
     * wasting several hundred MB for large packages such as PyTorch.
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
     * apt-get package lists in /var/lib/apt/lists stay in the layer (about 40 MB) unless
     * they are removed in the SAME RUN instruction.
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
     * Suggests the slim variant of common base images (python:3.12 is 1.62 GB,
     * python:3.12-slim only 179 MB).
     */
    protected function checkBaseImage(int $line, string $args): void
    {
        // "FROM <image>[:<tag>] [AS <name>]"; ignore flags such as --platform=.
        $args = preg_replace('/(^|\s)--\S+/', ' ', $args) ?? $args;
        if (!preg_match('/^\s*(\S+)/', $args, $matches)) {
            return;
        }
        $reference = $matches[1];

        // Only evaluate plain references; registry/namespace or digest references
        // (fau.de/…, myorg/python, image@sha256:…) cannot be interpreted reliably.
        if (strpos($reference, '/') !== false || strpos($reference, '@') !== false) {
            return;
        }

        $parts = explode(':', $reference, 2);
        $image = strtolower($parts[0]);
        $tag = $parts[1] ?? '';

        if (!in_array($image, self::SLIMMABLE_BASES, true)) {
            return;
        }
        // Without an explicit tag (or with "latest") no valid suggestion exists:
        // "FROM python" would yield "python:latest-slim", which does not exist.
        if ($tag === '' || strtolower($tag) === 'latest') {
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
