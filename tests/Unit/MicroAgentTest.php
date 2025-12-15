<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\Agents\MicroAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MicroAgentTest extends TestCase
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

    public function testMicroAgentCreation(): void
    {
        $microAgent = new MicroAgent($this->client, [
            'role' => 'executor',
        ]);

        $this->assertSame('executor', $microAgent->getRole());
    }

    public function testMicroAgentRoles(): void
    {
        $roles = ['decomposer', 'executor', 'composer', 'validator', 'discriminator'];

        foreach ($roles as $role) {
            $microAgent = new MicroAgent($this->client, ['role' => $role]);
            $this->assertSame($role, $microAgent->getRole());
        }
    }

    public function testExecuteReturnsResponse(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->andReturn($this->createMockResponse('Task completed'));

        $microAgent = new MicroAgent($this->client, [
            'role' => 'executor',
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Do something');

        $this->assertSame('Task completed', $result);
    }

    public function testExecuteUsesLowTemperature(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                $this->assertSame(0.1, $args['temperature']);

                return isset($args['temperature']) && $args['temperature'] === 0.1;
            })
            ->andReturn($this->createMockResponse('Result'));

        $microAgent = new MicroAgent($this->client, [
            'temperature' => 0.1,
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Task');
        $this->assertSame('Result', $result);
    }

    public function testExecuteWithRetrySucceedsOnSecondAttempt(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('First attempt fails'));

        $this->messages->shouldReceive('create')
            ->once()
            ->andReturn($this->createMockResponse('Second attempt succeeds'));

        $microAgent = new MicroAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $microAgent->executeWithRetry('Task', 3);

        $this->assertSame('Second attempt succeeds', $result);
    }

    public function testExecuteWithRetryFailsAfterMaxAttempts(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('API Error');

        $this->messages->shouldReceive('create')
            ->times(3)
            ->andThrow(new \Exception('API Error'));

        $microAgent = new MicroAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $microAgent->executeWithRetry('Task', 3);
    }

    public function testSetCustomSystemPrompt(): void
    {
        $customPrompt = 'Custom specialized behavior';

        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) use ($customPrompt) {
                $this->assertSame($customPrompt, $args['system']);

                return $args['system'] === $customPrompt;
            })
            ->andReturn($this->createMockResponse('Result'));

        $microAgent = new MicroAgent($this->client, [
            'role' => 'executor',
            'logger' => $this->logger,
        ]);

        $microAgent->setSystemPrompt($customPrompt);
        $result = $microAgent->execute('Task');
        $this->assertSame('Result', $result);
    }

    public function testDecomposerRole(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                return str_contains($args['system'], 'decomposer');
            })
            ->andReturn($this->createMockResponse('1. Step 1\n2. Step 2'));

        $microAgent = new MicroAgent($this->client, [
            'role' => 'decomposer',
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Break this task down');

        $this->assertStringContainsString('Step 1', $result);
        $this->assertStringContainsString('Step 2', $result);
    }

    public function testComposerRole(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                return str_contains($args['system'], 'composer');
            })
            ->andReturn($this->createMockResponse('Combined result'));

        $microAgent = new MicroAgent($this->client, [
            'role' => 'composer',
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Combine these results');

        $this->assertSame('Combined result', $result);
    }

    public function testValidatorRole(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                return str_contains($args['system'], 'validator');
            })
            ->andReturn($this->createMockResponse('Valid'));

        $microAgent = new MicroAgent($this->client, [
            'role' => 'validator',
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Validate this');

        $this->assertSame('Valid', $result);
    }

    public function testExecutorIsDefaultRole(): void
    {
        $microAgent = new MicroAgent($this->client);

        $this->assertSame('executor', $microAgent->getRole());
    }

    public function testExtractMultipleTextBlocks(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->andReturn(new Message(
                id: 'msg_test',
                type: 'message',
                role: 'assistant',
                content: [
                    ['type' => 'text', 'text' => 'First block'],
                    ['type' => 'text', 'text' => 'Second block'],
                ],
                model: 'claude-sonnet-4-5',
                stop_reason: 'end_turn',
                stop_sequence: null,
                usage: new Usage(
                    input_tokens: 10,
                    output_tokens: 5,
                ),
            ));

        $microAgent = new MicroAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Task');

        $this->assertStringContainsString('First block', $result);
        $this->assertStringContainsString('Second block', $result);
    }

    public function testModelConfiguration(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                $this->assertSame('claude-haiku-4', $args['model']);

                return $args['model'] === 'claude-haiku-4';
            })
            ->andReturn($this->createMockResponse('Result'));

        $microAgent = new MicroAgent($this->client, [
            'model' => 'claude-haiku-4',
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Task');
        $this->assertSame('Result', $result);
    }

    public function testMaxTokensConfiguration(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                $this->assertSame(1024, $args['max_tokens']);

                return $args['max_tokens'] === 1024;
            })
            ->andReturn($this->createMockResponse('Result'));

        $microAgent = new MicroAgent($this->client, [
            'max_tokens' => 1024,
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Task');
        $this->assertSame('Result', $result);
    }

    public function testDiscriminatorRole(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                return str_contains($args['system'], 'discriminator');
            })
            ->andReturn($this->createMockResponse('Option A is best'));

        $microAgent = new MicroAgent($this->client, [
            'role' => 'discriminator',
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Choose between A and B');

        $this->assertSame('Option A is best', $result);
    }

    public function testEmptyResponseHandling(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->andReturn($this->createMockResponse(''));

        $microAgent = new MicroAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Task');

        $this->assertSame('', $result);
    }

    public function testExecuteWithRetryUsesExponentialBackoff(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Fail 1'));

        $this->messages->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Fail 2'));

        $this->messages->shouldReceive('create')
            ->once()
            ->andReturn($this->createMockResponse('Success on third try'));

        $microAgent = new MicroAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $startTime = microtime(true);
        $result = $microAgent->executeWithRetry('Task', 3);
        $duration = microtime(true) - $startTime;

        $this->assertSame('Success on third try', $result);
        // Should have backoff delays: 0.1s + 0.2s = 0.3s minimum
        $this->assertGreaterThanOrEqual(0.25, $duration);
    }

    public function testAllRolesHaveSystemPrompts(): void
    {
        $roles = ['decomposer', 'executor', 'composer', 'validator', 'discriminator'];

        foreach ($roles as $role) {
            $this->messages->shouldReceive('create')
                ->once()
                ->withArgs(function ($args) {
                    // Each role should have a non-empty system prompt
                    return ! empty($args['system']);
                })
                ->andReturn($this->createMockResponse('Result'));

            $microAgent = new MicroAgent($this->client, [
                'role' => $role,
                'logger' => $this->logger,
            ]);

            $result = $microAgent->execute('Task');
            $this->assertNotEmpty($result);
        }
    }

    public function testCustomTemperatureConfiguration(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                $this->assertSame(0.5, $args['temperature']);

                return $args['temperature'] === 0.5;
            })
            ->andReturn($this->createMockResponse('Result'));

        $microAgent = new MicroAgent($this->client, [
            'temperature' => 0.5,
            'logger' => $this->logger,
        ]);

        $result = $microAgent->execute('Task');
        $this->assertSame('Result', $result);
    }

    public function testChainedMethodCalls(): void
    {
        $this->messages->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                return $args['system'] === 'Custom prompt';
            })
            ->andReturn($this->createMockResponse('Result'));

        $microAgent = new MicroAgent($this->client, [
            'role' => 'executor',
            'logger' => $this->logger,
        ]);

        $result = $microAgent->setSystemPrompt('Custom prompt')->execute('Task');

        $this->assertSame('Result', $result);
        $this->assertSame('executor', $microAgent->getRole());
    }

    // Helper methods

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
