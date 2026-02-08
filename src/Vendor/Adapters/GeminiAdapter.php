<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Adapters;

use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\VendorConfig;

/**
 * Adapter for the Google Gemini API.
 *
 * Supports chat (generateContent), Google Search grounding, code
 * execution, and Nano Banana image generation -- all via direct cURL.
 */
class GeminiAdapter extends AbstractVendorAdapter
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com';

    private const DEFAULT_CHAT_MODEL = 'gemini-2.5-flash';

    private const DEFAULT_IMAGE_MODEL = 'gemini-2.5-flash-image';

    public function __construct(string $apiKey, ?VendorConfig $config = null)
    {
        parent::__construct($apiKey, $config);
    }

    public function getName(): string
    {
        return 'google';
    }

    public function getSupportedCapabilities(): array
    {
        return [
            Capability::Chat,
            Capability::Grounding,
            Capability::CodeExecution,
            Capability::ImageGeneration,
        ];
    }

    public function executeCapability(Capability $capability, array $params): mixed
    {
        return match ($capability) {
            Capability::Chat => $this->chat(
                $params['prompt'] ?? '',
                $params,
            ),
            Capability::Grounding => $this->groundedSearch(
                $params['query'] ?? '',
                $params['model'] ?? null,
            ),
            Capability::CodeExecution => $this->codeExecution(
                $params['prompt'] ?? '',
                $params['model'] ?? null,
            ),
            Capability::ImageGeneration => $this->generateImage(
                $params['prompt'] ?? '',
                $params['model'] ?? null,
                $params,
            ),
            default => throw new \InvalidArgumentException(
                "Gemini adapter does not support capability: {$capability->value}"
            ),
        };
    }

    /**
     * Send a chat request via generateContent.
     *
     * @param string $prompt The user message
     * @param array<string, mixed> $options model, system, max_tokens, temperature
     * @return string The model's text response
     */
    public function chat(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->config?->defaultChatModel ?? self::DEFAULT_CHAT_MODEL;

        $body = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
        ];

        if (isset($options['system'])) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $options['system']]],
            ];
        }

        $generationConfig = [];
        if (isset($options['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = (int) $options['max_tokens'];
        }
        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = (float) $options['temperature'];
        }
        if (! empty($generationConfig)) {
            $body['generationConfig'] = $generationConfig;
        }

        $response = $this->httpPost($this->generateContentUrl($model), $body);

        return $this->extractText($response);
    }

    /**
     * Perform a Google Search grounded query.
     *
     * @param string $query The query to ground with Google Search
     * @param string|null $model Model override
     * @return string Grounded response with search metadata
     */
    public function groundedSearch(string $query, ?string $model = null): string
    {
        $model = $model ?? $this->config?->defaultChatModel ?? self::DEFAULT_CHAT_MODEL;

        $body = [
            'contents' => [
                ['parts' => [['text' => $query]]],
            ],
            'tools' => [['google_search' => new \stdClass()]],
        ];

        $response = $this->httpPost($this->generateContentUrl($model), $body);

        $text = $this->extractText($response);

        // Include grounding metadata if available
        $groundingMeta = $response['candidates'][0]['groundingMetadata'] ?? null;
        if ($groundingMeta !== null) {
            $sources = [];
            foreach ($groundingMeta['groundingChunks'] ?? [] as $chunk) {
                $web = $chunk['web'] ?? [];
                if (isset($web['uri'])) {
                    $sources[] = [
                        'title' => $web['title'] ?? '',
                        'url' => $web['uri'],
                    ];
                }
            }

            if (! empty($sources)) {
                $text .= "\n\nSources:\n" . json_encode($sources, JSON_PRETTY_PRINT);
            }
        }

        return $text;
    }

    /**
     * Execute code via Gemini's code execution tool.
     *
     * @param string $prompt Prompt describing what code to generate and run
     * @param string|null $model Model override
     * @return string Execution result including any code output
     */
    public function codeExecution(string $prompt, ?string $model = null): string
    {
        $model = $model ?? $this->config?->defaultChatModel ?? self::DEFAULT_CHAT_MODEL;

        $body = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'tools' => [['code_execution' => new \stdClass()]],
        ];

        $response = $this->httpPost($this->generateContentUrl($model), $body);

        return $this->extractFullContent($response);
    }

    /**
     * Generate or edit an image via Nano Banana.
     *
     * @param string $prompt Image description or editing instruction
     * @param string|null $model Model override (gemini-2.5-flash-image or gemini-3-pro-image-preview)
     * @param array<string, mixed> $options aspect_ratio, resolution, etc.
     * @return string JSON with base64 image data and text
     */
    public function generateImage(string $prompt, ?string $model = null, array $options = []): string
    {
        $model = $model ?? $this->config?->defaultImageModel ?? self::DEFAULT_IMAGE_MODEL;

        $body = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        // Add image config for Nano Banana Pro
        $imageConfig = [];
        if (isset($options['aspect_ratio'])) {
            $imageConfig['aspectRatio'] = $options['aspect_ratio'];
        }
        if (isset($options['resolution'])) {
            $imageConfig['imageSize'] = $options['resolution'];
        }
        if (! empty($imageConfig)) {
            $body['generationConfig']['imageConfig'] = $imageConfig;
        }

        // Add Google Search grounding if requested (supported by Nano Banana Pro)
        if (! empty($options['grounding'])) {
            $body['tools'] = [['google_search' => new \stdClass()]];
        }

        $response = $this->httpPost($this->generateContentUrl($model), $body);

        return $this->extractImageResponse($response);
    }

    protected function getAuthHeaders(): array
    {
        return ["x-goog-api-key: {$this->apiKey}"];
    }

    /**
     * Build the generateContent URL for a model.
     */
    private function generateContentUrl(string $model): string
    {
        $baseUrl = $this->config?->baseUrl ?? self::BASE_URL;

        return rtrim($baseUrl, '/') . "/v1beta/models/{$model}:generateContent";
    }

    /**
     * Extract text from a generateContent response.
     *
     * @param array<string, mixed> $response
     */
    private function extractText(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        $textParts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
            }
        }

        return implode("\n", $textParts);
    }

    /**
     * Extract full content including code execution results.
     *
     * @param array<string, mixed> $response
     */
    private function extractFullContent(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        $output = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $output[] = $part['text'];
            }
            if (isset($part['executableCode'])) {
                $output[] = "```python\n" . ($part['executableCode']['code'] ?? '') . "\n```";
            }
            if (isset($part['codeExecutionResult'])) {
                $output[] = "Output:\n" . ($part['codeExecutionResult']['output'] ?? '');
            }
        }

        return implode("\n\n", $output);
    }

    /**
     * Extract image generation response.
     *
     * @param array<string, mixed> $response
     * @return string JSON with text and image data
     */
    private function extractImageResponse(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        $result = ['text' => null, 'images' => []];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $result['text'] = $part['text'];
            }
            if (isset($part['inlineData'])) {
                $result['images'][] = [
                    'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    'data' => $part['inlineData']['data'] ?? '',
                ];
            }
        }

        return json_encode($result);
    }
}
