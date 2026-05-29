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
        // The marker logic now lives in ilExAutoScoreTask::publishToMemberStatusIfDue()
        // (single source of truth, see AutoNoteToGradesTest). The GUI delegates.
        $this->assertStringContainsString(
            'if ($this->getReturnTime() === $this->getPublishedReturnTime()) {',
            $this->taskModel,
            'Marker must gate publishing in the model (skip when already published)'
        );
        $this->assertStringContainsString(
            '$this->setPublishedReturnTime($this->getReturnTime());',
            $this->taskModel,
            'Marker must be set after publishing in the model'
        );
        $this->assertStringContainsString(
            '$did_publish = $this->publishAutoNoteIfDue($ass, $task);',
            $this->baseGui,
            'Student-side must delegate to the GUI helper'
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
    // receiveResult publishes through the shared model method
    // -------------------------------------------------------------------

    public function testReceiveResultUsesSharedMethod(): void
    {
        // receiveResult must go through publishToMemberStatusIfDue() (which sets
        // the marker + guards empty note / non-null points) instead of an
        // unguarded updateMemberStatus that could clobber a tutor grade.
        $this->assertStringContainsString(
            '$task->publishToMemberStatusIfDue($assignment);',
            $this->connector,
            'receiveResult() must publish via the shared model method'
        );
        $this->assertStringNotContainsString(
            '$task->updateMemberStatus($affected_users);',
            $this->connector,
            'The old unguarded updateMemberStatus call must be gone from receiveResult'
        );
    }
}
