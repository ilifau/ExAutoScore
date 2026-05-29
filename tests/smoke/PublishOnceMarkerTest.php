<?php

/**
 * Smoke tests for the "publish each correction result exactly once" mechanism.
 *
 * Background:
 *   The auto-publish block in buildSubmissionPropertiesAndActions() writes the
 *   auto-correction verdict into the ILIAS member status after the deadline.
 *   It runs on EVERY page render, so it needs a guard against re-firing.
 *
 *   - The first guard (status==='notgraded' || mark==='') re-fired forever when
 *     the verdict could not move the status away from notgraded/empty
 *     (min_points unset / no points) -> too_many_redirects.
 *   - The second guard (idempotency: current != computeTargetMemberStatus())
 *     fixed the loop but RE-ASSERTED the auto verdict whenever the stored
 *     status differed from it — which clobbered feedback a tutor had uploaded
 *     afterwards via status.csv.
 *
 *   The current mechanism uses a per-task marker `published_return_time`:
 *   the result is published only when task.return_time != published_return_time,
 *   i.e. exactly once per correction result. This both stops the loop AND
 *   leaves later tutor corrections untouched.
 *
 * Source-inspection tests (no ILIAS runtime needed), per project convention.
 */

use PHPUnit\Framework\TestCase;

class PublishOnceMarkerTest extends TestCase
{
    /** @var string */
    private $baseGui;
    /** @var string */
    private $taskModel;
    /** @var string */
    private $connector;
    /** @var string */
    private $dbUpdate;

    protected function setUp(): void
    {
        $base = __DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php';
        $task = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $conn = __DIR__ . '/../../classes/class.ilExAutoScoreConnector.php';
        $sql  = __DIR__ . '/../../sql/dbupdate.php';
        foreach ([$base, $task, $conn, $sql] as $f) {
            $this->assertFileExists($f);
        }
        $this->baseGui   = file_get_contents($base);
        $this->taskModel = file_get_contents($task);
        $this->connector = file_get_contents($conn);
        $this->dbUpdate  = file_get_contents($sql);
    }

    // -------------------------------------------------------------------
    // DB migration + backfill
    // -------------------------------------------------------------------

    public function testDbUpdateAddsPublishedReturnTimeColumn(): void
    {
        $this->assertStringContainsString(
            "tableColumnExists('exautoscore_task', 'published_return_time')",
            $this->dbUpdate,
            'dbupdate must add published_return_time idempotently'
        );
        $this->assertMatchesRegularExpression(
            '/addTableColumn\(\s*"exautoscore_task",\s*\'published_return_time\'/s',
            $this->dbUpdate,
            'dbupdate must call addTableColumn for published_return_time'
        );
    }

    public function testDbUpdateHasNoBackfill(): void
    {
        // The backfill was removed (2026-05-28): the "only fill empty note" rule
        // in publishAutoNoteIfDue() protects existing tutor corrections, so
        // marking existing results as published is unnecessary — and it would
        // wrongly block the automatism from filling pre-existing empty notes.
        $this->assertStringNotContainsString(
            'UPDATE exautoscore_task SET published_return_time = return_time',
            $this->dbUpdate,
            'dbupdate must NOT backfill the marker (empty-note guard protects instead)'
        );
    }

    // -------------------------------------------------------------------
    // Model
    // -------------------------------------------------------------------

    public function testModelHasPublishedReturnTimeField(): void
    {
        $this->assertMatchesRegularExpression(
            '/@con_has_field\s+true.*?@con_fieldtype\s+timestamp.*?protected \?string \$published_return_time/s',
            $this->taskModel,
            'Task model must persist published_return_time as a timestamp field'
        );
        $this->assertStringContainsString(
            'public function getPublishedReturnTime(): ?string',
            $this->taskModel
        );
        $this->assertStringContainsString(
            'public function setPublishedReturnTime(?string $published_return_time): void',
            $this->taskModel
        );
    }

    public function testClearSubmissionDataResetsMarker(): void
    {
        // A new submission must be publishable again -> marker reset on clear.
        $this->assertMatchesRegularExpression(
            '/clearSubmissionData\(\).*?setPublishedReturnTime\(null\);.*?\}/s',
            $this->taskModel,
            'clearSubmissionData() must reset published_return_time to null'
        );
    }

    // -------------------------------------------------------------------
    // Auto-publish guard (BaseGUI)
    // -------------------------------------------------------------------

    public function testAutoPublishUsesMarker(): void
    {
        // Since 2026-05 the marker logic lives in the shared helper
        // publishAutoNoteIfDue() (see AutoNoteToGradesTest). The student-side
        // auto-publish delegates to it. Here we only assert the marker is still
        // the gate: skip when already published, set it after publishing.
        $this->assertStringContainsString(
            'if ($task->getReturnTime() === $task->getPublishedReturnTime()) {',
            $this->baseGui,
            'Marker must gate publishing (skip when already published)'
        );
        $this->assertStringContainsString(
            '$task->setPublishedReturnTime($task->getReturnTime());',
            $this->baseGui,
            'Marker must be set after publishing'
        );
        $this->assertStringContainsString(
            '$did_publish = $this->publishAutoNoteIfDue($ass, $task);',
            $this->baseGui,
            'Student-side must delegate to the shared helper'
        );
    }

    public function testOldMechanismsAreGone(): void
    {
        // Neither the original notgraded/empty guard nor the idempotency helper
        // may remain in the publish path.
        $this->assertStringNotContainsString(
            'computeTargetMemberStatus',
            $this->baseGui,
            'The idempotency helper computeTargetMemberStatus() must be removed'
        );
        $this->assertStringNotContainsString(
            "\$current_status->getStatus() === 'notgraded' || \$current_status->getMark() === ''",
            $this->baseGui,
            'The original notgraded/empty guard must not reappear'
        );
    }

    public function testRedirectStillPresent(): void
    {
        // The loop fix relies on publishing once; the redirect itself stays so
        // the freshly published grade is shown without a manual reload.
        $this->assertStringContainsString(
            'if ($did_publish) {',
            $this->baseGui
        );
        $this->assertStringContainsString(
            '$DIC->ctrl()->redirectByClass($current_class, $DIC->ctrl()->getCmd());',
            $this->baseGui
        );
    }

    // -------------------------------------------------------------------
    // receiveResult must set the marker too
    // -------------------------------------------------------------------

    public function testReceiveResultSetsMarker(): void
    {
        // When the result arrives and the deadline is already reached,
        // receiveResult publishes directly — and must set the marker so the GUI
        // does not re-publish (and clobber a later tutor correction).
        $this->assertMatchesRegularExpression(
            '/\$task->updateMemberStatus\(\$affected_users\);.*?\$task->setPublishedReturnTime\(\$task->getReturnTime\(\)\);\s*\$task->save\(\);/s',
            $this->connector,
            'receiveResult() must set published_return_time after publishing'
        );
    }
}
