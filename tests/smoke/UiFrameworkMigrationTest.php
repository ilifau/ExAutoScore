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

    public function testPluginConfigurationUsesUiForm(): void
    {
        $gui = $this->source('class.ilExAutoScoreConfigGUI.php');
        $param = $this->source('param/class.ilExAutoScoreParam.php');
        foreach (['ilPropertyFormGUI', 'ilTextInputGUI', 'ilNumberInputGUI', 'ilCheckboxInputGUI', 'ilSelectInputGUI', 'ilTextAreaInputGUI', 'ilFormSectionHeaderGUI'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $gui . $param, "$legacy is deprecated with ILIAS 12");
        }
        $this->assertStringContainsString('form()->standard(', $gui);
        $this->assertStringContainsString('->withRequest(', $this->method($gui, 'saveBasicSettings'));
        $this->assertStringContainsString('->tag(', $this->method($param, 'getUiInput'), 'roles are entered as tags');
        $this->assertStringContainsString('->withUserCreatedTagsAllowed(true)', $this->method($param, 'getUiInput'), 'any role can be entered, as in ILIAS 9');
        $this->assertStringContainsString('getUnknownRoleTitles(', $this->method($param, 'getUiInput'), 'unknown roles are rejected with a message');
    }

    public function testFileTablesUseDataTable(): void
    {
        $table = $this->source('class.ilExAutoScoreFileTable.php');
        $this->assertStringContainsString('implements DataRetrieval', $table);
        $this->assertStringContainsString('->withAsync()', $this->method($table, 'getActions'), 'delete opens a modal instead of a page');
        $this->assertStringContainsString('->withAffectedItems(', $this->method($table, 'sendDeleteConfirmation'));
        $this->assertStringContainsString("'ALL_OBJECTS'", $this->method($table, 'sendDeleteConfirmation'));

        foreach (['ProvidedFiles', 'RequiredFiles'] as $name) {
            $this->assertStringContainsString('extends ilExAutoScoreFileTable', $this->source("class.ilExAutoScore{$name}TableGUI.php"));
            $gui = $this->source("class.ilExAutoScore{$name}GUI.php");
            foreach (['ilTable2GUI', 'ilConfirmationGUI', 'function confirmDeleteFiles(', "\$_POST['ids']"] as $legacy) {
                $this->assertStringNotContainsString($legacy, $gui, "$name: $legacy");
            }
            $this->assertStringContainsString('sendDeleteConfirmation()', $this->method($gui, 'listFiles'));
            $this->assertStringContainsString('getConfirmedIds()', $this->method($gui, 'deleteFiles'));
            $this->assertStringContainsString('getRequestedFileId(', $this->method($gui, 'editFile'));
            $this->assertStringContainsString('getRequestedFileId(', $this->method($gui, 'updateFile'));
        }
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
        $this->assertStringContainsString(
            "REQUEST_METHOD'] ?? '') !== 'POST'",
            $this->method($this->source('traits/trait.ilExAutoScoreGUIBase.php'), 'getConfirmedIds'),
            'deleting files must not be possible by a plain link'
        );
    }
}
