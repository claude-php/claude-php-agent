<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\State;

use ClaudeAgents\State\GoalStatus;
use PHPUnit\Framework\TestCase;

class GoalStatusTest extends TestCase
{
    public function testValues(): void
    {
        $values = GoalStatus::values();

        $this->assertContains('not_started', $values);
        $this->assertContains('in_progress', $values);
        $this->assertContains('completed', $values);
        $this->assertContains('paused', $values);
        $this->assertContains('cancelled', $values);
        $this->assertContains('failed', $values);
        $this->assertCount(6, $values);
    }

    public function testIsValid(): void
    {
        $this->assertTrue(GoalStatus::isValid('not_started'));
        $this->assertTrue(GoalStatus::isValid('in_progress'));
        $this->assertTrue(GoalStatus::isValid('completed'));
        $this->assertFalse(GoalStatus::isValid('invalid_status'));
        $this->assertFalse(GoalStatus::isValid(''));
    }

    public function testFromString(): void
    {
        $this->assertEquals(GoalStatus::IN_PROGRESS, GoalStatus::fromString('in_progress'));
        $this->assertEquals(GoalStatus::COMPLETED, GoalStatus::fromString('completed'));
        $this->assertEquals(GoalStatus::NOT_STARTED, GoalStatus::fromString('invalid'));
    }

    public function testEnumCases(): void
    {
        $this->assertEquals('not_started', GoalStatus::NOT_STARTED->value);
        $this->assertEquals('in_progress', GoalStatus::IN_PROGRESS->value);
        $this->assertEquals('completed', GoalStatus::COMPLETED->value);
        $this->assertEquals('paused', GoalStatus::PAUSED->value);
        $this->assertEquals('cancelled', GoalStatus::CANCELLED->value);
        $this->assertEquals('failed', GoalStatus::FAILED->value);
    }

    public function testTryFrom(): void
    {
        $this->assertEquals(GoalStatus::IN_PROGRESS, GoalStatus::tryFrom('in_progress'));
        $this->assertNull(GoalStatus::tryFrom('invalid'));
    }

    public function testFrom(): void
    {
        $this->assertEquals(GoalStatus::COMPLETED, GoalStatus::from('completed'));

        $this->expectException(\ValueError::class);
        GoalStatus::from('invalid');
    }
}
