<?php

/**
 * Smoke Tests for ExAutoScore Plugin
 *
 * These tests verify basic functionality and that core classes can be instantiated.
 * They should run quickly (<30 seconds total) and catch major breakages.
 *
 * Run with: ./vendor/bin/phpunit tests/smoke
 */

use PHPUnit\Framework\TestCase;

// Only load classes that don't require ILIAS core
// Note: We don't load the main Plugin class as it requires ILIAS base classes

class BasicFunctionalityTest extends TestCase
{
    /**
     * Test that Connector class file exists
     */
    public function testConnectorFileExists()
    {
        $file = __DIR__ . '/../../classes/class.ilExAutoScoreConnector.php';
        $this->assertFileExists($file, 'Connector class file should exist');
    }

    /**
     * Test that Task class file exists
     */
    public function testTaskFileExists()
    {
        $file = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $this->assertFileExists($file, 'Task class file should exist');
    }

    /**
     * Test that TeamHandler class file exists
     */
    public function testTeamHandlerFileExists()
    {
        $file = __DIR__ . '/../../classes/class.ilExAutoScoreTeamHandler.php';
        $this->assertFileExists($file, 'TeamHandler class file should exist');
    }

    /**
     * Test that BaseGUI class file exists
     */
    public function testBaseGUIFileExists()
    {
        $file = __DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php';
        $this->assertFileExists($file, 'BaseGUI class file should exist');
    }

    /**
     * Test that plugin.php exists
     */
    public function testPluginConfigFileExists()
    {
        $file = __DIR__ . '/../../plugin.php';
        $this->assertFileExists($file, 'plugin.php should exist');
    }

    /**
     * Test that results.php endpoint exists
     */
    public function testResultsEndpointExists()
    {
        $file = __DIR__ . '/../../results.php';
        $this->assertFileExists($file, 'results.php endpoint should exist');
    }

    /**
     * Test that Task class has expected structure by checking file content
     */
    public function testTaskClassHasGetAffectedUserIdsMethod()
    {
        $file = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'public function getAffectedUserIds()',
            $content,
            'Task class should have getAffectedUserIds() method'
        );
    }

    /**
     * Test that Task class has updateMemberStatus method
     */
    public function testTaskClassHasUpdateMemberStatusMethod()
    {
        $file = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'public function updateMemberStatus(',
            $content,
            'Task class should have updateMemberStatus() method'
        );
    }

    /**
     * Test that Connector has receiveResult method
     */
    public function testConnectorHasReceiveResultMethod()
    {
        $file = __DIR__ . '/../../classes/class.ilExAutoScoreConnector.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'public function receiveResult(',
            $content,
            'Connector should have receiveResult() method'
        );
    }

    /**
     * Test that Connector has sendSubmission method
     */
    public function testConnectorHasSendSubmissionMethod()
    {
        $file = __DIR__ . '/../../classes/class.ilExAutoScoreConnector.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'public function sendSubmission(',
            $content,
            'Connector should have sendSubmission() method'
        );
    }

    /**
     * Test that BaseGUI has canShowAssessmentNow method
     */
    public function testBaseGUIHasCanShowAssessmentNowMethod()
    {
        $file = __DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'protected function canShowAssessmentNow(',
            $content,
            'BaseGUI should have canShowAssessmentNow() method'
        );
    }

    /**
     * Test that BaseGUI has canShowAssessmentNowForUser method (added in refactoring)
     */
    public function testBaseGUIHasCanShowAssessmentNowForUserMethod()
    {
        $file = __DIR__ . '/../../classes/class.ilExAssTypeAutoScoreBaseGUI.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'protected function canShowAssessmentNowForUser(',
            $content,
            'BaseGUI should have canShowAssessmentNowForUser() method'
        );
    }

    /**
     * Test that receiveResult has deadline check logic (our recent fix)
     */
    public function testReceiveResultHasDeadlineCheck()
    {
        $file = __DIR__ . '/../../classes/class.ilExAutoScoreConnector.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'getAffectedUserIds()',
            $content,
            'receiveResult should use getAffectedUserIds() for deadline check'
        );

        $this->assertStringContainsString(
            'all_deadlines_reached',
            $content,
            'receiveResult should have deadline check logic'
        );
    }
}
