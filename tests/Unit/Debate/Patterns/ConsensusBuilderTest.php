<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate\Patterns;

use ClaudeAgents\Debate\DebateSystem;
use ClaudeAgents\Debate\Patterns\ConsensusBuilder;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class ConsensusBuilderTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
    }

    public function testCreateReturnsDebateSystem(): void
    {
        $system = ConsensusBuilder::create($this->client);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateWithDefaultRounds(): void
    {
        $system = ConsensusBuilder::create($this->client);

        $this->assertInstanceOf(DebateSystem::class, $system);
        $this->assertSame(3, $system->getAgentCount());
    }

    public function testCreateWithCustomRounds(): void
    {
        $system = ConsensusBuilder::create($this->client, rounds: 5);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateAddsThreeAgents(): void
    {
        $system = ConsensusBuilder::create($this->client);

        $this->assertSame(3, $system->getAgentCount());
    }

    public function testCreateAddsPragmatist(): void
    {
        $system = ConsensusBuilder::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('pragmatist', $agents);
        $this->assertSame('Pragmatist', $agents['pragmatist']->getName());
        $this->assertSame('practical', $agents['pragmatist']->getPerspective());
    }

    public function testCreateAddsIdealist(): void
    {
        $system = ConsensusBuilder::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('idealist', $agents);
        $this->assertSame('Idealist', $agents['idealist']->getName());
        $this->assertSame('vision', $agents['idealist']->getPerspective());
    }

    public function testCreateAddsMediator(): void
    {
        $system = ConsensusBuilder::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('mediator', $agents);
        $this->assertSame('Mediator', $agents['mediator']->getName());
        $this->assertSame('consensus', $agents['mediator']->getPerspective());
    }

    public function testPragmatistSystemPrompt(): void
    {
        $system = ConsensusBuilder::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['pragmatist']->getSystemPrompt();

        $this->assertStringContainsString('balance', $prompt);
        $this->assertStringContainsString('practical', $prompt);
    }

    public function testIdealistSystemPrompt(): void
    {
        $system = ConsensusBuilder::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['idealist']->getSystemPrompt();

        $this->assertStringContainsString('optimal', $prompt);
        $this->assertStringContainsString('vision', $prompt);
    }

    public function testMediatorSystemPrompt(): void
    {
        $system = ConsensusBuilder::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['mediator']->getSystemPrompt();

        $this->assertStringContainsString('common ground', $prompt);
        $this->assertStringContainsString('agreement', $prompt);
    }
}
