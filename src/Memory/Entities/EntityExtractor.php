<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory\Entities;

use ClaudePhp\ClaudePhp;

/**
 * Extracts entities (people, places, things) from text.
 *
 * Uses Claude to identify and extract named entities and their attributes
 * from conversation messages.
 */
class EntityExtractor
{
    private string $model;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array{model?: string} $options Configuration options
     */
    public function __construct(
        private readonly ClaudePhp $client,
        array $options = []
    ) {
        $this->model = $options['model'] ?? 'claude-3-5-haiku-20241022';
    }

    /**
     * Extract entities from messages.
     *
     * @param array<array<string, mixed>> $messages
     * @return array<string, array<string, mixed>> Entity name => entity data
     */
    public function extract(array $messages): array
    {
        $text = $this->formatMessages($messages);

        if (empty($text)) {
            return [];
        }

        $prompt = $this->buildExtractionPrompt($text);

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => 1000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            $content = $response->content[0] ?? null;
            if ($content && $content->type === 'text') {
                return $this->parseEntities($content->text);
            }
        } catch (\Exception $e) {
            // Fall back to simple extraction
            return $this->simpleExtract($text);
        }

        return [];
    }

    /**
     * Extract a single entity's information from messages.
     *
     * @param array<array<string, mixed>> $messages
     * @return array<string, mixed>|null
     */
    public function extractEntity(array $messages, string $entityName): ?array
    {
        $entities = $this->extract($messages);

        return $entities[$entityName] ?? null;
    }

    /**
     * Build the entity extraction prompt.
     */
    private function buildExtractionPrompt(string $text): string
    {
        return <<<PROMPT
            Extract all named entities (people, places, organizations, products, concepts) from the following text.

            For each entity, provide:
            1. Name (the entity identifier)
            2. Type (person, place, organization, product, concept, other)
            3. Attributes (any relevant information about the entity)

            Format your response as JSON with this structure:
            {
              "entity_name": {
                "type": "person|place|organization|product|concept|other",
                "attributes": {
                  "key": "value"
                }
              }
            }

            TEXT:
            {$text}

            Respond ONLY with valid JSON. Do not include any explanation or markdown formatting.
            PROMPT;
    }

    /**
     * Parse entities from LLM response.
     *
     * @return array<string, array<string, mixed>>
     */
    private function parseEntities(string $response): array
    {
        // Try to extract JSON from the response
        $response = trim($response);

        // Remove markdown code blocks if present
        $response = preg_replace('/```(?:json)?\s*/', '', $response);
        $response = trim($response);

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                return [];
            }

            // Validate structure
            $entities = [];
            foreach ($data as $name => $entity) {
                if (is_array($entity) && isset($entity['type'])) {
                    $entities[$name] = [
                        'type' => $entity['type'],
                        'attributes' => $entity['attributes'] ?? [],
                        'mentions' => 1,
                        'first_seen' => time(),
                        'last_seen' => time(),
                    ];
                }
            }

            return $entities;
        } catch (\JsonException $e) {
            return $this->simpleExtract($response);
        }
    }

    /**
     * Simple fallback entity extraction using capitalization heuristics.
     *
     * @return array<string, array<string, mixed>>
     */
    private function simpleExtract(string $text): array
    {
        $entities = [];

        // Find capitalized words (potential proper nouns)
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $text, $matches);

        if (empty($matches[0])) {
            return [];
        }

        foreach ($matches[0] as $match) {
            $name = trim($match);

            if (strlen($name) < 2) {
                continue;
            }

            // Skip common words
            if (in_array(strtolower($name), ['the', 'this', 'that', 'these', 'those', 'i'])) {
                continue;
            }

            if (! isset($entities[$name])) {
                $entities[$name] = [
                    'type' => 'unknown',
                    'attributes' => [],
                    'mentions' => 1,
                    'first_seen' => time(),
                    'last_seen' => time(),
                ];
            } else {
                $entities[$name]['mentions']++;
                $entities[$name]['last_seen'] = time();
            }
        }

        return $entities;
    }

    /**
     * Format messages into text for extraction.
     *
     * @param array<array<string, mixed>> $messages
     */
    private function formatMessages(array $messages): string
    {
        $texts = [];

        foreach ($messages as $message) {
            $content = $message['content'] ?? '';

            if (is_string($content)) {
                $texts[] = $content;
            } elseif (is_array($content)) {
                foreach ($content as $block) {
                    if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                        $texts[] = $block['text'];
                    }
                }
            }
        }

        return implode(' ', $texts);
    }
}
