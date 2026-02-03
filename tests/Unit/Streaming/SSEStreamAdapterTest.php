<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Streaming;

use ClaudeAgents\Streaming\SSEStreamAdapter;
use ClaudeAgents\Progress\AgentUpdate;
use PHPUnit\Framework\TestCase;

class SSEStreamAdapterTest extends TestCase
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

    public function test_creates_adapter(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
        ]);

        $this->assertInstanceOf(SSEStreamAdapter::class, $adapter);
    }

    public function test_sends_event_in_sse_format(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->sendEvent('test_event', ['key' => 'value']);

        $output = $this->getOutput();

        $this->assertStringContainsString('event: test_event', $output);
        $this->assertStringContainsString('data: {"key":"value"}', $output);
        $this->assertStringContainsString("\n\n", $output); // SSE requires double newline
    }

    public function test_sends_comment(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->sendComment('This is a comment');

        $output = $this->getOutput();

        $this->assertStringContainsString(': This is a comment', $output);
    }

    public function test_sends_ping(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->sendPing();

        $output = $this->getOutput();

        $this->assertStringContainsString(': ping', $output);
    }

    public function test_sends_data_without_event_name(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->sendData(['message' => 'hello']);

        $output = $this->getOutput();

        $this->assertStringContainsString('data: {"message":"hello"}', $output);
        $this->assertStringNotContainsString('event:', $output);
    }

    public function test_sets_retry_timeout(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $adapter->setRetry(3000);

        $output = $this->getOutput();

        $this->assertStringContainsString('retry: 3000', $output);
    }

    public function test_creates_callback_for_agent_update(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $callback = $adapter->createCallback();

        $this->assertIsCallable($callback);

        $update = new AgentUpdate('test.event', 'test_agent', ['data' => 'value'], microtime(true));
        $callback($update);

        $output = $this->getOutput();
        $this->assertStringContainsString('event: test.event', $output);
    }

    public function test_creates_callback_for_code_generation(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
        ]);

        $callback = $adapter->createCodeGenerationCallback();

        $this->assertIsCallable($callback);

        $callback('code.generated', ['lines' => 10]);

        $output = $this->getOutput();
        $this->assertStringContainsString('event: code.generated', $output);
        $this->assertStringContainsString('"lines":10', $output);
    }

    public function test_includes_comments_when_enabled(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
            'include_comments' => true,
        ]);

        $adapter->sendEvent('test', ['data' => 'value']);

        $output = $this->getOutput();
        $this->assertStringContainsString(': Event:', $output);
    }

    public function test_excludes_comments_when_disabled(): void
    {
        $adapter = new SSEStreamAdapter([
            'output_stream' => $this->outputStream,
            'auto_flush' => false,
            'include_comments' => false,
        ]);

        $adapter->sendEvent('test', ['data' => 'value']);

        $output = $this->getOutput();
        // Should only have the event line and data line, no comment lines
        $lines = explode("\n", trim($output));
        $commentLines = array_filter($lines, fn ($line) => str_starts_with($line, ':'));
        $this->assertEmpty($commentLines);
    }
}
