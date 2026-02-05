<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;
use ClaudeAgents\Contracts\SkillRegistryInterface;
use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;

/**
 * Registry for managing available Agent Skills.
 *
 * Provides registration, lookup, and search capabilities
 * for skills loaded from the filesystem or registered programmatically.
 */
class SkillRegistry implements SkillRegistryInterface
{
    /** @var array<string, SkillInterface> */
    private array $skills = [];

    /**
     * Register a skill.
     */
    public function register(SkillInterface $skill): void
    {
        $this->skills[$skill->getName()] = $skill;
    }

    /**
     * Register multiple skills at once.
     *
     * @param SkillInterface[] $skills
     */
    public function registerMany(array $skills): void
    {
        foreach ($skills as $skill) {
            $this->register($skill);
        }
    }

    /**
     * Unregister a skill by name.
     */
    public function unregister(string $name): void
    {
        if (!isset($this->skills[$name])) {
            throw SkillNotFoundException::withName($name);
        }

        unset($this->skills[$name]);
    }

    /**
     * Get a skill by name.
     */
    public function get(string $name): SkillInterface
    {
        if (!isset($this->skills[$name])) {
            throw SkillNotFoundException::withName($name);
        }

        return $this->skills[$name];
    }

    /**
     * Check if a skill is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->skills[$name]);
    }

    /**
     * Get all registered skills.
     *
     * @return SkillInterface[]
     */
    public function all(): array
    {
        return $this->skills;
    }

    /**
     * Search skills by query string.
     *
     * @return SkillInterface[]
     */
    public function search(string $query): array
    {
        return array_filter(
            $this->skills,
            fn(SkillInterface $skill) => $skill->matchesQuery($query)
        );
    }

    /**
     * Get skill count.
     */
    public function count(): int
    {
        return count($this->skills);
    }

    /**
     * Get all skill names.
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->skills);
    }

    /**
     * Get summaries for all skills (progressive disclosure - lightweight).
     *
     * @return array<string, array{name: string, description: string}>
     */
    public function summaries(): array
    {
        $summaries = [];
        foreach ($this->skills as $name => $skill) {
            $summaries[$name] = $skill->getSummary();
        }

        return $summaries;
    }

    /**
     * Get skills that are auto-invocable (not disabled).
     *
     * @return SkillInterface[]
     */
    public function getAutoInvocable(): array
    {
        return array_filter(
            $this->skills,
            fn(SkillInterface $skill) => $skill instanceof Skill && $skill->isAutoInvocable()
        );
    }

    /**
     * Get mode skills.
     *
     * @return SkillInterface[]
     */
    public function getModes(): array
    {
        return array_filter(
            $this->skills,
            fn(SkillInterface $skill) => $skill instanceof Skill && $skill->isMode()
        );
    }

    /**
     * Clear all registered skills.
     */
    public function clear(): void
    {
        $this->skills = [];
    }
}
