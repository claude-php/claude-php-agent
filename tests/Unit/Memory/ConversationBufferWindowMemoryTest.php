<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Memory;

use ClaudeAgents\Memory\ConversationBufferWindowMemory;
use PHPUnit\Framework\TestCase;

class ConversationBufferWindowMemoryTest extends TestCase
{
    private ConversationBufferWindowMemory $memory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memory = new ConversationBufferWindowMemory(windowSize: 3);
    }

    public function testConstructorValidatesWindowSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConversationBufferWindowMemory(windowSize: 0);
    }

    public function testAddMessage(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Hello']);

        $this->assertEquals(1, $this->memory->count());
    }

    public function testWindowSizeLimit(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Message 1']);
        $this->memory->add(['role' => 'assistant', 'content' => 'Response 1']);
        $this->memory->add(['role' => 'user', 'content' => 'Message 2']);
        $this->memory->add(['role' => 'assistant', 'content' => 'Response 2']);

        // Should only keep last 3 messages
        $this->assertEquals(3, $this->memory->count());

        $messages = $this->memory->getMessages();
        $this->assertEquals('Response 1', $messages[0]['content']);
        $this->assertEquals('Response 2', $messages[2]['content']);
    }

    public function testGetMessages(): void
    {
        $message = ['role' => 'user', 'content' => 'Test'];
        $this->memory->add($message);

        $messages = $this->memory->getMessages();

        $this->assertCount(1, $messages);
        $this->assertEquals($message, $messages[0]);
    }

    public function testGetContext(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Hello']);

        $context = $this->memory->getContext();

        $this->assertIsArray($context);
        $this->assertCount(1, $context);
    }

    public function testClear(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'Test']);
        $this->memory->clear();

        $this->assertEquals(0, $this->memory->count());
        $this->assertEquals(0, $this->memory->getEstimatedTokens());
    }

    public function testTokenEstimation(): void
    {
        $this->memory->add(['role' => 'user', 'content' => 'This is a test message']);

        $tokens = $this->memory->getEstimatedTokens();

        $this->assertGreaterThan(0, $tokens);
    }

    public function testGetWindowSize(): void
    {
        $this->assertEquals(3, $this->memory->getWindowSize());
    }

    public function testIsFull(): void
    {
        $this->assertFalse($this->memory->isFull());

        $this->memory->add(['role' => 'user', 'content' => '1']);
        $this->memory->add(['role' => 'user', 'content' => '2']);
        $this->assertFalse($this->memory->isFull());

        $this->memory->add(['role' => 'user', 'content' => '3']);
        $this->assertTrue($this->memory->isFull());
    }

    public function testGetOldest(): void
    {
        $this->assertNull($this->memory->getOldest());

        $first = ['role' => 'user', 'content' => 'First'];
        $this->memory->add($first);
        $this->memory->add(['role' => 'user', 'content' => 'Second']);

        $this->assertEquals($first, $this->memory->getOldest());
    }

    public function testGetNewest(): void
    {
        $this->assertNull($this->memory->getNewest());

        $this->memory->add(['role' => 'user', 'content' => 'First']);
        $last = ['role' => 'user', 'content' => 'Last'];
        $this->memory->add($last);

        $this->assertEquals($last, $this->memory->getNewest());
    }

    public function testAddMany(): void
    {
        $messages = [
            ['role' => 'user', 'content' => '1'],
            ['role' => 'assistant', 'content' => '2'],
            ['role' => 'user', 'content' => '3'],
        ];

        $this->memory->addMany($messages);

        $this->assertEquals(3, $this->memory->count());
    }

    public function testHandlesArrayContent(): void
    {
        $this->memory->add([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'Hello'],
                ['type' => 'tool_use', 'name' => 'calculator'],
            ],
        ]);

        $tokens = $this->memory->getEstimatedTokens();
        $this->assertGreaterThan(0, $tokens);
    }
}
