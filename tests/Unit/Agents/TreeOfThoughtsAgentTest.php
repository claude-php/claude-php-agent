<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TreeOfThoughtsAgentTest extends TestCase
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
        $agent = new TreeOfThoughtsAgent($this->client);

        $this->assertSame('tot_agent', $agent->getName());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $agent = new TreeOfThoughtsAgent($this->client, [
            'name' => 'custom_tot',
            'branch_count' => 5,
            'max_depth' => 6,
            'search_strategy' => 'depth_first',
            'logger' => $this->logger,
        ]);

        $this->assertSame('custom_tot', $agent->getName());
    }

    public function testGetName(): void
    {
        $agent = new TreeOfThoughtsAgent($this->client, ['name' => 'test_tot']);

        $this->assertSame('test_tot', $agent->getName());
    }

    public function testRunWithBestFirstStrategy(): void
    {
        // Mock the messages resource
        $messagesResource = $this->createMock(Messages::class);

        // Mock multiple API calls for thought generation and evaluation
        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        // Response for generating thoughts
        $thoughtResponse = new Message(
            id: 'msg_thought',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "Approach 1: Try addition first\nApproach 2: Try multiplication\nApproach 3: Try combination",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        // Response for evaluation
        $evalResponse = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '8',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $thoughtResponse,
                $evalResponse,
                $evalResponse,
                $evalResponse,
                $thoughtResponse,
                $evalResponse,
                $evalResponse,
                $evalResponse,
                $thoughtResponse,
                $evalResponse,
                $evalResponse,
                $evalResponse
            );

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('ToT Agent:'));

        $agent = new TreeOfThoughtsAgent($this->client, [
            'branch_count' => 3,
            'max_depth' => 2,
            'search_strategy' => 'best_first',
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Solve a math problem');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Best solution path found', $result->getAnswer());
        $this->assertSame(1, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('strategy', $metadata);
        $this->assertSame('best_first', $metadata['strategy']);
        $this->assertArrayHasKey('total_nodes', $metadata);
        $this->assertArrayHasKey('max_depth', $metadata);
        $this->assertArrayHasKey('path_length', $metadata);
    }

    public function testRunWithBreadthFirstStrategy(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $thoughtResponse = new Message(
            id: 'msg_thought',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "Approach 1: First approach\nApproach 2: Second approach\nApproach 3: Third approach",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $evalResponse = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '7',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $thoughtResponse,
                $evalResponse,
                $evalResponse,
                $evalResponse,
                $thoughtResponse,
                $evalResponse,
                $evalResponse,
                $evalResponse
            );

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new TreeOfThoughtsAgent($this->client, [
            'branch_count' => 3,
            'max_depth' => 2,
            'search_strategy' => 'breadth_first',
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Test problem');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('breadth_first', $result->getMetadata()['strategy']);
    }

    public function testRunWithDepthFirstStrategy(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 100, output_tokens: 50);

        $thoughtResponse = new Message(
            id: 'msg_thought',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "Approach 1: First approach\nApproach 2: Second approach",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $evalResponse = new Message(
            id: 'msg_eval',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '6',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $thoughtResponse,
                $evalResponse,
                $evalResponse,
                $thoughtResponse,
                $evalResponse,
                $evalResponse
            );

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new TreeOfThoughtsAgent($this->client, [
            'branch_count' => 2,
            'max_depth' => 2,
            'search_strategy' => 'depth_first',
        ]);

        $result = $agent->run('Test problem');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('depth_first', $result->getMetadata()['strategy']);
    }

    public function testRunWithApiError(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        // First call throws exception
        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willThrowException(new \Exception('API Error'));

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        // The agent logs warnings for failed thought generation
        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with($this->stringContains('Failed to generate thoughts'));

        $agent = new TreeOfThoughtsAgent($this->client, [
            'logger' => $this->logger,
            'branch_count' => 2,
            'max_depth' => 1,
        ]);

        $result = $agent->run('Test task');

        // Agent is resilient and returns success with just root node
        $this->assertTrue($result->isSuccess());

        // But with minimal exploration
        $metadata = $result->getMetadata();
        $this->assertSame(1, $metadata['total_nodes']); // Just root
        $this->assertSame(0, $metadata['max_depth']); // No depth
    }

    public function testRunLogsTask(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 25);
        $response = new Message(
            id: 'msg_log',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Approach 1: Test',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('Complex task'));

        $agent = new TreeOfThoughtsAgent($this->client, [
            'branch_count' => 1,
            'max_depth' => 1,
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Complex task');

        $this->assertTrue($result->isSuccess());
    }

    public function testResultMetadataStructure(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 123, output_tokens: 456);
        $response = new Message(
            id: 'msg_metadata',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Approach 1: Solution',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new TreeOfThoughtsAgent($this->client, [
            'branch_count' => 1,
            'max_depth' => 1,
            'search_strategy' => 'best_first',
        ]);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();

        $this->assertArrayHasKey('strategy', $metadata);
        $this->assertArrayHasKey('total_nodes', $metadata);
        $this->assertArrayHasKey('max_depth', $metadata);
        $this->assertArrayHasKey('path_length', $metadata);
        $this->assertArrayHasKey('best_score', $metadata);
        $this->assertArrayHasKey('tokens', $metadata);

        $this->assertSame('best_first', $metadata['strategy']);
        $this->assertIsInt($metadata['total_nodes']);
        $this->assertIsInt($metadata['max_depth']);
        $this->assertIsInt($metadata['path_length']);
    }

    public function testEmptyFrontierHandling(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 25);

        // Return empty thought generation to create empty frontier
        $emptyResponse = new Message(
            id: 'msg_empty',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'No approaches found',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($emptyResponse);

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new TreeOfThoughtsAgent($this->client, [
            'branch_count' => 2,
            'max_depth' => 3,
        ]);

        $result = $agent->run('Test problem');

        // Should still succeed even with empty frontier
        $this->assertTrue($result->isSuccess());
    }

    public function testExtractTextContentFromMultipleBlocks(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $usage = new Usage(input_tokens: 50, output_tokens: 25);
        $response = new Message(
            id: 'msg_multi',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Approach 1: First',
                ],
                [
                    'type' => 'text',
                    'text' => 'Approach 2: Second',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $messagesResource->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($response);

        $this->client->expects($this->atLeastOnce())
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new TreeOfThoughtsAgent($this->client, [
            'branch_count' => 2,
            'max_depth' => 1,
        ]);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());
    }
}
