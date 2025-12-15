<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Debate;

use ClaudeAgents\Debate\DebateModerator;
use ClaudeAgents\Debate\DebateRound;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DebateModeratorTest extends TestCase
{
    private ClaudePhp $client;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testConstructorWithDefaultOptions(): void
    {
        $moderator = new DebateModerator($this->client);

        $this->assertInstanceOf(DebateModerator::class, $moderator);
    }

    public function testConstructorWithLogger(): void
    {
        $moderator = new DebateModerator($this->client, ['logger' => $this->logger]);

        $this->assertInstanceOf(DebateModerator::class, $moderator);
    }

    public function testSynthesizeWithSingleRound(): void
    {
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 200, output_tokens: 100);

        $response = new Message(
            id: 'msg_123',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "Key areas of agreement:\n1. Both sides acknowledge the importance\n\nRecommended decision:\nProceed with caution",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                return $params['model'] === 'claude-sonnet-4-5'
                    && $params['max_tokens'] === 2048
                    && str_contains($params['system'], 'synthesize multi-agent debates')
                    && str_contains($params['messages'][0]['content'], 'Topic:')
                    && str_contains($params['messages'][0]['content'], 'Debate transcript:')
                    && str_contains($params['messages'][0]['content'], 'Synthesize this debate');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $round = new DebateRound(1, [
            'Pro' => 'I support this',
            'Con' => 'I oppose this',
        ]);

        $moderator = new DebateModerator($this->client, ['logger' => $this->logger]);
        $synthesis = $moderator->synthesize('Should we adopt feature X?', [$round]);

        $this->assertIsString($synthesis);
        $this->assertStringContainsString('agreement', $synthesis);
        $this->assertStringContainsString('decision', $synthesis);
    }

    public function testSynthesizeWithMultipleRounds(): void
    {
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 300, output_tokens: 150);

        $response = new Message(
            id: 'msg_124',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Synthesis of multi-round debate',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                $content = $params['messages'][0]['content'];

                return str_contains($content, 'Round 1')
                    && str_contains($content, 'Round 2')
                    && str_contains($content, 'Round 3');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $rounds = [
            new DebateRound(1, ['Agent' => 'Statement 1']),
            new DebateRound(2, ['Agent' => 'Statement 2']),
            new DebateRound(3, ['Agent' => 'Statement 3']),
        ];

        $moderator = new DebateModerator($this->client);
        $synthesis = $moderator->synthesize('Test topic', $rounds);

        $this->assertIsString($synthesis);
    }

    public function testSynthesizeLogsInfo(): void
    {
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $response = new Message(
            id: 'msg_125',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Synthesis']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Moderator synthesizing debate'));

        $round = new DebateRound(1, ['Agent' => 'Statement']);

        $moderator = new DebateModerator($this->client, ['logger' => $this->logger]);
        $moderator->synthesize('Test topic', [$round]);
    }

    public function testSynthesizeHandlesApiError(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $messagesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new \RuntimeException('API Error'));

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Synthesis failed'));

        $round = new DebateRound(1, ['Agent' => 'Statement']);

        $moderator = new DebateModerator($this->client, ['logger' => $this->logger]);
        $result = $moderator->synthesize('Test topic', [$round]);

        $this->assertStringContainsString('Synthesis error', $result);
        $this->assertStringContainsString('API Error', $result);
    }

    public function testMeasureAgreementHighAgreement(): void
    {
        $statements = [
            'I agree with this proposal',
            'Yes, this is correct',
            'I support this idea',
            'Indeed, this is valid',
        ];

        $moderator = new DebateModerator($this->client);
        $score = $moderator->measureAgreement($statements);

        $this->assertGreaterThan(0.5, $score);
    }

    public function testMeasureAgreementHighDisagreement(): void
    {
        $statements = [
            'I disagree with this proposal',
            'However, there are concerns',
            'But this has problems',
            'There are significant issues here',
        ];

        $moderator = new DebateModerator($this->client);
        $score = $moderator->measureAgreement($statements);

        $this->assertLessThan(0.5, $score);
    }

    public function testMeasureAgreementMixed(): void
    {
        $statements = [
            'I agree with some points',
            'However, I have concerns',
            'This is valid but risky',
        ];

        $moderator = new DebateModerator($this->client);
        $score = $moderator->measureAgreement($statements);

        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    public function testMeasureAgreementEmpty(): void
    {
        $moderator = new DebateModerator($this->client);
        $score = $moderator->measureAgreement([]);

        $this->assertSame(0.5, $score); // Neutral when no statements
    }

    public function testMeasureAgreementCaseInsensitive(): void
    {
        $statements = [
            'I AGREE with this',
            'YES, this is correct',
            'SUPPORT this idea',
        ];

        $moderator = new DebateModerator($this->client);
        $score = $moderator->measureAgreement($statements);

        $this->assertGreaterThan(0.5, $score);
    }

    public function testMeasureAgreementNeutralStatements(): void
    {
        $statements = [
            'The data shows various trends',
            'There are multiple factors to consider',
            'This requires further analysis',
        ];

        $moderator = new DebateModerator($this->client);
        $score = $moderator->measureAgreement($statements);

        // Should default to 0.5 when no agreement or disagreement words
        $this->assertSame(0.5, $score);
    }

    public function testMeasureAgreementBoundaries(): void
    {
        $moderator = new DebateModerator($this->client);

        $score1 = $moderator->measureAgreement(['agree agree agree agree']);
        $this->assertGreaterThanOrEqual(0.0, $score1);
        $this->assertLessThanOrEqual(1.0, $score1);

        $score2 = $moderator->measureAgreement(['disagree disagree disagree']);
        $this->assertGreaterThanOrEqual(0.0, $score2);
        $this->assertLessThanOrEqual(1.0, $score2);
    }

    public function testMeasureAgreementMultipleOccurrences(): void
    {
        $statements = [
            'I agree, agree, agree with everything',
            'Yes yes yes, this is correct',
        ];

        $moderator = new DebateModerator($this->client);
        $score = $moderator->measureAgreement($statements);

        $this->assertGreaterThan(0.7, $score); // High agreement due to repetition
    }

    public function testSynthesizeIncludesAllRequiredSections(): void
    {
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 200, output_tokens: 100);

        $response = new Message(
            id: 'msg_126',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Comprehensive synthesis',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                $content = $params['messages'][0]['content'];

                return str_contains($content, 'Key areas of agreement')
                    && str_contains($content, 'Valid concerns from all sides')
                    && str_contains($content, 'Recommended decision with rationale')
                    && str_contains($content, 'Potential risks and mitigations');
            }))
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $round = new DebateRound(1, ['Agent' => 'Statement']);

        $moderator = new DebateModerator($this->client);
        $moderator->synthesize('Test topic', [$round]);
    }

    public function testSynthesizeExtractsMultipleTextBlocks(): void
    {
        $messagesResource = $this->createMock(Messages::class);
        $usage = new Usage(input_tokens: 200, output_tokens: 100);

        $response = new Message(
            id: 'msg_127',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Part one of synthesis.'],
                ['type' => 'text', 'text' => 'Part two of synthesis.'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->once())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $round = new DebateRound(1, ['Agent' => 'Statement']);

        $moderator = new DebateModerator($this->client);
        $synthesis = $moderator->synthesize('Test topic', [$round]);

        $this->assertStringContainsString('Part one', $synthesis);
        $this->assertStringContainsString('Part two', $synthesis);
    }
}
