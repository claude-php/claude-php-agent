<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor\Tools;

use ClaudeAgents\Vendor\Adapters\OpenAIAdapter;
use ClaudeAgents\Vendor\Tools\OpenAIImageTool;
use ClaudeAgents\Vendor\Tools\OpenAITTSTool;
use ClaudeAgents\Vendor\Tools\OpenAIWebSearchTool;
use PHPUnit\Framework\TestCase;

class OpenAIToolsTest extends TestCase
{
    // ---- OpenAIWebSearchTool ----

    public function testWebSearchToolName(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAIWebSearchTool($adapter);

        $this->assertEquals('openai_web_search', $tool->getName());
    }

    public function testWebSearchToolDescription(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAIWebSearchTool($adapter);

        $this->assertStringContainsString('Search the web', $tool->getDescription());
        $this->assertStringContainsString('citations', $tool->getDescription());
    }

    public function testWebSearchToolSchema(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAIWebSearchTool($adapter);
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertArrayHasKey('context', $schema['properties']);
        $this->assertContains('query', $schema['required']);
    }

    public function testWebSearchExecuteSuccess(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('webSearch')
            ->with('PHP 9 release date', null)
            ->willReturn('PHP 9 is expected in 2027.');

        $tool = new OpenAIWebSearchTool($adapter);
        $result = $tool->execute(['query' => 'PHP 9 release date']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('PHP 9 is expected in 2027.', $result->getContent());
    }

    public function testWebSearchExecuteEmptyQuery(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAIWebSearchTool($adapter);
        $result = $tool->execute(['query' => '']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('query parameter is required', $result->getContent());
    }

    public function testWebSearchExecuteHandlesException(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('webSearch')
            ->willThrowException(new \RuntimeException('Rate limit'));

        $tool = new OpenAIWebSearchTool($adapter);
        $result = $tool->execute(['query' => 'test']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('OpenAI web search error', $result->getContent());
    }

    public function testWebSearchToDefinition(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAIWebSearchTool($adapter);
        $definition = $tool->toDefinition();

        $this->assertEquals('openai_web_search', $definition['name']);
        $this->assertArrayHasKey('input_schema', $definition);
    }

    // ---- OpenAIImageTool ----

    public function testImageToolName(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAIImageTool($adapter);

        $this->assertEquals('openai_image_generation', $tool->getName());
    }

    public function testImageToolSchema(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAIImageTool($adapter);
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('prompt', $schema['properties']);
        $this->assertArrayHasKey('size', $schema['properties']);
        $this->assertArrayHasKey('model', $schema['properties']);
        $this->assertContains('prompt', $schema['required']);
    }

    public function testImageToolExecuteSuccess(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('generateImage')
            ->with('A cat wearing a hat', null, '1024x1024')
            ->willReturn('{"url":"https://example.com/image.png"}');

        $tool = new OpenAIImageTool($adapter);
        $result = $tool->execute(['prompt' => 'A cat wearing a hat']);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('url', $result->getContent());
    }

    public function testImageToolExecuteEmptyPrompt(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAIImageTool($adapter);
        $result = $tool->execute(['prompt' => '']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('prompt parameter is required', $result->getContent());
    }

    public function testImageToolExecuteHandlesException(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('generateImage')
            ->willThrowException(new \RuntimeException('Content policy violation'));

        $tool = new OpenAIImageTool($adapter);
        $result = $tool->execute(['prompt' => 'test']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('OpenAI image generation error', $result->getContent());
    }

    // ---- OpenAITTSTool ----

    public function testTTSToolName(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAITTSTool($adapter);

        $this->assertEquals('openai_text_to_speech', $tool->getName());
    }

    public function testTTSToolSchema(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAITTSTool($adapter);
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('text', $schema['properties']);
        $this->assertArrayHasKey('voice', $schema['properties']);
        $this->assertArrayHasKey('instructions', $schema['properties']);
        $this->assertArrayHasKey('model', $schema['properties']);
        $this->assertContains('text', $schema['required']);
    }

    public function testTTSToolExecuteSuccess(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('textToSpeech')
            ->with('Hello world', 'alloy', null, null)
            ->willReturn(base64_encode('fake-audio-bytes'));

        $tool = new OpenAITTSTool($adapter);
        $result = $tool->execute(['text' => 'Hello world']);

        $this->assertTrue($result->isSuccess());
        $decoded = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('audio_base64', $decoded);
        $this->assertEquals('mp3', $decoded['format']);
        $this->assertEquals('alloy', $decoded['voice']);
    }

    public function testTTSToolExecuteEmptyText(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new OpenAITTSTool($adapter);
        $result = $tool->execute(['text' => '']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('text parameter is required', $result->getContent());
    }

    public function testTTSToolExecuteHandlesException(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('textToSpeech')
            ->willThrowException(new \RuntimeException('Quota exceeded'));

        $tool = new OpenAITTSTool($adapter);
        $result = $tool->execute(['text' => 'test']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('OpenAI TTS error', $result->getContent());
    }

    /**
     * @return OpenAIAdapter&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockAdapter(): OpenAIAdapter
    {
        return $this->createMock(OpenAIAdapter::class);
    }
}
