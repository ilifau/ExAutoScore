<?php

/**
 * Tests for the single deadline rule (ilExAutoScoreDeadline) and its use.
 *
 * Before, six places computed "personal deadline, else general deadline" on their
 * own. That ignored the grace period and relative deadlines (deadline = 0 was read
 * as "no deadline", so results were shown and published immediately).
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../classes/class.ilExAutoScoreDeadline.php';

class DeadlineTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(__DIR__ . '/../../classes/' . $path);
    }

    public function testNoDeadlineMeansNoProtection(): void
    {
        $this->assertTrue(ilExAutoScoreDeadline::isOver(0, 1000));
    }

    public function testBeforeAndAtTheDeadlineIsNotOver(): void
    {
        $this->assertFalse(ilExAutoScoreDeadline::isOver(1000, 999));
        // ILIAS (ilExcAssMemberState::hasSubmissionEnded) still accepts submissions at the deadline second
        $this->assertFalse(ilExAutoScoreDeadline::isOver(1000, 1000));
    }

    public function testAfterTheDeadlineIsOver(): void
    {
        $this->assertTrue(ilExAutoScoreDeadline::isOver(1000, 1001));
    }

    /** The rule must come from ILIAS, which includes grace period and relative deadlines. */
    public function testUsesTheEffectiveDeadlineOfIlias(): void
    {
        $this->assertStringContainsString(
            'ilExcAssMemberState::getInstanceByIds($ass->getId(), $user_id)->getEffectiveDeadline()',
            $this->source('class.ilExAutoScoreDeadline.php')
        );
    }

    /** Since ILIAS 10 the core finds the team of a member only for TYPE_UPLOAD_TEAM. */
    public function testTeamDeadlineOfPluginTeamTypes(): void
    {
        $source = $this->source('class.ilExAutoScoreDeadline.php');
        $this->assertStringContainsString('->usesTeams() && $ass->getType() != ilExAssignment::TYPE_UPLOAD_TEAM', $source);
        $this->assertStringContainsString('ilExcIndividualDeadline::getInstance($ass->getId(), $team_id, true)', $source);
        $this->assertStringContainsString('ilExcAssMemberState::getInstance($ass, ', $source);
    }

    /** No grading or feedback code computes the deadline on its own any more. */
    public function testNoOwnDeadlineComputation(): void
    {
        foreach (['class.ilExAssTypeAutoScoreBaseGUI.php', 'class.ilExAutoScoreConnector.php', 'models/class.ilExAutoScoreTask.php'] as $file) {
            $source = $this->source($file);
            $this->assertStringNotContainsString('$effective_deadline', $source, $file);
            $this->assertStringNotContainsString('$general_deadline', $source, $file);
        }
    }

    public static function gateProvider(): array
    {
        return [
            'assessment'        => ['class.ilExAssTypeAutoScoreBaseGUI.php', 'canShowAssessmentNow', 'ilExAutoScoreDeadline::hasEnded('],
            'assessment of user'=> ['class.ilExAssTypeAutoScoreBaseGUI.php', 'canShowAssessmentNowForUser', 'ilExAutoScoreDeadline::hasEnded('],
            'extended feedback' => ['class.ilExAssTypeAutoScoreBaseGUI.php', 'canShowExtendedFeedbackByDeadline', '$this->canShowAssessmentNow('],
            'feedback files'    => ['class.ilExAutoScoreConnector.php', 'saveFeedbackFiles', 'ilExAutoScoreDeadline::hasEndedForAll('],
            'publish'           => ['models/class.ilExAutoScoreTask.php', 'publishToMemberStatusIfDue', 'ilExAutoScoreDeadline::hasEnded('],
            'member status'     => ['models/class.ilExAutoScoreTask.php', 'updateMemberStatus', 'ilExAutoScoreDeadline::hasEnded('],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('gateProvider')]
    public function testGateUsesTheRule(string $file, string $method, string $call): void
    {
        $source = $this->source($file);
        $start = strpos($source, 'function ' . $method . '(');
        $this->assertNotFalse($start, "$method() not found");
        // up to the next method (a body may contain closures)
        $next = preg_match('/\n\s*(public|protected|private) (static )?function /', $source, $m, PREG_OFFSET_CAPTURE, $start + 10)
            ? $m[0][1] : null;
        $body = substr($source, $start, $next === null ? null : $next - $start);
        $this->assertStringContainsString($call, $body);
    }

    /** The fingerprint is taken from exactly the values written into the member status. */
    public function testNoteHashMatchesTheWrittenValues(): void
    {
        $task = $this->source('models/class.ilExAutoScoreTask.php');
        $this->assertMatchesRegularExpression(
            '/setComment\(\$comment\);.*?setStatus\(\$status\);.*?setMark\(\$mark_text\);.*?\$note_hash = self::noteHash\(\$status, \$mark_text, \$comment\);/s',
            $task
        );
        $this->assertStringContainsString(
            'self::noteHash($status->getStatus(), $status->getMark(), $status->getComment())',
            $task
        );
    }
}
