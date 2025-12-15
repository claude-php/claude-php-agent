<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration;

use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;
use ClaudeAgents\Debate\Patterns\ConsensusBuilder;
use ClaudeAgents\Debate\Patterns\DevilsAdvocate;
use ClaudeAgents\Debate\Patterns\ProConDebate;
use ClaudeAgents\Debate\Patterns\RoundTableDebate;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Debate functionality.
 * These tests require an actual API key and make real API calls.
 *
 * To run: PHPUnit_INTEGRATION_TESTS=1 vendor/bin/phpunit tests/Integration/DebateIntegrationTest.php
 */
class DebateIntegrationTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        if (! getenv('PHPUNIT_INTEGRATION_TESTS')) {
            $this->markTestSkipped('Integration tests are disabled. Set PHPUNIT_INTEGRATION_TESTS=1 to run.');
        }

        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY environment variable not set.');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
    }

    public function testBasicDebateWithTwoAgents(): void
    {
        $proAgent = new DebateAgent(
            $this->client,
            'Proponent',
            'support',
            'You support the proposal. Present benefits and positive outcomes.'
        );

        $conAgent = new DebateAgent(
            $this->client,
            'Opponent',
            'oppose',
            'You oppose the proposal. Present risks and concerns.'
        );

        $system = DebateSystem::create($this->client)
            ->addAgent('pro', $proAgent)
            ->addAgent('con', $conAgent)
            ->rounds(1);

        $result = $system->debate('Should companies allow 4-day work weeks?');

        $this->assertSame('Should companies allow 4-day work weeks?', $result->getTopic());
        $this->assertSame(1, $result->getRoundCount());
        $this->assertNotEmpty($result->getSynthesis());
        $this->assertIsFloat($result->getAgreementScore());
        $this->assertGreaterThanOrEqual(0.0, $result->getAgreementScore());
        $this->assertLessThanOrEqual(1.0, $result->getAgreementScore());

        // Check that both agents spoke
        $rounds = $result->getRounds();
        $this->assertCount(1, $rounds);
        $statements = $rounds[0]->getStatements();
        $this->assertArrayHasKey('Proponent', $statements);
        $this->assertArrayHasKey('Opponent', $statements);
        $this->assertNotEmpty($statements['Proponent']);
        $this->assertNotEmpty($statements['Opponent']);
    }

    public function testProConDebatePattern(): void
    {
        $system = ProConDebate::create($this->client, 'Should remote work be mandatory?', rounds: 1);
        $result = $system->debate('Should remote work be mandatory?');

        $this->assertNotEmpty($result->getSynthesis());
        $this->assertSame(1, $result->getRoundCount());
        $this->assertIsString($result->getTranscript());
        $this->assertStringContainsString('Proponent', $result->getTranscript());
        $this->assertStringContainsString('Opponent', $result->getTranscript());
    }

    public function testRoundTableDebatePattern(): void
    {
        $system = RoundTableDebate::create($this->client, rounds: 1);
        $result = $system->debate('What technology should we use for our next project?');

        $this->assertSame(4, $system->getAgentCount());
        $this->assertNotEmpty($result->getSynthesis());
        $this->assertSame(1, $result->getRoundCount());

        // Check all four participants spoke
        $rounds = $result->getRounds();
        $statements = $rounds[0]->getStatements();
        $this->assertCount(4, $statements);
    }

    public function testConsensusBuilderPattern(): void
    {
        $system = ConsensusBuilder::create($this->client, rounds: 1);
        $result = $system->debate('How should we balance technical debt vs new features?');

        $this->assertSame(3, $system->getAgentCount());
        $this->assertNotEmpty($result->getSynthesis());
        $this->assertSame(1, $result->getRoundCount());
    }

    public function testDevilsAdvocatePattern(): void
    {
        $system = DevilsAdvocate::create($this->client, rounds: 1);
        $result = $system->debate('We should migrate all services to microservices');

        $this->assertSame(2, $system->getAgentCount());
        $this->assertNotEmpty($result->getSynthesis());
        $this->assertSame(1, $result->getRoundCount());

        // Devil's advocate should challenge, so agreement should be lower
        // (though we can't guarantee this in every case)
        $this->assertIsFloat($result->getAgreementScore());
    }

    public function testMultiRoundDebate(): void
    {
        $system = ProConDebate::create($this->client, 'Should AI replace human developers?', rounds: 2);
        $result = $system->debate('Should AI replace human developers?');

        $this->assertSame(2, $result->getRoundCount());

        $rounds = $result->getRounds();
        $this->assertCount(2, $rounds);

        // Each round should have statements from both agents
        foreach ($rounds as $round) {
            $statements = $round->getStatements();
            $this->assertCount(2, $statements);
        }
    }

    public function testDebateResultToArray(): void
    {
        $system = ProConDebate::create($this->client, 'Test topic', rounds: 1);
        $result = $system->debate('Test topic');

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('topic', $array);
        $this->assertArrayHasKey('rounds', $array);
        $this->assertArrayHasKey('synthesis', $array);
        $this->assertArrayHasKey('agreement_score', $array);
        $this->assertArrayHasKey('round_count', $array);
        $this->assertArrayHasKey('total_tokens', $array);
    }

    public function testDebateTranscriptFormat(): void
    {
        $system = ProConDebate::create($this->client, 'Should we use TypeScript?', rounds: 1);
        $result = $system->debate('Should we use TypeScript?');

        $transcript = $result->getTranscript();

        $this->assertStringContainsString('Debate:', $transcript);
        $this->assertStringContainsString('Round 1', $transcript);
        $this->assertStringContainsString('Proponent:', $transcript);
        $this->assertStringContainsString('Opponent:', $transcript);
    }

    public function testModeratorSynthesisQuality(): void
    {
        $system = ProConDebate::create($this->client, 'Should we implement dark mode?', rounds: 1);
        $result = $system->debate('Should we implement dark mode?');

        $synthesis = $result->getSynthesis();

        // Synthesis should be substantial
        $this->assertGreaterThan(50, strlen($synthesis));

        // Should contain some analysis (though exact format may vary)
        $this->assertNotEmpty($synthesis);
    }
}
