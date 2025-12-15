<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Events;

use ClaudeAgents\Events\AgentStartedEvent;
use ClaudeAgents\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function test_listen_and_dispatch(): void
    {
        $called = false;

        $this->dispatcher->listen(AgentStartedEvent::class, function () use (&$called) {
            $called = true;
        });

        $event = new AgentStartedEvent('test', 'task');
        $this->dispatcher->dispatch($event);

        $this->assertTrue($called);
    }

    public function test_multiple_listeners(): void
    {
        $count = 0;

        $this->dispatcher->listen(AgentStartedEvent::class, function () use (&$count) {
            $count++;
        });

        $this->dispatcher->listen(AgentStartedEvent::class, function () use (&$count) {
            $count++;
        });

        $event = new AgentStartedEvent('test', 'task');
        $this->dispatcher->dispatch($event);

        $this->assertSame(2, $count);
    }

    public function test_dispatch_without_listeners(): void
    {
        $event = new AgentStartedEvent('test', 'task');

        // Should not throw
        $this->dispatcher->dispatch($event);

        $this->assertTrue(true);
    }

    public function test_clear_listeners(): void
    {
        $called = false;

        $this->dispatcher->listen(AgentStartedEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->clearListeners(AgentStartedEvent::class);

        $event = new AgentStartedEvent('test', 'task');
        $this->dispatcher->dispatch($event);

        $this->assertFalse($called);
    }

    public function test_clear_all_listeners(): void
    {
        $called = false;

        $this->dispatcher->listen(AgentStartedEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->clearAllListeners();

        $event = new AgentStartedEvent('test', 'task');
        $this->dispatcher->dispatch($event);

        $this->assertFalse($called);
    }

    public function test_has_listeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners(AgentStartedEvent::class));

        $this->dispatcher->listen(AgentStartedEvent::class, function () {
        });

        $this->assertTrue($this->dispatcher->hasListeners(AgentStartedEvent::class));
    }

    public function test_listener_receives_event(): void
    {
        $receivedEvent = null;

        $this->dispatcher->listen(AgentStartedEvent::class, function ($event) use (&$receivedEvent) {
            $receivedEvent = $event;
        });

        $event = new AgentStartedEvent('test', 'my task');
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $receivedEvent);
        $this->assertSame('test', $receivedEvent->getAgentName());
        $this->assertSame('my task', $receivedEvent->getTask());
    }
}
