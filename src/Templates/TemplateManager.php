<?php

declare(strict_types=1);

namespace ClaudeAgents\Templates;

use ClaudeAgents\Agent;
use ClaudeAgents\Templates\Exceptions\TemplateNotFoundException;
use ClaudeAgents\Templates\Exceptions\TemplateValidationException;

/**
 * Central manager for template operations (search, load, instantiate, export).
 */
class TemplateManager
{
    private static ?self $instance = null;
    private TemplateLoader $loader;
    private TemplateInstantiator $instantiator;
    private TemplateExporter $exporter;

    public function __construct(?string $templatesPath = null)
    {
        // Default to templates directory in project root
        if ($templatesPath === null) {
            $templatesPath = $this->getDefaultTemplatesPath();
        }

        $this->loader = new TemplateLoader($templatesPath);
        $this->instantiator = new TemplateInstantiator();
        $this->exporter = new TemplateExporter();
    }

    /**
     * Get singleton instance.
     */
    public static function getInstance(?string $templatesPath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($templatesPath);
        }

        return self::$instance;
    }

    /**
     * Search templates by query, tags, category, and fields.
     *
     * @param string|null $query Search term for name/description
     * @param array|null $tags Filter by tags (templates with ANY of these tags)
     * @param string|null $category Filter by category
     * @param array|null $fields Fields to include in results (null = all fields)
     * @return array Array of templates or filtered template data
     */
    public function search(
        ?string $query = null,
        ?array $tags = null,
        ?string $category = null,
        ?array $fields = null
    ): array {
        $templates = $this->loader->loadAll();

        // Apply filters
        $filtered = array_filter($templates, function (Template $template) use ($query, $tags, $category) {
            if (!$template->matchesQuery($query)) {
                return false;
            }

            if (!$template->matchesTags($tags)) {
                return false;
            }

            if (!$template->matchesCategory($category)) {
                return false;
            }

            return true;
        });

        // Apply field filtering if specified
        if ($fields !== null) {
            return array_map(function (Template $template) use ($fields) {
                return $this->filterFields($template->toArray(), $fields);
            }, array_values($filtered));
        }

        return array_values($filtered);
    }

    /**
     * Filter template data to only include specified fields.
     */
    private function filterFields(array $data, array $fields): array
    {
        $filtered = [];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $filtered[$field] = $data[$field];
            }
        }

        return $filtered;
    }

    /**
     * Get a template by ID.
     *
     * @throws TemplateNotFoundException
     */
    public function getById(string $id): Template
    {
        $template = $this->loader->findById($id);

        if ($template === null) {
            throw TemplateNotFoundException::byId($id);
        }

        return $template;
    }

    /**
     * Get a template by name (finds first match).
     *
     * @throws TemplateNotFoundException
     */
    public function getByName(string $name): Template
    {
        $templates = $this->loader->findByName($name);

        if (empty($templates)) {
            throw TemplateNotFoundException::byName($name);
        }

        return reset($templates);
    }

    /**
     * Get all templates in a category.
     *
     * @return Template[]
     */
    public function getByCategory(string $category): array
    {
        return $this->loader->findByCategory($category);
    }

    /**
     * Get all templates with specified tags.
     *
     * @return Template[]
     */
    public function getByTags(array $tags): array
    {
        return $this->loader->findByTags($tags);
    }

    /**
     * Get all available categories.
     */
    public function getCategories(): array
    {
        return $this->loader->getAllCategories();
    }

    /**
     * Get all available tags.
     */
    public function getAllTags(): array
    {
        return $this->loader->getAllTags();
    }

    /**
     * Get total count of templates.
     */
    public function count(): int
    {
        return $this->loader->count();
    }

    /**
     * Load all templates.
     *
     * @return Template[]
     */
    public function loadAll(): array
    {
        return $this->loader->loadAll();
    }

    /**
     * Instantiate an agent from a template (by ID or name).
     *
     * @param string $identifier Template ID or name
     * @param array $config Configuration overrides (api_key, model, tools, etc.)
     * @return object The instantiated agent
     */
    public function instantiate(string $identifier, array $config = []): object
    {
        // Try to find by ID first, then by name
        $template = $this->loader->findById($identifier);

        if ($template === null) {
            $templates = $this->loader->findByName($identifier);
            if (empty($templates)) {
                throw TemplateNotFoundException::byName($identifier);
            }
            $template = reset($templates);
        }

        return $this->instantiator->instantiate($template, $config);
    }

    /**
     * Instantiate an agent from a template object.
     */
    public function instantiateFromTemplate(Template $template, array $config = []): object
    {
        return $this->instantiator->instantiate($template, $config);
    }

    /**
     * Export an agent as a template.
     */
    public function exportAgent(object $agent, array $metadata = []): Template
    {
        return $this->exporter->export($agent, $metadata);
    }

    /**
     * Save a template to a file.
     *
     * @param Template $template The template to save
     * @param string $path File path (relative to templates directory or absolute)
     * @return bool True on success
     */
    public function saveTemplate(Template $template, string $path): bool
    {
        // If path is relative, make it relative to templates directory
        if (!str_starts_with($path, '/')) {
            $path = $this->loader->getTemplatesPath() . '/' . $path;
        }

        return $this->exporter->save($template, $path);
    }

    /**
     * Get the template loader.
     */
    public function getLoader(): TemplateLoader
    {
        return $this->loader;
    }

    /**
     * Get the template instantiator.
     */
    public function getInstantiator(): TemplateInstantiator
    {
        return $this->instantiator;
    }

    /**
     * Get the template exporter.
     */
    public function getExporter(): TemplateExporter
    {
        return $this->exporter;
    }

    /**
     * Get default templates path.
     */
    private function getDefaultTemplatesPath(): string
    {
        // Try to find templates directory relative to vendor
        $vendorDir = dirname(__DIR__, 2);

        // Check if we're in vendor directory
        if (basename($vendorDir) === 'vendor') {
            $projectRoot = dirname($vendorDir);
            $templatesPath = $projectRoot . '/templates';
        } else {
            // We're in development, use relative to src
            $templatesPath = dirname(__DIR__, 2) . '/templates';
        }

        return $templatesPath;
    }

    /**
     * Set custom templates path.
     */
    public function setTemplatesPath(string $path): self
    {
        $this->loader->setTemplatesPath($path);
        return $this;
    }

    /**
     * Get current templates path.
     */
    public function getTemplatesPath(): string
    {
        return $this->loader->getTemplatesPath();
    }

    /**
     * Clear template cache.
     */
    public function clearCache(): self
    {
        $this->loader->clearCache();
        return $this;
    }

    /**
     * Static helper for search (uses singleton).
     */
    public static function searchTemplates(
        ?string $query = null,
        ?array $tags = null,
        ?string $category = null,
        ?array $fields = null
    ): array {
        return self::getInstance()->search($query, $tags, $category, $fields);
    }
}
