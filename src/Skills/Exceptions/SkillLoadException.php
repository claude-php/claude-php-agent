<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills\Exceptions;

/**
 * Thrown when a skill cannot be loaded from the filesystem.
 */
class SkillLoadException extends SkillException
{
    public static function readError(string $path): self
    {
        return new self("Failed to read SKILL.md from: '{$path}'");
    }

    public static function parseError(string $path, string $reason): self
    {
        return new self("Failed to parse SKILL.md at '{$path}': {$reason}");
    }

    public static function directoryNotFound(string $path): self
    {
        return new self("Skills directory not found: '{$path}'");
    }
}
