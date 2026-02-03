<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEvent;
use ClaudeAgents\Events\FlowEventManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \ClaudeAgents\Events\FlowEventManager
 */
class FlowEventManagerTest extends TestCase
{
    private EventQueue $queue;
    private FlowEventManager $manager;

    protected function setUp(): void
    {
        $this->queue = new EventQueue(maxSize: 100);
        $this->manager = new FlowEventManager($this->queue, new NullLogger());
    }

    public function testCanRegisterEvent(): void
    {
        $this->manager->registerEvent('on_test', FlowEvent::TOKEN_RECEIVED);

        $this->assertTrue($this->manager->hasEvent('on_test'));
        $this->assertContains('on_test', $this->manager->getRegisteredEvents());
    }

    public function testEventNameMustStartWithOn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Event name must start with 'on_'");

        $this->manager->registerEvent('test_event', FlowEvent::TOKEN_RECEIVED);
    }

    public function testCannotRegisterEmptyEventName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name cannot be empty');

        $this->manager->registerEvent('', FlowEvent::TOKEN_RECEIVED);
    }

    public function testCanEmitEvent(): void
    {
        $this->manager->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED);

        $result = $this->manager->emit(FlowEvent::TOKEN_RECEIVED, ['token' => 'hello']);

        $this->assertTrue($result);
        $this->assertFalse($this->queue->isEmpty());

        $event = $this->queue->dequeue();
        $this->assertInstanceOf(FlowEvent::class, $event);
        $this->assertEquals(FlowEvent::TOKEN_RECEIVED, $event->type);
        $this->assertEquals('hello', $event->data['token']);
    }

    public function testEventCallbackIsExecuted(): void
    {
        $callbackExecuted = false;
        $receivedEvent = null;

        $callback = function (FlowEvent $event) use (&$callbackExecuted, &$receivedEvent) {
            $callbackExecuted = true;
            $receivedEvent = $event;
        };

        $this->manager->registerEvent('on_test', FlowEvent::TOKEN_RECEIVED, $callback);
        $this->manager->emit(FlowEvent::TOKEN_RECEIVED, ['test' => 'data']);

        $this->assertTrue($callbackExecuted);
        $this->assertInstanceOf(FlowEvent::class, $receivedEvent);
        $this->assertEquals(FlowEvent::TOKEN_RECEIVED, $receivedEvent->type);
        $this->assertEquals('data', $receivedEvent->data['test']);
    }

    public function testCanSubscribeListener(): void
    {
        $eventReceived = false;

        $listener = function (FlowEvent $event) use (&$eventReceived) {
            $eventReceived = true;
        };

        $listenerId = $this->manager->subscribe($listener);

        $this->assertNotEmpty($listenerId);
        $this->assertEquals(1, $this->manager->getListenerCount());

        $this->manager->emit(FlowEvent::TOKEN_RECEIVED, ['test' => 'data']);

        $this->assertTrue($eventReceived);
    }

    public function testCanUnsubscribeListener(): void
    {
        $listener = function (FlowEvent $event) {
            // No-op
        };

        $listenerId = $this->manager->subscribe($listener);
        $this->assertEquals(1, $this->manager->getListenerCount());

        $this->manager->unsubscribe($listenerId);
        $this->assertEquals(0, $this->manager->getListenerCount());
    }

    public function testMultipleListenersReceiveEvents(): void
    {
        $listener1Called = false;
        $listener2Called = false;

        $listener1 = function (FlowEvent $event) use (&$listener1Called) {
            $listener1Called = true;
        };

        $listener2 = function (FlowEvent $event) use (&$listener2Called) {
            $listener2Called = true;
        };

        $this->manager->subscribe($listener1);
        $this->manager->subscribe($listener2);

        $this->manager->emit(FlowEvent::TOKEN_RECEIVED, ['test' => 'data']);

        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
    }

    public function testCanClearAllListeners(): void
    {
        $this->manager->subscribe(fn() => null);
        $this->manager->subscribe(fn() => null);
        $this->manager->subscribe(fn() => null);

        $this->assertEquals(3, $this->manager->getListenerCount());

        $this->manager->clearListeners();

        $this->assertEquals(0, $this->manager->getListenerCount());
    }

    public function testMagicCallMethodEmitsRegisteredEvent(): void
    {
        $this->manager->registerEvent('on_token', FlowEvent::TOKEN_RECEIVED);

        $result = $this->manager->on_token(['token' => 'hello']);

        $this->assertTrue($result);
        $this->assertFalse($this->queue->isEmpty());

        $event = $this->queue->dequeue();
        $this->assertEquals('hello', $event->data['token']);
    }

    public function testMagicCallReturnsFalseForUnregisteredEvent(): void
    {
        $result = $this->manager->on_nonexistent(['data' => 'test']);

        $this->assertFalse($result);
    }

    public function testRegisterDefaultEventsCreatesAllExpectedEvents(): void
    {
        $this->manager->registerDefaultEvents();

        $expectedEvents = [
            'on_token',
            'on_vertices_sorted',
            'on_error',
            'on_end',
            'on_message',
            'on_remove_message',
            'on_end_vertex',
            'on_build_start',
            'on_build_end',
        ];

        foreach ($expectedEvents as $eventName) {
            $this->assertTrue($this->manager->hasEvent($eventName), "Missing event: {$eventName}");
        }
    }

    public function testRegisterStreamingEventsCreatesStreamingEvents(): void
    {
        $this->manager->registerStreamingEvents();

        $this->assertTrue($this->manager->hasEvent('on_message'));
        $this->assertTrue($this->manager->hasEvent('on_token'));
        $this->assertTrue($this->manager->hasEvent('on_end'));
    }

    public function testEmitReturnsFalseWhenQueueIsFull(): void
    {
        $smallQueue = new EventQueue(maxSize: 1);
        $manager = new FlowEventManager($smallQueue);

        $this->assertTrue($manager->emit(FlowEvent::TOKEN_RECEIVED, ['token' => '1']));
        $this->assertFalse($manager->emit(FlowEvent::TOKEN_RECEIVED, ['token' => '2'])); // Queue full
    }

    public function testGetQueueReturnsEventQueue(): void
    {
        $queue = $this->manager->getQueue();

        $this->assertInstanceOf(EventQueue::class, $queue);
        $this->assertSame($this->queue, $queue);
    }
}
