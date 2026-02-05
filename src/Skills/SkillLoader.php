<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;
use ClaudeAgents\Contracts\SkillLoaderInterface;
use ClaudeAgents\Skills\Exceptions\SkillLoadException;
use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;

/**
 * Loads Agent Skills from the filesystem.
 *
 * Discovers SKILL.md files in skill directories and parses them
 * according to the agentskills.io specification.
 *
 * Supports progressive disclosure: only skill summaries (name + description)
 * are loaded initially. Full instructions are loaded on demand.
 */
class SkillLoader implements SkillLoaderInterface
{
    /** @var array<string, Skill> */
    private array $cache = [];
    private bool $cacheEnabled = true;

    /**
     * @param string $skillsPath Base directory containing skill folders
     * @param string[] $additionalPaths Additional directories to scan
     */
    public function __construct(
        private string $skillsPath,
        private array $additionalPaths = [],
    ) {
    }

    /**
     * Load all skills from all configured paths.
     *
     * @return Skill[]
     */
    public function loadAll(): array
    {
        $skills = [];

        $paths = array_merge([$this->skillsPath], $this->additionalPaths);

        foreach ($paths as $basePath) {
            if (!is_dir($basePath)) {
                continue;
            }

            $items = scandir($basePath);
            if ($items === false) {
                continue;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $skillDir = $basePath . '/' . $item;
                if (!is_dir($skillDir)) {
                    continue;
                }

                $skillFile = $skillDir . '/SKILL.md';
                if (!file_exists($skillFile)) {
                    continue;
                }

                try {
                    $skill = $this->loadFromPath($skillDir);
                    $skills[$skill->getName()] = $skill;
                } catch (SkillLoadException $e) {
                    error_log("Failed to load skill from {$skillDir}: " . $e->getMessage());
                }
            }
        }

        return $skills;
    }

    /**
     * Load a specific skill by name.
     */
    public function load(string $name): SkillInterface
    {
        if ($this->cacheEnabled && isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $paths = array_merge([$this->skillsPath], $this->additionalPaths);

        foreach ($paths as $basePath) {
            $skillDir = $basePath . '/' . $name;
            $skillFile = $skillDir . '/SKILL.md';

            if (file_exists($skillFile)) {
                return $this->loadFromPath($skillDir);
            }
        }

        throw SkillNotFoundException::withName($name);
    }

    /**
     * Check if a skill exists by name.
     */
    public function exists(string $name): bool
    {
        $paths = array_merge([$this->skillsPath], $this->additionalPaths);

        foreach ($paths as $basePath) {
            $skillFile = $basePath . '/' . $name . '/SKILL.md';
            if (file_exists($skillFile)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load a skill from a specific directory path.
     */
    public function loadFromPath(string $skillDir): Skill
    {
        $skillFile = $skillDir . '/SKILL.md';

        if (!file_exists($skillFile)) {
            throw SkillNotFoundException::noSkillFile($skillDir);
        }

        $content = file_get_contents($skillFile);
        if ($content === false) {
            throw SkillLoadException::readError($skillFile);
        }

        try {
            $parsed = FrontmatterParser::parse($content);
        } catch (\Exception $e) {
            throw SkillLoadException::parseError($skillFile, $e->getMessage());
        }

        $metadata = SkillMetadata::fromArray($parsed['frontmatter']);
        $skill = Skill::create($skillDir, $metadata, $parsed['body']);

        if ($this->cacheEnabled) {
            $this->cache[$skill->getName()] = $skill;
        }

        return $skill;
    }

    /**
     * Add an additional search path.
     */
    public function addPath(string $path): self
    {
        $this->additionalPaths[] = $path;

        return $this;
    }

    /**
     * Enable or disable caching.
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        if (!$enabled) {
            $this->cache = [];
        }

        return $this;
    }

    /**
     * Clear the loader cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get the base skills path.
     */
    public function getSkillsPath(): string
    {
        return $this->skillsPath;
    }
}
