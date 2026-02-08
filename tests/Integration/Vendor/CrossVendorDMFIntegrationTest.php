<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Vendor;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\CrossVendorToolFactory;
use ClaudeAgents\Vendor\ModelInfo;
use ClaudeAgents\Vendor\ModelRegistry;
use ClaudeAgents\Vendor\VendorConfig;
use ClaudeAgents\Vendor\VendorRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Cross-Vendor Dynamic Model Fusion system.
 *
 * These tests verify the full workflow of configuring vendors,
 * discovering capabilities, and creating tools -- without making
 * actual API calls.
 */
class CrossVendorDMFIntegrationTest extends TestCase
{
    /**
     * Test the complete vendor setup -> tool creation workflow.
     */
    public function testFullVendorSetupAndToolCreation(): void
    {
        // Step 1: Configure vendor keys
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('anthropic', 'sk-ant-test');
        $vendorRegistry->registerKey('openai', 'sk-openai-test');
        $vendorRegistry->registerKey('google', 'gemini-test-key');

        // Step 2: Verify availability
        $this->assertTrue($vendorRegistry->isAvailable('anthropic'));
        $this->assertTrue($vendorRegistry->isAvailable('openai'));
        $this->assertTrue($vendorRegistry->isAvailable('google'));
        $this->assertTrue($vendorRegistry->hasExternalVendors());

        // Step 3: Create tool factory
        $modelRegistry = ModelRegistry::default();
        $factory = new CrossVendorToolFactory($vendorRegistry, $modelRegistry);

        // Step 4: Create all tools
        $tools = $factory->createAllTools();

        // Should have 7 tools: vendor_chat + 3 OpenAI + 3 Gemini
        $this->assertCount(7, $tools);

        // Step 5: Verify each tool is properly configured
        $toolNames = [];
        foreach ($tools as $tool) {
            $this->assertInstanceOf(ToolInterface::class, $tool);
            $toolNames[] = $tool->getName();

            // Every tool should have a valid definition for the Claude API
            $definition = $tool->toDefinition();
            $this->assertNotEmpty($definition['name']);
            $this->assertNotEmpty($definition['description']);
            $this->assertArrayHasKey('input_schema', $definition);
        }

        // Verify all expected tools are present
        $this->assertContains('vendor_chat', $toolNames);
        $this->assertContains('openai_web_search', $toolNames);
        $this->assertContains('openai_image_generation', $toolNames);
        $this->assertContains('openai_text_to_speech', $toolNames);
        $this->assertContains('gemini_grounding', $toolNames);
        $this->assertContains('gemini_code_execution', $toolNames);
        $this->assertContains('gemini_image_generation', $toolNames);
    }

    /**
     * Test model registry discovery for capability-based tool selection.
     */
    public function testModelRegistryCapabilityDiscovery(): void
    {
        $registry = ModelRegistry::default();

        // Discover image generation models across all vendors
        $imageModels = $registry->getModelsWithCapability(Capability::ImageGeneration);

        $vendors = [];
        foreach ($imageModels as $model) {
            $vendors[] = $model->vendor;
        }
        $vendors = array_unique($vendors);

        // Both OpenAI and Google should have image generation models
        $this->assertContains('openai', $vendors);
        $this->assertContains('google', $vendors);

        // Verify default image models
        $this->assertEquals('gpt-image-1.5', $registry->getDefaultModel('openai', Capability::ImageGeneration));
        $this->assertEquals('gemini-2.5-flash-image', $registry->getDefaultModel('google', Capability::ImageGeneration));
    }

    /**
     * Test that vendor config overrides are properly propagated to adapters.
     */
    public function testVendorConfigPropagation(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $vendorRegistry->setConfig('openai', new VendorConfig(
            vendor: 'openai',
            defaultChatModel: 'gpt-5.2-pro',
            defaultImageModel: 'gpt-image-1',
            defaultTTSModel: 'tts-1-hd',
            timeout: 120.0,
            maxRetries: 5,
        ));

        $factory = new CrossVendorToolFactory($vendorRegistry);

        $adapter = $factory->getOpenAIAdapter();

        $this->assertNotNull($adapter);
        $this->assertEquals('openai', $adapter->getName());
        $this->assertTrue($adapter->isAvailable());
    }

    /**
     * Test selective tool creation for a specific capability.
     */
    public function testCapabilityBasedToolCreation(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $vendorRegistry->registerKey('google', 'gemini-key');

        $factory = new CrossVendorToolFactory($vendorRegistry);

        // Image generation should produce tools from both vendors
        $imageTools = $factory->createToolsForCapability(Capability::ImageGeneration);
        $this->assertCount(2, $imageTools);

        // Web search is only OpenAI
        $searchTools = $factory->createToolsForCapability(Capability::WebSearch);
        $this->assertCount(1, $searchTools);
        $this->assertEquals('openai_web_search', $searchTools[0]->getName());

        // Grounding is only Gemini
        $groundingTools = $factory->createToolsForCapability(Capability::Grounding);
        $this->assertCount(1, $groundingTools);
        $this->assertEquals('gemini_grounding', $groundingTools[0]->getName());

        // Code execution is only Gemini
        $codeTools = $factory->createToolsForCapability(Capability::CodeExecution);
        $this->assertCount(1, $codeTools);
        $this->assertEquals('gemini_code_execution', $codeTools[0]->getName());

        // TTS is only OpenAI
        $ttsTools = $factory->createToolsForCapability(Capability::TextToSpeech);
        $this->assertCount(1, $ttsTools);
        $this->assertEquals('openai_text_to_speech', $ttsTools[0]->getName());
    }

