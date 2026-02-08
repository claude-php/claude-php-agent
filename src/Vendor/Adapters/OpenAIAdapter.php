<?php

declare(strict_types=1);

namespace ClaudeAgents\Vendor\Adapters;

use ClaudeAgents\Vendor\Capability;
use ClaudeAgents\Vendor\VendorConfig;

/**
 * Adapter for the OpenAI API.
 *
 * Supports Chat Completions, Responses API (web search), image
 * generation, and text-to-speech -- all via direct cURL calls.
 */
class OpenAIAdapter extends AbstractVendorAdapter
{
    private const BASE_URL = 'https://api.openai.com';

    private const DEFAULT_CHAT_MODEL = 'gpt-5.2';

    private const DEFAULT_IMAGE_MODEL = 'gpt-image-1.5';

    private const DEFAULT_TTS_MODEL = 'gpt-4o-mini-tts';

    public function __construct(string $apiKey, ?VendorConfig $config = null)
    {
        parent::__construct($apiKey, $config);
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function getSupportedCapabilities(): array
    {
        return [
            Capability::Chat,
            Capability::WebSearch,
            Capability::ImageGeneration,
            Capability::TextToSpeech,
        ];
    }

    public function executeCapability(Capability $capability, array $params): mixed
    {
        return match ($capability) {
            Capability::Chat => $this->chat(
                $params['prompt'] ?? '',
                $params,
            ),
            Capability::WebSearch => $this->webSearch(
                $params['query'] ?? '',
                $params['context'] ?? null,
                $params['model'] ?? null,
            ),
            Capability::ImageGeneration => $this->generateImage(
                $params['prompt'] ?? '',
                $params['model'] ?? null,
                $params['size'] ?? '1024x1024',
            ),
            Capability::TextToSpeech => $this->textToSpeech(
                $params['text'] ?? '',
                $params['voice'] ?? 'alloy',
                $params['instructions'] ?? null,
                $params['model'] ?? null,
            ),
            default => throw new \InvalidArgumentException(
                "OpenAI adapter does not support capability: {$capability->value}"
            ),
        };
    }

    /**
     * Send a chat completion request.
     *
     * @param string $prompt The user message
     * @param array<string, mixed> $options model, max_tokens, temperature, system
     * @return string The assistant's response text
     */
    public function chat(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->config?->defaultChatModel ?? self::DEFAULT_CHAT_MODEL;

        $messages = [];

        if (isset($options['system'])) {
            $messages[] = ['role' => 'system', 'content' => $options['system']];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }

        $response = $this->httpPost($this->url('/v1/chat/completions'), $body);

        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Perform a web search using the Responses API.
     *
     * @param string $query The search query
     * @param string|null $context Additional context for the search
     * @param string|null $model Model override
     * @return string Search results with citations
     */
    public function webSearch(string $query, ?string $context = null, ?string $model = null): string
    {
        $model = $model ?? $this->config?->defaultChatModel ?? self::DEFAULT_CHAT_MODEL;

        $input = $context !== null
            ? "Context: {$context}\n\nSearch query: {$query}"
            : $query;

        $body = [
            'model' => $model,
            'input' => $input,
            'tools' => [['type' => 'web_search']],
        ];

        $response = $this->httpPost($this->url('/v1/responses'), $body);

        return $this->extractResponsesApiText($response);
    }

    /**
     * Generate an image.
     *
     * @param string $prompt Image description
     * @param string|null $model Model override
     * @param string $size Image size (e.g. '1024x1024')
     * @return string JSON with image URL or base64 data
     */
    public function generateImage(string $prompt, ?string $model = null, string $size = '1024x1024'): string
    {
        $model = $model ?? $this->config?->defaultImageModel ?? self::DEFAULT_IMAGE_MODEL;

        $body = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
        ];

        $response = $this->httpPost($this->url('/v1/images/generations'), $body);

        return json_encode([
            'url' => $response['data'][0]['url'] ?? null,
            'b64_json' => $response['data'][0]['b64_json'] ?? null,
            'revised_prompt' => $response['data'][0]['revised_prompt'] ?? null,
        ]);
    }

    /**
     * Convert text to speech.
     *
     * @param string $text Text to speak
     * @param string $voice Voice preset (alloy, echo, fable, onyx, nova, shimmer)
     * @param string|null $instructions Style instructions for expressive TTS
     * @param string|null $model Model override
     * @return string Base64-encoded audio data
     */
    public function textToSpeech(
        string $text,
        string $voice = 'alloy',
        ?string $instructions = null,
        ?string $model = null,
    ): string {
        $model = $model ?? $this->config?->defaultTTSModel ?? self::DEFAULT_TTS_MODEL;

        $body = [
            'model' => $model,
            'input' => $text,
            'voice' => $voice,
        ];

        if ($instructions !== null) {
            $body['instructions'] = $instructions;
        }

        $audioBytes = $this->httpPostBinary($this->url('/v1/audio/speech'), $body);

        return base64_encode($audioBytes);
    }

    protected function getAuthHeaders(): array
    {
        return ["Authorization: Bearer {$this->apiKey}"];
    }

    /**
     * Build a full URL from a path.
     */
    private function url(string $path): string
    {
        $baseUrl = $this->config?->baseUrl ?? self::BASE_URL;

        return rtrim($baseUrl, '/') . $path;
    }

    /**
     * Extract text content from a Responses API response.
     *
     * The Responses API returns output items with different types.
     *
     * @param array<string, mixed> $response
     */
    private function extractResponsesApiText(array $response): string
    {
        $parts = [];

        $output = $response['output'] ?? [];
        foreach ($output as $item) {
            $type = $item['type'] ?? '';

            if ($type === 'message') {
                foreach ($item['content'] ?? [] as $content) {
                    if (($content['type'] ?? '') === 'output_text') {
                        $parts[] = $content['text'] ?? '';
                    }
                }
            }
        }

        return implode("\n", $parts) ?: json_encode($response);
    }
}
