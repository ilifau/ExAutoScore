<?php

/**
 * results.php is reachable from outside. Without a key, anyone who knows a task UUID
 * could set points and feedback. The grading service sends the shared API key in the
 * X-Autocor-Key header, and receiveResult() rejects every result without it.
 */

use PHPUnit\Framework\TestCase;

class WebhookKeyTest extends TestCase
{
    private string $connector;

    protected function setUp(): void
    {
        $this->connector = file_get_contents(__DIR__ . '/../../classes/class.ilExAutoScoreConnector.php');
    }

    private function method(string $name): string
    {
        $start = strpos($this->connector, 'function ' . $name . '(');
        $this->assertNotFalse($start, "$name() not found");
        preg_match('/\n    (public|protected|private) (static )?function /', $this->connector, $m, PREG_OFFSET_CAPTURE, $start + 10);
        return substr($this->connector, $start, $m[0][1] - $start);
    }

    public function testKeyIsCheckedBeforeAnythingIsRead(): void
    {
        $body = $this->method('receiveResult');
        $check = strpos($body, '$this->hasValidResultKey()');
        $this->assertNotFalse($check);
        $this->assertLessThan(strpos($body, 'getUploadedFiles()'), $check, 'key check must come first');
        $this->assertMatchesRegularExpression(
            '/if \(!\$this->hasValidResultKey\(\)\) \{.*?http_response_code\(403\);.*?return;/s',
            $body
        );
    }

    public function testKeyComparisonIsConstantTimeAndNeverEmpty(): void
    {
        $body = $this->method('hasValidResultKey');
        $this->assertStringContainsString("\$this->config->get('service_api_key')", $body);
        $this->assertStringContainsString('getHeaderLine(self::RESULT_KEY_HEADER)', $body);
        $this->assertStringContainsString("\$expected !== '' && hash_equals(\$expected, \$given)", $body);
        $this->assertStringContainsString("const RESULT_KEY_HEADER = 'X-Autocor-Key';", $this->connector);
    }
}
