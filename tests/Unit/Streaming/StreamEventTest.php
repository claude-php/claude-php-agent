<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Streaming;

use ClaudeAgents\Streaming\StreamEvent;
use PHPUnit\Framework\TestCase;

class StreamEventTest extends TestCase
{
    public function testConstruction(): void
    {
        $event = new StreamEvent('test_type', 'test text', ['key' => 'value'], 1234567890);

        $this->assertEquals('test_type', $event->getType());
        $this->assertEquals('test text', $event->getText());
        $this->assertEquals(['key' => 'value'], $event->getData());
        $this->assertEquals(1234567890, $event->getTimestamp());
    }

    public function testIsText(): void
    {
        $textEvent = new StreamEvent(StreamEvent::TYPE_TEXT, 'Hello');
        $deltaEvent = new StreamEvent(StreamEvent::TYPE_CONTENT_BLOCK_DELTA, 'Hi');
        $toolEvent = new StreamEvent(StreamEvent::TYPE_TOOL_USE);

        $this->assertTrue($textEvent->isText());
        $this->assertTrue($deltaEvent->isText());
        $this->assertFalse($toolEvent->isText());
    }

    public function testIsToolUse(): void
    {
        $toolEvent = new StreamEvent(StreamEvent::TYPE_TOOL_USE);
        $textEvent = new StreamEvent(StreamEvent::TYPE_TEXT);

        $this->assertTrue($toolEvent->isToolUse());
        $this->assertFalse($textEvent->isToolUse());
    }

    public function testGetToolUse(): void
    {
        $toolData = ['name' => 'calculator', 'input' => ['a' => 5]];
        $toolEvent = new StreamEvent(StreamEvent::TYPE_TOOL_USE, '', $toolData);
        $textEvent = new StreamEvent(StreamEvent::TYPE_TEXT);

        $this->assertEquals($toolData, $toolEvent->getToolUse());
        $this->assertNull($textEvent->getToolUse());
    }

    public function testTextFactory(): void
    {
        $event = StreamEvent::text('Hello');

        $this->assertEquals(StreamEvent::TYPE_TEXT, $event->getType());
        $this->assertEquals('Hello', $event->getText());
    }

    public function testToolUseFactory(): void
    {
        $toolData = ['name' => 'test'];
        $event = StreamEvent::toolUse($toolData);

        $this->assertEquals(StreamEvent::TYPE_TOOL_USE, $event->getType());
        $this->assertEquals($toolData, $event->getData());
        $this->assertEquals('', $event->getText());
    }

    public function testDeltaFactory(): void
    {
        $event = StreamEvent::delta('chunk');

        $this->assertEquals(StreamEvent::TYPE_CONTENT_BLOCK_DELTA, $event->getType());
        $this->assertEquals('chunk', $event->getText());
    }

    public function testToArray(): void
    {
        $event = new StreamEvent('test_type', 'test text', ['key' => 'value'], 1234567890);

        $array = $event->toArray();

        $this->assertEquals('test_type', $array['type']);
        $this->assertEquals('test text', $array['text']);
        $this->assertEquals(['key' => 'value'], $array['data']);
        $this->assertEquals(1234567890, $array['timestamp']);
    }

    public function testErrorFactory(): void
    {
        $event = StreamEvent::error('Something went wrong', ['code' => 500]);

        $this->assertEquals(StreamEvent::TYPE_ERROR, $event->getType());
        $this->assertEquals('Something went wrong', $event->getText());
        $this->assertEquals(['code' => 500], $event->getData());
        $this->assertTrue($event->isError());
    }

    public function testMetadataFactory(): void
    {
        $metadata = ['model' => 'claude-3', 'version' => '1.0'];
        $event = StreamEvent::metadata($metadata);

        $this->assertEquals(StreamEvent::TYPE_METADATA, $event->getType());
        $this->assertEquals($metadata, $event->getData());
        $this->assertTrue($event->isMetadata());
    }

    public function testPingFactory(): void
    {
        $event = StreamEvent::ping();

        $this->assertEquals(StreamEvent::TYPE_PING, $event->getType());
        $this->assertEquals('', $event->getText());
        $this->assertTrue($event->isPing());
    }

    public function testIsError(): void
    {
        $errorEvent = StreamEvent::error('Error');
        $textEvent = StreamEvent::text('Text');

        $this->assertTrue($errorEvent->isError());
        $this->assertFalse($textEvent->isError());
    }

    public function testIsMetadata(): void
    {
        $metadataEvent = StreamEvent::metadata(['key' => 'value']);
        $textEvent = StreamEvent::text('Text');

        $this->assertTrue($metadataEvent->isMetadata());
        $this->assertFalse($textEvent->isMetadata());
    }

    public function testIsPing(): void
    {
        $pingEvent = StreamEvent::ping();
        $textEvent = StreamEvent::text('Text');

        $this->assertTrue($pingEvent->isPing());
        $this->assertFalse($textEvent->isPing());
    }
}
