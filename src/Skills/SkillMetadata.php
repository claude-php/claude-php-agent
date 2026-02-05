<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

/**
 * Value object representing skill metadata from YAML frontmatter.
 *
 * Follows the Agent Skills specification (agentskills.io/specification).
 * Contains both required fields (name, description) and optional fields
 * (license, version, metadata, dependencies, compatibility).
 */
class SkillMetadata
{
    /**
     * @param string $name Human-friendly skill name (max 64 chars)
     * @param string $description Description used for skill matching (max 200 chars)
     * @param string|null $license License identifier or license file name
     * @param string|null $version Skill version for tracking
     * @param array $metadata Additional metadata (author, tags, etc.)
     * @param array $dependencies Required software packages
     * @param array $compatibility Compatibility constraints (product, system packages, network)
     * @param bool $disableModelInvocation If true, skill can only be invoked manually
     * @param bool $mode If true, skill is a mode command modifying agent behavior
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $license = null,
        public readonly ?string $version = null,
        public readonly array $metadata = [],
        public readonly array $dependencies = [],
        public readonly array $compatibility = [],
        public readonly bool $disableModelInvocation = false,
        public readonly bool $mode = false,
    ) {
    }

    /**
     * Create from parsed YAML frontmatter array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            license: $data['license'] ?? null,
            version: $data['version'] ?? null,
            metadata: $data['metadata'] ?? [],
            dependencies: $data['dependencies'] ?? [],
            compatibility: $data['compatibility'] ?? [],
            disableModelInvocation: (bool) ($data['disable-model-invocation'] ?? false),
            mode: (bool) ($data['mode'] ?? false),
        );
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'description' => $this->description,
        ];

        if ($this->license !== null) {
            $data['license'] = $this->license;
        }
        if ($this->version !== null) {
            $data['version'] = $this->version;
        }
        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }
        if (!empty($this->dependencies)) {
            $data['dependencies'] = $this->dependencies;
        }
        if (!empty($this->compatibility)) {
            $data['compatibility'] = $this->compatibility;
        }
        if ($this->disableModelInvocation) {
            $data['disable-model-invocation'] = true;
        }
        if ($this->mode) {
            $data['mode'] = true;
        }

        return $data;
    }

    /**
     * Get author from metadata.
     */
    public function getAuthor(): ?string
    {
        return $this->metadata['author'] ?? null;
    }

    /**
     * Get tags from metadata.
     */
    public function getTags(): array
    {
        $tags = $this->metadata['tags'] ?? [];
        if (is_string($tags)) {
            return [$tags];
        }

        return is_array($tags) ? $tags : [];
    }
}
