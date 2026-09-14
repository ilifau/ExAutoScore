<?php

/**
 * Smoke tests for reusing the grading service's UUID when an assignment is set up again.
 *
 * Every "Einrichten" used to create a new assignment on the grading service, and with
 * it a new Docker image of several GB; the previous image stayed tagged and was never
 * cleaned up (August 2026: 62 such images, 48 GB). The plugin now sends the UUID it
 * already has, and the service replaces that assignment instead.
 *
 * The UUID needed for this cannot be exautoscore_assignment.uuid alone:
 * resetCorrection() clears it whenever support files or settings change, which is
 * exactly when the lecturer sets the assignment up again. Hence service_uuid, which
 * survives the reset.
 *
 * Source-inspection tests, per project convention (no ILIAS runtime needed).
 */

use PHPUnit\Framework\TestCase;

class ServiceUuidReuseTest extends TestCase
{
    /** @var string */
    private $connector;
    /** @var string */
    private $assignmentModel;
    /** @var string */
    private $dbUpdate;

    protected function setUp(): void
    {
        $base = __DIR__ . '/../../';
        $this->connector = file_get_contents($base . 'classes/class.ilExAutoScoreConnector.php');
        $this->assignmentModel = file_get_contents($base . 'classes/models/class.ilExAutoScoreAssignment.php');
        $this->dbUpdate = file_get_contents($base . 'sql/dbupdate.php');
    }

    /** Body of a method, from its signature to the next method signature. */
    private function method(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertNotFalse($start, "method $name() not found");
        $next = strpos($source, 'function ', $start + 10);
        return substr($source, $start, $next === false ? null : $next - $start);
    }

    public function testDbUpdateAddsAndBackfillsServiceUuid(): void
    {
        $this->assertMatchesRegularExpression(
            "/<#\\d+>.*addTableColumn\\(\"exautoscore_assignment\", 'service_uuid'/s",
            $this->dbUpdate,
            'service_uuid must be added in a dbupdate step'
        );
        $this->assertStringContainsString(
            'SET service_uuid = uuid',
            $this->dbUpdate,
            'existing assignments must benefit on their next setup, not only new ones'
        );
    }

    public function testModelDeclaresServiceUuidAsActiveRecordField(): void
    {
        $this->assertMatchesRegularExpression(
            '/@con_has_field\s+true.*?protected \?string \$service_uuid/s',
            $this->assignmentModel
        );
        $this->assertStringContainsString('public function getServiceUuid()', $this->assignmentModel);
        $this->assertStringContainsString('public function setServiceUuid(string $service_uuid)', $this->assignmentModel);
    }

    public function testResetClearsUuidButKeepsServiceUuid(): void
    {
        $reset = $this->method($this->assignmentModel, 'resetCorrection');
        $this->assertStringContainsString("setUuid('')", $reset, 'reset must still block submissions');
        $this->assertStringNotContainsString(
            'setServiceUuid',
            $reset,
            'clearing service_uuid on reset would bring back one image per setup'
        );
    }

    public function testSendAssignmentPostsThePreviousUuid(): void
    {
        $send = $this->method($this->connector, 'sendAssignment');

        $postUuid = strpos($send, "\$post['uuid']");
        $call = strpos($send, 'callService(');
        $this->assertNotFalse($postUuid, "sendAssignment must post 'uuid'");
        $this->assertLessThan($call, $postUuid, "'uuid' must be set before the request is sent");

        $this->assertMatchesRegularExpression(
            '/getServiceUuid\(\).*getUuid\(\)/s',
            substr($send, 0, $postUuid),
            'service_uuid must take precedence: uuid is empty right after a reset'
        );
    }

    public function testSendAssignmentRemembersTheServiceUuidOnSuccess(): void
    {
        $send = $this->method($this->connector, 'sendAssignment');
        $this->assertMatchesRegularExpression(
            '/if \(\$success\) \{[^}]*setUuid\([^}]*setServiceUuid\([^}]*save\(\)/s',
            $send
        );
    }
}
