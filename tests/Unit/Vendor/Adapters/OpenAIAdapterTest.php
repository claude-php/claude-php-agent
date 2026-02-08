<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor\Adapters;

use ClaudeAgents\Vendor\Adapters\OpenAIAdapter;
use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\VendorConfig;
use PHPUnit\Framework\TestCase;

class OpenAIAdapterTest extends TestCase
{
    public function testGetName(): void
    {
        $adapter = new OpenAIAdapter('sk-test');

        $this->assertEquals('openai', $adapter->getName());
    }

    public function testIsAvailable(): void
    {
        $available = new OpenAIAdapter('sk-test-key');
        $this->assertTrue($available->isAvailable());

        $unavailable = new OpenAIAdapter('');
        $this->assertFalse($unavailable->isAvailable());
    }

    public function testSupportedCapabilities(): void
    {
        $adapter = new OpenAIAdapter('sk-test');

        $capabilities = $adapter->getSupportedCapabilities();

        $this->assertContains(Capability::Chat, $capabilities);
        $this->assertContains(Capability::WebSearch, $capabilities);
        $this->assertContains(Capability::ImageGeneration, $capabilities);
        $this->assertContains(Capability::TextToSpeech, $capabilities);
        $this->assertCount(4, $capabilities);
    }

    public function testSupportsCapability(): void
    {
        $adapter = new OpenAIAdapter('sk-test');

        $this->assertTrue($adapter->supportsCapability(Capability::Chat));
        $this->assertTrue($adapter->supportsCapability(Capability::WebSearch));
        $this->assertTrue($adapter->supportsCapability(Capability::ImageGeneration));
        $this->assertTrue($adapter->supportsCapability(Capability::TextToSpeech));

        $this->assertFalse($adapter->supportsCapability(Capability::Grounding));
        $this->assertFalse($adapter->supportsCapability(Capability::CodeExecution));
        $this->assertFalse($adapter->supportsCapability(Capability::SpeechToText));
        $this->assertFalse($adapter->supportsCapability(Capability::DeepResearch));
    }

    public function testExecuteCapabilityThrowsForUnsupported(): void
    {
        $adapter = new OpenAIAdapter('sk-test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support capability: grounding');

        $adapter->executeCapability(Capability::Grounding, ['query' => 'test']);
    }

    public function testConfigOverridesAreAccepted(): void
    {
        $config = new VendorConfig(
            vendor: 'openai',
            defaultChatModel: 'gpt-5.2-pro',
            defaultImageModel: 'gpt-image-1',
            defaultTTSModel: 'tts-1-hd',
            baseUrl: 'https://custom-openai.example.com',
            timeout: 60.0,
            maxRetries: 5,
        );

        // Should not throw - accepts config
        $adapter = new OpenAIAdapter('sk-test', $config);

        $this->assertEquals('openai', $adapter->getName());
        $this->assertTrue($adapter->isAvailable());
    }
}
