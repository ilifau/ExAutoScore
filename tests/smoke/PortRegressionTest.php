<?php

/**
 * Regression tests for fixes found while porting to ILIAS 10 and 11.
 * Source-inspection tests, per project convention (no ILIAS runtime needed).
 */

use PHPUnit\Framework\TestCase;

class PortRegressionTest extends TestCase
{
    private const ILIAS_VERSION = 9;

    /** @var string */
    private $base;

    protected function setUp(): void
    {
        $this->base = __DIR__ . '/../../';
    }

    /** @return array<string, string> relative path => source of all plugin PHP files */
    private function sources(): array
    {
        $files = array_merge(
            glob($this->base . 'classes/*.php'),
            glob($this->base . 'classes/*/*.php'),
            [$this->base . 'plugin.php', $this->base . 'results.php']
        );
        $sources = [];
        foreach ($files as $file) {
            $sources[substr($file, strlen($this->base))] = file_get_contents($file);
        }
        return $sources;
    }

    private function assertNowhere(string $pattern, string $message): void
    {
        foreach ($this->sources() as $file => $source) {
            $this->assertDoesNotMatchRegularExpression($pattern, $source, "$file: $message");
        }
    }

    // --- all ILIAS versions ---

    public function testNoRemovedIlUtilFileHelpers(): void
    {
        $this->assertNowhere('/ilUtil::(getASCIIFilename|ilTempnam)\s*\(/', 'moved to ilFileUtils since ILIAS 9');
    }

    public function testTablesAreDroppedOnUninstall(): void
    {
        $plugin = file_get_contents($this->base . 'classes/class.ilExAutoScorePlugin.php');
        $this->assertStringNotContainsString('function uninstallCustom(', $plugin, 'ilPlugin never calls uninstallCustom()');
        $this->assertMatchesRegularExpression(
            '/function afterUninstall\(\): void\s*\{.*dropTable\(.exautoscore_assignment/s',
            $plugin,
            'afterUninstall() must drop the plugin tables'
        );
    }

    public function testPluginVersionRangeMatchesBranch(): void
    {
        $plugin = file_get_contents($this->base . 'plugin.php');
        $v = self::ILIAS_VERSION;
        $this->assertMatchesRegularExpression('/\$ilias_max_version\s*=\s*"' . $v . '\.999"/', $plugin);
        $this->assertMatchesRegularExpression('/\$version\s*=\s*"' . ($v >= 10 ? $v . '\.' : '0\.') . '/', $plugin);
    }
}
