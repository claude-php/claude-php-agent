<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;
use ClaudeAgents\Skills\Exceptions\SkillValidationException;

/**
 * Represents a single Agent Skill following the agentskills.io specification.
 *
 * A skill is a directory containing a SKILL.md file with YAML frontmatter
 * and markdown instructions. Skills can optionally include scripts/,
 * references/, and assets/ directories.
 *
 * @see https://agentskills.io/specification
 */
class Skill implements SkillInterface
{
    private bool $loaded = false;

    /**
     * @param SkillMetadata $metadata Parsed YAML frontmatter
     * @param string $instructions Markdown body content
     * @param string $path Filesystem path to skill directory
     * @param array $scripts Available script files
     * @param array $references Available reference files
     * @param array $assets Available asset files
     */
    public function __construct(
        private SkillMetadata $metadata,
        private string $instructions,
        private string $path,
        private array $scripts = [],
        private array $references = [],
        private array $assets = [],
    ) {
    }

    /**
     * Create from a parsed SKILL.md file.
     *
     * @param string $path Path to the skill directory
     * @param SkillMetadata $metadata Parsed frontmatter
     * @param string $instructions Markdown content
     */
    public static function create(
        string $path,
        SkillMetadata $metadata,
        string $instructions
    ): self {
        $skill = new self(
            metadata: $metadata,
            instructions: $instructions,
            path: $path,
        );

        $skill->discoverResources();

        return $skill;
    }

    /**
     * Create from a raw SKILL.md file content string.
     */
    public static function fromMarkdown(string $content, string $path = ''): self
    {
        $parsed = FrontmatterParser::parse($content);

        $metadata = SkillMetadata::fromArray($parsed['frontmatter']);
        $instructions = $parsed['body'];

        return new self(
            metadata: $metadata,
            instructions: $instructions,
            path: $path,
        );
    }

    public function getName(): string
    {
        return $this->metadata->name;
    }

    public function getDescription(): string
    {
        return $this->metadata->description;
    }

    public function getMetadata(): SkillMetadata
    {
        return $this->metadata;
    }

    public function getInstructions(): string
    {
        return $this->instructions;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getScripts(): array
    {
        return $this->scripts;
    }

    public function getReferences(): array
    {
        return $this->references;
    }

    public function getAssets(): array
    {
        return $this->assets;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Mark skill as loaded (instructions have been read into context).
     */
    public function markLoaded(): void
    {
        $this->loaded = true;
    }

    /**
     * Check if this skill should be auto-invoked by the model.
     */
    public function isAutoInvocable(): bool
    {
        return !$this->metadata->disableModelInvocation;
    }

    /**
     * Check if this skill is a mode command.
     */
    public function isMode(): bool
    {
        return $this->metadata->mode;
    }

    /**
     * Get a specific reference file's content.
     */
    public function getReference(string $name): ?string
    {
        $refPath = $this->path . '/references/' . $name;
        if (file_exists($refPath)) {
            return file_get_contents($refPath) ?: null;
        }

        return null;
    }

    /**
     * Get a specific script file's content.
     */
    public function getScript(string $name): ?string
    {
        $scriptPath = $this->path . '/scripts/' . $name;
        if (file_exists($scriptPath)) {
            return file_get_contents($scriptPath) ?: null;
        }

        return null;
    }

    /**
     * Get a specific asset file's path.
     */
    public function getAsset(string $name): ?string
    {
        $assetPath = $this->path . '/assets/' . $name;
        if (file_exists($assetPath)) {
            return $assetPath;
        }

        return null;
    }

    /**
     * Check if this skill matches a search query.
     */
    public function matchesQuery(string $query): bool
    {
        $query = strtolower($query);
        $name = strtolower($this->metadata->name);
        $description = strtolower($this->metadata->description);

        if (str_contains($name, $query)) {
            return true;
        }

        if (str_contains($description, $query)) {
            return true;
        }

        // Check tags
        foreach ($this->metadata->getTags() as $tag) {
            if (str_contains(strtolower($tag), $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Common English stop words that should be ignored during relevance scoring.
     *
     * These short, frequent words (like "in", "a", "the") cause false positives
     * because they appear as substrings in unrelated skill names/descriptions
     * (e.g. "in" matches "guidel-in-es", "f-in-ancial", "coauthor-in-g").
     */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'in', 'on', 'at', 'to', 'for', 'of', 'by',
        'is', 'it', 'or', 'and', 'but', 'not', 'no', 'so', 'if',
        'do', 'my', 'me', 'we', 'be', 'am', 'are', 'was', 'has',
        'can', 'will', 'how', 'what', 'who', 'this', 'that', 'with',
    ];

    /**
     * Calculate relevance score for a query (0.0 to 1.0).
     *
     * Words shorter than 3 characters and common stop words are filtered
     * out before scoring to prevent false positive matches.
     */
    public function relevanceScore(string $query): float
    {
        $query = strtolower(trim($query, '?!., '));
        $words = array_values(array_filter(explode(' ', $query), function (string $w): bool {
            return strlen($w) >= 3 && !in_array($w, self::STOP_WORDS, true);
        }));
        $score = 0.0;
        $maxScore = count($words) > 0 ? count($words) : 1;

        $name = strtolower($this->metadata->name);
        $description = strtolower($this->metadata->description);

        foreach ($words as $word) {
            if (str_contains($name, $word)) {
                $score += 1.0;
            } elseif (str_contains($description, $word)) {
                $score += 0.7;
            } else {
                foreach ($this->metadata->getTags() as $tag) {
                    if (str_contains(strtolower($tag), $word)) {
                        $score += 0.5;
                        break;
                    }
                }
            }
        }

        return min(1.0, $score / $maxScore);
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->metadata->name,
            'description' => $this->metadata->description,
            'path' => $this->path,
            'metadata' => $this->metadata->toArray(),
            'instructions' => $this->instructions,
            'scripts' => $this->scripts,
            'references' => $this->references,
            'assets' => $this->assets,
            'loaded' => $this->loaded,
        ];
    }

    /**
     * Get a summary for progressive disclosure (lightweight representation).
     */
    public function getSummary(): array
    {
        return [
            'name' => $this->metadata->name,
            'description' => $this->metadata->description,
        ];
    }

    /**
     * Discover available resources in the skill directory.
     */
    private function discoverResources(): void
    {
        if (empty($this->path) || !is_dir($this->path)) {
            return;
        }

        // Discover scripts
        $scriptsDir = $this->path . '/scripts';
        if (is_dir($scriptsDir)) {
            $this->scripts = $this->scanDirectory($scriptsDir);
        }

        // Discover references
        $referencesDir = $this->path . '/references';
        if (is_dir($referencesDir)) {
            $this->references = $this->scanDirectory($referencesDir);
        }

        // Discover assets
        $assetsDir = $this->path . '/assets';
        if (is_dir($assetsDir)) {
            $this->assets = $this->scanDirectory($assetsDir);
        }
    }

    /**
     * Scan a directory and return list of files.
     */
    private function scanDirectory(string $dir): array
    {
        $files = [];
        $items = scandir($dir);
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $files[] = $item;
        }

        return $files;
    }
}
