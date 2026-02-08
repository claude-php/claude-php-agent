<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Tools;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Vendor\Adapters\OpenAIAdapter;

/**
 * OpenAI text-to-speech tool.
 *
 * Converts text to spoken audio using OpenAI's TTS models.
 * Supports expressive voice styles via the instructions parameter.
 */
class OpenAITTSTool implements ToolInterface
{
    public function __construct(
        private readonly OpenAIAdapter $adapter,
    ) {
    }

    public function getName(): string
    {
        return 'openai_text_to_speech';
    }

    public function getDescription(): string
    {
        return 'Convert text to speech audio using OpenAI TTS. Supports multiple '
            . 'voices and expressive style instructions. Returns base64-encoded audio.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => 'The text to convert to speech (max ~4096 chars)',
                ],
                'voice' => [
                    'type' => 'string',
                    'description' => 'Voice preset to use',
                    'enum' => ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'],
                ],
                'instructions' => [
                    'type' => 'string',
                    'description' => 'Style instructions (e.g. "Speak in a warm, friendly tone" or "Talk like a sympathetic customer service agent")',
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'TTS model (default: gpt-4o-mini-tts, alternatives: tts-1, tts-1-hd)',
                ],
            ],
            'required' => ['text'],
        ];
    }

    public function execute(array $input): ToolResultInterface
    {
        $text = $input['text'] ?? '';

        if ($text === '') {
            return ToolResult::error('The text parameter is required.');
        }

        try {
            $base64Audio = $this->adapter->textToSpeech(
                $text,
                $input['voice'] ?? 'alloy',
                $input['instructions'] ?? null,
                $input['model'] ?? null,
            );

            return ToolResult::success(json_encode([
                'audio_base64' => $base64Audio,
                'format' => 'mp3',
                'voice' => $input['voice'] ?? 'alloy',
            ]));
        } catch (\Throwable $e) {
            return ToolResult::error("OpenAI TTS error: {$e->getMessage()}");
        }
    }

    public function toDefinition(): array
    {
        $schema = $this->getInputSchema();
        $schema['properties'] = (object) $schema['properties'];

        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'input_schema' => $schema,
        ];
    }
}
