<?php

/**
 * Smoke tests for the auto-publish redirect-loop fix in
 * class.ilExAssTypeAutoScoreBaseGUI.php.
 *
 * Background:
 *   When a course admin uploaded their own submission and the assignment
 *   either had min_points unset or no return_points were available, the
 *   auto-publish block re-fired its updateMemberStatus()+redirect on every
 *   render, causing Chrome to abort with ERR_TOO_MANY_REDIRECTS.
 *
 *   The fix replaces the broken guard
 *     status === 'notgraded' || mark === ''
 *   with an idempotency check: only publish (and redirect) if calling
 *   updateMemberStatus() would actually change the stored member status.
 *
 *   The new helper computeTargetMemberStatus() mirrors the logic of
 *   ilExAutoScoreTask::updateMemberStatus() so we can predict its output
 *   without writing to the DB.
 *
 * These tests do not exercise real ILIAS objects (the existing smoke tests
 * follow the same convention). They verify by source inspection that the
 * patch is in place and that the four logical branches of
 * computeTargetMemberStatus() are covered.
 */

use PHPUnit\Framework\TestCase;

class RedirectLoopFixTest extends TestCase
{
    /** @var string */
    private $baseGuiSource;

    protected function setUp(): void
    {
        $file = __DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php';
        $this->assertFileExists($file, 'BaseGUI file must exist');
        $this->baseGuiSource = file_get_contents($file);
    }

    // -------------------------------------------------------------------
    // Helper method existence and signature
    // -------------------------------------------------------------------

    public function testComputeTargetMemberStatusMethodExists(): void
    {
        $this->assertStringContainsString(
            'protected function computeTargetMemberStatus(',
            $this->baseGuiSource,
            'Helper method computeTargetMemberStatus() should exist'
        );
    }

    public function testComputeTargetMemberStatusTakesTaskAndScoreAssignment(): void
    {
        // The helper must take a task and the per-assignment score config so
        // it can mirror updateMemberStatus()'s decision tree without writing.
        $this->assertMatchesRegularExpression(
            '/protected function computeTargetMemberStatus\(\s*ilExAutoScoreTask \$task,\s*ilExAutoScoreAssignment \$scoreAss\s*\)/',
            $this->baseGuiSource,
            'Helper must accept ilExAutoScoreTask and ilExAutoScoreAssignment parameters'
        );
    }

    public function testComputeTargetMemberStatusReturnsArray(): void
    {
        // Return type matters: callers destructure the result via $target['status'] / $target['mark'].
        $this->assertMatchesRegularExpression(
            '/computeTargetMemberStatus\([^)]*\)\s*:\s*array/s',
            $this->baseGuiSource,
            'Helper must declare array return type'
        );
    }

    // -------------------------------------------------------------------
    // The four logical branches must all be present
    // -------------------------------------------------------------------

    public function testBranchNoReturnTimeYieldsNotGraded(): void
    {
        // Branch 1: no result yet -> notgraded with empty mark.
        // This mirrors updateMemberStatus()'s "if (empty($this->getReturnTime()))" branch.
        $this->assertMatchesRegularExpression(
            '/empty\(\$task->getReturnTime\(\)\)\)\s*\{\s*return\s*\[\s*\'status\'\s*=>\s*\'notgraded\',\s*\'mark\'\s*=>\s*\'\'\s*\]/s',
            $this->baseGuiSource,
            'No-return-time branch must yield notgraded with empty mark'
        );
    }

    public function testBranchEmptyMinPointsYieldsNotGraded(): void
    {
        // Branch 2: result exists but min_points is not configured -> notgraded
        // (the assignment has no pass/fail threshold, so we can't decide).
        $this->assertMatchesRegularExpression(
            '/if \(empty\(\$min\)\)\s*\{\s*\$status\s*=\s*\'notgraded\'/s',
            $this->baseGuiSource,
            'Empty-min_points branch must set status to notgraded'
        );
    }

    public function testBranchPassedRequiresPointsAtOrAboveMin(): void
    {
        // Branch 3: points >= min_points -> passed.
        // The "$points !== null" guard prevents the PHP-8 deprecation
        // (null >= float) and the false-positive of treating null as 0.
        $this->assertMatchesRegularExpression(
            '/elseif \(\$points !== null && \$points >= \$min\)\s*\{\s*\$status\s*=\s*\'passed\'/s',
            $this->baseGuiSource,
            'Passed branch must require non-null points >= min_points'
        );
    }

