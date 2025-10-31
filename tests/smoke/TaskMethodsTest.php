<?php

/**
 * Smoke Tests for Task-related methods
 *
 * Tests the new getAffectedUserIds() method behavior without requiring DB access
 */

use PHPUnit\Framework\TestCase;

// Note: We can't actually instantiate ilExAutoScoreTask without ILIAS DB
// These tests verify the method exists in the source code

class TaskMethodsTest extends TestCase
{
    /**
     * Test that getAffectedUserIds method exists in source code
     */
    public function testGetAffectedUserIdsMethodExistsInSource()
    {
        $file = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'public function getAffectedUserIds(): array',
            $content,
            'getAffectedUserIds() method should exist with correct signature'
        );
    }

    /**
     * Test that getAffectedUserIds handles user case
     */
    public function testGetAffectedUserIdsHandlesUserCase()
    {
        $file = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $content = file_get_contents($file);

        // Check that method checks for user_id
        $this->assertStringContainsString(
            'getUserId()',
            $content,
            'Method should check getUserId()'
        );

        // Check that it returns array with user_id
        $this->assertStringContainsString(
            'return [$this->getUserId()];',
            $content,
            'Method should return array with user_id for single user'
        );
    }

    /**
     * Test that getAffectedUserIds handles team case
     */
    public function testGetAffectedUserIdsHandlesTeamCase()
    {
        $file = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $content = file_get_contents($file);

        // Check that method checks for team_id
        $this->assertStringContainsString(
            'getTeamId()',
            $content,
            'Method should check getTeamId()'
        );

        // Check that it uses ilExAssignmentTeam
        $this->assertStringContainsString(
            'ilExAssignmentTeam',
            $content,
            'Method should use ilExAssignmentTeam for teams'
        );

        // Check that it calls getMembers()
        $this->assertStringContainsString(
            'getMembers()',
            $content,
            'Method should call getMembers() on team'
        );
    }

    /**
     * Test that getAffectedUserIds returns empty array as fallback
     */
    public function testGetAffectedUserIdsHasEmptyFallback()
    {
        $file = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString(
            'return [];',
            $content,
            'Method should return empty array as fallback'
        );
    }

    /**
     * Test that getAffectedUserIds is used in updateMemberStatus
     */
    public function testGetAffectedUserIdsIsUsedInUpdateMemberStatus()
    {
        $file = __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';
        $content = file_get_contents($file);

        // Find updateMemberStatus method
        $this->assertStringContainsString(
            'public function updateMemberStatus(',
            $content,
            'updateMemberStatus method should exist'
        );

        // Check that it uses getAffectedUserIds()
        $this->assertStringContainsString(
            '$this->getAffectedUserIds()',
            $content,
            'updateMemberStatus should use getAffectedUserIds()'
        );
    }
}
