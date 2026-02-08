<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Tools;

use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\Contracts\ToolResultInterface;
use ClaudeAgents\Tools\ToolResult;
use ClaudeAgents\Vendor\Adapters\GeminiAdapter;

/**
 * Gemini Nano Banana image generation tool.
 *
 * Generates and edits images using Gemini's native image generation
 * (codename "Nano Banana"). Supports text-to-image, conversational
 * editing, and high-fidelity text rendering.
 *
 * Models:
 * - gemini-2.5-flash-image (Nano Banana) -- fast, high-volume
 * - gemini-3-pro-image-preview (Nano Banana Pro) -- professional 4K output
 */
class GeminiImageTool implements ToolInterface
{
    public function __construct(
        private readonly GeminiAdapter $adapter,
    ) {
    }

    public function getName(): string
    {
        return 'gemini_image_generation';
    }

    public function getDescription(): string
    {
        return 'Generate or edit images using Google Gemini Nano Banana. '
            . 'Supports text-to-image generation, conversational image editing, '
            . 'high-fidelity text rendering, and character consistency. '
            . 'Use gemini-3-pro-image-preview for professional 4K output.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'Description of the image to generate or editing instructions',
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Model to use: gemini-2.5-flash-image (fast) or gemini-3-pro-image-preview (4K pro)',
                    'enum' => ['gemini-2.5-flash-image', 'gemini-3-pro-image-preview'],
                ],
                'aspect_ratio' => [
                    'type' => 'string',
                    'description' => 'Aspect ratio for the image',
                    'enum' => ['1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9'],
                ],
                'resolution' => [
                    'type' => 'string',
                    'description' => 'Output resolution (Nano Banana Pro only)',
                    'enum' => ['1K', '2K', '4K'],
                ],
                'grounding' => [
                    'type' => 'boolean',
                    'description' => 'Enable Google Search grounding for real-time data in images (Pro only)',
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
                [
                    'aspect_ratio' => $input['aspect_ratio'] ?? null,
                    'resolution' => $input['resolution'] ?? null,
                    'grounding' => $input['grounding'] ?? false,
                ],
            );

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error("Gemini image generation error: {$e->getMessage()}");
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
