<?php

/**
 * Unit tests for ilExAutoScoreDockerfileAnalyzer.
 *
 * Unlike the smoke tests in tests/smoke (which inspect source text because they
 * cannot boot ILIAS), this one runs the real class: the analyzer deliberately has
 * no ILIAS dependencies, so it can simply be required and called.
 *
 * The central case is the one measured on 07.08.2026, where 21 near-identical
 * correction images occupied 47.6 GB without sharing a single layer: the expensive
 * "RUN pip install" sat below the "ADD" of the assignment files.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../classes/class.ilExAutoScoreDockerfileAnalyzer.php';

class DockerfileAnalyzerTest extends TestCase
{
    private ilExAutoScoreDockerfileAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ilExAutoScoreDockerfileAnalyzer();
    }

    /**
     * @param array<int, array> $findings
     * @return array<int, array>
     */
    private function withKey(array $findings, string $key): array
    {
        return array_values(array_filter($findings, function (array $f) use ($key): bool {
            return $f['key'] === $key;
        }));
    }

    /** The real-world layout that caused the incident. */
    private function badDockerfile(): string
    {
        return <<<'DOCKER'
FROM debian:bookworm
ENV RESULTDIR=/result
RUN mkdir $RESULTDIR
WORKDIR /work
ADD . ./
RUN if [ -f provided.tgz ]; then tar xzf provided.tgz; rm provided.tgz; fi
RUN pip install torch --index-url https://download.pytorch.org/whl/cpu
RUN pip install --no-cache-dir -r requirements.txt
DOCKER;
    }

    public function testExpensiveStepBelowAddIsReported(): void
    {
        $findings = $this->analyzer->analyze($this->badDockerfile());
        $afterCopy = $this->withKey($findings, 'dockerfile_check_after_copy');

        $this->assertCount(2, $afterCopy, 'both pip install lines sit below the ADD');

        // args = [line of the RUN, label, barrier keyword, line of the barrier]
        $this->assertSame([7, 'pip install', 'ADD', 5], $afterCopy[0]['args']);
        $this->assertSame([8, 'pip install', 'ADD', 5], $afterCopy[1]['args']);
        $this->assertSame(
            ilExAutoScoreDockerfileAnalyzer::LEVEL_WARNING,
            $afterCopy[0]['level'],
            'this is the finding that costs gigabytes, so it must not be a mere hint'
        );
    }

    public function testMovingInstallsAboveTheAddSilencesTheWarning(): void
    {
        $good = <<<'DOCKER'
FROM debian:bookworm
WORKDIR /work
RUN pip install --no-cache-dir torch --index-url https://download.pytorch.org/whl/cpu
ADD . ./
RUN if [ -f provided.tgz ]; then tar xzf provided.tgz; rm provided.tgz; fi
DOCKER;

        $this->assertSame([], $this->withKey($this->analyzer->analyze($good), 'dockerfile_check_after_copy'));
    }

    public function testPipWithoutNoCacheDirIsReported(): void
    {
        $findings = $this->analyzer->analyze($this->badDockerfile());
        $cache = $this->withKey($findings, 'dockerfile_check_pip_cache');

        $this->assertCount(1, $cache, 'only the torch line lacks --no-cache-dir');
        $this->assertSame([7], $cache[0]['args']);
    }

    public function testGlobalPipEnvSuppressesTheCacheWarning(): void
    {
        $content = "FROM python:3.12-slim\nENV PIP_NO_CACHE_DIR=1\nRUN pip install torch\n";
        $this->assertSame([], $this->withKey($this->analyzer->analyze($content), 'dockerfile_check_pip_cache'));
    }

    /** A multi-line RUN must be reported at its FIRST line — that is what the editor shows. */
    public function testLineContinuationReportsTheFirstLine(): void
    {
        $content = "FROM debian:bookworm\n"      // 1
            . "COPY . ./\n"                      // 2
            . "RUN apt-get update && \\\n"       // 3  <- expected
            . "    apt-get install -y gcc && \\\n"
            . "    rm -rf /var/lib/apt/lists/*\n";

        $afterCopy = $this->withKey($this->analyzer->analyze($content), 'dockerfile_check_after_copy');
        $this->assertCount(1, $afterCopy);
        $this->assertSame(3, $afterCopy[0]['line']);

        $this->assertSame(
            [],
            $this->withKey($this->analyzer->analyze($content), 'dockerfile_check_apt_lists'),
            'the lists are removed within the same RUN, so there is nothing to report'
        );
    }

    public function testAptWithoutListCleanupIsReported(): void
    {
        $content = "FROM debian:bookworm\nRUN apt-get update && apt-get install -y gcc\n";
        $apt = $this->withKey($this->analyzer->analyze($content), 'dockerfile_check_apt_lists');

        $this->assertCount(1, $apt);
        $this->assertSame(ilExAutoScoreDockerfileAnalyzer::LEVEL_HINT, $apt[0]['level']);
    }

    /** "COPY --from=" pulls from another build stage, not from the per-assignment context. */
    public function testCopyFromOtherStageIsNoBarrier(): void
    {
        $content = <<<'DOCKER'
FROM debian:bookworm AS builder
RUN pip install --no-cache-dir torch
FROM debian:bookworm
COPY --from=builder /usr/local /usr/local
RUN pip install --no-cache-dir pytest
DOCKER;

        $this->assertSame([], $this->withKey($this->analyzer->analyze($content), 'dockerfile_check_after_copy'));
    }

    /** Each FROM opens a new cache chain, so a barrier must not leak across stages. */
    public function testBarrierResetsAtNewStage(): void
    {
        $content = <<<'DOCKER'
FROM debian:bookworm AS builder
COPY . ./
FROM debian:bookworm
RUN pip install --no-cache-dir torch
DOCKER;

        $this->assertSame([], $this->withKey($this->analyzer->analyze($content), 'dockerfile_check_after_copy'));
    }

    public function testFatBaseImageIsHinted(): void
    {
        $base = $this->withKey($this->analyzer->analyze("FROM python:3.12\n"), 'dockerfile_check_fat_base');

        $this->assertCount(1, $base);
        $this->assertSame([1, 'python:3.12', 'python:3.12-slim'], $base[0]['args']);
        $this->assertSame(ilExAutoScoreDockerfileAnalyzer::LEVEL_HINT, $base[0]['level']);
    }

    public function testSlimAndForeignBasesAreLeftAlone(): void
    {
        foreach (['FROM python:3.12-slim', 'FROM debian:bookworm', 'FROM eclipse-temurin:21-jdk',
                     'FROM registry.fau.de/python:3.12'] as $line) {
            $this->assertSame(
                [],
                $this->withKey($this->analyzer->analyze($line . "\n"), 'dockerfile_check_fat_base'),
                'must not complain about: ' . $line
            );
        }
    }

    public function testHarmlessDockerfileProducesNoFindings(): void
    {
        $content = <<<'DOCKER'
# a comment
FROM debian:bookworm

ENV RESULTDIR=/result
RUN mkdir $RESULTDIR
WORKDIR /work
ADD . ./
RUN chmod 777 /work
DOCKER;

        $this->assertSame([], $this->analyzer->analyze($content),
            'mkdir/chmod cost nothing — staying silent here is what keeps the message credible');
    }

    public function testWarningsAreListedBeforeHints(): void
    {
        $content = "FROM python:3.12\nCOPY . ./\nRUN pip install torch\n";
        $levels = array_column($this->analyzer->analyze($content), 'level');

        $this->assertNotEmpty($levels);
        $this->assertSame(
            ilExAutoScoreDockerfileAnalyzer::LEVEL_WARNING,
            $levels[0],
            'the expensive finding belongs at the top'
        );
    }

    /**
     * Every message key must exist in BOTH language files (project convention) and
     * carry exactly as many placeholders as the analyzer passes arguments — a
     * mismatch would produce a broken sentence in front of a lecturer.
     */
    public function testLanguageKeysExistAndPlaceholdersMatch(): void
    {
        $expectedArgs = [
            'dockerfile_check_title' => 0,
            'dockerfile_check_intro' => 0,
            'dockerfile_check_after_copy' => 4,
            'dockerfile_check_pip_cache' => 1,
            'dockerfile_check_apt_lists' => 1,
            'dockerfile_check_fat_base' => 3,
        ];

        foreach (['de', 'en'] as $lang) {
            $file = __DIR__ . '/../../lang/ilias_' . $lang . '.lang';
            $this->assertFileExists($file);
            $content = file_get_contents($file);

            foreach ($expectedArgs as $key => $count) {
                $this->assertSame(
                    1,
                    preg_match('/^' . preg_quote($key, '/') . '#:#(.*)$/m', $content, $m),
                    "key '$key' missing in ilias_$lang.lang"
                );
                $this->assertSame(
                    $count,
                    substr_count($m[1], '%s'),
                    "key '$key' in ilias_$lang.lang has the wrong number of placeholders"
                );
            }
        }
    }
}
