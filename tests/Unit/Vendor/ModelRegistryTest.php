<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor;

use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\ModelInfo;
use ClaudeAgents\Vendor\ModelRegistry;
use PHPUnit\Framework\TestCase;

class ModelRegistryTest extends TestCase
{
    public function testDefaultRegistryHasAllVendors(): void
    {
        $registry = ModelRegistry::default();

        $vendors = $registry->getVendors();

        $this->assertContains('anthropic', $vendors);
        $this->assertContains('openai', $vendors);
        $this->assertContains('google', $vendors);
    }

    public function testDefaultRegistryHasAnthropicModels(): void
    {
        $registry = ModelRegistry::default();

        $anthropicModels = $registry->getModelsForVendor('anthropic');

        $this->assertArrayHasKey('claude-opus-4-6', $anthropicModels);
        $this->assertArrayHasKey('claude-sonnet-4-5', $anthropicModels);
        $this->assertArrayHasKey('claude-haiku-4-5', $anthropicModels);
    }

    public function testDefaultRegistryHasOpenAIModels(): void
    {
        $registry = ModelRegistry::default();

        $openaiModels = $registry->getModelsForVendor('openai');

        // Frontier models
        $this->assertArrayHasKey('gpt-5.2', $openaiModels);
        $this->assertArrayHasKey('gpt-5-mini', $openaiModels);
        $this->assertArrayHasKey('gpt-5-nano', $openaiModels);
        $this->assertArrayHasKey('gpt-4.1', $openaiModels);

        // Reasoning models
        $this->assertArrayHasKey('o3', $openaiModels);
        $this->assertArrayHasKey('o4-mini', $openaiModels);

        // Specialized models
        $this->assertArrayHasKey('gpt-image-1.5', $openaiModels);
        $this->assertArrayHasKey('gpt-4o-mini-tts', $openaiModels);
        $this->assertArrayHasKey('o3-deep-research', $openaiModels);
    }

    public function testDefaultRegistryHasGeminiModels(): void
    {
        $registry = ModelRegistry::default();

        $geminiModels = $registry->getModelsForVendor('google');

        // Chat models
        $this->assertArrayHasKey('gemini-3-pro-preview', $geminiModels);
        $this->assertArrayHasKey('gemini-3-flash-preview', $geminiModels);
        $this->assertArrayHasKey('gemini-2.5-pro', $geminiModels);
        $this->assertArrayHasKey('gemini-2.5-flash', $geminiModels);
        $this->assertArrayHasKey('gemini-2.5-flash-lite', $geminiModels);

        // Nano Banana image models
        $this->assertArrayHasKey('gemini-2.5-flash-image', $geminiModels);
        $this->assertArrayHasKey('gemini-3-pro-image-preview', $geminiModels);
    }

    public function testGetModelsWithCapability(): void
    {
        $registry = ModelRegistry::default();

        $chatModels = $registry->getModelsWithCapability(Capability::Chat);
        $this->assertNotEmpty($chatModels);

        // All chat models should have the Chat capability
        foreach ($chatModels as $model) {
            $this->assertTrue($model->hasCapability(Capability::Chat));
        }

        $imageModels = $registry->getModelsWithCapability(Capability::ImageGeneration);
        $this->assertNotEmpty($imageModels);
        $this->assertArrayHasKey('gpt-image-1.5', $imageModels);
        $this->assertArrayHasKey('gemini-2.5-flash-image', $imageModels);
    }

    public function testGetDefaultModelForVendorAndCapability(): void
    {
        $registry = ModelRegistry::default();

        // OpenAI defaults
        $this->assertEquals('gpt-5.2', $registry->getDefaultModel('openai', Capability::Chat));
        $this->assertEquals('gpt-image-1.5', $registry->getDefaultModel('openai', Capability::ImageGeneration));
        $this->assertEquals('gpt-4o-mini-tts', $registry->getDefaultModel('openai', Capability::TextToSpeech));

        // Google defaults
        $this->assertEquals('gemini-2.5-flash', $registry->getDefaultModel('google', Capability::Chat));
        $this->assertEquals('gemini-2.5-flash-image', $registry->getDefaultModel('google', Capability::ImageGeneration));

        // Anthropic defaults
        $this->assertEquals('claude-opus-4-6', $registry->getDefaultModel('anthropic', Capability::Chat));
    }

    public function testGetDefaultModelReturnsNullForUnsupported(): void
    {
        $registry = ModelRegistry::default();

        $this->assertNull($registry->getDefaultModel('anthropic', Capability::ImageGeneration));
        $this->assertNull($registry->getDefaultModel('google', Capability::TextToSpeech));
        $this->assertNull($registry->getDefaultModel('nonexistent', Capability::Chat));
    }

    public function testRegisterCustomModel(): void
    {
        $registry = ModelRegistry::default();

        $custom = new ModelInfo(
            id: 'my-fine-tuned-model',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'My fine-tuned model',
        );

        $registry->register($custom);

        $this->assertTrue($registry->has('my-fine-tuned-model'));
        $this->assertEquals('My fine-tuned model', $registry->get('my-fine-tuned-model')->description);
    }

    public function testGetReturnsNullForUnknownModel(): void
    {
        $registry = ModelRegistry::default();

        $this->assertNull($registry->get('nonexistent-model'));
    }

    public function testHasModel(): void
    {
        $registry = ModelRegistry::default();

        $this->assertTrue($registry->has('gpt-5.2'));
        $this->assertTrue($registry->has('gemini-2.5-flash'));
        $this->assertFalse($registry->has('nonexistent'));
    }

    public function testEmptyRegistry(): void
    {
        $registry = new ModelRegistry();

        $this->assertEmpty($registry->all());
        $this->assertEmpty($registry->getVendors());
        $this->assertNull($registry->getDefaultModel('openai', Capability::Chat));
    }

    public function testGeminiModelsHaveGroundingCapability(): void
    {
        $registry = ModelRegistry::default();

        $groundingModels = $registry->getModelsWithCapability(Capability::Grounding);

        $this->assertNotEmpty($groundingModels);
        // Gemini chat models should support grounding
        $this->assertArrayHasKey('gemini-2.5-flash', $groundingModels);
        $this->assertArrayHasKey('gemini-3-pro-preview', $groundingModels);
    }

    public function testGeminiModelsHaveCodeExecutionCapability(): void
    {
        $registry = ModelRegistry::default();

        $codeExecModels = $registry->getModelsWithCapability(Capability::CodeExecution);

        $this->assertNotEmpty($codeExecModels);
        $this->assertArrayHasKey('gemini-2.5-flash', $codeExecModels);
    }

    public function testOpenAIModelsHaveWebSearchCapability(): void
    {
        $registry = ModelRegistry::default();

        $webSearchModels = $registry->getModelsWithCapability(Capability::WebSearch);

        $this->assertArrayHasKey('gpt-5.2', $webSearchModels);
        $this->assertArrayHasKey('gpt-5-mini', $webSearchModels);
    }
}
