<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory;

use ClaudeAgents\Memory\Entities\EntityExtractor;
use ClaudePhp\ClaudePhp;

/**
 * Knowledge Graph conversation memory.
 *
 * Builds a knowledge graph of entities and their relationships from conversations,
 * enabling complex knowledge-based retrieval and reasoning.
 */
class ConversationKGMemory
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $messages = [];

    /**
     * @var array<string, array<string, mixed>> Entity name => entity data
     */
    private array $entities = [];

    /**
     * @var array<array{subject: string, predicate: string, object: string, timestamp: int}>
     */
    private array $relationships = [];

    private int $extractionInterval;
    private int $messagesSinceExtraction = 0;
    private string $model;

    /**
     * @param ClaudePhp $client Claude API client
     * @param EntityExtractor $extractor Entity extractor
     * @param array{extraction_interval?: int, model?: string} $options Configuration
     */
    public function __construct(
        private readonly ClaudePhp $client,
        private readonly EntityExtractor $extractor,
        array $options = []
    ) {
        $this->extractionInterval = $options['extraction_interval'] ?? 5;
        $this->model = $options['model'] ?? 'claude-3-5-haiku-20241022';
    }

    /**
     * Add a message to memory.
     *
     * @param array<string, mixed> $message
     */
    public function add(array $message): void
    {
        $this->messages[] = $message;
        $this->messagesSinceExtraction++;

        // Extract entities and relationships periodically
        if ($this->messagesSinceExtraction >= $this->extractionInterval) {
            $this->extractKnowledge();
        }
    }

    /**
     * Add multiple messages.
     *
     * @param array<array<string, mixed>> $messages
     */
    public function addMany(array $messages): void
    {
        foreach ($messages as $message) {
            $this->add($message);
        }
    }

    /**
     * Get context for the LLM (messages + knowledge graph).
     *
     * @return array<array<string, mixed>>
     */
    public function getContext(): array
    {
        $context = [];

        // Add knowledge graph context if we have data
        if (! empty($this->entities) || ! empty($this->relationships)) {
            $kgSummary = $this->buildKnowledgeGraphSummary();
            $context[] = [
                'role' => 'user',
                'content' => "Knowledge from previous conversation:\n{$kgSummary}",
            ];
        }

        // Add recent messages
        $recentMessages = array_slice($this->messages, -10);
        foreach ($recentMessages as $message) {
            $context[] = $message;
        }

        return $context;
    }

    /**
     * Get all messages.
     *
     * @return array<array<string, mixed>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get all entities.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * Get all relationships.
     *
     * @return array<array{subject: string, predicate: string, object: string, timestamp: int}>
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }

    /**
     * Get relationships for a specific entity.
     *
     * @return array<array{subject: string, predicate: string, object: string, timestamp: int}>
     */
    public function getEntityRelationships(string $entityName): array
    {
        return array_filter(
            $this->relationships,
            fn ($rel) => $rel['subject'] === $entityName || $rel['object'] === $entityName
        );
    }

    /**
     * Query the knowledge graph.
     *
     * @return array<string, mixed>
     */
    public function query(string $subject, ?string $predicate = null, ?string $object = null): array
    {
        return array_filter(
            $this->relationships,
            function ($rel) use ($subject, $predicate, $object) {
                $match = true;

                if ($subject !== null && $rel['subject'] !== $subject) {
                    $match = false;
                }

                if ($predicate !== null && $rel['predicate'] !== $predicate) {
                    $match = false;
                }

                if ($object !== null && $rel['object'] !== $object) {
                    $match = false;
                }

                return $match;
            }
        );
    }

    /**
     * Extract knowledge (entities and relationships) from messages.
     */
    public function extractKnowledge(): void
    {
        if (empty($this->messages)) {
            return;
        }

        // Extract entities
        $recentMessages = array_slice($this->messages, -$this->extractionInterval);
        $newEntities = $this->extractor->extract($recentMessages);

        foreach ($newEntities as $name => $data) {
            if (isset($this->entities[$name])) {
                // Merge with existing
                $this->entities[$name]['mentions'] =
                    ($this->entities[$name]['mentions'] ?? 0) + ($data['mentions'] ?? 1);
                $this->entities[$name]['last_seen'] = time();
                $this->entities[$name]['attributes'] = array_merge(
                    $this->entities[$name]['attributes'] ?? [],
                    $data['attributes'] ?? []
                );
            } else {
                $this->entities[$name] = $data;
            }
        }

        // Extract relationships
        $newRelationships = $this->extractRelationships($recentMessages);
        $this->relationships = array_merge($this->relationships, $newRelationships);

        $this->messagesSinceExtraction = 0;
    }

    /**
     * Clear all memory.
     */
    public function clear(): void
    {
        $this->messages = [];
        $this->entities = [];
        $this->relationships = [];
        $this->messagesSinceExtraction = 0;
    }

    /**
     * Get message count.
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Get statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'message_count' => count($this->messages),
            'entity_count' => count($this->entities),
            'relationship_count' => count($this->relationships),
            'messages_since_extraction' => $this->messagesSinceExtraction,
        ];
    }

    /**
     * Extract relationships from messages using LLM.
     *
     * @param array<array<string, mixed>> $messages
     * @return array<array{subject: string, predicate: string, object: string, timestamp: int}>
     */
    private function extractRelationships(array $messages): array
    {
        $text = $this->formatMessages($messages);

        if (empty($text)) {
            return [];
        }

        $prompt = $this->buildRelationshipPrompt($text);

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
                return $this->parseRelationships($content->text);
            }
        } catch (\Exception $e) {
            // Silently fail - no relationships extracted
        }

        return [];
    }

    /**
     * Build relationship extraction prompt.
     */
    private function buildRelationshipPrompt(string $text): string
    {
        return <<<PROMPT
            Extract relationships between entities from the following text.

            For each relationship, provide a triple in the format: (subject, predicate, object)

            Examples:
            - (John, works_at, Acme Corp)
            - (Paris, is_capital_of, France)
            - (Alice, knows, Bob)

            Format your response as JSON array:
            [
              {"subject": "entity1", "predicate": "relationship", "object": "entity2"}
            ]

            TEXT:
            {$text}

            Respond ONLY with valid JSON. Do not include any explanation or markdown formatting.
            PROMPT;
    }

    /**
     * Parse relationships from LLM response.
     *
     * @return array<array{subject: string, predicate: string, object: string, timestamp: int}>
     */
    private function parseRelationships(string $response): array
    {
        $response = trim($response);
        $response = preg_replace('/```(?:json)?\s*/', '', $response);
        $response = trim($response);

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                return [];
            }

            $relationships = [];
            foreach ($data as $rel) {
                if (is_array($rel) &&
                    isset($rel['subject']) &&
                    isset($rel['predicate']) &&
                    isset($rel['object'])) {
                    $relationships[] = [
                        'subject' => $rel['subject'],
                        'predicate' => $rel['predicate'],
                        'object' => $rel['object'],
                        'timestamp' => time(),
                    ];
                }
            }

            return $relationships;
        } catch (\JsonException $e) {
            return [];
        }
    }

    /**
     * Build knowledge graph summary for context.
     */
    private function buildKnowledgeGraphSummary(): string
    {
        $lines = [];

        // Add entities
        if (! empty($this->entities)) {
            $lines[] = 'Entities:';
            $count = 0;
            foreach ($this->entities as $name => $entity) {
                if ($count >= 10) {
                    break;
                }
                $type = $entity['type'] ?? 'unknown';
                $lines[] = "- {$name} ({$type})";
                $count++;
            }
            $lines[] = '';
        }

        // Add relationships
        if (! empty($this->relationships)) {
            $lines[] = 'Relationships:';
            $recentRels = array_slice($this->relationships, -15);
            foreach ($recentRels as $rel) {
                $lines[] = "- {$rel['subject']} {$rel['predicate']} {$rel['object']}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format messages into text.
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
