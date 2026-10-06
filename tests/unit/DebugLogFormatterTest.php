<?php

/**
 * Unit tests for ilExAutoScoreDebugLogFormatter.
 *
 * The former formatter added a flat +2 h (wrong in winter, no date rollover at
 * midnight) and mapped every "Okt" date to 13.10.2025.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../classes/class.ilExAutoScoreDebugLogFormatter.php';

class DebugLogFormatterTest extends TestCase
{
    private function formatter(string $now): ilExAutoScoreDebugLogFormatter
    {
        $tz = new DateTimeZone('Europe/Berlin');
        return new ilExAutoScoreDebugLogFormatter($tz, new DateTimeImmutable($now, $tz));
    }

    public function testCelerySummerTimeIsUtcPlusTwo(): void
    {
        $out = $this->formatter('2026-10-06 12:00')->format('[2026-10-06 07:44:13,810: INFO/MainProcess] received');
        $this->assertStringContainsString('[06.10.2026 09:44:13: INFO/MainProcess] received', $out);
    }

    public function testCeleryWinterTimeIsUtcPlusOne(): void
    {
        $out = $this->formatter('2026-12-01 12:00')->format('[2026-12-01 07:44:13,810: INFO] x');
        $this->assertStringContainsString('[01.12.2026 08:44:13: INFO] x', $out);
    }

    public function testCeleryDateRollsOverAtMidnight(): void
    {
        $out = $this->formatter('2026-10-07 12:00')->format('[2026-10-06 23:30:00,000: INFO] x');
        $this->assertStringContainsString('[07.10.2026 01:30:00: INFO] x', $out);
    }

    public function testJournalctlKeepsDayAndUsesCurrentYear(): void
    {
        $out = $this->formatter('2026-10-20 12:00')->format("Okt 06 09:01:02 host worker\nOct 7 10:00:00 host");
        $this->assertStringContainsString('06.10.2026 09:01:02 host worker', $out);
        $this->assertStringContainsString('07.10.2026 10:00:00 host', $out);
    }

    public function testJournalctlFromLastDecemberReadInJanuary(): void
    {
        $out = $this->formatter('2027-01-02 12:00')->format('Dez 31 23:59:00 host');
        $this->assertStringContainsString('31.12.2026 23:59:00 host', $out);
    }

    public function testOtherTextIsUntouched(): void
    {
        $line = 'Step 1/8 : FROM eclipse-temurin:21-jdk';
        $this->assertStringEndsWith($line, $this->formatter('2026-10-06 12:00')->format($line));
        $this->assertSame('', $this->formatter('2026-10-06 12:00')->format(''));
    }
}
