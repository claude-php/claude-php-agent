<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Tools;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Vendor\Adapters\OpenAIAdapter;

/**
 * OpenAI image generation tool.
 *
 * Generates images using GPT Image 1.5 (or configurable model)
 * via the OpenAI Images API.
 */
class OpenAIImageTool implements ToolInterface
{
    public function __construct(
        private readonly OpenAIAdapter $adapter,
    ) {
    }

    public function getName(): string
    {
        return 'openai_image_generation';
    }

    public function getDescription(): string
    {
        return 'Generate images using OpenAI GPT Image. Supports detailed text '
            . 'prompts, photorealistic output, and accurate text rendering in images.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'Detailed description of the image to generate',
                ],
                'size' => [
                    'type' => 'string',
                    'description' => 'Image size (e.g. 1024x1024, 1792x1024, 1024x1792)',
                    'enum' => ['1024x1024', '1792x1024', '1024x1792'],
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Model to use (default: gpt-image-1.5)',
                ],
            ],
            'required' => ['prompt'],
        ];
    }

    public function execute(array $input): ToolResultInterface
    {
        $prompt = $input['prompt'] ?? '';

        if ($prompt === '') {
            return ToolResult::error('The prompt parameter is required.');
        }

        try {
            $result = $this->adapter->generateImage(
                $prompt,
                $input['model'] ?? null,
                $input['size'] ?? '1024x1024',
            );

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error("OpenAI image generation error: {$e->getMessage()}");
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
