<?php

declare(strict_types=1);

namespace Tests\Unit\MultiAgent;

use ClaudeAgents\MultiAgent\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function test_creates_message_with_required_fields(): void
    {
        $message = new Message(
            from: 'agent1',
            to: 'agent2',
            content: 'Hello, agent2!'
        );

        $this->assertIsString($message->getId());
        $this->assertEquals('agent1', $message->getFrom());
        $this->assertEquals('agent2', $message->getTo());
        $this->assertEquals('Hello, agent2!', $message->getContent());
        $this->assertEquals('message', $message->getType());
        $this->assertIsFloat($message->getTimestamp());
        $this->assertFalse($message->isBroadcast());
    }

    public function test_creates_message_with_custom_type(): void
    {
        $message = new Message(
            from: 'agent1',
            to: 'agent2',
            content: 'Request data',
            type: 'request'
        );

        $this->assertEquals('request', $message->getType());
    }

    public function test_creates_message_with_metadata(): void
    {
        $metadata = ['priority' => 'high', 'session_id' => '123'];

        $message = new Message(
            from: 'agent1',
            to: 'agent2',
            content: 'Urgent message',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $message->getMetadata());
    }

    public function test_identifies_broadcast_message(): void
    {
        $message = new Message(
            from: 'agent1',
            to: 'broadcast',
            content: 'Announcement to all'
        );

        $this->assertTrue($message->isBroadcast());
    }

    public function test_generates_unique_ids(): void
    {
        $message1 = new Message('agent1', 'agent2', 'Message 1');
        $message2 = new Message('agent1', 'agent2', 'Message 2');

        $this->assertNotEquals($message1->getId(), $message2->getId());
    }

    public function test_converts_to_array(): void
    {
        $message = new Message(
            from: 'agent1',
            to: 'agent2',
            content: 'Test',
            type: 'request',
            metadata: ['key' => 'value']
        );

        $array = $message->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('from', $array);
        $this->assertArrayHasKey('to', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('timestamp', $array);

        $this->assertEquals('agent1', $array['from']);
        $this->assertEquals('agent2', $array['to']);
        $this->assertEquals('Test', $array['content']);
        $this->assertEquals('request', $array['type']);
        $this->assertEquals(['key' => 'value'], $array['metadata']);
    }

    public function test_timestamp_is_recent(): void
    {
        $before = microtime(true);
        $message = new Message('agent1', 'agent2', 'Test');
        $after = microtime(true);

        $timestamp = $message->getTimestamp();
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }
}
