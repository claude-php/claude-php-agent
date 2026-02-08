<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Vendor;

use ClaudeAgents\Vendor\Capability;
use PHPUnit\Framework\TestCase;

class CapabilityTest extends TestCase
{
    public function testAllCapabilitiesExist(): void
    {
        $expected = [
            'chat', 'web_search', 'image_generation', 'text_to_speech',
            'speech_to_text', 'code_execution', 'grounding', 'deep_research',
        ];

        $actual = array_map(fn (Capability $c) => $c->value, Capability::cases());

        $this->assertEquals($expected, $actual);
    }

    public function testCapabilityFromString(): void
    {
        $this->assertEquals(Capability::Chat, Capability::from('chat'));
        $this->assertEquals(Capability::WebSearch, Capability::from('web_search'));
        $this->assertEquals(Capability::ImageGeneration, Capability::from('image_generation'));
        $this->assertEquals(Capability::Grounding, Capability::from('grounding'));
        $this->assertEquals(Capability::CodeExecution, Capability::from('code_execution'));
        $this->assertEquals(Capability::TextToSpeech, Capability::from('text_to_speech'));
    }

    public function testCapabilityTryFromInvalid(): void
    {
        $this->assertNull(Capability::tryFrom('nonexistent'));
    }
}
