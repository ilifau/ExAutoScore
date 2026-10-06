<?php

/**
 * Texts from the correction service must be escaped before they reach the page.
 *
 * The connector stores raw service responses (up to 1000 characters of an HTTP error
 * body) as result/submit message. ilInfoScreenGUI::addProperty() and
 * setOnScreenMessage() print their value as HTML, so the GUIs have to escape it.
 */

use PHPUnit\Framework\TestCase;

class ServiceTextEscapingTest extends TestCase
{
    private function source(string $class): string
    {
        return file_get_contents(__DIR__ . '/../../classes/class.' . $class . '.php');
    }

    /** Every use of the connector's result message in a GUI is wrapped in htmlspecialchars(). */
    public function testResultMessageIsEscaped(): void
    {
        $settings = $this->source('ilExAutoScoreSettingsGUI');
        $total = substr_count($settings, '$connector->getResultMessage()');
        $escaped = substr_count($settings, 'htmlspecialchars((string) $connector->getResultMessage()');
        $this->assertGreaterThan(0, $total);
        $this->assertSame($total, $escaped, 'unescaped getResultMessage() in ilExAutoScoreSettingsGUI');
    }

    /** The submit message on the info screen is escaped. */
    public function testSubmitMessageIsEscaped(): void
    {
        $base = $this->source('ilExAssTypeAutoScoreBaseGUI');
        $this->assertDoesNotMatchRegularExpression(
            '/addProperty\([^;]*,\s*\$task->getSubmitMessage\(\)\s*\)/',
            $base
        );
        $this->assertStringContainsString(
            "nl2br(htmlspecialchars(\$task->getSubmitMessage(), ENT_QUOTES, 'UTF-8'))",
            $base
        );
    }

    /** The instant message is shown through formatInstantMessage(), which escapes. */
    public function testInstantMessageIsEscaped(): void
    {
        $base = $this->source('ilExAssTypeAutoScoreBaseGUI');
        $start = strpos($base, 'function formatInstantMessage(');
        $this->assertNotFalse($start);
        $body = substr($base, $start, strpos($base, 'function ', $start + 10) - $start);
        $this->assertStringContainsString('htmlspecialchars($message)', $body);
    }
}
