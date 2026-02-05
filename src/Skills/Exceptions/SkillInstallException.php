<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills\Exceptions;

/**
 * Thrown when skill installation or removal fails.
 */
class SkillInstallException extends SkillException
{
    public static function alreadyInstalled(string $name): self
    {
        return new self("Skill '{$name}' is already installed");
    }

    public static function installFailed(string $name, string $reason): self
    {
        return new self("Failed to install skill '{$name}': {$reason}");
    }

    public static function removeFailed(string $name, string $reason): self
    {
        return new self("Failed to remove skill '{$name}': {$reason}");
    }
}
