<?php

/**
 * Smoke tests for "auto-correction grade flows into the ILIAS 'Note' column".
 *
 * Two trigger sides feed the same member-status note via one shared helper
 * publishAutoNoteIfDue():
 *   - student opens their submission (buildSubmissionPropertiesAndActions)
 *   - tutor opens "Abgaben und Noten" (modifySubmissionTableActions, per row)
 *
 * Rule: publish each correction result exactly once, only into an EMPTY note,
 * after the affected user's deadline. Never over a tutor/manual value.
 * "Alle Abgaben korrigieren" force-resets all notes first (full redo).
 *
 * Source-inspection tests, per project convention.
 */

use PHPUnit\Framework\TestCase;

class AutoNoteToGradesTest extends TestCase
{
    /** @var string */
    private $baseGui;
    /** @var string */
    private $settingsGui;

    protected function setUp(): void
    {
        $base = __DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php';
        $set  = __DIR__ . '/../../classes/class.ilExAutoScoreSettingsGUI.php';
        $this->assertFileExists($base);
        $this->assertFileExists($set);
        $this->baseGui     = file_get_contents($base);
        $this->settingsGui = file_get_contents($set);
    }

    // -------------------------------------------------------------------
    // Shared helper
    // -------------------------------------------------------------------

    public function testHelperExists(): void
    {
        $this->assertStringContainsString(
            'protected function publishAutoNoteIfDue(ilExAssignment $ass, ?ilExAutoScoreTask $task): bool',
            $this->baseGui,
            'Shared helper publishAutoNoteIfDue() must exist'
        );
    }

    public function testHelperRequiresUnpublishedResult(): void
    {
        // return_time must exist and differ from published_return_time (marker).
        $this->assertStringContainsString(
            'if (empty($task->getReturnTime())) {',
            $this->baseGui
        );
        $this->assertStringContainsString(
            'if ($task->getReturnTime() === $task->getPublishedReturnTime()) {',
            $this->baseGui,
            'Helper must skip when the result was already published (marker)'
        );
    }

    public function testHelperChecksAffectedUserDeadlineDirectly(): void
    {
        // Viewer-independent deadline check (NOT canShowAssessmentNowForUser).
        $this->assertMatchesRegularExpression(
            '/foreach \(\$affected as \$uid\) \{.*?\$ass->getPersonalDeadline\(\$uid\).*?\$now < \$effective_deadline.*?return false;/s',
            $this->baseGui,
            'Helper must check each affected user\'s real deadline'
        );
    }

    public function testHelperOnlyFillsEmptyNote(): void
    {
        $this->assertMatchesRegularExpression(
            '/if \(\$current->getStatus\(\) !== \'notgraded\' \|\| \$current->getMark\(\) !== \'\'\) \{\s*return false;/s',
            $this->baseGui,
            'Helper must leave a non-empty note (tutor/earlier auto) untouched'
        );
    }

    public function testHelperSetsMarkerAfterPublish(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$task->updateMemberStatus\(\$affected\);\s*\$task->setPublishedReturnTime\(\$task->getReturnTime\(\)\);\s*\$task->save\(\);\s*return true;/s',
            $this->baseGui,
            'Helper must write the grade, set the marker, save, and report success'
        );
    }

    // -------------------------------------------------------------------
    // Both sides use the shared helper
    // -------------------------------------------------------------------

    public function testStudentSideUsesHelper(): void
    {
        $this->assertStringContainsString(
            '$did_publish = $this->publishAutoNoteIfDue($ass, $task);',
            $this->baseGui,
            'Student-side auto-publish must delegate to the shared helper'
        );
        // The old inline idempotency/marker block must be gone from the student side.
        $this->assertStringNotContainsString(
            'if ($task->getReturnTime() !== $task->getPublishedReturnTime()) {',
            $this->baseGui,
            'The old inline marker block must be replaced by the helper'
        );
    }

    public function testManagementSideUsesHelperAndBanner(): void
    {
        // modifySubmissionTableActions publishes per row and injects a one-shot
        // "please reload" banner.
        $this->assertMatchesRegularExpression(
            '/modifySubmissionTableActions\([^)]*\): void\s*\{.*?publishAutoNoteIfDue\(\$ass, \$task\)/s',
            $this->baseGui,
            'modifySubmissionTableActions must call the shared helper'
        );
        $this->assertStringContainsString(
            'autoNoteReloadHintShown',
            $this->baseGui,
            'A one-shot guard for the reload banner must exist'
        );
        $this->assertStringContainsString(
            "\$this->plugin->txt('autonote_reload_hint')",
            $this->baseGui,
            'The reload banner must use the autonote_reload_hint lang string'
        );
        $this->assertStringContainsString(
            'addOnLoadCode',
            $this->baseGui,
            'The banner is injected via addOnLoadCode (consistent with existing pattern)'
        );
    }

    // -------------------------------------------------------------------
    // "Alle Abgaben korrigieren" = full redo (reset notes first)
    // -------------------------------------------------------------------

    public function testSendAllTasksResetsNotesBeforeResend(): void
    {
        $this->assertMatchesRegularExpression(
            '/getSubmissionTask\(\$submission\);\s*\$task->updateMemberStatus\(\[\], true\);\s*\$connector->sendSubmission\(\$submission/s',
            $this->settingsGui,
            'sendAllTasks must force-reset the note before re-sending each submission'
        );
    }

    public function testConfirmDialogMentionsReset(): void
    {
        $de = file_get_contents(__DIR__ . '/../../lang/ilias_de.lang');
        $this->assertMatchesRegularExpression(
            '/confirm_send_all_tasks#:#.*zurückgesetzt/u',
            $de,
            'The confirm dialog must warn that grades are reset'
        );
    }

    // -------------------------------------------------------------------
    // Lang + version
    // -------------------------------------------------------------------

    public function testReloadHintLangStringsExist(): void
    {
        $de = file_get_contents(__DIR__ . '/../../lang/ilias_de.lang');
        $en = file_get_contents(__DIR__ . '/../../lang/ilias_en.lang');
        $this->assertStringContainsString('autonote_reload_hint#:#', $de);
        $this->assertStringContainsString('autonote_reload_hint#:#', $en);
    }

    public function testVersionBumped(): void
    {
        $plugin = file_get_contents(__DIR__ . '/../../plugin.php');
        $this->assertDoesNotMatchRegularExpression(
            '/\$version\s*=\s*"0\.3\.4"/',
            $plugin,
            'plugin.php version must be bumped past 0.3.4'
        );
    }
}
