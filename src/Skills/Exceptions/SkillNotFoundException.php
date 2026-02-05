<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills\Exceptions;

/**
 * Thrown when a requested skill cannot be found.
 */
class SkillNotFoundException extends SkillException
{
    public static function withName(string $name): self
    {
        return new self("Skill not found: '{$name}'");
    }

    public static function inPath(string $path): self
    {
        return new self("No skill found at path: '{$path}'");
    }

    public static function noSkillFile(string $path): self
    {
        return new self("No SKILL.md file found in: '{$path}'");
    }
}
