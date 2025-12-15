<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory;

use ClaudeAgents\Memory\Entities\EntityExtractor;
use ClaudeAgents\Memory\Entities\EntityStore;

/**
 * Memory that extracts and tracks entities across conversations.
 *
 * Automatically identifies people, places, organizations, and other entities,
 * maintaining their attributes and enabling entity-based recall.
 */
class EntityMemory
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $messages = [];

    private int $extractionInterval;
    private int $messagesSinceExtraction = 0;

    /**
     * @param EntityExtractor $extractor Entity extractor
     * @param EntityStore $store Entity store
     * @param int $extractionInterval Extract entities every N messages
     */
    public function __construct(
        private readonly EntityExtractor $extractor,
        private readonly EntityStore $store,
        int $extractionInterval = 5
    ) {
        if ($extractionInterval < 1) {
            throw new \InvalidArgumentException('Extraction interval must be at least 1');
        }

        $this->extractionInterval = $extractionInterval;
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

        // Extract entities periodically
        if ($this->messagesSinceExtraction >= $this->extractionInterval) {
            $this->extractEntities();
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
     * Get context for the LLM (messages + entity information).
     *
     * @return array<array<string, mixed>>
     */
    public function getContext(): array
    {
        $context = [];

        // Add entity context if we have entities
        $entities = $this->store->all();
        if (! empty($entities)) {
            $entitySummary = $this->buildEntitySummary($entities);
            $context[] = [
                'role' => 'user',
                'content' => "Known entities and their information:\n{$entitySummary}",
            ];
        }

        // Add conversation messages
        foreach ($this->messages as $message) {
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
     * Get the entity store.
     */
    public function getEntityStore(): EntityStore
    {
        return $this->store;
    }

    /**
     * Get information about a specific entity.
     *
     * @return array<string, mixed>|null
     */
    public function getEntity(string $name): ?array
    {
        return $this->store->get($name);
    }

    /**
     * Search for entities.
     *
     * @return array<string, array<string, mixed>>
     */
    public function searchEntities(string $query): array
    {
        return $this->store->search($query);
    }

    /**
     * Get entities by type.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEntitiesByType(string $type): array
    {
        return $this->store->getByType($type);
    }

    /**
     * Force entity extraction from current messages.
     */
    public function extractEntities(): void
    {
        if (empty($this->messages)) {
            return;
        }

        // Extract from recent messages
        $recentMessages = array_slice($this->messages, -$this->extractionInterval);
        $entities = $this->extractor->extract($recentMessages);

        // Store extracted entities
        $this->store->storeMany($entities);

        $this->messagesSinceExtraction = 0;
    }

    /**
     * Clear all memory.
     */
    public function clear(): void
    {
        $this->messages = [];
        $this->store->clear();
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
     * Get entity count.
     */
    public function getEntityCount(): int
    {
        return $this->store->count();
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
            'entity_stats' => $this->store->getStats(),
            'messages_since_extraction' => $this->messagesSinceExtraction,
        ];
    }

    /**
     * Build a summary of entities for context.
     *
     * @param array<string, array<string, mixed>> $entities
     */
    private function buildEntitySummary(array $entities): string
    {
        $lines = [];

        // Get most mentioned entities (limit for context size)
        $topEntities = $this->store->getMostMentioned(10);

        foreach ($topEntities as $name => $entity) {
            $type = $entity['type'] ?? 'unknown';
            $mentions = $entity['mentions'] ?? 0;

            $line = "- {$name} ({$type}, mentioned {$mentions}x)";

            // Add key attributes
            if (! empty($entity['attributes'])) {
                $attrs = [];
                $count = 0;
                foreach ($entity['attributes'] as $key => $value) {
                    if ($count >= 3) {
                        break; // Limit attributes for brevity
                    }
                    $attrs[] = "{$key}: {$value}";
                    $count++;
                }

                if (! empty($attrs)) {
                    $line .= ' - ' . implode(', ', $attrs);
                }
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
