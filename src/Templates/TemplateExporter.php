<?php

declare(strict_types=1);

namespace ClaudeAgents\Templates;

use ClaudeAgents\Agent;
use ClaudeAgents\Templates\Exceptions\TemplateValidationException;
use ReflectionClass;
use ReflectionException;

/**
 * Exports agent configurations as templates.
 */
class TemplateExporter
{
    /**
     * Export an agent as a template.
     *
     * @param object $agent The agent to export
     * @param array $metadata Template metadata (name, description, category, tags, etc.)
     * @return Template The generated template
     * @throws TemplateValidationException
     */
    public function export(object $agent, array $metadata = []): Template
    {
        // Extract agent type
        $agentType = $this->getAgentType($agent);

        // Extract configuration from agent
        $config = $this->extractConfig($agent);
        $config['agent_type'] = $agentType;

        // Build template data
        $templateData = array_merge([
            'name' => $metadata['name'] ?? "Exported {$agentType}",
            'description' => $metadata['description'] ?? "Template exported from {$agentType}",
            'category' => $metadata['category'] ?? 'custom',
            'tags' => $metadata['tags'] ?? ['exported', strtolower($agentType)],
            'version' => $metadata['version'] ?? '1.0.0',
            'author' => $metadata['author'] ?? 'Anonymous',
            'last_tested_version' => $metadata['last_tested_version'] ?? null,
            'requirements' => $metadata['requirements'] ?? $this->getDefaultRequirements(),
            'metadata' => array_merge(
                $this->extractMetadata($agent),
                $metadata['metadata'] ?? []
            ),
            'config' => $config,
        ], $metadata);

        return Template::fromArray($templateData);
    }

    /**
     * Get the agent type name.
     */
    private function getAgentType(object $agent): string
    {
        $reflection = new ReflectionClass($agent);
        return $reflection->getShortName();
    }

    /**
     * Extract configuration from an agent.
     */
    private function extractConfig(object $agent): array
    {
        $config = [];

        try {
            $reflection = new ReflectionClass($agent);

            // Try to get public properties
            $properties = $reflection->getProperties();

            foreach ($properties as $property) {
                if ($property->isPublic()) {
                    $name = $property->getName();
                    $value = $property->getValue($agent);

                    // Only include serializable values
                    if ($this->isSerializable($value)) {
                        $config[$name] = $value;
                    }
                }
            }

            // Try to extract common config via methods if available
            if (method_exists($agent, 'getConfig')) {
                $agentConfig = $agent->getConfig();
                if (is_array($agentConfig)) {
                    $config = array_merge($config, $agentConfig);
                }
            }
        } catch (ReflectionException $e) {
            // If reflection fails, return minimal config
            error_log("Failed to reflect agent: " . $e->getMessage());
        }

        return $config;
    }

    /**
     * Check if a value can be serialized to JSON.
     */
    private function isSerializable(mixed $value): bool
    {
        if (is_null($value) || is_scalar($value)) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!$this->isSerializable($item)) {
                    return false;
                }
            }
            return true;
        }

        // Skip objects, resources, etc.
        return false;
    }

    /**
     * Extract metadata from an agent.
     */
    private function extractMetadata(object $agent): array
    {
        $metadata = [];

        $reflection = new ReflectionClass($agent);

        // Extract from docblock if available
        $docComment = $reflection->getDocComment();
        if ($docComment !== false) {
            // Try to extract description from docblock
            if (preg_match('/@description\s+(.+)$/m', $docComment, $matches)) {
                $metadata['description_from_doc'] = trim($matches[1]);
            }
        }

        // Add class name and namespace
        $metadata['class'] = $reflection->getName();
        $metadata['namespace'] = $reflection->getNamespaceName();

        return $metadata;
    }

    /**
     * Get default requirements.
     */
    private function getDefaultRequirements(): array
    {
        return [
            'php' => '>=8.1',
            'extensions' => ['json', 'mbstring'],
            'packages' => ['claude-php/agent'],
        ];
    }

    /**
     * Save a template to a file.
     *
     * @param Template $template The template to save
     * @param string $path The file path (with .json or .php extension)
     * @return bool True on success
     * @throws TemplateValidationException
     */
    public function save(Template $template, string $path): bool
    {
        // Validate template before saving
        if (!$template->isValid()) {
            throw TemplateValidationException::withErrors($template->getErrors());
        }

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new TemplateValidationException("Failed to create directory: {$dir}");
            }
        }

        // Determine format from extension
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $content = match ($extension) {
            'json' => $template->toJson(),
            'php' => $template->toPhp(),
            default => throw new TemplateValidationException("Unsupported file extension: {$extension}. Use .json or .php"),
        };

        $result = file_put_contents($path, $content);

        if ($result === false) {
            throw new TemplateValidationException("Failed to write template to: {$path}");
        }

        return true;
    }

    /**
     * Export multiple agents as templates.
     *
     * @param array $agents Array of agents to export
     * @param array $metadataList Array of metadata arrays (indexed by same keys)
     * @return Template[]
     */
    public function exportMultiple(array $agents, array $metadataList = []): array
    {
        $templates = [];

        foreach ($agents as $key => $agent) {
            $metadata = $metadataList[$key] ?? [];
            $templates[$key] = $this->export($agent, $metadata);
        }

        return $templates;
    }
}
