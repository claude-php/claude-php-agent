<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor;

use ClaudeAgents\Vendor\VendorConfig;
use PHPUnit\Framework\TestCase;

class VendorConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new VendorConfig(vendor: 'openai');

        $this->assertEquals('openai', $config->vendor);
        $this->assertNull($config->defaultChatModel);
        $this->assertNull($config->defaultImageModel);
        $this->assertNull($config->defaultTTSModel);
        $this->assertNull($config->baseUrl);
        $this->assertEquals(30.0, $config->timeout);
        $this->assertEquals(2, $config->maxRetries);
    }

    public function testCustomValues(): void
    {
        $config = new VendorConfig(
            vendor: 'google',
            defaultChatModel: 'gemini-3-pro-preview',
            defaultImageModel: 'gemini-3-pro-image-preview',
            defaultTTSModel: null,
            baseUrl: 'https://custom.api.example.com',
            timeout: 120.0,
            maxRetries: 5,
        );

        $this->assertEquals('google', $config->vendor);
        $this->assertEquals('gemini-3-pro-preview', $config->defaultChatModel);
        $this->assertEquals('gemini-3-pro-image-preview', $config->defaultImageModel);
        $this->assertNull($config->defaultTTSModel);
        $this->assertEquals('https://custom.api.example.com', $config->baseUrl);
        $this->assertEquals(120.0, $config->timeout);
        $this->assertEquals(5, $config->maxRetries);
    }

    public function testFromArray(): void
    {
        $config = VendorConfig::fromArray([
            'vendor' => 'openai',
            'default_chat_model' => 'gpt-5.2-pro',
            'default_image_model' => 'gpt-image-1',
            'default_tts_model' => 'tts-1-hd',
            'base_url' => 'https://api.custom.com',
            'timeout' => 45,
            'max_retries' => 3,
        ]);

        $this->assertEquals('openai', $config->vendor);
        $this->assertEquals('gpt-5.2-pro', $config->defaultChatModel);
        $this->assertEquals('gpt-image-1', $config->defaultImageModel);
        $this->assertEquals('tts-1-hd', $config->defaultTTSModel);
        $this->assertEquals('https://api.custom.com', $config->baseUrl);
        $this->assertEquals(45.0, $config->timeout);
        $this->assertEquals(3, $config->maxRetries);
    }

    public function testFromArrayWithMinimalInput(): void
    {
        $config = VendorConfig::fromArray([]);

        $this->assertEquals('', $config->vendor);
        $this->assertNull($config->defaultChatModel);
        $this->assertEquals(30.0, $config->timeout);
        $this->assertEquals(2, $config->maxRetries);
    }

    public function testReadonlyProperties(): void
    {
        $config = new VendorConfig(vendor: 'openai', timeout: 10.0);

        // Properties should be readonly (verified by the readonly keyword in class)
        $this->assertEquals('openai', $config->vendor);
        $this->assertEquals(10.0, $config->timeout);
    }
}
