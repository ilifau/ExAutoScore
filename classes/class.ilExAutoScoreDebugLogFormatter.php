<?php
declare(strict_types=1);

// Copyright (c) 2026 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Converts the timestamps in the service debug logs to local time
 *
 * Has no ILIAS dependencies, so it can be unit tested directly.
 */
class ilExAutoScoreDebugLogFormatter
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mär' => 3, 'mar' => 3, 'apr' => 4, 'mai' => 5, 'may' => 5,
        'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10,
        'nov' => 11, 'dez' => 12, 'dec' => 12,
    ];

    private DateTimeZone $timezone;
    private DateTimeImmutable $now;

    public function __construct(?DateTimeZone $timezone = null, ?DateTimeImmutable $now = null)
    {
        $this->timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
        $this->now = $now ?? new DateTimeImmutable('now', $this->timezone);
    }

    public function format(string $logs): string
    {
        if ($logs === '') {
            return $logs;
        }

        // Celery writes UTC: [2025-10-13 06:36:52,313: -> [13.10.2025 08:36:52:
        $logs = preg_replace_callback(
            '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+:/',
            function (array $m): string {
                $utc = new DateTimeImmutable($m[1], new DateTimeZone('UTC'));
                return '[' . $utc->setTimezone($this->timezone)->format('d.m.Y H:i:s') . ':';
            },
            $logs
        );

        // journalctl writes local time without a year: Okt 13 08:36:52 -> 13.10.2025 08:36:52
        $logs = preg_replace_callback(
            '/\b(' . implode('|', array_keys(self::MONTHS)) . ')\.? +(\d{1,2}) (\d{2}:\d{2}:\d{2})/iu',
            function (array $m): string {
                $month = self::MONTHS[mb_strtolower($m[1])];
                $year = (int) $this->now->format('Y');
                // a date after today belongs to the previous year (logs from late December read in January)
                if (sprintf('%02d%02d', $month, $m[2]) > $this->now->format('md')) {
                    $year--;
                }
                return sprintf('%02d.%02d.%d %s', $m[2], $month, $year, $m[3]);
            },
            $logs
        );

        $header = "=== DEBUG-PROTOKOLL ===\n"
            . "Zeitstempel in Ortszeit (" . $this->timezone->getName() . ").\n"
            . "Quelle: Celery-Logs, Docker-Build-Output, Task-Ausführung\n\n";

        return $header . $logs;
    }
}
