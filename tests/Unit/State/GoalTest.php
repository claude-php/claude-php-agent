<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\State;

use ClaudeAgents\State\Goal;
use ClaudeAgents\State\GoalStatus;
use PHPUnit\Framework\TestCase;

class GoalTest extends TestCase
{
    public function testConstruction(): void
    {
        $goal = new Goal('Test goal');

        $this->assertEquals('Test goal', $goal->getDescription());
        $this->assertEquals('not_started', $goal->getStatus());
        $this->assertEquals(0, $goal->getProgressPercentage());
    }

    public function testSetStatus(): void
    {
        $goal = new Goal('Test goal');
        $goal->setStatus('in_progress');

        $this->assertEquals('in_progress', $goal->getStatus());
    }

    public function testSetProgressPercentage(): void
    {
        $goal = new Goal('Test goal');
        $goal->setProgressPercentage(50);

        $this->assertEquals(50, $goal->getProgressPercentage());
    }

    public function testSetProgressPercentageClampsValues(): void
    {
        $goal = new Goal('Test goal');

        $goal->setProgressPercentage(150);
        $this->assertEquals(100, $goal->getProgressPercentage());

        $goal->setProgressPercentage(-10);
        $this->assertEquals(0, $goal->getProgressPercentage());
    }

    public function testCompleteSubgoal(): void
    {
        $goal = new Goal('Test goal');
        $goal->completeSubgoal('Subgoal 1');
        $goal->completeSubgoal('Subgoal 2');

        $subgoals = $goal->getCompletedSubgoals();

        $this->assertCount(2, $subgoals);
        $this->assertContains('Subgoal 1', $subgoals);
        $this->assertContains('Subgoal 2', $subgoals);
    }

    public function testCompleteSubgoalIgnoresDuplicates(): void
    {
        $goal = new Goal('Test goal');
        $goal->completeSubgoal('Subgoal 1');
        $goal->completeSubgoal('Subgoal 1');

        $this->assertCount(1, $goal->getCompletedSubgoals());
    }

    public function testMetadata(): void
    {
        $goal = new Goal('Test goal');
        $goal->setMetadataValue('key', 'value');

        $this->assertEquals('value', $goal->getMetadataValue('key'));
        $this->assertEquals(['key' => 'value'], $goal->getMetadata());
    }

    public function testMetadataWithDefault(): void
    {
        $goal = new Goal('Test goal');

        $this->assertEquals('default', $goal->getMetadataValue('nonexistent', 'default'));
    }

    public function testComplete(): void
    {
        $goal = new Goal('Test goal');
        $goal->complete();

        $this->assertEquals('completed', $goal->getStatus());
        $this->assertEquals(100, $goal->getProgressPercentage());
        $this->assertTrue($goal->isComplete());
    }

    public function testStart(): void
    {
        $goal = new Goal('Test goal');
        $goal->start();

        $this->assertEquals('in_progress', $goal->getStatus());
        $this->assertEquals(10, $goal->getProgressPercentage());
    }

    public function testStartDoesNotResetProgress(): void
    {
        $goal = new Goal('Test goal');
        $goal->setProgressPercentage(50);
        $goal->start();

        $this->assertEquals(50, $goal->getProgressPercentage());
    }

    public function testIsComplete(): void
    {
        $goal = new Goal('Test goal');

        $this->assertFalse($goal->isComplete());

        $goal->complete();

        $this->assertTrue($goal->isComplete());
    }

    public function testToArray(): void
    {
        $goal = new Goal('Test goal');
        $goal->setStatus('in_progress');
        $goal->setProgressPercentage(50);
        $goal->completeSubgoal('Subgoal 1');
        $goal->setMetadataValue('key', 'value');

        $array = $goal->toArray();

        $this->assertEquals('Test goal', $array['description']);
        $this->assertEquals('in_progress', $array['status']);
        $this->assertEquals(50, $array['progress_percentage']);
        $this->assertContains('Subgoal 1', $array['completed_subgoals']);
        $this->assertEquals('value', $array['metadata']['key']);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('created_at', $array);
    }

    public function testEmptyDescriptionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Goal description cannot be empty');

