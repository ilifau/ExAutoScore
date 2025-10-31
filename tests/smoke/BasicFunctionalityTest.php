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

require_once __DIR__ . '/../../classes/class.ilExAutoScorePlugin.php';
require_once __DIR__ . '/../../classes/class.ilExAutoScoreConnector.php';
require_once __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
require_once __DIR__ . '/../../classes/class.ilExAutoScoreTeamHandler.php';

class BasicFunctionalityTest extends TestCase
{
    /**
     * Test that plugin class exists and can be loaded
     */
    public function testPluginClassExists()
    {
        $this->assertTrue(
            class_exists('ilExAutoScorePlugin'),
            'Plugin class should exist'
        );
    }

    /**
     * Test that Connector class can be instantiated
     */
    public function testConnectorCanInstantiate()
    {
        $this->assertTrue(
            class_exists('ilExAutoScoreConnector'),
            'Connector class should exist'
        );

        // Note: Full instantiation requires ILIAS globals, so we just check class existence
    }

    /**
     * Test that Task class exists
     */
    public function testTaskClassExists()
    {
        $this->assertTrue(
            class_exists('ilExAutoScoreTask'),
            'Task class should exist'
        );
    }

    /**
     * Test that TeamHandler class exists
     */
    public function testTeamHandlerClassExists()
    {
        $this->assertTrue(
            class_exists('ilExAutoScoreTeamHandler'),
            'TeamHandler class should exist'
        );
    }

    /**
     * Test that getAffectedUserIds method exists on Task
     */
    public function testGetAffectedUserIdsMethodExists()
    {
        $this->assertTrue(
            method_exists('ilExAutoScoreTask', 'getAffectedUserIds'),
            'getAffectedUserIds() method should exist on Task class'
        );
    }

    /**
     * Test that updateMemberStatus method exists on Task
     */
    public function testUpdateMemberStatusMethodExists()
    {
        $this->assertTrue(
            method_exists('ilExAutoScoreTask', 'updateMemberStatus'),
            'updateMemberStatus() method should exist on Task class'
        );
    }

    /**
     * Test that receiveResult method exists on Connector
     */
    public function testReceiveResultMethodExists()
    {
        $this->assertTrue(
            method_exists('ilExAutoScoreConnector', 'receiveResult'),
            'receiveResult() method should exist on Connector class'
        );
    }

    /**
     * Test that sendSubmission method exists on Connector
     */
    public function testSendSubmissionMethodExists()
    {
        $this->assertTrue(
            method_exists('ilExAutoScoreConnector', 'sendSubmission'),
            'sendSubmission() method should exist on Connector class'
        );
    }
}
