<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate\Patterns;

use ClaudeAgents\Debate\DebateSystem;
use ClaudeAgents\Debate\Patterns\DevilsAdvocate;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class DevilsAdvocateTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
    }

    public function testCreateReturnsDebateSystem(): void
    {
        $system = DevilsAdvocate::create($this->client);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateWithDefaultRounds(): void
    {
        $system = DevilsAdvocate::create($this->client);

        $this->assertInstanceOf(DebateSystem::class, $system);
        $this->assertSame(2, $system->getAgentCount());
    }

    public function testCreateWithCustomRounds(): void
    {
        $system = DevilsAdvocate::create($this->client, rounds: 5);

        $this->assertInstanceOf(DebateSystem::class, $system);
    }

    public function testCreateAddsTwoAgents(): void
    {
        $system = DevilsAdvocate::create($this->client);

        $this->assertSame(2, $system->getAgentCount());
    }

    public function testCreateAddsProposer(): void
    {
        $system = DevilsAdvocate::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('proposer', $agents);
        $this->assertSame('Proposer', $agents['proposer']->getName());
        $this->assertSame('advocate', $agents['proposer']->getPerspective());
    }

    public function testCreateAddsDevilsAdvocate(): void
    {
        $system = DevilsAdvocate::create($this->client);
        $agents = $system->getAgents();

        $this->assertArrayHasKey('devil', $agents);
        $this->assertSame("Devil's Advocate", $agents['devil']->getName());
        $this->assertSame('challenger', $agents['devil']->getPerspective());
    }

    public function testProposerSystemPrompt(): void
    {
        $system = DevilsAdvocate::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['proposer']->getSystemPrompt();

        $this->assertStringContainsString('advocate', $prompt);
        $this->assertStringContainsString('proposal', $prompt);
    }

    public function testDevilsAdvocateSystemPrompt(): void
    {
        $system = DevilsAdvocate::create($this->client);
        $agents = $system->getAgents();

        $prompt = $agents['devil']->getSystemPrompt();

        $this->assertStringContainsString('challenge', $prompt);
        $this->assertStringContainsString('flaws', $prompt);
        $this->assertStringContainsString('skeptical', $prompt);
    }
}
