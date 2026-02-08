<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor;

use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\CrossVendorToolFactory;
use ClaudeAgents\Vendor\ModelRegistry;
use ClaudeAgents\Vendor\Tools\GeminiCodeExecTool;
use ClaudeAgents\Vendor\Tools\GeminiGroundingTool;
use ClaudeAgents\Vendor\Tools\GeminiImageTool;
use ClaudeAgents\Vendor\Tools\OpenAIImageTool;
use ClaudeAgents\Vendor\Tools\OpenAITTSTool;
use ClaudeAgents\Vendor\Tools\OpenAIWebSearchTool;
use ClaudeAgents\Vendor\Tools\VendorChatTool;
use ClaudeAgents\Vendor\VendorRegistry;
use PHPUnit\Framework\TestCase;

class CrossVendorToolFactoryTest extends TestCase
{
    public function testCreateAllToolsWithNoVendors(): void
    {
        $vendorRegistry = new VendorRegistry();
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createAllTools();

        $this->assertEmpty($tools);
    }

    public function testCreateAllToolsWithOpenAIOnly(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createAllTools();

        // Should have: VendorChatTool + 3 OpenAI tools = 4
        $this->assertCount(4, $tools);

        $toolNames = array_map(fn ($t) => $t->getName(), $tools);
        $this->assertContains('vendor_chat', $toolNames);
        $this->assertContains('openai_web_search', $toolNames);
        $this->assertContains('openai_image_generation', $toolNames);
        $this->assertContains('openai_text_to_speech', $toolNames);
    }

    public function testCreateAllToolsWithGeminiOnly(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('google', 'test-key');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createAllTools();

        // Should have: VendorChatTool + 3 Gemini tools = 4
        $this->assertCount(4, $tools);

        $toolNames = array_map(fn ($t) => $t->getName(), $tools);
        $this->assertContains('vendor_chat', $toolNames);
        $this->assertContains('gemini_grounding', $toolNames);
        $this->assertContains('gemini_code_execution', $toolNames);
        $this->assertContains('gemini_image_generation', $toolNames);
    }

    public function testCreateAllToolsWithBothVendors(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $vendorRegistry->registerKey('google', 'test-key');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createAllTools();

        // VendorChatTool + 3 OpenAI + 3 Gemini = 7
        $this->assertCount(7, $tools);

        $toolNames = array_map(fn ($t) => $t->getName(), $tools);
        $this->assertContains('vendor_chat', $toolNames);
        $this->assertContains('openai_web_search', $toolNames);
        $this->assertContains('openai_image_generation', $toolNames);
        $this->assertContains('openai_text_to_speech', $toolNames);
        $this->assertContains('gemini_grounding', $toolNames);
        $this->assertContains('gemini_code_execution', $toolNames);
        $this->assertContains('gemini_image_generation', $toolNames);
    }

    public function testCreateOpenAITools(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createOpenAITools();

        $this->assertCount(3, $tools);
        $this->assertInstanceOf(OpenAIWebSearchTool::class, $tools[0]);
        $this->assertInstanceOf(OpenAIImageTool::class, $tools[1]);
        $this->assertInstanceOf(OpenAITTSTool::class, $tools[2]);
    }

    public function testCreateOpenAIToolsWithoutKey(): void
    {
        $vendorRegistry = new VendorRegistry();
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createOpenAITools();

        $this->assertEmpty($tools);
    }

    public function testCreateGeminiTools(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('google', 'test-key');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createGeminiTools();

        $this->assertCount(3, $tools);
        $this->assertInstanceOf(GeminiGroundingTool::class, $tools[0]);
        $this->assertInstanceOf(GeminiCodeExecTool::class, $tools[1]);
        $this->assertInstanceOf(GeminiImageTool::class, $tools[2]);
    }

    public function testCreateGeminiToolsWithoutKey(): void
    {
        $vendorRegistry = new VendorRegistry();
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createGeminiTools();

        $this->assertEmpty($tools);
    }