        new Goal('');
    }

    public function testWhitespaceDescriptionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Goal description cannot be empty');

        new Goal('   ');
    }

    public function testInvalidStatusUsesDefault(): void
    {
        $goal = new Goal('Test goal', 'invalid_status');

        $this->assertEquals('not_started', $goal->getStatus());
    }

    public function testSetInvalidStatusThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid goal status');

        $goal = new Goal('Test goal');
        $goal->setStatus('invalid_status');
    }

    public function testStatusEnum(): void
    {
        $goal = new Goal('Test goal', GoalStatus::IN_PROGRESS);

        $this->assertEquals('in_progress', $goal->getStatus());
        $this->assertEquals(GoalStatus::IN_PROGRESS, $goal->getStatusEnum());
    }

    public function testPause(): void
    {
        $goal = new Goal('Test goal');
        $goal->start();
        $goal->pause();

        $this->assertEquals('paused', $goal->getStatus());
        $this->assertTrue($goal->isPaused());
    }

    public function testCancel(): void
    {
        $goal = new Goal('Test goal');
        $goal->start();
        $goal->cancel();

        $this->assertEquals('cancelled', $goal->getStatus());
        $this->assertTrue($goal->isCancelled());
    }

    public function testFail(): void
    {
        $goal = new Goal('Test goal');
        $goal->start();
        $goal->fail();

        $this->assertEquals('failed', $goal->getStatus());
        $this->assertTrue($goal->isFailed());
    }

    public function testIsInProgress(): void
    {
        $goal = new Goal('Test goal');
        $goal->start();

        $this->assertTrue($goal->isInProgress());
    }

    public function testGetId(): void
    {
        $goal = new Goal('Test goal');

        $this->assertNotEmpty($goal->getId());
        $this->assertStringStartsWith('goal_', $goal->getId());
    }

    public function testGetCreatedAt(): void
    {
        $goal = new Goal('Test goal');

        $this->assertGreaterThan(0, $goal->getCreatedAt());
        $this->assertLessThanOrEqual(time(), $goal->getCreatedAt());
    }

    public function testCreateFromArray(): void
    {
        $data = [
            'id' => 'goal_123',
            'description' => 'Test goal',
            'status' => 'in_progress',
            'progress_percentage' => 50,
            'completed_subgoals' => ['Sub 1'],
            'metadata' => ['key' => 'value'],
            'created_at' => 1234567890,
        ];

        $goal = Goal::createFromArray($data);

        $this->assertEquals('goal_123', $goal->getId());
        $this->assertEquals('Test goal', $goal->getDescription());
        $this->assertEquals('in_progress', $goal->getStatus());
        $this->assertEquals(50, $goal->getProgressPercentage());
        $this->assertContains('Sub 1', $goal->getCompletedSubgoals());
        $this->assertEquals('value', $goal->getMetadataValue('key'));
        $this->assertEquals(1234567890, $goal->getCreatedAt());
    }

    public function testToJson(): void
    {
        $goal = new Goal('Test goal');
        $json = $goal->toJson();

        $this->assertJson($json);
        $data = json_decode($json, true);
        $this->assertEquals('Test goal', $data['description']);
    }

    public function testCreateFromJson(): void
    {
        $json = json_encode([
            'description' => 'Test goal',
            'status' => 'in_progress',
            'progress_percentage' => 75,
        ]);

        $goal = Goal::createFromJson($json);

        $this->assertEquals('Test goal', $goal->getDescription());
        $this->assertEquals('in_progress', $goal->getStatus());
        $this->assertEquals(75, $goal->getProgressPercentage());
    }

    public function testGetStateId(): void
    {
        $goal = new Goal('Test goal');

        $this->assertEquals($goal->getId(), $goal->getStateId());
    }

    public function testGetVersion(): void
    {
        $goal = new Goal('Test goal');

        $this->assertEquals('1.0', $goal->getVersion());
    }

    public function testProgressPercentageClampedInConstructor(): void
    {
        $goal1 = new Goal('Test', 'not_started', 150);
        $this->assertEquals(100, $goal1->getProgressPercentage());

        $goal2 = new Goal('Test', 'not_started', -10);
        $this->assertEquals(0, $goal2->getProgressPercentage());
    }
}
