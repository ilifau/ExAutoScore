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
    /** @var string */
    private $taskModel;
    /** @var string */
    private $connector;

    protected function setUp(): void
    {
        $base = __DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php';
        $set  = __DIR__ . '/../../classes/class.ilExAutoScoreSettingsGUI.php';
        $task = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $conn = __DIR__ . '/../../classes/class.ilExAutoScoreConnector.php';
        foreach ([$base, $set, $task, $conn] as $f) {
            $this->assertFileExists($f);
        }
        $this->baseGui     = file_get_contents($base);
        $this->settingsGui = file_get_contents($set);
        $this->taskModel   = file_get_contents($task);
        $this->connector   = file_get_contents($conn);
    }

    // -------------------------------------------------------------------
    // Single source of truth: ilExAutoScoreTask::publishToMemberStatusIfDue()
    // -------------------------------------------------------------------

    public function testModelMethodExists(): void
    {
        $this->assertStringContainsString(
            'public function publishToMemberStatusIfDue(ilExAssignment $ass): bool',
            $this->taskModel,
            'The shared publish rule must live in the Task model'
        );
    }

    public function testModelRequiresScoredUnpublishedResult(): void
    {
        // Must have return_time AND non-null points (no auto-fail on build/no-score),
        // and must not be published yet (marker).
        $this->assertStringContainsString(
            'if (empty($this->getReturnTime()) || $this->getReturnPoints() === null) {',
            $this->taskModel,
            'Must require a non-null score (return_points) — fix B'
        );
        $this->assertStringContainsString(
            'if ($this->getReturnTime() === $this->getPublishedReturnTime()) {',
            $this->taskModel,
            'Must skip when already published (marker)'
        );
    }

    public function testModelChecksEveryAffectedUserDeadlineAndEmptyNote(): void
    {
        // ONE loop over all affected users: deadline must have passed AND each
        // member's note must be empty (fix C — not just affected[0]).
        $this->assertMatchesRegularExpression(
            '/foreach \(\$affected as \$uid\) \{.*?\$ass->getPersonalDeadline\(\$uid\).*?\$now < \$effective_deadline.*?return false;.*?\$ms = \$ass->getMemberStatus\(\$uid\);.*?\$ms->getStatus\(\) !== \'notgraded\' \|\| \$ms->getMark\(\) !== \'\'.*?return false;/s',
            $this->taskModel,
            'Must check deadline AND empty note for EVERY affected user'
        );
    }

    public function testModelWritesThenMarksThenSaves(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$this->updateMemberStatus\(\$affected\);\s*\$this->setPublishedReturnTime\(\$this->getReturnTime\(\)\);\s*\$this->save\(\);\s*return true;/s',
            $this->taskModel,
            'On publish: write grade, set marker, save, report success'
        );
    }

    // -------------------------------------------------------------------
    // All three call sites delegate to the model method
    // -------------------------------------------------------------------

    public function testGuiHelperDelegatesToModel(): void
    {
        $this->assertStringContainsString(
            'return $task ? $task->publishToMemberStatusIfDue($ass) : false;',
            $this->baseGui,
            'GUI helper must delegate to the model method'
        );
    }

    public function testStudentSideUsesHelper(): void
    {
        $this->assertStringContainsString(
            '$did_publish = $this->publishAutoNoteIfDue($ass, $task);',
            $this->baseGui,
            'Student-side auto-publish must call the helper'
        );
    }

    public function testReceiveResultUsesModelMethod(): void
    {
        // The connector path must use the SAME rule (fix A: no more unguarded
        // updateMemberStatus that could clobber a tutor grade).
        $this->assertStringContainsString(
            '$task->publishToMemberStatusIfDue($assignment);',
            $this->connector,
            'receiveResult must publish through the shared model method'
        );
    }

    public function testManagementSideBulkPublishesAllTasks(): void
    {
        // Fix D: on the first row for an assignment, publish ALL of its tasks
        // (not just rendered rows), guarded once per request.
        $this->assertStringContainsString(
            'self::$autoNoteBulkDone',
            $this->baseGui,
            'Management side must guard the bulk pass per assignment'
        );
        $this->assertMatchesRegularExpression(
            '/foreach \(ilExAutoScoreTask::getForAssignment\(\$ass_id\) as \$t\) \{\s*if \(\$t->publishToMemberStatusIfDue\(\$ass\)\)/s',
            $this->baseGui,
            'Management side must bulk-publish all assignment tasks'
        );
    }

    public function testManagementHintUsesSafeOnScreenMessageNotRawJs(): void
    {
        // The hint must use the ILIAS-native on-screen message — NOT addOnLoadCode,
        // whose raw-JS injection broke the ExerciseStatusFile multi-feedback button.
        $this->assertMatchesRegularExpression(
            '/setOnScreenMessage\(\s*\'info\',\s*\$this->plugin->txt\(\'autonote_reload_hint\'\)/s',
            $this->baseGui,
            'Management hint must use setOnScreenMessage(info, autonote_reload_hint)'
        );
        // No addOnLoadCode in the bulk/hint block (the only addOnLoadCode left is
        // the pre-existing feedback-modal injection, further down in the method).
        $bulk = substr(
            $this->baseGui,
            (int) strpos($this->baseGui, 'self::$autoNoteBulkDone[] = $ass_id;'),
            300
        );
        $this->assertStringNotContainsString(
            'addOnLoadCode',
            $bulk,
            'The auto-note hint must not use addOnLoadCode (raw JS conflict)'
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
