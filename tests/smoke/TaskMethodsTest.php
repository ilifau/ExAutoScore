<?php

/**
 * Smoke Tests for Task-related methods
 *
 * Tests the new getAffectedUserIds() method behavior without requiring DB access
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../classes/models/class.ilExAutoScoreTask.php';

class TaskMethodsTest extends TestCase
{
    /**
     * Test getAffectedUserIds returns empty array for new Task
     *
     * This tests the edge case where neither user_id nor team_id is set
     */
    public function testGetAffectedUserIdsReturnsEmptyForNewTask()
    {
        // Create a mock Task that doesn't require DB
        $task = $this->getMockBuilder('ilExAutoScoreTask')
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserId', 'getTeamId'])
            ->getMock();

        $task->method('getUserId')->willReturn(null);
        $task->method('getTeamId')->willReturn(null);

        $result = $task->getAffectedUserIds();

        $this->assertIsArray($result, 'getAffectedUserIds should return array');
        $this->assertEmpty($result, 'Should return empty array when no user or team');
    }

    /**
     * Test getAffectedUserIds returns user ID for single user task
     */
    public function testGetAffectedUserIdsReturnsUserIdForSingleUser()
    {
        $task = $this->getMockBuilder('ilExAutoScoreTask')
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserId', 'getTeamId'])
            ->getMock();

        $task->method('getUserId')->willReturn(123);
        $task->method('getTeamId')->willReturn(null);

        $result = $task->getAffectedUserIds();

        $this->assertIsArray($result, 'Should return array');
        $this->assertCount(1, $result, 'Should return exactly one user ID');
        $this->assertEquals([123], $result, 'Should return the user ID in array');
    }

    /**
     * Test that user_id takes precedence over team_id if both are set
     * (this shouldn't happen normally, but tests defensive programming)
     */
    public function testUserIdTakesPrecedenceOverTeamId()
    {
        $task = $this->getMockBuilder('ilExAutoScoreTask')
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserId', 'getTeamId'])
            ->getMock();

        $task->method('getUserId')->willReturn(456);
        $task->method('getTeamId')->willReturn(789);

        $result = $task->getAffectedUserIds();

        $this->assertEquals([456], $result, 'User ID should take precedence');
    }

    /**
     * Test that method signature is correct
     */
    public function testGetAffectedUserIdsSignature()
    {
        $reflection = new ReflectionClass('ilExAutoScoreTask');
        $method = $reflection->getMethod('getAffectedUserIds');

        $this->assertTrue($method->isPublic(), 'Method should be public');
        $this->assertEquals(0, $method->getNumberOfParameters(), 'Method should take no parameters');
    }
}
