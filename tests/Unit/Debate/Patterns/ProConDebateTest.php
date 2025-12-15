<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate\Patterns;

use ClaudeAgents\Debate\DebateSystem;
use ClaudeAgents\Debate\Patterns\ProConDebate;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class ProConDebateTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
    }

    public function testCreateReturnsDebateSystem(): void
    {
        $system = ProConDebate::create($this->client, 'Test topic');

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateWithDefaultRounds(): void
    {
        $system = ProConDebate::create($this->client, 'Test topic');

        $this->assertInstanceOf(DebateSystem::class, $system);
        $this->assertSame(2, $system->getAgentCount());
    }

    public function testCreateWithCustomRounds(): void
    {
        $system = ProConDebate::create($this->client, 'Test topic', rounds: 5);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateAddsTwoAgents(): void
    {
        $system = ProConDebate::create($this->client, 'Should we adopt remote work?');

        $this->assertSame(2, $system->getAgentCount());
    }

    public function testCreateAddsProAgent(): void
    {
        $system = ProConDebate::create($this->client, 'Test topic');
        $agents = $system->getAgents();

        $this->assertArrayHasKey('pro', $agents);
        $this->assertSame('Proponent', $agents['pro']->getName());
        $this->assertSame('support', $agents['pro']->getPerspective());
    }

    public function testCreateAddsConAgent(): void
    {
        $system = ProConDebate::create($this->client, 'Test topic');
        $agents = $system->getAgents();

        $this->assertArrayHasKey('con', $agents);
        $this->assertSame('Opponent', $agents['con']->getName());
        $this->assertSame('oppose', $agents['con']->getPerspective());
    }

    public function testProAgentSystemPrompt(): void
    {
        $system = ProConDebate::create($this->client, 'Test topic');
        $agents = $system->getAgents();

        $proPrompt = $agents['pro']->getSystemPrompt();

        $this->assertStringContainsString('advocate', $proPrompt);
        $this->assertStringContainsString('benefits', $proPrompt);
        $this->assertStringContainsString('positive', $proPrompt);
    }

    public function testConAgentSystemPrompt(): void
    {
        $system = ProConDebate::create($this->client, 'Test topic');
        $agents = $system->getAgents();

        $conPrompt = $agents['con']->getSystemPrompt();

        $this->assertStringContainsString('challenge', $conPrompt);
        $this->assertStringContainsString('risks', $conPrompt);
        $this->assertStringContainsString('critical', $conPrompt);
    }

    public function testCreateIsIdempotent(): void
    {
        $system1 = ProConDebate::create($this->client, 'Topic 1');
        $system2 = ProConDebate::create($this->client, 'Topic 2');

        $this->assertNotSame($system1, $system2);
        $this->assertSame(2, $system1->getAgentCount());
        $this->assertSame(2, $system2->getAgentCount());
    }
}
