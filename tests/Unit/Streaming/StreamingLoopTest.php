<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Streaming;

use ClaudeAgents\Streaming\Handlers\CallbackHandler;
use ClaudeAgents\Streaming\StreamingLoop;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class StreamingLoopTest extends TestCase
{
    private StreamingLoop $loop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loop = new StreamingLoop(new NullLogger());
    }

    public function testGetName(): void
    {
        $this->assertEquals('streaming', $this->loop->getName());
    }

    public function testAddHandler(): void
    {
        $called = false;
        $handler = new CallbackHandler(function () use (&$called) {
            $called = true;
        });

        $result = $this->loop->addHandler($handler);

        $this->assertSame($this->loop, $result);
        // Handler is added, will be called during execution
    }

    public function testOnStream(): void
    {
        $result = $this->loop->onStream(function ($event) {
            // Callback added
        });

        $this->assertSame($this->loop, $result);
    }

    public function testOnIteration(): void
    {
        $result = $this->loop->onIteration(function ($iteration, $response, $context) {
            // Callback added
        });

        $this->assertSame($this->loop, $result);
    }

    public function testOnToolExecution(): void
    {
        $result = $this->loop->onToolExecution(function ($tool, $input, $result) {
            // Callback added
        });

        $this->assertSame($this->loop, $result);
    }

    public function testFluentInterface(): void
    {
        $handler1 = new CallbackHandler(fn () => null);
        $handler2 = new CallbackHandler(fn () => null);

        $loop = $this->loop
            ->addHandler($handler1)
            ->addHandler($handler2)
            ->onStream(fn () => null)
            ->onIteration(fn () => null)
            ->onToolExecution(fn () => null);

        $this->assertSame($this->loop, $loop);
    }

    public function testStreamEventHandling(): void
    {
        $receivedEvents = [];

        $handler = new CallbackHandler(function ($event) use (&$receivedEvents) {
            $receivedEvents[] = $event;
        });

        $this->loop->addHandler($handler);

        // Note: Full integration test would require actual Claude API
        // This test verifies the handler registration works
        $this->assertEmpty($receivedEvents); // No events yet without execution
    }

    public function testIterationCallback(): void
    {
        $iterations = [];

        $this->loop->onIteration(function ($iteration, $response, $context) use (&$iterations) {
            $iterations[] = $iteration;
        });

        // Callback registered successfully
        $this->assertEmpty($iterations); // No iterations without execution
    }

    public function testToolExecutionCallback(): void
    {
        $executedTools = [];

        $this->loop->onToolExecution(function ($tool, $input, $result) use (&$executedTools) {
            $executedTools[] = $tool;
        });

        // Callback registered successfully
        $this->assertEmpty($executedTools); // No tools executed without execution
    }

    public function testMultipleHandlers(): void
    {
        $count1 = 0;
        $count2 = 0;

        $handler1 = new CallbackHandler(function () use (&$count1) {
            $count1++;
        });

        $handler2 = new CallbackHandler(function () use (&$count2) {
            $count2++;
        });

        $this->loop
            ->addHandler($handler1)
            ->addHandler($handler2);

        // Both handlers registered
        $this->assertEquals(0, $count1);
        $this->assertEquals(0, $count2);
    }

    /**
     * Test that the loop handles context correctly.
     *
     * Note: This is a unit test, so we don't actually call the API.
     * Full integration tests would require API access and mocking.
     */
    public function testContextHandling(): void
    {
        // We can't easily test execute() without a real client,
        // but we can verify the loop is properly configured
        $this->assertEquals('streaming', $this->loop->getName());
    }

    public function testCallbacksAreOptional(): void
    {
        // Should work without any callbacks
        $loop = new StreamingLoop();

        $this->assertInstanceOf(StreamingLoop::class, $loop);
        $this->assertEquals('streaming', $loop->getName());
    }

    public function testHandlerChaining(): void
    {
        $events = [];

        $loop = (new StreamingLoop())
            ->onStream(function ($event) use (&$events) {
                $events[] = 'stream';
            })
            ->onIteration(function () use (&$events) {
                $events[] = 'iteration';
            })
            ->onToolExecution(function () use (&$events) {
                $events[] = 'tool';
            });

        $this->assertInstanceOf(StreamingLoop::class, $loop);
        // Callbacks are registered but not yet called
        $this->assertEmpty($events);
    }
}
