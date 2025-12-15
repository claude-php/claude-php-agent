<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit;

use ClaudeAgents\AgentResult;
use PHPUnit\Framework\TestCase;

class AgentResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Test'],
            ['role' => 'assistant', 'content' => 'Response'],
        ];

        $result = AgentResult::success(
            answer: 'Final answer',
            messages: $messages,
            iterations: 3,
            metadata: ['custom' => 'value'],
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Final answer', $result->getAnswer());
        $this->assertEquals($messages, $result->getMessages());
        $this->assertEquals(3, $result->getIterations());
        $this->assertArrayHasKey('custom', $result->getMetadata());
        $this->assertNull($result->getError());
    }

    public function testFailureResult(): void
    {
        $result = AgentResult::failure(
            error: 'Something went wrong',
            messages: [],
            iterations: 2,
        );

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Something went wrong', $result->getError());
        $this->assertEquals(2, $result->getIterations());
        $this->assertEquals('', $result->getAnswer());
    }

    public function testGetTokenUsage(): void
    {
        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 1,
            metadata: [
                'token_usage' => [
                    'input' => 100,
                    'output' => 50,
                    'total' => 150,
                ],
            ],
        );

        $usage = $result->getTokenUsage();
        $this->assertEquals(100, $usage['input']);
        $this->assertEquals(50, $usage['output']);
        $this->assertEquals(150, $usage['total']);
    }

    public function testGetTokenUsageDefaultsToZero(): void
    {
        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 1,
        );

        $usage = $result->getTokenUsage();
        $this->assertEquals(0, $usage['input']);
        $this->assertEquals(0, $usage['output']);
        $this->assertEquals(0, $usage['total']);
    }

    public function testGetToolCalls(): void
    {
        $toolCalls = [
            ['tool' => 'calculator', 'input' => ['a' => 1], 'result' => '1'],
            ['tool' => 'search', 'input' => ['q' => 'test'], 'result' => 'found'],
        ];

        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 2,
            metadata: ['tool_calls' => $toolCalls],
        );

        $this->assertEquals($toolCalls, $result->getToolCalls());
    }

    public function testGetToolCallsDefaultsToEmpty(): void
    {
        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 1,
        );

        $this->assertIsArray($result->getToolCalls());
        $this->assertEmpty($result->getToolCalls());
    }

    public function testToArray(): void
    {
        $result = AgentResult::success(
            answer: 'Test answer',
            messages: [['role' => 'user', 'content' => 'Hi']],
            iterations: 5,
            metadata: ['key' => 'value'],
        );

        $array = $result->toArray();

        $this->assertTrue($array['success']);
        $this->assertEquals('Test answer', $array['answer']);
        $this->assertEquals(5, $array['iterations']);
        $this->assertNull($array['error']);
        $this->assertArrayHasKey('key', $array['metadata']);
    }

    public function testToArrayWithFailure(): void
    {
        $result = AgentResult::failure(
            error: 'Failed',
            iterations: 1,
        );

        $array = $result->toArray();

        $this->assertFalse($array['success']);
        $this->assertEquals('Failed', $array['error']);
        $this->assertEquals('', $array['answer']);
    }

    public function testGetMessages(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Question'],
            ['role' => 'assistant', 'content' => 'Answer'],
            ['role' => 'user', 'content' => 'Follow-up'],
        ];

        $result = AgentResult::success(
            answer: 'Done',
            messages: $messages,
            iterations: 2,
        );

        $this->assertCount(3, $result->getMessages());
        $this->assertEquals($messages, $result->getMessages());
    }

    public function testMinimalSuccessResult(): void
    {
        $result = AgentResult::success(
            answer: 'Quick answer',
            messages: [],
            iterations: 0,
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Quick answer', $result->getAnswer());
        $this->assertEquals(0, $result->getIterations());
        $this->assertEmpty($result->getMessages());
    }

    public function testMinimalFailureResult(): void
    {
        $result = AgentResult::failure(error: 'Error');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Error', $result->getError());
        $this->assertEquals(0, $result->getIterations());
        $this->assertEmpty($result->getMessages());
    }

    public function testComplexMetadata(): void
    {
        $metadata = [
            'token_usage' => ['input' => 100, 'output' => 50, 'total' => 150],
            'tool_calls' => [['tool' => 'test', 'result' => 'ok']],
            'custom_metrics' => ['accuracy' => 0.95, 'latency' => 1.2],
            'debug_info' => ['step' => 5, 'state' => 'completed'],
        ];

        $result = AgentResult::success(
            answer: 'Answer',
            messages: [],
            iterations: 1,
            metadata: $metadata,
        );

        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertArrayHasKey('custom_metrics', $result->getMetadata());
        $this->assertEquals(0.95, $result->getMetadata()['custom_metrics']['accuracy']);
    }
}
