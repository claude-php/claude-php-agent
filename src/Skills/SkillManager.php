<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;
use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;

/**
 * Central manager for Agent Skills operations.
 *
 * Provides a unified API for skill discovery, loading, registration,
 * resolution, validation, installation, and export. Implements the
 * progressive disclosure pattern from the agentskills.io specification.
 *
 * @see https://agentskills.io/specification
 */
class SkillManager
{
    private static ?self $instance = null;
    private SkillLoader $loader;
    private SkillRegistry $registry;
    private SkillResolver $resolver;
    private SkillValidator $validator;
    private bool $discovered = false;

    public function __construct(?string $skillsPath = null)
    {
        if ($skillsPath === null) {
            $skillsPath = $this->getDefaultSkillsPath();
        }

        $this->loader = new SkillLoader($skillsPath);
        $this->registry = new SkillRegistry();
        $this->resolver = new SkillResolver($this->registry);
        $this->validator = new SkillValidator();
    }

    /**
     * Get singleton instance.
     */
    public static function getInstance(?string $skillsPath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($skillsPath);
        }

        return self::$instance;
    }

    /**
     * Reset singleton (for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Discover and load all skills from configured paths.
     *
     * @return SkillInterface[]
     */
    public function discover(): array
    {
        $skills = $this->loader->loadAll();
        $this->registry->registerMany($skills);
        $this->discovered = true;

        return $skills;
    }

    /**
     * Ensure skills have been discovered.
     */
    public function ensureDiscovered(): void
    {
        if (!$this->discovered) {
            $this->discover();
        }
    }

    /**
     * Get a skill by name (loads on demand if not yet discovered).
     */
    public function get(string $name): SkillInterface
    {
        if ($this->registry->has($name)) {
            return $this->registry->get($name);
        }

        // Try to load on demand
        if ($this->loader->exists($name)) {
            $skill = $this->loader->load($name);
            $this->registry->register($skill);
            return $skill;
        }

        throw SkillNotFoundException::withName($name);
    }

    /**
     * Resolve relevant skills for a user input/task.
     *
     * @param string $input User input or task description
     * @param float $threshold Minimum relevance score
     * @return SkillInterface[]
     */
    public function resolve(string $input, float $threshold = 0.3): array
    {
        $this->ensureDiscovered();

        return $this->resolver->resolve($input, $threshold);
    }

    /**
     * Resolve the single most relevant skill.
     */
    public function resolveOne(string $input): ?SkillInterface
    {
        $this->ensureDiscovered();

        return $this->resolver->resolveOne($input);
    }

    /**
     * Search skills by query.
     *
     * @return SkillInterface[]
     */
    public function search(string $query): array
    {
        $this->ensureDiscovered();

        return $this->registry->search($query);
    }

    /**
     * Get all registered skills.
     *
     * @return SkillInterface[]
     */
    public function all(): array
    {
        $this->ensureDiscovered();

        return $this->registry->all();
    }

    /**
     * Get lightweight summaries for progressive disclosure.
     *
     * @return array<string, array{name: string, description: string}>
     */
    public function summaries(): array
    {
        $this->ensureDiscovered();

        return $this->registry->summaries();
    }

    /**
     * Register a skill programmatically (not from filesystem).
     */
    public function register(SkillInterface $skill): void
    {
        $this->registry->register($skill);
    }

    /**
     * Register a skill from raw SKILL.md content.
     */
    public function registerFromMarkdown(string $content, string $path = ''): SkillInterface
    {
        $skill = Skill::fromMarkdown($content, $path);
        $this->registry->register($skill);

        return $skill;
    }

    /**
     * Validate a skill's SKILL.md content.
     *
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public function validate(string $content): array
    {
        return $this->validator->validate($content);
    }

    /**
     * Validate a skill directory.
     *
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public function validateDirectory(string $path): array
    {
        return $this->validator->validateDirectory($path);
    }

    /**
     * Install a skill from a source path to the skills directory.
     */
    public function install(string $sourcePath): SkillInterface
    {
        $installer = new SkillInstaller($this->loader->getSkillsPath());

        return $installer->install($sourcePath, $this->registry);
    }

    /**
     * Uninstall a skill by name.
     */
    public function uninstall(string $name): void
    {
        $installer = new SkillInstaller($this->loader->getSkillsPath());
        $installer->uninstall($name, $this->registry);
    }

    /**
     * Export a skill to a target directory.
     */
    public function export(string $name, string $targetPath): string
    {
        $skill = $this->get($name);
        $exporter = new SkillExporter();

        return $exporter->export($skill, $targetPath);
    }

    /**
     * Create a new skill from provided data.
     */
    public function create(array $data): Skill
    {
        $exporter = new SkillExporter();

        return $exporter->createSkill($data);
    }

    /**
     * Add an additional search path for skills.
     */
    public function addPath(string $path): self
    {
        $this->loader->addPath($path);
        $this->discovered = false; // Force re-discovery

        return $this;
    }

    /**
     * Get the count of registered skills.
     */
    public function count(): int
    {
        $this->ensureDiscovered();

        return $this->registry->count();
    }

    /**
     * Get the underlying registry.
     */
    public function getRegistry(): SkillRegistry
    {
        return $this->registry;
    }

    /**
     * Get the underlying loader.
     */
    public function getLoader(): SkillLoader
    {
        return $this->loader;
    }

    /**
     * Get the underlying validator.
     */
    public function getValidator(): SkillValidator
    {
        return $this->validator;
    }

    /**
     * Get the underlying resolver.
     */
    public function getResolver(): SkillResolver
    {
        return $this->resolver;
    }

    /**
     * Generate system prompt additions for progressive disclosure.
     *
     * Returns a compact representation of available skills that can
     * be injected into the system prompt without loading full instructions.
     */
    public function generateSkillsPrompt(): string
    {
        $this->ensureDiscovered();

        $summaries = $this->registry->summaries();
        if (empty($summaries)) {
            return '';
        }

        $lines = ["## Available Skills\n"];
        $lines[] = "The following skills are available. When a user's request matches a skill, load its full instructions.\n";

        $modes = $this->registry->getModes();
        if (!empty($modes)) {
            $lines[] = "### Mode Commands";
            foreach ($modes as $skill) {
                $lines[] = "- **{$skill->getName()}**: {$skill->getDescription()}";
            }
            $lines[] = '';
        }

        $autoInvocable = $this->registry->getAutoInvocable();
        if (!empty($autoInvocable)) {
            $lines[] = "### Skills";
            foreach ($autoInvocable as $skill) {
                if ($skill instanceof Skill && !$skill->isMode()) {
                    $lines[] = "- **{$skill->getName()}**: {$skill->getDescription()}";
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get the default skills directory path.
     */
    private function getDefaultSkillsPath(): string
    {
        // Check for project-level skills directory
        $projectRoot = dirname(__DIR__, 2);
        $projectSkills = $projectRoot . '/skills';
        if (is_dir($projectSkills)) {
            return $projectSkills;
        }

        // Fallback to a skills directory in the working directory
        return getcwd() . '/skills';
    }
}
