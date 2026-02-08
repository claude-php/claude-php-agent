<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor;

use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\ModelInfo;
use PHPUnit\Framework\TestCase;

class ModelInfoTest extends TestCase
{
    public function testConstructor(): void
    {
        $model = new ModelInfo(
            id: 'gpt-5.2',
            vendor: 'openai',
            capabilities: [Capability::Chat, Capability::WebSearch],
            description: 'Latest OpenAI model',
        );

        $this->assertEquals('gpt-5.2', $model->id);
        $this->assertEquals('openai', $model->vendor);
        $this->assertCount(2, $model->capabilities);
        $this->assertEquals('Latest OpenAI model', $model->description);
        $this->assertNull($model->endpoint);
        $this->assertNull($model->maxTokens);
        $this->assertNull($model->contextWindow);
        $this->assertFalse($model->isDefault);
    }

    public function testConstructorWithAllParameters(): void
    {
        $model = new ModelInfo(
            id: 'gemini-2.5-flash',
            vendor: 'google',
            capabilities: [Capability::Chat, Capability::Grounding],
            description: 'Fast Gemini model',
            endpoint: '/v1beta/models/gemini-2.5-flash:generateContent',
            maxTokens: 65536,
            contextWindow: 1048576,
            isDefault: true,
        );

        $this->assertEquals('gemini-2.5-flash', $model->id);
        $this->assertEquals('/v1beta/models/gemini-2.5-flash:generateContent', $model->endpoint);
        $this->assertEquals(65536, $model->maxTokens);
        $this->assertEquals(1048576, $model->contextWindow);
        $this->assertTrue($model->isDefault);
    }

    public function testHasCapability(): void
    {
        $model = new ModelInfo(
            id: 'test-model',
            vendor: 'test',
            capabilities: [Capability::Chat, Capability::WebSearch],
            description: 'Test',
        );

        $this->assertTrue($model->hasCapability(Capability::Chat));
        $this->assertTrue($model->hasCapability(Capability::WebSearch));
        $this->assertFalse($model->hasCapability(Capability::ImageGeneration));
        $this->assertFalse($model->hasCapability(Capability::TextToSpeech));
    }

    public function testToArray(): void
    {
        $model = new ModelInfo(
            id: 'gpt-5.2',
            vendor: 'openai',
            capabilities: [Capability::Chat],
            description: 'Test model',
            maxTokens: 4096,
            isDefault: true,
        );

        $array = $model->toArray();

        $this->assertEquals('gpt-5.2', $array['id']);
        $this->assertEquals('openai', $array['vendor']);
        $this->assertEquals(['chat'], $array['capabilities']);
        $this->assertEquals('Test model', $array['description']);
        $this->assertEquals(4096, $array['max_tokens']);
        $this->assertTrue($array['is_default']);
        $this->assertNull($array['endpoint']);
    }
}