    public function testCreateToolsForCapabilityWebSearch(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createToolsForCapability(Capability::WebSearch);

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(OpenAIWebSearchTool::class, $tools[0]);
    }

    public function testCreateToolsForCapabilityImageGeneration(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $vendorRegistry->registerKey('google', 'test-key');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createToolsForCapability(Capability::ImageGeneration);

        $this->assertCount(2, $tools);

        $toolNames = array_map(fn ($t) => $t->getName(), $tools);
        $this->assertContains('openai_image_generation', $toolNames);
        $this->assertContains('gemini_image_generation', $toolNames);
    }

    public function testCreateToolsForCapabilityGrounding(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('google', 'test-key');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createToolsForCapability(Capability::Grounding);

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(GeminiGroundingTool::class, $tools[0]);
    }

    public function testCreateToolsForCapabilityTTS(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createToolsForCapability(Capability::TextToSpeech);

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(OpenAITTSTool::class, $tools[0]);
    }

    public function testCreateToolsForCapabilityCodeExecution(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('google', 'test-key');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createToolsForCapability(Capability::CodeExecution);

        $this->assertCount(1, $tools);
        $this->assertInstanceOf(GeminiCodeExecTool::class, $tools[0]);
    }

    public function testCreateToolsForCapabilityWithNoVendors(): void
    {
        $vendorRegistry = new VendorRegistry();
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $this->assertEmpty($factory->createToolsForCapability(Capability::Chat));
        $this->assertEmpty($factory->createToolsForCapability(Capability::WebSearch));
        $this->assertEmpty($factory->createToolsForCapability(Capability::ImageGeneration));
    }

    public function testGetOpenAIAdapter(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $adapter = $factory->getOpenAIAdapter();

        $this->assertNotNull($adapter);
        $this->assertEquals('openai', $adapter->getName());
    }

    public function testGetOpenAIAdapterReturnsNullWithoutKey(): void
    {
        $vendorRegistry = new VendorRegistry();
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $this->assertNull($factory->getOpenAIAdapter());
    }

    public function testGetGeminiAdapter(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('google', 'test-key');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $adapter = $factory->getGeminiAdapter();

        $this->assertNotNull($adapter);
        $this->assertEquals('google', $adapter->getName());
    }

    public function testGetGeminiAdapterReturnsNullWithoutKey(): void
    {
        $vendorRegistry = new VendorRegistry();
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $this->assertNull($factory->getGeminiAdapter());
    }

    public function testAdaptersCached(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $adapter1 = $factory->getOpenAIAdapter();
        $adapter2 = $factory->getOpenAIAdapter();

        // Should be the exact same instance (cached)
        $this->assertSame($adapter1, $adapter2);
    }

    public function testCustomModelRegistry(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $modelRegistry = new ModelRegistry();

        $factory = new CrossVendorToolFactory($vendorRegistry, $modelRegistry);

        // Should still create OpenAI tools even with empty model registry
        $tools = $factory->createOpenAITools();
        $this->assertCount(3, $tools);
    }

    public function testAllToolsImplementToolInterface(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $vendorRegistry->registerKey('google', 'test-key');
        $factory = new CrossVendorToolFactory($vendorRegistry);

        $tools = $factory->createAllTools();

        foreach ($tools as $tool) {
            $this->assertInstanceOf(\ClaudeAgents\Contracts\ToolInterface::class, $tool);

            // Each tool should have a valid name
            $this->assertNotEmpty($tool->getName());
            $this->assertNotEmpty($tool->getDescription());

            // Each tool should produce a valid definition
            $definition = $tool->toDefinition();
            $this->assertArrayHasKey('name', $definition);
            $this->assertArrayHasKey('description', $definition);
            $this->assertArrayHasKey('input_schema', $definition);
        }
    }
}
