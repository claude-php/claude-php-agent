<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MakerAgentTest extends TestCase
{
    private ClaudePhp $client;
    private Messages $messages;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messages = Mockery::mock(Messages::class);
        $this->client = Mockery::mock(ClaudePhp::class);
        $this->client->shouldReceive('messages')->andReturn($this->messages);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldIgnoreMissing();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testMakerAgentCreation(): void
    {
        $maker = new MakerAgent($this->client, [
            'name' => 'test_maker',
            'voting_k' => 3,
            'enable_red_flagging' => true,
        ]);

        $this->assertSame('test_maker', $maker->getName());
    }

    public function testSetVotingK(): void
    {
        $maker = new MakerAgent($this->client);
        $result = $maker->setVotingK(5);

        $this->assertSame($maker, $result); // Fluent interface
    }

    public function testSetRedFlagging(): void
    {
        $maker = new MakerAgent($this->client);
        $result = $maker->setRedFlagging(false);

        $this->assertSame($maker, $result); // Fluent interface
    }

    public function testSimpleAtomicTaskExecution(): void
    {
        $this->mockSuccessfulResponse('The answer is 42');

        $maker = new MakerAgent($this->client, [
            'voting_k' => 2,
            'enable_red_flagging' => false,
            'logger' => $this->logger,
        ]);

        $result = $maker->run('What is 6 * 7?');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('42', $result->getAnswer());

        $stats = $maker->getExecutionStats();
        $this->assertArrayHasKey('total_steps', $stats);
        $this->assertArrayHasKey('votes_cast', $stats);
    }

    public function testDecompositionTriggered(): void
    {
        // First response: decomposition
        $this->messages->shouldReceive('create')
            ->andReturn($this->createMockResponse("1. Step one\n2. Step two"));

        $maker = new MakerAgent($this->client, [
            'voting_k' => 2,
            'max_decomposition_depth' => 2,
            'logger' => $this->logger,
        ]);

        $result = $maker->run('First do this, then do that, and finally complete it');

        $this->assertTrue($result->isSuccess());

        $stats = $maker->getExecutionStats();
        $this->assertGreaterThan(0, $stats['decompositions']);
    }

    public function testRedFlaggingDetection(): void
    {
        // First response with red flag
        $this->messages->shouldReceive('create')
            ->once()
            ->andReturn($this->createMockResponse('Wait, maybe I should reconsider...'));

        // Second response without red flag
        $this->messages->shouldReceive('create')
            ->andReturn($this->createMockResponse('The correct answer is 15'));

        $maker = new MakerAgent($this->client, [
            'voting_k' => 2,
            'enable_red_flagging' => true,
            'logger' => $this->logger,
        ]);

        $result = $maker->run('Calculate 3 * 5');

        $stats = $maker->getExecutionStats();
        $this->assertGreaterThan(0, $stats['red_flags_detected']);
    }

    public function testVotingConsensus(): void
    {
        // Multiple responses for voting
        $this->messages->shouldReceive('create')
            ->times(4)
            ->andReturnUsing(function () {
                static $count = 0;
                $count++;

                // First 3 return "answer A", 4th returns "answer B"
                if ($count <= 3) {
                    return $this->createMockResponse('answer A');
                }

                return $this->createMockResponse('answer B');
            });

        $maker = new MakerAgent($this->client, [
            'voting_k' => 2,  // Need 2-vote margin
            'enable_red_flagging' => false,
            'logger' => $this->logger,
        ]);

        $result = $maker->run('Simple task');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('A', $result->getAnswer());

        $stats = $maker->getExecutionStats();
        $this->assertGreaterThanOrEqual(3, $stats['votes_cast']);
    }

    public function testExecutionStatisticsTracking(): void
    {
        $this->mockSuccessfulResponse('Done');

        $maker = new MakerAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $maker->run('Do something');
        $stats = $maker->getExecutionStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_steps', $stats);
        $this->assertArrayHasKey('atomic_executions', $stats);
        $this->assertArrayHasKey('decompositions', $stats);
        $this->assertArrayHasKey('subtasks_created', $stats);
        $this->assertArrayHasKey('votes_cast', $stats);
        $this->assertArrayHasKey('red_flags_detected', $stats);
    }

    public function testErrorRateCalculation(): void
    {
        $this->mockSuccessfulResponse('Result');

        $maker = new MakerAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $maker->run('Task');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();

        $this->assertArrayHasKey('error_rate', $metadata);
        $this->assertIsFloat($metadata['error_rate']);
        $this->assertGreaterThanOrEqual(0, $metadata['error_rate']);
    }

    public function testFailureHandling(): void
    {
        $this->messages->shouldReceive('create')
            ->andThrow(new \Exception('API Error'));

        $maker = new MakerAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $maker->run('Task that will fail');

        $this->assertFalse($result->isSuccess());
        $this->assertNotEmpty($result->getError());
    }

    public function testMetadataInclusion(): void
    {
        $this->mockSuccessfulResponse('Success');

        $maker = new MakerAgent($this->client, [
            'voting_k' => 3,
            'enable_red_flagging' => true,
            'logger' => $this->logger,
        ]);

        $result = $maker->run('Task');
        $metadata = $result->getMetadata();

        $this->assertArrayHasKey('execution_stats', $metadata);
        $this->assertArrayHasKey('duration_seconds', $metadata);
        $this->assertArrayHasKey('error_rate', $metadata);
        $this->assertArrayHasKey('voting_k', $metadata);
        $this->assertArrayHasKey('red_flagging_enabled', $metadata);

        $this->assertSame(3, $metadata['voting_k']);
        $this->assertTrue($metadata['red_flagging_enabled']);
    }

    public function testMaxDecompositionDepthRespected(): void
    {
        $this->messages->shouldReceive('create')
            ->andReturn($this->createMockResponse('1. Sub1\n2. Sub2'));

        $maker = new MakerAgent($this->client, [
            'max_decomposition_depth' => 1,
            'logger' => $this->logger,
        ]);

        $result = $maker->run('Complex task that could decompose deeply');

        $this->assertTrue($result->isSuccess());

        $stats = $maker->getExecutionStats();
        // With depth 1, we should have limited decompositions
        $this->assertLessThanOrEqual(2, $stats['decompositions']);
    }

    // Helper methods

    private function mockSuccessfulResponse(string $text, ?int $times = null): void
    {
        $mock = $this->messages->shouldReceive('create');

        if ($times !== null) {
            $mock->times($times);
        }

        $mock->andReturn($this->createMockResponse($text));
    }

    private function createMockResponse(string $text): Message
    {
        return new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(
                input_tokens: 100,
                output_tokens: 50,
            ),
        );
    }
}
