<?php

declare(strict_types=1);

namespace ClaudeAgents\RAG\Loaders;

/**
 * Loads documents from web pages.
 *
 * Requires PHP's DOM extension.
 */
class WebLoader implements DocumentLoaderInterface
{
    /**
     * @param string $url URL to load
     * @param bool $stripTags Whether to strip HTML tags
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly string $url,
        private readonly bool $stripTags = true,
        private readonly array $metadata = [],
    ) {
    }

    public function load(): array
    {
        $content = @file_get_contents($this->url);

        if ($content === false) {
            throw new \RuntimeException("Failed to fetch URL: {$this->url}");
        }

        $title = $this->extractTitle($content);
        $text = $this->stripTags ? $this->extractText($content) : $content;

        return [[
            'title' => $title,
            'content' => $text,
            'metadata' => array_merge(
                [
                    'source' => $this->url,
                    'source_type' => 'web',
                    'fetched_at' => date('Y-m-d H:i:s'),
                ],
                $this->metadata
            ),
        ]];
    }

    /**
     * Extract title from HTML.
     */
    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        // Fallback to URL
        return parse_url($this->url, PHP_URL_HOST) ?? $this->url;
    }

    /**
     * Extract text content from HTML.
     */
    private function extractText(string $html): string
    {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Strip remaining tags
        $text = strip_tags($html ?? '');

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }
}
