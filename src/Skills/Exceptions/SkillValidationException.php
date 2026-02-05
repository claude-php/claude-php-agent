<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills\Exceptions;

/**
 * Thrown when skill validation fails.
 */
class SkillValidationException extends SkillException
{
    /**
     * @var string[]
     */
    private array $errors;

    /**
     * @param string $message
     * @param string[] $errors
     */
    public function __construct(string $message = '', array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    public static function missingField(string $field): self
    {
        return new self("Required field '{$field}' is missing from SKILL.md frontmatter");
    }

    public static function invalidField(string $field, string $reason): self
    {
        return new self("Invalid field '{$field}': {$reason}");
    }

    public static function withErrors(array $errors): self
    {
        return new self(
            'Skill validation failed: ' . implode('; ', $errors),
            $errors
        );
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
