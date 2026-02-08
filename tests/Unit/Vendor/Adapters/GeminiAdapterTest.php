<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor\Adapters;

use ClaudeAgents\Vendor\Adapters\GeminiAdapter;
use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\VendorConfig;
use PHPUnit\Framework\TestCase;

class GeminiAdapterTest extends TestCase
{
    public function testGetName(): void
    {
        $adapter = new GeminiAdapter('test-key');

        $this->assertEquals('google', $adapter->getName());
    }

    public function testIsAvailable(): void
    {
        $available = new GeminiAdapter('test-key');
        $this->assertTrue($available->isAvailable());

        $unavailable = new GeminiAdapter('');
        $this->assertFalse($unavailable->isAvailable());
    }

    public function testSupportedCapabilities(): void
    {
        $adapter = new GeminiAdapter('test-key');

        $capabilities = $adapter->getSupportedCapabilities();

        $this->assertContains(Capability::Chat, $capabilities);
        $this->assertContains(Capability::Grounding, $capabilities);
        $this->assertContains(Capability::CodeExecution, $capabilities);
        $this->assertContains(Capability::ImageGeneration, $capabilities);
        $this->assertCount(4, $capabilities);
    }

    public function testSupportsCapability(): void
    {
        $adapter = new GeminiAdapter('test-key');

        $this->assertTrue($adapter->supportsCapability(Capability::Chat));
        $this->assertTrue($adapter->supportsCapability(Capability::Grounding));
        $this->assertTrue($adapter->supportsCapability(Capability::CodeExecution));
        $this->assertTrue($adapter->supportsCapability(Capability::ImageGeneration));

        $this->assertFalse($adapter->supportsCapability(Capability::WebSearch));
        $this->assertFalse($adapter->supportsCapability(Capability::TextToSpeech));
        $this->assertFalse($adapter->supportsCapability(Capability::SpeechToText));
        $this->assertFalse($adapter->supportsCapability(Capability::DeepResearch));
    }

    public function testExecuteCapabilityThrowsForUnsupported(): void
    {
        $adapter = new GeminiAdapter('test-key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support capability: web_search');

        $adapter->executeCapability(Capability::WebSearch, ['query' => 'test']);
    }

    public function testConfigOverridesAreAccepted(): void
    {
        $config = new VendorConfig(
            vendor: 'google',
            defaultChatModel: 'gemini-3-pro-preview',
            defaultImageModel: 'gemini-3-pro-image-preview',
            baseUrl: 'https://custom-gemini.example.com',
            timeout: 90.0,
            maxRetries: 3,
        );

        $adapter = new GeminiAdapter('test-key', $config);

        $this->assertEquals('google', $adapter->getName());
        $this->assertTrue($adapter->isAvailable());
    }
}
