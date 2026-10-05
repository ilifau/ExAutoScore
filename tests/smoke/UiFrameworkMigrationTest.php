<?php

/**
 * Migration of legacy UI components to the UI framework (preparation for ILIAS 12).
 * Source-inspection tests, per project convention (no ILIAS runtime needed).
 */

use PHPUnit\Framework\TestCase;

class UiFrameworkMigrationTest extends TestCase
{
    /** @var string */
    private $base;

    protected function setUp(): void
    {
        $this->base = __DIR__ . '/../../';
    }

    private function source(string $file): string
    {
        return file_get_contents($this->base . 'classes/' . $file);
    }

    /** @return array<string, string> */
    private function classSources(): array
    {
        $sources = [];
        foreach (array_merge(glob($this->base . 'classes/*.php'), glob($this->base . 'classes/*/*.php')) as $file) {
            $sources[basename($file)] = file_get_contents($file);
        }
        return $sources;
    }

    /** Body of a method, from its signature to the next method signature. */
    private function method(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertNotFalse($start, "method $name() not found");
        $next = strpos($source, 'function ', $start + 10);
        return substr($source, $start, $next === false ? null : $next - $start);
    }

    public function testNoAdvancedSelectionList(): void
    {
        foreach ($this->classSources() as $file => $source) {
            $this->assertStringNotContainsString('ilAdvancedSelectionListGUI', $source, "$file: removed in ILIAS 12, use a UI dropdown");
        }
    }

    public function testNoLegacyLinkButton(): void
    {
        foreach ($this->classSources() as $file => $source) {
            $this->assertStringNotContainsString('ilLinkButton', $source, "$file: deprecated with ILIAS 12, use UI buttons");
        }
    }

    public function testConfirmationsUseInterruptiveModal(): void
    {
        $trait = $this->source('traits/trait.ilExAutoScoreGUIBase.php');
        $this->assertStringContainsString('modal()->interruptive(', $trait);

        $this->assertStringContainsString("'deleteSubmission'", $this->method($this->source('class.ilExAssTypeAutoScoreBaseGUI.php'), 'submissionScreen'));
        $this->assertStringContainsString("'sendAllTasks'", $this->method($this->source('class.ilExAutoScoreSettingsGUI.php'), 'setToolbar'));

        $this->assertStringNotContainsString('function confirmDeleteSubmission(', $this->source('class.ilExAssTypeAutoScoreBaseGUI.php'));
        $this->assertStringNotContainsString('function confirmSendAllTasks(', $this->source('class.ilExAutoScoreSettingsGUI.php'));
    }

    public function testDestructiveCommandsRequirePost(): void
    {
        $this->assertStringContainsString(
            "REQUEST_METHOD'] ?? '') !== 'POST'",
            $this->method($this->source('class.ilExAssTypeAutoScoreBaseGUI.php'), 'deleteSubmission'),
            'deleting a submission must not be possible by a plain link'
        );
        $this->assertStringContainsString(
            "REQUEST_METHOD'] ?? '') !== 'POST'",
            $this->method($this->source('class.ilExAutoScoreSettingsGUI.php'), 'sendAllTasks'),
            're-sending all submissions must not be possible by a plain link'
        );
    }
}
