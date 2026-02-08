<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor\Tools;

use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\Contracts\VendorAdapterInterface;
use ClaudeAgents\Vendor\Tools\VendorChatTool;
use PHPUnit\Framework\TestCase;

class VendorChatToolTest extends TestCase
{
    public function testGetName(): void
    {
        $tool = new VendorChatTool([]);

        $this->assertEquals('vendor_chat', $tool->getName());
    }

    public function testGetDescriptionIncludesVendors(): void
    {
        $adapters = [
            'openai' => $this->createMockAdapter('openai'),
            'google' => $this->createMockAdapter('google'),
        ];

        $tool = new VendorChatTool($adapters);

        $this->assertStringContainsString('openai', $tool->getDescription());
        $this->assertStringContainsString('google', $tool->getDescription());
    }

    public function testGetInputSchema(): void
    {
        $adapters = [
            'openai' => $this->createMockAdapter('openai'),
        ];

        $tool = new VendorChatTool($adapters);
        $schema = $tool->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('prompt', $schema['properties']);
        $this->assertArrayHasKey('vendor', $schema['properties']);
        $this->assertArrayHasKey('model', $schema['properties']);
        $this->assertArrayHasKey('system', $schema['properties']);
        $this->assertContains('prompt', $schema['required']);
        $this->assertContains('vendor', $schema['required']);
    }

    public function testExecuteWithValidVendor(): void
    {
        $adapter = $this->createMockAdapter('openai');
        $adapter->method('chat')
            ->with('Hello from Claude', $this->anything())
            ->willReturn('Hello from OpenAI!');

        $tool = new VendorChatTool(['openai' => $adapter]);

        $result = $tool->execute([
            'prompt' => 'Hello from Claude',
            'vendor' => 'openai',
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hello from OpenAI!', $result->getContent());
    }

    public function testExecuteWithModelAndSystemOptions(): void
    {
        $adapter = $this->createMockAdapter('openai');
        $adapter->expects($this->once())
            ->method('chat')
            ->with('Test prompt', $this->callback(function (array $options) {
                return $options['model'] === 'gpt-5.2-pro' && $options['system'] === 'Be concise';
            }))
            ->willReturn('Concise response');

        $tool = new VendorChatTool(['openai' => $adapter]);

        $result = $tool->execute([
            'prompt' => 'Test prompt',
            'vendor' => 'openai',
            'model' => 'gpt-5.2-pro',
            'system' => 'Be concise',
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Concise response', $result->getContent());
    }

    public function testExecuteWithInvalidVendor(): void
    {
        $tool = new VendorChatTool([
            'openai' => $this->createMockAdapter('openai'),
        ]);

        $result = $tool->execute([
            'prompt' => 'Hello',
            'vendor' => 'nonexistent',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString("'nonexistent' is not available", $result->getContent());
    }

    public function testExecuteWithEmptyPrompt(): void
    {
        $tool = new VendorChatTool([
            'openai' => $this->createMockAdapter('openai'),
        ]);

        $result = $tool->execute([
            'prompt' => '',
            'vendor' => 'openai',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('prompt parameter is required', $result->getContent());
    }

    public function testExecuteHandlesAdapterException(): void
    {
        $adapter = $this->createMockAdapter('openai');
        $adapter->method('chat')
            ->willThrowException(new \RuntimeException('API rate limit exceeded'));

        $tool = new VendorChatTool(['openai' => $adapter]);

        $result = $tool->execute([
            'prompt' => 'Hello',
            'vendor' => 'openai',
        ]);

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Vendor chat error', $result->getContent());
        $this->assertStringContainsString('API rate limit exceeded', $result->getContent());
    }

    public function testToDefinition(): void
    {
        $tool = new VendorChatTool([
            'openai' => $this->createMockAdapter('openai'),
        ]);

        $definition = $tool->toDefinition();

        $this->assertEquals('vendor_chat', $definition['name']);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('input_schema', $definition);
    }

    /**
     * @return VendorAdapterInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockAdapter(string $name): VendorAdapterInterface
    {
        $mock = $this->createMock(VendorAdapterInterface::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isAvailable')->willReturn(true);

        return $mock;
    }
}
