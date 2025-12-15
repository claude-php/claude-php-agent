<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Streaming;

use ClaudeAgents\Streaming\Handlers\CallbackHandler;
use ClaudeAgents\Streaming\StreamEvent;
use PHPUnit\Framework\TestCase;

class CallbackHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $called = false;
        $receivedEvent = null;

        $callback = function ($event) use (&$called, &$receivedEvent) {
            $called = true;
            $receivedEvent = $event;
        };

        $handler = new CallbackHandler($callback);
        $event = StreamEvent::text('Test');

        $handler->handle($event);

        $this->assertTrue($called);
        $this->assertSame($event, $receivedEvent);
    }

    public function testGetName(): void
    {
        $handler = new CallbackHandler(fn () => null);

        $this->assertEquals('callback', $handler->getName());
    }

    public function testHandleMultipleTimes(): void
    {
        $count = 0;

        $handler = new CallbackHandler(function () use (&$count) {
            $count++;
        });

        $handler->handle(StreamEvent::text('1'));
        $handler->handle(StreamEvent::text('2'));
        $handler->handle(StreamEvent::text('3'));

        $this->assertEquals(3, $count);
    }

    public function testHandleWithEventData(): void
    {
        $texts = [];

        $handler = new CallbackHandler(function ($event) use (&$texts) {
            $texts[] = $event->getText();
        });

        $handler->handle(StreamEvent::text('Hello'));
        $handler->handle(StreamEvent::text('World'));

        $this->assertEquals(['Hello', 'World'], $texts);
    }
}
