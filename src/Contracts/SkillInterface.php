<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Skills\SkillMetadata;

/**
 * Interface for Agent Skills following the agentskills.io specification.
 */
interface SkillInterface
{
    /**
     * Get the skill name.
     */
    public function getName(): string;

    /**
     * Get the skill description.
     */
    public function getDescription(): string;

    /**
     * Get the skill metadata.
     */
    public function getMetadata(): SkillMetadata;

    /**
     * Get the skill instructions (markdown body).
     */
    public function getInstructions(): string;

    /**
     * Get the filesystem path to the skill directory.
     */
    public function getPath(): string;

    /**
     * Get available script files.
     *
     * @return string[]
     */
    public function getScripts(): array;

    /**
     * Get available reference files.
     *
     * @return string[]
     */
    public function getReferences(): array;

    /**
     * Get available asset files.
     *
     * @return string[]
     */
    public function getAssets(): array;

    /**
     * Check if the skill instructions have been loaded into context.
     */
    public function isLoaded(): bool;

    /**
     * Check if the skill matches a search query.
     */
    public function matchesQuery(string $query): bool;

    /**
     * Calculate relevance score for a query (0.0 to 1.0).
     */
    public function relevanceScore(string $query): float;

    /**
     * Get a lightweight summary for progressive disclosure.
     *
     * @return array{name: string, description: string}
     */
    public function getSummary(): array;

    /**
     * Convert to array representation.
     */
    public function toArray(): array;
}