    /**
     * Test that the system correctly handles partial vendor availability.
     */
    public function testPartialVendorAvailability(): void
    {
        // Only Google key available
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('google', 'gemini-key');

        $factory = new CrossVendorToolFactory($vendorRegistry);

        // Should only create Gemini tools + vendor chat
        $tools = $factory->createAllTools();
        $this->assertCount(4, $tools); // vendor_chat + 3 Gemini

        // OpenAI-specific capabilities should return no tools
        $searchTools = $factory->createToolsForCapability(Capability::WebSearch);
        $this->assertEmpty($searchTools);

        $ttsTools = $factory->createToolsForCapability(Capability::TextToSpeech);
        $this->assertEmpty($ttsTools);
    }

    /**
     * Test runtime model registration.
     */
    public function testRuntimeModelRegistration(): void
    {
        $registry = ModelRegistry::default();

        // Register a fine-tuned model
        $custom = new ModelInfo(
            id: 'ft:gpt-5.2:my-org:custom-coding',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'Fine-tuned model for PHP code generation',
        );

        $registry->register($custom);

        $this->assertTrue($registry->has('ft:gpt-5.2:my-org:custom-coding'));
        $model = $registry->get('ft:gpt-5.2:my-org:custom-coding');
        $this->assertEquals('openai', $model->vendor);
        $this->assertTrue($model->hasCapability(Capability::Chat));
        $this->assertFalse($model->hasCapability(Capability::ImageGeneration));
    }

    /**
     * Test that tool definitions are valid for the Claude API format.
     */
    public function testToolDefinitionsAreClaudeApiCompatible(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $vendorRegistry->registerKey('google', 'gemini-key');

        $factory = new CrossVendorToolFactory($vendorRegistry);
        $tools = $factory->createAllTools();

        foreach ($tools as $tool) {
            $definition = $tool->toDefinition();

            // Claude API requires these fields
            $this->assertArrayHasKey('name', $definition, "Tool missing 'name': {$tool->getName()}");
            $this->assertArrayHasKey('description', $definition, "Tool missing 'description': {$tool->getName()}");
            $this->assertArrayHasKey('input_schema', $definition, "Tool missing 'input_schema': {$tool->getName()}");

            // Name should be a valid tool name (alphanumeric + underscores)
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $definition['name'],
                "Invalid tool name: {$definition['name']}"
            );

            // Description should be non-empty and reasonable length
            $this->assertNotEmpty($definition['description']);
            $this->assertLessThan(1024, strlen($definition['description']));

            // Input schema should have required structure
            $schema = $definition['input_schema'];
            $this->assertEquals('object', $schema['type'] ?? null, "Schema type should be 'object': {$tool->getName()}");
        }
    }

    /**
     * Test that tools handle validation errors gracefully (no exceptions).
     */
    public function testToolsHandleEmptyInputGracefully(): void
    {
        $vendorRegistry = new VendorRegistry();
        $vendorRegistry->registerKey('openai', 'sk-test');
        $vendorRegistry->registerKey('google', 'gemini-key');

        $factory = new CrossVendorToolFactory($vendorRegistry);
        $tools = $factory->createAllTools();

        foreach ($tools as $tool) {
            // Calling with empty input should return an error result, never throw
            $result = $tool->execute([]);

            // Should return an error, not throw
            $this->assertTrue(
                $result->isError(),
                "Tool '{$tool->getName()}' should return error for empty input, got: {$result->getContent()}"
            );
        }
    }

    /**
     * Test cross-vendor model capability matrix.
     */
    public function testCrossVendorCapabilityMatrix(): void
    {
        $registry = ModelRegistry::default();

        // Build a capability matrix
        $matrix = [];
        foreach (Capability::cases() as $capability) {
            $models = $registry->getModelsWithCapability($capability);
            $vendorsForCapability = [];
            foreach ($models as $model) {
                $vendorsForCapability[$model->vendor] = true;
            }
            $matrix[$capability->value] = array_keys($vendorsForCapability);
        }

        // Chat should be available from all three vendors
        $this->assertContains('anthropic', $matrix['chat']);
        $this->assertContains('openai', $matrix['chat']);
        $this->assertContains('google', $matrix['chat']);

        // Image generation from OpenAI and Google
        $this->assertContains('openai', $matrix['image_generation']);
        $this->assertContains('google', $matrix['image_generation']);

        // Grounding only from Google
        $this->assertContains('google', $matrix['grounding']);
        $this->assertNotContains('openai', $matrix['grounding']);

        // Code execution only from Google
        $this->assertContains('google', $matrix['code_execution']);
        $this->assertNotContains('openai', $matrix['code_execution']);

        // Web search from OpenAI
        $this->assertContains('openai', $matrix['web_search']);

        // TTS from OpenAI
        $this->assertContains('openai', $matrix['text_to_speech']);

        // Deep research from OpenAI
        $this->assertContains('openai', $matrix['deep_research']);
    }
}
