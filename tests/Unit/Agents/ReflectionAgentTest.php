<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReflectionAgentTest extends TestCase
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
        $agent = new ReflectionAgent($this->client);

        $this->assertSame('reflection_agent', $agent->getName());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $agent = new ReflectionAgent($this->client, [
            'name' => 'custom_reflection',
            'model' => 'claude-opus-4',
            'max_tokens' => 4096,
            'max_refinements' => 5,
            'quality_threshold' => 9,
            'criteria' => 'custom criteria',
            'logger' => $this->logger,
        ]);

        $this->assertSame('custom_reflection', $agent->getName());
    }

    public function testGetName(): void
    {
        $agent = new ReflectionAgent($this->client, ['name' => 'test_agent']);

        $this->assertSame('test_agent', $agent->getName());
    }

    public function testRunSuccessWithQualityThresholdMet(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        // Mock responses: generate, reflect (high score)
        $generateUsage = new Usage(input_tokens: 100, output_tokens: 200);
        $generateResponse = new Message(
            id: 'msg_gen',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Initial output for the task',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $generateUsage
        );

        $reflectUsage = new Usage(input_tokens: 150, output_tokens: 100);
        $reflectResponse = new Message(
            id: 'msg_reflect',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'This is good work. Quality score: 9/10',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $reflectUsage
        );

        $messagesResource->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($generateResponse, $reflectResponse);

        $this->client->expects($this->exactly(2))
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        $agent = new ReflectionAgent($this->client, [
            'quality_threshold' => 8,
            'max_refinements' => 3,
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Write a function');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Initial output', $result->getAnswer());
        $this->assertSame(2, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('token_usage', $metadata);
        $this->assertArrayHasKey('reflections', $metadata);
        $this->assertArrayHasKey('final_score', $metadata);
        $this->assertSame(9, $metadata['final_score']);
        $this->assertSame(250, $metadata['token_usage']['input']);
        $this->assertSame(300, $metadata['token_usage']['output']);
    }

    public function testRunSuccessWithMaxRefinementsReached(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        // Simulate: generate, reflect (low), refine, reflect (low), refine, reflect (low)
        $generateUsage = new Usage(input_tokens: 100, output_tokens: 100);
        $generateResponse = new Message(
            id: 'msg_gen',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Initial output']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $generateUsage
        );

        $reflectUsage = new Usage(input_tokens: 50, output_tokens: 50);
        $reflectResponse = new Message(
            id: 'msg_reflect',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Needs improvement. Score: 5/10']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $reflectUsage
        );

        $refineUsage = new Usage(input_tokens: 75, output_tokens: 75);
        $refineResponse = new Message(
            id: 'msg_refine',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Refined output']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $refineUsage
        );

        // Pattern: generate, reflect, refine, reflect, refine, reflect (3 refinements max, so 7 calls)
        $messagesResource->expects($this->exactly(7))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $generateResponse,    // 1. generate
                $reflectResponse,     // 2. reflect
                $refineResponse,      // 3. refine
                $reflectResponse,     // 4. reflect
                $refineResponse,      // 5. refine
                $reflectResponse,     // 6. reflect
                $refineResponse       // 7. refine (won't happen - only 3 refinements)
            );

        $this->client->expects($this->exactly(7))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ReflectionAgent($this->client, [
            'quality_threshold' => 8,
            'max_refinements' => 3,
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Write a function');

        $this->assertTrue($result->isSuccess());
        // 1 generate + 3 * (reflect + refine) = 7 iterations
        $this->assertSame(7, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('reflections', $metadata);
        $this->assertCount(3, $metadata['reflections']);
    }

    public function testRunWithApiError(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $messagesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('API Error'));

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('API Error'));

        $agent = new ReflectionAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Test task');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('API Error', $result->getError());
    }

    /**
     * @dataProvider scoreFormatProvider
     */
    public function testExtractScoreVariousFormats(string $text, int $expectedScore): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $generateUsage = new Usage(input_tokens: 10, output_tokens: 10);
        $generateResponse = new Message(
            id: 'msg_gen',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Output']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $generateUsage
        );

        $reflectUsage = new Usage(input_tokens: 10, output_tokens: 10);
        $reflectResponse = new Message(
            id: 'msg_reflect',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => $text]],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $reflectUsage
        );

        $refineUsage = new Usage(input_tokens: 10, output_tokens: 10);
        $refineResponse = new Message(
            id: 'msg_refine',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Refined output']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $refineUsage
        );

        // Sequence: generate -> reflect -> refine (when threshold not met)
        $messagesResource->expects($this->exactly(3))
            ->method('create')
            ->willReturnOnConsecutiveCalls($generateResponse, $reflectResponse, $refineResponse);

        $this->client->expects($this->exactly(3))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ReflectionAgent($this->client, [
            'quality_threshold' => 100, // Never meet threshold so we can test score extraction
            'max_refinements' => 1, // At least 1 to get a reflection
        ]);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();
        $this->assertSame(
            $expectedScore,
            $metadata['final_score'],
            "Failed for text: {$text}"
        );
    }

    public static function scoreFormatProvider(): array
    {
        return [
            'score with slash' => ['Score: 7/10', 7],
            'quality number' => ['Quality: 8', 8],
            'rating phrase' => ['rating of 6', 6],
            'score in sentence' => ['Overall score is 9/10', 9],
            'no score' => ['No score mentioned', 5], // default
        ];
    }

    public function testCustomCriteria(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $generateUsage = new Usage(input_tokens: 100, output_tokens: 100);
        $generateResponse = new Message(
            id: 'msg_gen',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Output']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $generateUsage
        );

        $reflectUsage = new Usage(input_tokens: 100, output_tokens: 100);
        $reflectResponse = new Message(
            id: 'msg_reflect',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Score: 9/10']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $reflectUsage
        );

        $messagesResource->expects($this->exactly(2))
            ->method('create')
            ->with($this->callback(function ($params) {
                static $callCount = 0;
                $callCount++;

                // Second call should be reflection with custom criteria
                if ($callCount === 2) {
                    return str_contains($params['messages'][0]['content'], 'performance and scalability');
                }

                return true;
            }))
            ->willReturnOnConsecutiveCalls($generateResponse, $reflectResponse);

        $this->client->expects($this->exactly(2))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ReflectionAgent($this->client, [
            'criteria' => 'performance and scalability',
            'quality_threshold' => 8,
        ]);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());
    }

    public function testLogging(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $generateUsage = new Usage(input_tokens: 10, output_tokens: 10);
        $generateResponse = new Message(
            id: 'msg_gen',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Output']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $generateUsage
        );

        $reflectUsage = new Usage(input_tokens: 10, output_tokens: 10);
        $reflectResponse = new Message(
            id: 'msg_reflect',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Score: 9/10']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $reflectUsage
        );

        $messagesResource->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($generateResponse, $reflectResponse);

        $this->client->expects($this->exactly(2))
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->logicalOr(
                $this->stringContains('Starting reflection agent'),
                $this->stringContains('Quality threshold met')
            ));

        $this->logger->expects($this->atLeastOnce())
            ->method('debug');

        $agent = new ReflectionAgent($this->client, [
            'logger' => $this->logger,
            'quality_threshold' => 8,
        ]);

        $result = $agent->run('Test task');

        $this->assertTrue($result->isSuccess());
    }

    public function testMultipleTextBlocks(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $generateUsage = new Usage(input_tokens: 10, output_tokens: 10);
        $generateResponse = new Message(
            id: 'msg_gen',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'Part 1'],
                ['type' => 'text', 'text' => 'Part 2'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $generateUsage
        );

        $reflectUsage = new Usage(input_tokens: 10, output_tokens: 10);
        $reflectResponse = new Message(
            id: 'msg_reflect',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Score: 9/10']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $reflectUsage
        );

        $messagesResource->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($generateResponse, $reflectResponse);

        $this->client->expects($this->exactly(2))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ReflectionAgent($this->client, [
            'quality_threshold' => 8,
        ]);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Part 1', $result->getAnswer());
        $this->assertStringContainsString('Part 2', $result->getAnswer());
    }

    public function testReflectionMetadata(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        // Simulate multiple reflections
        $generateResponse = new Message(
            id: 'msg_gen',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Initial']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 10, output_tokens: 10)
        );

        $reflectResponse1 = new Message(
            id: 'msg_reflect1',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Score: 5/10']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 10, output_tokens: 10)
        );

        $refineResponse = new Message(
            id: 'msg_refine',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Refined']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 10, output_tokens: 10)
        );

        $reflectResponse2 = new Message(
            id: 'msg_reflect2',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Score: 9/10']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 10, output_tokens: 10)
        );

        $messagesResource->expects($this->exactly(4))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $generateResponse,
                $reflectResponse1,
                $refineResponse,
                $reflectResponse2
            );

        $this->client->expects($this->exactly(4))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new ReflectionAgent($this->client, [
            'quality_threshold' => 8,
            'max_refinements' => 3,
        ]);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('reflections', $metadata);
        $this->assertCount(2, $metadata['reflections']);

        $this->assertSame(1, $metadata['reflections'][0]['iteration']);
        $this->assertSame(5, $metadata['reflections'][0]['score']);

        $this->assertSame(2, $metadata['reflections'][1]['iteration']);
        $this->assertSame(9, $metadata['reflections'][1]['score']);

        $this->assertSame(9, $metadata['final_score']);
    }
}
