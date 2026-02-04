<?php

declare(strict_types=1);

namespace ClaudeAgents\Templates;

use ClaudeAgents\Templates\Exceptions\TemplateNotFoundException;
use ClaudeAgents\Templates\Exceptions\TemplateValidationException;

/**
 * Loads templates from various sources (JSON files, PHP files).
 */
class TemplateLoader
{
    private array $cache = [];
    private bool $cacheEnabled = true;

    public function __construct(
        private string $templatesPath
    ) {
    }

    /**
     * Load all templates from the templates directory.
     *
     * @return Template[]
     */
    public function loadAll(): array
    {
        if (!is_dir($this->templatesPath)) {
            throw TemplateNotFoundException::inPath($this->templatesPath);
        }

        $templates = [];

        // Load JSON templates
        $jsonFiles = glob($this->templatesPath . '/*.json');
        foreach ($jsonFiles as $file) {
            try {
                $templates[] = $this->loadFromJson($file);
            } catch (TemplateValidationException $e) {
                // Log and skip invalid templates
                error_log("Failed to load template {$file}: " . $e->getMessage());
            }
        }

        // Load PHP templates
        $phpFiles = glob($this->templatesPath . '/*.php');
        foreach ($phpFiles as $file) {
            try {
                $templates[] = $this->loadFromPhp($file);
            } catch (TemplateValidationException $e) {
                error_log("Failed to load template {$file}: " . $e->getMessage());
            }
        }

        return $templates;
    }

    /**
     * Load a template from a JSON file.
     */
    public function loadFromJson(string $path): Template
    {
        if ($this->cacheEnabled && isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        if (!file_exists($path)) {
            throw TemplateNotFoundException::inPath($path);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new TemplateValidationException("Failed to read file: {$path}");
        }

        $template = Template::fromJson($content);

        if ($this->cacheEnabled) {
            $this->cache[$path] = $template;
        }

        return $template;
    }

    /**
     * Load a template from a PHP file that returns an array.
     */
    public function loadFromPhp(string $path): Template
    {
        if ($this->cacheEnabled && isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        if (!file_exists($path)) {
            throw TemplateNotFoundException::inPath($path);
        }

        $data = require $path;

        if (!is_array($data)) {
            throw new TemplateValidationException("PHP template must return an array: {$path}");
        }

        $template = Template::fromArray($data);

        if ($this->cacheEnabled) {
            $this->cache[$path] = $template;
        }

        return $template;
    }

    /**
     * Find templates by name pattern.
     *
     * @return Template[]
     */
    public function findByName(string $pattern): array
    {
        $templates = $this->loadAll();
        $pattern = strtolower($pattern);

        return array_filter($templates, function (Template $template) use ($pattern) {
            return str_contains(strtolower($template->getName()), $pattern);
        });
    }

    /**
     * Find a template by exact ID.
     */
    public function findById(string $id): ?Template
    {
        $templates = $this->loadAll();

        foreach ($templates as $template) {
            if ($template->getId() === $id) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Get templates by category.
     *
     * @return Template[]
     */
    public function findByCategory(string $category): array
    {
        $templates = $this->loadAll();

        return array_filter($templates, function (Template $template) use ($category) {
            return $template->getCategory() === $category;
        });
    }

    /**
     * Get templates by tags (templates with ANY of the specified tags).
     *
     * @return Template[]
     */
    public function findByTags(array $tags): array
    {
        $templates = $this->loadAll();

        return array_filter($templates, function (Template $template) use ($tags) {
            return $template->matchesTags($tags);
        });
    }

    /**
     * Enable or disable caching.
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        return $this;
    }

    /**
     * Clear the template cache.
     */
    public function clearCache(): self
    {
        $this->cache = [];
        return $this;
    }

    /**
     * Get the templates path.
     */
    public function getTemplatesPath(): string
    {
        return $this->templatesPath;
    }

    /**
     * Set a new templates path and clear cache.
     */
    public function setTemplatesPath(string $path): self
    {
        $this->templatesPath = $path;
        $this->clearCache();
        return $this;
    }

    /**
     * Count total templates.
     */
    public function count(): int
    {
        return count($this->loadAll());
    }

    /**
     * Get all unique tags across all templates.
     */
    public function getAllTags(): array
    {
        $templates = $this->loadAll();
        $tags = [];

        foreach ($templates as $template) {
            $tags = array_merge($tags, $template->getTags());
        }

        return array_values(array_unique($tags));
    }

    /**
     * Get all unique categories.
     */
    public function getAllCategories(): array
    {
        $templates = $this->loadAll();
        $categories = [];

        foreach ($templates as $template) {
            $categories[] = $template->getCategory();
        }

        return array_values(array_unique($categories));
    }
}
