<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEvent;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \ClaudeAgents\Execution\StreamingFlowExecutor
 */
class StreamingFlowExecutorTest extends TestCase
{
    private EventQueue $queue;
    private FlowEventManager $eventManager;
    private StreamingFlowExecutor $executor;

    protected function setUp(): void
    {
        $this->queue = new EventQueue(maxSize: 100);
        $this->eventManager = new FlowEventManager($this->queue, new NullLogger());
        $this->eventManager->registerDefaultEvents();
        $this->executor = new StreamingFlowExecutor($this->eventManager, $this->queue, new NullLogger());
    }

    public function testGetName(): void
    {
        $this->assertEquals('streaming_flow_executor', $this->executor->getName());
    }

    public function testIsRunningInitiallyFalse(): void
    {
        $this->assertFalse($this->executor->isRunning());
    }

    public function testExecuteWithStreamingYieldsEvents(): void
    {
        $agent = $this->createMockAgent('Test output');

        $events = [];
        foreach ($this->executor->executeWithStreaming($agent, 'Test task') as $event) {
            $events[] = $event;
        }

        $this->assertNotEmpty($events);

        // Check for flow_started event
        $flowStarted = array_filter($events, fn($e) => $e['type'] === 'flow_started');
        $this->assertNotEmpty($flowStarted);

        // Check for end event
        $endEvents = array_filter($events, fn($e) => $e['type'] === 'end');
        $this->assertNotEmpty($endEvents);
    }

    public function testExecuteWithStreamingEmitsFlowStartedEvent(): void
    {
        $agent = $this->createMockAgent('Test output');

        $events = [];
        foreach ($this->executor->executeWithStreaming($agent, 'Test task') as $event) {
            $events[] = $event;
            // flow_started should be first event
            if ($event['type'] === 'flow_started') {
                $this->assertEquals('flow_started', $event['type']);
                $this->assertArrayHasKey('agent', $event['data']);
                $this->assertEquals('test_agent', $event['data']['agent']);
                return; // Test passes
            }
        }

        $this->fail('flow_started event was not emitted');
    }

    public function testExecuteWithStreamingHandlesErrors(): void
    {
        $agent = $this->createMockAgent(null, true);

        $events = [];
        $errorThrown = false;

        try {
            foreach ($this->executor->executeWithStreaming($agent, 'Test task') as $event) {
                $events[] = $event;
            }
        } catch (\Exception $e) {
            $errorThrown = true;
        }

        $this->assertTrue($errorThrown);

        // Check for error event in collected events
        $errorEvents = array_filter($events, fn($e) => $e['type'] === 'error');
        $this->assertNotEmpty($errorEvents);
    }

    public function testExecuteWithStreamingTracksProgress(): void
    {
        $agent = $this->createMockAgent('Test output');

        $events = iterator_to_array(
            $this->executor->executeWithStreaming($agent, 'Test task', ['track_progress' => true])
        );

        // Should have progress events
        $progressEvents = array_filter($events, fn($e) => $e['type'] === 'progress');
        $this->assertNotEmpty($progressEvents);
    }

    public function testExecuteBlockingReturnsAgentResult(): void
    {
        $agent = $this->createMockAgent('Test output');

        $result = $this->executor->execute($agent, 'Test task');

        $this->assertInstanceOf(AgentResult::class, $result);
    }

    public function testGetCurrentProgressReturnsNullWhenNotRunning(): void
    {
        $this->assertNull($this->executor->getCurrentProgress());
    }

    public function testStreamSSEFormatsEventsCorrectly(): void
    {
        $agent = $this->createMockAgent('Test output');

        $sseEvents = [];
        foreach ($this->executor->streamSSE($agent, 'Test task') as $sseData) {
            $sseEvents[] = $sseData;
        }

        $this->assertNotEmpty($sseEvents);

        // Check SSE format
        foreach ($sseEvents as $sseData) {
            $this->assertStringContainsString('event:', $sseData);
            $this->assertStringContainsString('data:', $sseData);
            $this->assertStringContainsString("\n\n", $sseData);
        }
    }

    public function testExecuteWithStreamingDisablesProgressTracking(): void
    {
        $agent = $this->createMockAgent('Test output');

        $events = iterator_to_array(
            $this->executor->executeWithStreaming($agent, 'Test task', ['track_progress' => false])
        );

        // Progress events should be minimal or none
        $progressEvents = array_filter($events, fn($e) => $e['type'] === 'progress');
        $this->assertEmpty($progressEvents);
    }

    /**
     * Create a mock agent for testing
     */
    private function createMockAgent(
        ?string $output = 'Test output',
        bool $shouldThrow = false
    ): AgentInterface {
        $agent = $this->createMock(AgentInterface::class);

        $agent->method('getName')->willReturn('test_agent');

        if ($shouldThrow) {
            $agent->method('run')
                ->willThrowException(new \RuntimeException('Test error'));
        } else {
            // Use real AgentResult instead of mock
            $result = AgentResult::success(
                answer: $output,
                messages: [],
                iterations: 1,
                metadata: []
            );

            $agent->method('run')->willReturn($result);
        }

        return $agent;
    }
}
