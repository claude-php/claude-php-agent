<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Feature;

use ClaudeAgents\Streaming\SSEStreamAdapter;
use ClaudeAgents\Progress\AgentUpdate;
use PHPUnit\Framework\TestCase;

/**
 * Feature test for SSE streaming workflow.
 *
 * @group feature
 */
class SSEStreamingWorkflowTest extends TestCase
{
    private $outputStream;

    protected function setUp(): void
    {
        $this->outputStream = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->outputStream)) {
            fclose($this->outputStream);
        }
    }

    private function getOutput(): string
    {
        rewind($this->outputStream);
        return stream_get_contents($this->outputStream);
    }

    public function test_complete_sse_workflow(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        // Simulate a code generation workflow
        $adapter->sendEvent('code.generating', ['description' => 'Create a class']);
        $adapter->sendEvent('code.generated', ['lines' => 20]);
        $adapter->sendEvent('validation.started', ['validators' => 2]);
        $adapter->sendEvent('validation.passed', ['duration' => 150]);
        $adapter->sendEvent('component.completed', ['success' => true]);

        $output = $this->getOutput();

        $this->assertStringContainsString('event: code.generating', $output);
        $this->assertStringContainsString('event: code.generated', $output);
        $this->assertStringContainsString('event: validation.started', $output);
        $this->assertStringContainsString('event: validation.passed', $output);
        $this->assertStringContainsString('event: component.completed', $output);
    }

    public function test_sse_format_compliance(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->sendEvent('test', ['key' => 'value']);

        $output = $this->getOutput();

        // SSE format: event: name\ndata: json\n\n
        $this->assertMatchesRegularExpression('/event: \w+\n/', $output);
        $this->assertMatchesRegularExpression('/data: \{.*\}\n\n/', $output);
    }

    public function test_sse_with_agent_update(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $callback = $adapter->createCallback();

        $update = new AgentUpdate(
            type: 'test.event',
            agent: 'test_agent',
            data: ['message' => 'test'],
            timestamp: microtime(true)
        );

        $callback($update);

        $output = $this->getOutput();

        $this->assertStringContainsString('event: test.event', $output);
        $this->assertStringContainsString('"agent":"test_agent"', $output);
        $this->assertStringContainsString('"message":"test"', $output);
    }

    public function test_sse_with_code_generation_callback(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $callback = $adapter->createCodeGenerationCallback();

        $callback('code.generated', [
            'lines' => 50,
            'bytes' => 1500,
        ]);

        $output = $this->getOutput();

        $this->assertStringContainsString('event: code.generated', $output);
        $this->assertStringContainsString('"lines":50', $output);
        $this->assertStringContainsString('"bytes":1500', $output);
    }

    public function test_sse_keepalive_ping(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->sendPing();

        $output = $this->getOutput();

        $this->assertStringContainsString(': ping', $output);
    }

    public function test_sse_comment_format(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->sendComment('This is a debug message');

        $output = $this->getOutput();

        $this->assertStringContainsString(': This is a debug message', $output);
    }

    public function test_sse_retry_directive(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->setRetry(3000);

        $output = $this->getOutput();

        $this->assertStringContainsString('retry: 3000', $output);
    }

    public function test_sse_multiple_events_sequence(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        // Simulate validation retry workflow
        $adapter->sendEvent('validation.started', ['attempt' => 1]);
        $adapter->sendEvent('validation.failed', ['errors' => ['Syntax error']]);
        $adapter->sendEvent('retry.attempt', ['attempt' => 2]);
        $adapter->sendEvent('validation.started', ['attempt' => 2]);
        $adapter->sendEvent('validation.passed', []);

        $output = $this->getOutput();

        // Count events
        $eventCount = substr_count($output, 'event:');
        $this->assertEquals(5, $eventCount);
    }
}
