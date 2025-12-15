<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Utility for extracting text content from Claude API responses.
 *
 * Handles the common pattern of extracting text from response content blocks.
 */
class TextContentExtractor
{
    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content Response content blocks
     * @return string Combined text content
     */
    public static function extract(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Extract text content from a response object.
     *
     * @param object|array<mixed> $response Response object or array with content property
     * @return string Combined text content
     */
    public static function extractFromResponse(object|array $response): string
    {
        if (is_array($response)) {
            $content = $response['content'] ?? [];
        } else {
            $content = $response->content ?? [];
        }

        return self::extract($content);
    }
}
