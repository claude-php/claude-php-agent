<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate\Patterns;

use ClaudeAgents\Debate\DebateSystem;
use ClaudeAgents\Debate\Patterns\RoundTableDebate;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class RoundTableDebateTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
    }

    public function testCreateReturnsDebateSystem(): void
    {
        $system = RoundTableDebate::create($this->client);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateWithDefaultRounds(): void
    {
        $system = RoundTableDebate::create($this->client);

        $this->assertInstanceOf(DebateSystem::class, $system);
        $this->assertSame(4, $system->getAgentCount());
    }

    public function testCreateWithCustomRounds(): void
    {
        $system = RoundTableDebate::create($this->client, rounds: 5);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateAddsFourAgents(): void
    {
        $system = RoundTableDebate::create($this->client);

        $this->assertSame(4, $system->getAgentCount());
    }

    public function testCreateAddsUserAdvocate(): void
    {
        $system = RoundTableDebate::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('user', $agents);
        $this->assertSame('User Advocate', $agents['user']->getName());
        $this->assertSame('user-focused', $agents['user']->getPerspective());
    }

    public function testCreateAddsEngineer(): void
    {
        $system = RoundTableDebate::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('engineer', $agents);
        $this->assertSame('Engineer', $agents['engineer']->getName());
        $this->assertSame('technical', $agents['engineer']->getPerspective());
    }

    public function testCreateAddsBusinessAnalyst(): void
    {
        $system = RoundTableDebate::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('business', $agents);
        $this->assertSame('Business Analyst', $agents['business']->getName());
        $this->assertSame('business', $agents['business']->getPerspective());
    }

    public function testCreateAddsDesigner(): void
    {
        $system = RoundTableDebate::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('designer', $agents);
        $this->assertSame('Designer', $agents['designer']->getName());
        $this->assertSame('design', $agents['designer']->getPerspective());
    }

    public function testUserAdvocateSystemPrompt(): void
    {
        $system = RoundTableDebate::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['user']->getSystemPrompt();

        $this->assertStringContainsString('user', $prompt);
        $this->assertStringContainsString('experience', $prompt);
        $this->assertStringContainsString('usability', $prompt);
    }

    public function testEngineerSystemPrompt(): void
    {
        $system = RoundTableDebate::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['engineer']->getSystemPrompt();

        $this->assertStringContainsString('technical', $prompt);
        $this->assertStringContainsString('feasibility', $prompt);
        $this->assertStringContainsString('maintainability', $prompt);
    }

    public function testBusinessAnalystSystemPrompt(): void
    {
        $system = RoundTableDebate::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['business']->getSystemPrompt();

        $this->assertStringContainsString('ROI', $prompt);
        $this->assertStringContainsString('business', $prompt);
    }

    public function testDesignerSystemPrompt(): void
    {
        $system = RoundTableDebate::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['designer']->getSystemPrompt();

        $this->assertStringContainsString('UX', $prompt);
        $this->assertStringContainsString('design', $prompt);
        $this->assertStringContainsString('experience', $prompt);
    }
}
