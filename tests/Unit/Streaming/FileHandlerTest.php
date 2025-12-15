<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Streaming;

use ClaudeAgents\Streaming\Handlers\FileHandler;
use ClaudeAgents\Streaming\StreamEvent;
use PHPUnit\Framework\TestCase;

class FileHandlerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = sys_get_temp_dir() . '/test_stream_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testHandleTextEvent(): void
    {
        $handler = new FileHandler($this->tempFile);
        $event = StreamEvent::text('Hello World');

        $handler->handle($event);
        unset($handler); // Trigger destructor to close file

        $content = file_get_contents($this->tempFile);
        $this->assertEquals('Hello World', $content);
    }

    public function testAppendMode(): void
    {
        file_put_contents($this->tempFile, 'Existing ');

        $handler = new FileHandler($this->tempFile, append: true);
        $handler->handle(StreamEvent::text('Content'));
        unset($handler);

        $content = file_get_contents($this->tempFile);
        $this->assertEquals('Existing Content', $content);
    }

    public function testOverwriteMode(): void
    {
        file_put_contents($this->tempFile, 'Old Content');

        $handler = new FileHandler($this->tempFile, append: false);
        $handler->handle(StreamEvent::text('New Content'));
        unset($handler);

        $content = file_get_contents($this->tempFile);
        $this->assertEquals('New Content', $content);
    }

    public function testIncludeTimestamps(): void
    {
        $handler = new FileHandler($this->tempFile, includeTimestamps: true);
        $event = StreamEvent::text('Test');

        $handler->handle($event);
        unset($handler);

        $content = file_get_contents($this->tempFile);
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] Test$/', $content);
    }

    public function testIncludeEventTypes(): void
    {
        $handler = new FileHandler($this->tempFile, includeEventTypes: true);
        $event = StreamEvent::text('Test');

        $handler->handle($event);
        unset($handler);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('[text]', $content);
    }

    public function testHandleErrorEvent(): void
    {
        $handler = new FileHandler($this->tempFile);
        $event = StreamEvent::error('Test error', ['code' => 500]);

        $handler->handle($event);
        unset($handler);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('ERROR: Test error', $content);
        $this->assertStringContainsString('"code": 500', $content);
    }

    public function testHandleToolUseEvent(): void
    {
        $handler = new FileHandler($this->tempFile);
        $event = StreamEvent::toolUse(['name' => 'calculator', 'input' => ['a' => 5]]);

        $handler->handle($event);
        unset($handler);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('TOOL_USE:', $content);
        $this->assertStringContainsString('calculator', $content);
    }

    public function testHandleMetadataEvent(): void
    {
        $handler = new FileHandler($this->tempFile);
        $event = StreamEvent::metadata(['version' => '1.0', 'model' => 'claude-3']);

        $handler->handle($event);
        unset($handler);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('METADATA:', $content);
        $this->assertStringContainsString('version', $content);
    }

    public function testGetName(): void
    {
        $handler = new FileHandler($this->tempFile);

        $this->assertEquals('file', $handler->getName());
    }

    public function testInvalidFilePath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to open file');

        new FileHandler('/invalid/path/that/does/not/exist/file.txt');
    }

    public function testMultipleEvents(): void
    {
        $handler = new FileHandler($this->tempFile);
        $handler->handle(StreamEvent::text('Hello '));
        $handler->handle(StreamEvent::text('World'));
        unset($handler);

        $content = file_get_contents($this->tempFile);
        $this->assertEquals('Hello World', $content);
    }
}
