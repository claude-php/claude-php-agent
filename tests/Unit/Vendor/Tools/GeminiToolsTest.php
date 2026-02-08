<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor\Tools;

use ClaudeAgents\Vendor\Adapters\GeminiAdapter;
use ClaudeAgents\Vendor\Tools\GeminiCodeExecTool;
use ClaudeAgents\Vendor\Tools\GeminiGroundingTool;
use ClaudeAgents\Vendor\Tools\GeminiImageTool;
use PHPUnit\Framework\TestCase;

class GeminiToolsTest extends TestCase
{
    // ---- GeminiGroundingTool ----

    public function testGroundingToolName(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiGroundingTool($adapter);

        $this->assertEquals('gemini_grounding', $tool->getName());
    }

    public function testGroundingToolDescription(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiGroundingTool($adapter);

        $this->assertStringContainsString('Google Search', $tool->getDescription());
        $this->assertStringContainsString('grounded', $tool->getDescription());
    }

    public function testGroundingToolSchema(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiGroundingTool($adapter);
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertArrayHasKey('model', $schema['properties']);
        $this->assertContains('query', $schema['required']);
    }

    public function testGroundingToolExecuteSuccess(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('groundedSearch')
            ->with('What is PHP?', null)
            ->willReturn('PHP is a server-side scripting language. Sources: [php.net]');

        $tool = new GeminiGroundingTool($adapter);
        $result = $tool->execute(['query' => 'What is PHP?']);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('PHP is a server-side', $result->getContent());
    }

    public function testGroundingToolExecuteEmptyQuery(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiGroundingTool($adapter);
        $result = $tool->execute(['query' => '']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('query parameter is required', $result->getContent());
    }

    public function testGroundingToolExecuteHandlesException(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('groundedSearch')
            ->willThrowException(new \RuntimeException('API Error'));

        $tool = new GeminiGroundingTool($adapter);
        $result = $tool->execute(['query' => 'test']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Gemini grounding error', $result->getContent());
    }

    public function testGroundingToolToDefinition(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiGroundingTool($adapter);
        $definition = $tool->toDefinition();

        $this->assertEquals('gemini_grounding', $definition['name']);
        $this->assertArrayHasKey('input_schema', $definition);
    }

    // ---- GeminiCodeExecTool ----

    public function testCodeExecToolName(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiCodeExecTool($adapter);

        $this->assertEquals('gemini_code_execution', $tool->getName());
    }

    public function testCodeExecToolDescription(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiCodeExecTool($adapter);

        $this->assertStringContainsString('Python', $tool->getDescription());
        $this->assertStringContainsString('execute', $tool->getDescription());
    }

    public function testCodeExecToolSchema(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiCodeExecTool($adapter);
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('prompt', $schema['properties']);
        $this->assertArrayHasKey('model', $schema['properties']);
        $this->assertContains('prompt', $schema['required']);
    }

    public function testCodeExecToolExecuteSuccess(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('codeExecution')
            ->with('Calculate 2+2', null)
            ->willReturn("```python\nprint(2+2)\n```\n\nOutput:\n4");

        $tool = new GeminiCodeExecTool($adapter);
        $result = $tool->execute(['prompt' => 'Calculate 2+2']);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('4', $result->getContent());
    }

    public function testCodeExecToolExecuteEmptyPrompt(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiCodeExecTool($adapter);
        $result = $tool->execute(['prompt' => '']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('prompt parameter is required', $result->getContent());
    }

    public function testCodeExecToolExecuteHandlesException(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('codeExecution')
            ->willThrowException(new \RuntimeException('Sandbox timeout'));

        $tool = new GeminiCodeExecTool($adapter);
        $result = $tool->execute(['prompt' => 'infinite loop']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Gemini code execution error', $result->getContent());
    }

    // ---- GeminiImageTool ----

    public function testImageToolName(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiImageTool($adapter);

        $this->assertEquals('gemini_image_generation', $tool->getName());
    }

    public function testImageToolDescription(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiImageTool($adapter);

        $this->assertStringContainsString('Nano Banana', $tool->getDescription());
    }

    public function testImageToolSchema(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiImageTool($adapter);
        $schema = $tool->getInputSchema();

        $this->assertArrayHasKey('prompt', $schema['properties']);
        $this->assertArrayHasKey('model', $schema['properties']);
        $this->assertArrayHasKey('aspect_ratio', $schema['properties']);
        $this->assertArrayHasKey('resolution', $schema['properties']);
        $this->assertArrayHasKey('grounding', $schema['properties']);
        $this->assertContains('prompt', $schema['required']);
    }

    public function testImageToolExecuteSuccess(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('generateImage')
            ->willReturn('{"text":"A beautiful sunset","images":[{"mime_type":"image/png","data":"base64data"}]}');

        $tool = new GeminiImageTool($adapter);
        $result = $tool->execute(['prompt' => 'A beautiful sunset']);

        $this->assertTrue($result->isSuccess());
        $decoded = json_decode($result->getContent(), true);
        $this->assertNotNull($decoded);
    }

    public function testImageToolExecuteEmptyPrompt(): void
    {
        $adapter = $this->createMockAdapter();
        $tool = new GeminiImageTool($adapter);
        $result = $tool->execute(['prompt' => '']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('prompt parameter is required', $result->getContent());
    }

    public function testImageToolExecuteHandlesException(): void
    {
        $adapter = $this->createMockAdapter();
        $adapter->method('generateImage')
            ->willThrowException(new \RuntimeException('Model overloaded'));

        $tool = new GeminiImageTool($adapter);
        $result = $tool->execute(['prompt' => 'test']);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Gemini image generation error', $result->getContent());
    }

    /**
     * @return GeminiAdapter&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockAdapter(): GeminiAdapter
    {
        return $this->createMock(GeminiAdapter::class);
    }
}
