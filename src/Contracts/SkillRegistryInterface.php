<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for skill registries that manage available skills.
 */
interface SkillRegistryInterface
{
    /**
     * Register a skill.
     */
    public function register(SkillInterface $skill): void;

    /**
     * Unregister a skill by name.
     */
    public function unregister(string $name): void;

    /**
     * Get a skill by name.
     */
    public function get(string $name): SkillInterface;

    /**
     * Check if a skill is registered.
     */
    public function has(string $name): bool;

    /**
     * Get all registered skills.
     *
     * @return SkillInterface[]
     */
    public function all(): array;

    /**
     * Search skills by query string.
     *
     * @return SkillInterface[]
     */
    public function search(string $query): array;

    /**
     * Get skill count.
     */
    public function count(): int;
}
