<?php

declare(strict_types=1);

namespace ClaudeAgents\Templates\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a template cannot be found.
 */
class TemplateNotFoundException extends RuntimeException
{
    public static function byId(string $id): self
    {
        return new self("Template with ID '{$id}' not found.");
    }

    public static function byName(string $name): self
    {
        return new self("Template with name '{$name}' not found.");
    }

    public static function inPath(string $path): self
    {
        return new self("No templates found in path: {$path}");
    }
}
