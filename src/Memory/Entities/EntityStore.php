<?php

declare(strict_types=1);

namespace ClaudeAgents\Memory\Entities;

/**
 * Storage for extracted entities.
 *
 * Maintains a database of entities with their attributes and relationships.
 */
class EntityStore
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $entities = [];

    /**
     * Store or update an entity.
     *
     * @param string $name Entity name
     * @param array<string, mixed> $data Entity data
     */
    public function store(string $name, array $data): void
    {
        if (isset($this->entities[$name])) {
            // Update existing entity
            $this->entities[$name] = $this->mergeEntity($this->entities[$name], $data);
        } else {
            // Store new entity
            $this->entities[$name] = array_merge([
                'type' => 'unknown',
                'attributes' => [],
                'mentions' => 1,
                'first_seen' => time(),
                'last_seen' => time(),
            ], $data);
        }
    }

    /**
     * Store multiple entities.
     *
     * @param array<string, array<string, mixed>> $entities
     */
    public function storeMany(array $entities): void
    {
        foreach ($entities as $name => $data) {
            $this->store($name, $data);
        }
    }

    /**
     * Get an entity by name.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        return $this->entities[$name] ?? null;
    }

    /**
     * Check if an entity exists.
     */
    public function has(string $name): bool
    {
        return isset($this->entities[$name]);
    }

    /**
     * Get all entities.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entities;
    }

    /**
     * Get entities by type.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getByType(string $type): array
    {
        return array_filter(
            $this->entities,
            fn ($entity) => ($entity['type'] ?? '') === $type
        );
    }

    /**
     * Search entities by name pattern.
     *
     * @return array<string, array<string, mixed>>
     */
    public function search(string $query): array
    {
        $query = strtolower($query);

        return array_filter(
            $this->entities,
            fn ($entity, $name) => stripos(strtolower($name), $query) !== false,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Get most mentioned entities.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getMostMentioned(int $limit = 10): array
    {
        $sorted = $this->entities;

        uasort($sorted, function ($a, $b) {
            return ($b['mentions'] ?? 0) <=> ($a['mentions'] ?? 0);
        });

        return array_slice($sorted, 0, $limit, true);
    }

    /**
     * Get recently accessed entities.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRecent(int $limit = 10): array
    {
        $sorted = $this->entities;

        uasort($sorted, function ($a, $b) {
            return ($b['last_seen'] ?? 0) <=> ($a['last_seen'] ?? 0);
        });

        return array_slice($sorted, 0, $limit, true);
    }

    /**
     * Remove an entity.
     */
    public function forget(string $name): bool
    {
        if (isset($this->entities[$name])) {
            unset($this->entities[$name]);

            return true;
        }

        return false;
    }

    /**
     * Clear all entities.
     */
    public function clear(): void
    {
        $this->entities = [];
    }

    /**
     * Get entity count.
     */
    public function count(): int
    {
        return count($this->entities);
    }

    /**
     * Get statistics about stored entities.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $types = [];
        $totalMentions = 0;

        foreach ($this->entities as $entity) {
            $type = $entity['type'] ?? 'unknown';
            $types[$type] = ($types[$type] ?? 0) + 1;
            $totalMentions += $entity['mentions'] ?? 0;
        }

        return [
            'total_entities' => count($this->entities),
            'types' => $types,
            'total_mentions' => $totalMentions,
            'avg_mentions' => count($this->entities) > 0 ? $totalMentions / count($this->entities) : 0,
        ];
    }

    /**
     * Merge new entity data with existing entity.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $new
     * @return array<string, mixed>
     */
    private function mergeEntity(array $existing, array $new): array
    {
        // Increment mentions
        $existing['mentions'] = ($existing['mentions'] ?? 0) + ($new['mentions'] ?? 1);
        $existing['last_seen'] = time();

        // Update type if new is more specific
        if (isset($new['type']) && $new['type'] !== 'unknown') {
            $existing['type'] = $new['type'];
        }

        // Merge attributes
        if (isset($new['attributes']) && is_array($new['attributes'])) {
            $existing['attributes'] = array_merge(
                $existing['attributes'] ?? [],
                $new['attributes']
            );
        }

        return $existing;
    }
}