    public function testBranchFailedFallback(): void
    {
        // Branch 4: result exists, min_points configured, but points missing
        // or below threshold -> failed.
        $this->assertMatchesRegularExpression(
            '/else\s*\{\s*\$status\s*=\s*\'failed\'/s',
            $this->baseGuiSource,
            'Failed branch must be the else fallback'
        );
    }

    public function testMarkFormattingHandlesNullPoints(): void
    {
        // Mark is the points cast to string, or '' if points is null.
        // This must match what updateMemberStatus() writes via setMark().
        $this->assertMatchesRegularExpression(
            '/\'mark\'\s*=>\s*\$points !== null \? \(string\) \$points : \'\'/s',
            $this->baseGuiSource,
            "Mark must be string-cast points, or '' when points is null"
        );
    }

    // -------------------------------------------------------------------
    // The auto-publish block must use the new idempotency check
    // -------------------------------------------------------------------

    public function testAutoPublishGuardUsesNewHelper(): void
    {
        $this->assertStringContainsString(
            '$target = $this->computeTargetMemberStatus($task, $scoreAss);',
            $this->baseGuiSource,
            'Auto-publish block must call the new helper'
        );
    }

    public function testAutoPublishGuardComparesCurrentToTarget(): void
    {
        // The new guard reads the live ILIAS member status and compares it
        // against the predicted target. Only when they differ do we publish.
        $this->assertMatchesRegularExpression(
            '/if \(\$current->getStatus\(\) !== \$target\[\'status\'\]\s*\|\|\s*\$current->getMark\(\) !== \$target\[\'mark\'\]\)/s',
            $this->baseGuiSource,
            'Guard must compare current status/mark against the target'
        );
    }

    public function testOldBrokenGuardIsGone(): void
    {
        // The original guard re-fired forever when updateMemberStatus()
        // could not move the state away from notgraded/empty mark.
        // Make sure nobody re-introduces it.
        $this->assertStringNotContainsString(
            "\$current_status->getStatus() === 'notgraded' || \$current_status->getMark() === ''",
            $this->baseGuiSource,
            'The old broken guard must not reappear in the publish path'
        );
    }

    public function testPublishStillTriggersRedirect(): void
    {
        // The redirect itself is still wanted - it ensures the UI shows
        // fresh data immediately after the first real publish.
        // We only changed *when* it fires, not whether it fires.
        $this->assertStringContainsString(
            'if ($did_publish) {',
            $this->baseGuiSource,
            'did_publish redirect block must still exist'
        );
        $this->assertStringContainsString(
            '$DIC->ctrl()->redirectByClass($current_class, $DIC->ctrl()->getCmd());',
            $this->baseGuiSource,
            'Redirect to current controller must still happen on publish'
        );
    }

    // -------------------------------------------------------------------
    // Defensive: helper logic must mirror updateMemberStatus()
    // -------------------------------------------------------------------

    public function testHelperLogicMatchesUpdateMemberStatus(): void
    {
        // Both functions must agree on the status/mark mapping, otherwise
        // the idempotency check is a lie and the loop returns.
        $taskFile = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $taskSource = file_get_contents($taskFile);

        // updateMemberStatus uses the same gating on getReturnTime()
        $this->assertStringContainsString(
            "if (empty(\$this->getReturnTime())) {",
            $taskSource,
            'updateMemberStatus must still gate on empty getReturnTime()'
        );

        // updateMemberStatus uses the same gating on getMinPoints()
        $this->assertStringContainsString(
            "if (empty(\$scoreAss->getMinPoints())) {",
            $taskSource,
            'updateMemberStatus must still gate on empty getMinPoints()'
        );

        // updateMemberStatus uses the same passed/failed split
        $this->assertStringContainsString(
            "\$this->getReturnPoints() >= \$scoreAss->getMinPoints()",
            $taskSource,
            'updateMemberStatus must keep the >= comparison for passed/failed'
        );

        // updateMemberStatus uses the same mark formatting
        $this->assertStringContainsString(
            "\$mark !== null ? (string) \$mark : ''",
            $taskSource,
            'updateMemberStatus must keep the same null->empty-string mapping for mark'
        );
    }
}
