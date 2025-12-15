<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate;

use ClaudeAgents\Debate\DebateResult;
use ClaudeAgents\Debate\DebateRound;
use PHPUnit\Framework\TestCase;

class DebateResultTest extends TestCase
{
    public function testConstructorWithBasicArguments(): void
    {
        $round1 = new DebateRound(1, ['Agent A' => 'Statement 1']);
        $round2 = new DebateRound(2, ['Agent A' => 'Statement 2']);

        $result = new DebateResult(
            topic: 'Should we adopt remote work?',
            rounds: [$round1, $round2],
            synthesis: 'The debate concluded that...',
            agreementScore: 0.75
        );

        $this->assertSame('Should we adopt remote work?', $result->getTopic());
        $this->assertCount(2, $result->getRounds());
        $this->assertSame('The debate concluded that...', $result->getSynthesis());
        $this->assertSame(0.75, $result->getAgreementScore());
        $this->assertSame(0, $result->getTotalTokens());
    }

    public function testConstructorWithTotalTokens(): void
    {
        $round = new DebateRound(1, ['Agent' => 'Statement']);

        $result = new DebateResult(
            topic: 'Test topic',
            rounds: [$round],
            synthesis: 'Test synthesis',
            agreementScore: 0.5,
            totalTokens: 1000
        );

        $this->assertSame(1000, $result->getTotalTokens());
    }

    public function testGetTopic(): void
    {
        $result = new DebateResult(
            topic: 'What is the best programming language?',
            rounds: [],
            synthesis: '',
            agreementScore: 0.0
        );

        $this->assertSame('What is the best programming language?', $result->getTopic());
    }

    public function testGetRounds(): void
    {
        $round1 = new DebateRound(1, ['A' => 'Statement 1']);
        $round2 = new DebateRound(2, ['A' => 'Statement 2']);
        $round3 = new DebateRound(3, ['A' => 'Statement 3']);

        $result = new DebateResult(
            topic: 'Test',
            rounds: [$round1, $round2, $round3],
            synthesis: 'Synthesis',
            agreementScore: 0.6
        );

        $rounds = $result->getRounds();

        $this->assertCount(3, $rounds);
        $this->assertSame($round1, $rounds[0]);
        $this->assertSame($round2, $rounds[1]);
        $this->assertSame($round3, $rounds[2]);
    }

    public function testGetSynthesis(): void
    {
        $synthesis = 'After careful consideration of all perspectives, the key conclusions are: 1) Both sides have merit, 2) A balanced approach is needed.';

        $result = new DebateResult(
            topic: 'Test',
            rounds: [],
            synthesis: $synthesis,
            agreementScore: 0.8
        );

        $this->assertSame($synthesis, $result->getSynthesis());
    }

    public function testGetAgreementScore(): void
    {
        $result = new DebateResult(
            topic: 'Test',
            rounds: [],
            synthesis: '',
            agreementScore: 0.42
        );

        $this->assertSame(0.42, $result->getAgreementScore());
    }

    public function testGetRoundCount(): void
    {
        $rounds = [
            new DebateRound(1, ['A' => 'S1']),
            new DebateRound(2, ['A' => 'S2']),
            new DebateRound(3, ['A' => 'S3']),
            new DebateRound(4, ['A' => 'S4']),
        ];

        $result = new DebateResult(
            topic: 'Test',
            rounds: $rounds,
            synthesis: '',
            agreementScore: 0.5
        );

        $this->assertSame(4, $result->getRoundCount());
    }

    public function testGetRoundCountEmpty(): void
    {
        $result = new DebateResult(
            topic: 'Test',
            rounds: [],
            synthesis: '',
            agreementScore: 0.5
        );

        $this->assertSame(0, $result->getRoundCount());
    }

