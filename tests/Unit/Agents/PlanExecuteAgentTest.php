<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Agents;

use ClaudeAgents\Agents\PlanExecuteAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlanExecuteAgentTest extends TestCase
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
        $agent = new PlanExecuteAgent($this->client);

        $this->assertSame('plan_execute_agent', $agent->getName());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $agent = new PlanExecuteAgent($this->client, [
            'name' => 'custom_planner',
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4096,
            'allow_replan' => false,
            'logger' => $this->logger,
        ]);

        $this->assertSame('custom_planner', $agent->getName());
    }

    public function testGetName(): void
    {
        $agent = new PlanExecuteAgent($this->client, ['name' => 'test_agent']);

        $this->assertSame('test_agent', $agent->getName());
    }

    public function testAddTool(): void
    {
        $tool = Tool::create('test_tool')
            ->description('A test tool')
            ->handler(function () {
                return 'test result';
            });

        $agent = new PlanExecuteAgent($this->client);
        $result = $agent->addTool($tool);

        $this->assertInstanceOf(PlanExecuteAgent::class, $result);
        $this->assertSame($agent, $result);
    }

    public function testRunCreatesAndExecutesPlan(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        // Mock plan creation response
        $planResponse = new Message(
            id: 'msg_plan',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "1. First step to complete\n2. Second step to complete\n3. Final step to complete",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        // Mock step execution responses
        $stepResponse1 = new Message(
            id: 'msg_step1',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Step 1 completed successfully',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 25)
        );

        $stepResponse2 = new Message(
            id: 'msg_step2',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Step 2 completed successfully',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 25)
        );

        $stepResponse3 = new Message(
            id: 'msg_step3',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Step 3 completed successfully',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 25)
        );

        // Mock synthesis response
        $synthesisResponse = new Message(
            id: 'msg_synth',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'All steps completed successfully. Task is done.',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 150, output_tokens: 50)
        );

        $messagesResource->expects($this->exactly(5))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $planResponse,
                $stepResponse1,
                $stepResponse2,
                $stepResponse3,
                $synthesisResponse
            );

        $this->client->expects($this->exactly(5))
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        $this->logger->expects($this->atLeastOnce())
            ->method('debug');

        $agent = new PlanExecuteAgent($this->client, [
            'allow_replan' => false,
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Complete a complex task');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('All steps completed', $result->getAnswer());
        $this->assertSame(5, $result->getIterations()); // Plan + 3 steps + synthesis = 5
        $this->assertArrayHasKey('plan_steps', $result->getMetadata());
        $this->assertSame(3, $result->getMetadata()['plan_steps']);
        $this->assertArrayHasKey('step_results', $result->getMetadata());
        $this->assertCount(3, $result->getMetadata()['step_results']);
    }

    public function testRunWithReplanningWhenStepFails(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        // Mock plan creation
        $planResponse = new Message(
            id: 'msg_plan',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "1. First step\n2. Second step",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        // Mock step 1 - fails
        $stepResponse1 = new Message(
            id: 'msg_step1',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Error: Unable to complete step 1',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 25)
        );

        // Mock replanning
        $replanResponse = new Message(
            id: 'msg_replan',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '1. Alternative approach step',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 150, output_tokens: 40)
        );

        // Mock alternative step execution
        $altStepResponse = new Message(
            id: 'msg_alt',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Alternative step completed',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 25)
        );

        // Mock synthesis
        $synthesisResponse = new Message(
            id: 'msg_synth',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Task completed with replanning',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 30)
        );

        $messagesResource->expects($this->exactly(5))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $planResponse,
                $stepResponse1,
                $replanResponse,
                $altStepResponse,
                $synthesisResponse
            );

        $this->client->expects($this->exactly(5))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new PlanExecuteAgent($this->client, [
            'allow_replan' => true,
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Complete task with potential failure');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('replanning', $result->getAnswer());
    }

    public function testRunWithApiError(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $messagesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('API Connection Failed'));

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('API Connection Failed'));

        $agent = new PlanExecuteAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Test task');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('API Connection Failed', $result->getError());
    }

    public function testRunLogsProgress(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $planResponse = new Message(
            id: 'msg_plan',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => '1. Single step',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $stepResponse = new Message(
            id: 'msg_step',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Step completed',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 25)
        );

        $synthesisResponse = new Message(
            id: 'msg_synth',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => 'Complete',
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 75, output_tokens: 20)
        );

        $messagesResource->expects($this->exactly(3))
            ->method('create')
            ->willReturnOnConsecutiveCalls($planResponse, $stepResponse, $synthesisResponse);

        $this->client->expects($this->exactly(3))
            ->method('messages')
            ->willReturn($messagesResource);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Starting plan_execute_agent'),
                $this->arrayHasKey('task')
            );

        $this->logger->expects($this->atLeastOnce())
            ->method('debug');

        $agent = new PlanExecuteAgent($this->client, [
            'logger' => $this->logger,
        ]);

        $result = $agent->run('Test task with logging');

        $this->assertTrue($result->isSuccess());
    }

    public function testResultMetadata(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $planResponse = new Message(
            id: 'msg_plan',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "1. Step one\n2. Step two",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 40)
        );

        $stepResponse1 = new Message(
            id: 'msg_step1',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Done 1']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 20)
        );

        $stepResponse2 = new Message(
            id: 'msg_step2',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Done 2']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 20)
        );

        $synthesisResponse = new Message(
            id: 'msg_synth',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Final']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 120, output_tokens: 30)
        );

        $messagesResource->expects($this->exactly(4))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $planResponse,
                $stepResponse1,
                $stepResponse2,
                $synthesisResponse
            );

        $this->client->expects($this->exactly(4))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new PlanExecuteAgent($this->client, [
            'allow_replan' => false,
        ]);

        $result = $agent->run('Test metadata');

        $this->assertTrue($result->isSuccess());
        $metadata = $result->getMetadata();

        $this->assertArrayHasKey('token_usage', $metadata);
        $this->assertArrayHasKey('input', $metadata['token_usage']);
        $this->assertArrayHasKey('output', $metadata['token_usage']);
        $this->assertArrayHasKey('total', $metadata['token_usage']);
        $this->assertSame(320, $metadata['token_usage']['input']);
        $this->assertSame(110, $metadata['token_usage']['output']);
        $this->assertSame(430, $metadata['token_usage']['total']);

        $this->assertArrayHasKey('plan_steps', $metadata);
        $this->assertSame(2, $metadata['plan_steps']);

        $this->assertArrayHasKey('step_results', $metadata);
        $this->assertCount(2, $metadata['step_results']);
        $this->assertSame(1, $metadata['step_results'][0]['step']);
        $this->assertSame('Step one', $metadata['step_results'][0]['description']);
        $this->assertSame('Done 1', $metadata['step_results'][0]['result']);
    }

    public function testExtractTextContentFromMultipleBlocks(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        $planResponse = new Message(
            id: 'msg_plan',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => '1. First'],
                ['type' => 'text', 'text' => '2. Second'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 50, output_tokens: 25)
        );

        $stepResponse = new Message(
            id: 'msg_step',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Done']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 30, output_tokens: 10)
        );

        $synthesisResponse = new Message(
            id: 'msg_synth',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Complete']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 40, output_tokens: 10)
        );

        $stepResponse2 = new Message(
            id: 'msg_step2',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Done too']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 30, output_tokens: 10)
        );

        $messagesResource->expects($this->exactly(4))
            ->method('create')
            ->willReturnOnConsecutiveCalls($planResponse, $stepResponse, $stepResponse2, $synthesisResponse);

        $this->client->expects($this->exactly(4))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new PlanExecuteAgent($this->client);

        $result = $agent->run('Test');

        $this->assertTrue($result->isSuccess());
    }

    public function testPlanParsingHandlesVariousFormats(): void
    {
        $messagesResource = $this->createMock(Messages::class);

        // Test plan with extra whitespace and formatting
        $planResponse = new Message(
            id: 'msg_plan',
            type: 'message',
            role: 'assistant',
            content: [
                [
                    'type' => 'text',
                    'text' => "Here's the plan:\n\n1.   Step one with spaces  \n2. Step two\n\nLet me know if this works!",
                ],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 100, output_tokens: 50)
        );

        $stepResponse1 = new Message(
            id: 'msg_step1',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Done 1']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 40, output_tokens: 15)
        );

        $stepResponse2 = new Message(
            id: 'msg_step2',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Done 2']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 40, output_tokens: 15)
        );

        $synthesisResponse = new Message(
            id: 'msg_synth',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Complete']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: new Usage(input_tokens: 60, output_tokens: 20)
        );

        $messagesResource->expects($this->exactly(4))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $planResponse,
                $stepResponse1,
                $stepResponse2,
                $synthesisResponse
            );

        $this->client->expects($this->exactly(4))
            ->method('messages')
            ->willReturn($messagesResource);

        $agent = new PlanExecuteAgent($this->client);

        $result = $agent->run('Test plan parsing');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(2, $result->getMetadata()['plan_steps']);
    }
}
