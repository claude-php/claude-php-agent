<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for skill loaders that discover and load skills from various sources.
 */
interface SkillLoaderInterface
{
    /**
     * Load all skills from the configured source.
     *
     * @return SkillInterface[]
     */
    public function loadAll(): array;

    /**
     * Load a specific skill by name.
     */
    public function load(string $name): SkillInterface;

    /**
     * Check if a skill exists.
     */
    public function exists(string $name): bool;
}
