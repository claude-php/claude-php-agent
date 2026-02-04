<?php

declare(strict_types=1);

namespace ClaudeAgents\Templates;

use ClaudeAgents\Templates\Exceptions\TemplateValidationException;

/**
 * Represents an agent template with metadata and configuration.
 */
class Template
{
    private array $errors = [];

    public function __construct(
        private string $id,
        private string $name,
        private string $description,
        private string $category,
        private array $tags = [],
        private string $version = '1.0.0',
        private string $author = 'Anonymous',
        private ?string $lastTestedVersion = null,
        private array $requirements = [],
        private array $metadata = [],
        private array $config = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? self::generateId(),
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            category: $data['category'] ?? 'custom',
            tags: $data['tags'] ?? [],
            version: $data['version'] ?? '1.0.0',
            author: $data['author'] ?? 'Anonymous',
            lastTestedVersion: $data['last_tested_version'] ?? null,
            requirements: $data['requirements'] ?? [],
            metadata: $data['metadata'] ?? [],
            config: $data['config'] ?? []
        );
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new TemplateValidationException('Invalid JSON: ' . json_last_error_msg());
        }
        return self::fromArray($data);
    }

    private static function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public function validate(): bool
    {
        $this->errors = [];

        // Required fields
        if (empty($this->name)) {
            $this->errors[] = 'Name is required';
        }

        if (empty($this->description)) {
            $this->errors[] = 'Description is required';
        }

        if (empty($this->category)) {
            $this->errors[] = 'Category is required';
        }

        // Validate category
        $validCategories = ['agents', 'chatbots', 'rag', 'workflows', 'specialized', 'production', 'custom'];
        if (!in_array($this->category, $validCategories, true)) {
            $this->errors[] = "Invalid category. Must be one of: " . implode(', ', $validCategories);
        }

        // Validate config has agent_type
        if (empty($this->config['agent_type'])) {
            $this->errors[] = 'Config must include agent_type';
        }

        // Validate version format
        if (!preg_match('/^\d+\.\d+\.\d+$/', $this->version)) {
            $this->errors[] = 'Version must follow semantic versioning (e.g., 1.0.0)';
        }

        return empty($this->errors);
    }

    public function isValid(): bool
    {
        return $this->validate();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $this->tags,
            'version' => $this->version,
            'author' => $this->author,
            'last_tested_version' => $this->lastTestedVersion,
            'requirements' => $this->requirements,
            'metadata' => $this->metadata,
            'config' => $this->config,
        ];
    }

    public function toJson(int $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function toPhp(): string
    {
        $export = var_export($this->toArray(), true);
        return "<?php\n\nreturn {$export};\n";
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getLastTestedVersion(): ?string
    {
        return $this->lastTestedVersion;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }
        return $this->metadata[$key] ?? null;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getAgentType(): ?string
    {
        return $this->config['agent_type'] ?? null;
    }

    // Setters
    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function addTag(string $tag): self
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    public function removeTag(string $tag): self
    {
        $this->tags = array_values(array_filter($this->tags, fn($t) => $t !== $tag));
        return $this;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    public function matchesQuery(?string $query): bool
    {
        if ($query === null) {
            return true;
        }

        $query = strtolower($query);
        return str_contains(strtolower($this->name), $query)
            || str_contains(strtolower($this->description), $query);
    }

    public function matchesTags(?array $tags): bool
    {
        if ($tags === null || empty($tags)) {
            return true;
        }

        foreach ($tags as $tag) {
            if (in_array($tag, $this->tags, true)) {
                return true;
            }
        }
        return false;
    }

    public function matchesCategory(?string $category): bool
    {
        if ($category === null) {
            return true;
        }

        return $this->category === $category;
    }
}