    public function testGetTranscript(): void
    {
        $round1 = new DebateRound(1, [
            'Pro' => 'I support this idea',
            'Con' => 'I oppose this idea',
        ]);
        $round2 = new DebateRound(2, [
            'Pro' => 'Let me add more reasons',
            'Con' => 'I have concerns',
        ]);

        $result = new DebateResult(
            topic: 'Should we implement feature X?',
            rounds: [$round1, $round2],
            synthesis: 'Conclusion',
            agreementScore: 0.6
        );

        $transcript = $result->getTranscript();

        $this->assertStringContainsString('Debate: Should we implement feature X?', $transcript);
        $this->assertStringContainsString('Round 1', $transcript);
        $this->assertStringContainsString('Round 2', $transcript);
        $this->assertStringContainsString('Pro:', $transcript);
        $this->assertStringContainsString('Con:', $transcript);
        $this->assertStringContainsString('I support this idea', $transcript);
        $this->assertStringContainsString('I oppose this idea', $transcript);
        $this->assertStringContainsString('Let me add more reasons', $transcript);
        $this->assertStringContainsString('I have concerns', $transcript);
    }

    public function testGetTranscriptEmpty(): void
    {
        $result = new DebateResult(
            topic: 'Empty debate',
            rounds: [],
            synthesis: '',
            agreementScore: 0.5
        );

        $transcript = $result->getTranscript();

        $this->assertStringContainsString('Debate: Empty debate', $transcript);
    }

    public function testToArray(): void
    {
        $round1 = new DebateRound(1, ['Agent' => 'Statement 1']);
        $round2 = new DebateRound(2, ['Agent' => 'Statement 2']);

        $result = new DebateResult(
            topic: 'Test Topic',
            rounds: [$round1, $round2],
            synthesis: 'Test Synthesis',
            agreementScore: 0.85,
            totalTokens: 500
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertSame('Test Topic', $array['topic']);
        $this->assertIsArray($array['rounds']);
        $this->assertCount(2, $array['rounds']);
        $this->assertSame('Test Synthesis', $array['synthesis']);
        $this->assertSame(0.85, $array['agreement_score']);
        $this->assertSame(2, $array['round_count']);
        $this->assertSame(500, $array['total_tokens']);
    }

    public function testToArrayStructure(): void
    {
        $result = new DebateResult(
            topic: 'Test',
            rounds: [],
            synthesis: '',
            agreementScore: 0.5
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('topic', $array);
        $this->assertArrayHasKey('rounds', $array);
        $this->assertArrayHasKey('synthesis', $array);
        $this->assertArrayHasKey('agreement_score', $array);
        $this->assertArrayHasKey('round_count', $array);
        $this->assertArrayHasKey('total_tokens', $array);
    }

    public function testToArrayRoundsAreConverted(): void
    {
        $round = new DebateRound(1, ['Agent' => 'Statement']);

        $result = new DebateResult(
            topic: 'Test',
            rounds: [$round],
            synthesis: '',
            agreementScore: 0.5
        );

        $array = $result->toArray();

        $this->assertIsArray($array['rounds'][0]);
        $this->assertArrayHasKey('round_number', $array['rounds'][0]);
        $this->assertArrayHasKey('statements', $array['rounds'][0]);
    }

    public function testAgreementScoreBoundaries(): void
    {
        $result1 = new DebateResult('Test', [], '', 0.0);
        $result2 = new DebateResult('Test', [], '', 1.0);
        $result3 = new DebateResult('Test', [], '', 0.5);

        $this->assertSame(0.0, $result1->getAgreementScore());
        $this->assertSame(1.0, $result2->getAgreementScore());
        $this->assertSame(0.5, $result3->getAgreementScore());
    }

    public function testLongSynthesis(): void
    {
        $longSynthesis = str_repeat('This is a comprehensive synthesis. ', 200);

        $result = new DebateResult(
            topic: 'Test',
            rounds: [],
            synthesis: $longSynthesis,
            agreementScore: 0.5
        );

        $this->assertSame($longSynthesis, $result->getSynthesis());
        $this->assertGreaterThan(5000, strlen($result->getSynthesis()));
    }
}
