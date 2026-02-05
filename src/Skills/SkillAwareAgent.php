<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;

/**
 * Trait that adds Agent Skills capabilities to any agent.
 *
 * Enables agents to discover, load, and use skills following
 * the agentskills.io specification with progressive disclosure.
 *
 * @see https://agentskills.io/specification
 */
trait SkillAwareAgent
{
    /** @var SkillInterface[] */
    private array $loadedSkills = [];

    /** @var SkillManager|null */
    private ?SkillManager $skillManager = null;

    /** @var bool */
    private bool $autoResolveSkills = true;

    /**
     * Set the skill manager for this agent.
     *
     * @return $this
     */
    public function withSkillManager(SkillManager $manager): static
    {
        $this->skillManager = $manager;

        return $this;
    }

    /**
     * Load a specific skill by name.
     *
     * @return $this
     */
    public function withSkill(string $name): static
    {
        $manager = $this->getSkillManager();
        $skill = $manager->get($name);
        $this->loadedSkills[$skill->getName()] = $skill;

        if ($skill instanceof Skill) {
            $skill->markLoaded();
        }

        return $this;
    }

    /**
     * Load multiple skills by name.
     *
     * @param string[] $names
     * @return $this
     */
    public function withSkills(array $names): static
    {
        foreach ($names as $name) {
            $this->withSkill($name);
        }

        return $this;
    }

    /**
     * Add a skill object directly.
     *
     * @return $this
     */
    public function addSkill(SkillInterface $skill): static
    {
        $this->loadedSkills[$skill->getName()] = $skill;

        if ($skill instanceof Skill) {
            $skill->markLoaded();
        }

        return $this;
    }

    /**
     * Enable or disable automatic skill resolution.
     *
     * @return $this
     */
    public function autoResolveSkills(bool $enabled = true): static
    {
        $this->autoResolveSkills = $enabled;

        return $this;
    }

    /**
     * Resolve and load relevant skills for a task.
     *
     * @return SkillInterface[]
     */
    public function resolveSkillsForTask(string $task): array
    {
        if (!$this->autoResolveSkills) {
            return $this->loadedSkills;
        }

        $manager = $this->getSkillManager();
        $resolved = $manager->resolve($task);

        foreach ($resolved as $skill) {
            if (!isset($this->loadedSkills[$skill->getName()])) {
                $this->loadedSkills[$skill->getName()] = $skill;
                if ($skill instanceof Skill) {
                    $skill->markLoaded();
                }
            }
        }

        return $this->loadedSkills;
    }

    /**
     * Get all loaded skills.
     *
     * @return SkillInterface[]
     */
    public function getLoadedSkills(): array
    {
        return $this->loadedSkills;
    }

    /**
     * Build skill-enhanced system prompt.
     *
     * Composes the base system prompt with skill instructions
     * following the progressive disclosure pattern.
     */
    public function buildSkillEnhancedPrompt(string $basePrompt, string $task = ''): string
    {
        $composer = new SkillPromptComposer();

        // If we have loaded skills, use them
        $skills = $this->loadedSkills;

        // If auto-resolve is enabled and we have a task, try to resolve more
        if ($this->autoResolveSkills && !empty($task)) {
            $resolved = $this->resolveSkillsForTask($task);
            $skills = array_merge($skills, $resolved);
        }

        if (empty($skills)) {
            // Add skill summaries for discovery
            $manager = $this->getSkillManager();
            $summaryPrompt = $manager->generateSkillsPrompt();
            if (!empty($summaryPrompt)) {
                return $basePrompt . "\n\n" . $summaryPrompt;
            }
            return $basePrompt;
        }

        return $composer->compose($basePrompt, $skills);
    }

    /**
     * Get or create the skill manager.
     */
    private function getSkillManager(): SkillManager
    {
        if ($this->skillManager === null) {
            $this->skillManager = SkillManager::getInstance();
        }

        return $this->skillManager;
    }
}
