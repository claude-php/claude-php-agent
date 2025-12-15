<?php

declare(strict_types=1);

namespace ClaudeAgents\Prompts;

use ClaudeAgents\Exceptions\ValidationException;

/**
 * Template for prompt generation with variable substitution.
 */
class PromptTemplate
{
    /**
     * @var array<string> Variable names in the template
     */
    private array $variables = [];

    /**
     * @param string $template The template string with {variable} placeholders
     */
    public function __construct(
        private readonly string $template,
    ) {
        $this->extractVariables();
    }

    /**
     * Create a new template.
     */
    public static function create(string $template): self
    {
        return new self($template);
    }

    /**
     * Format the template with values.
     *
     * @param array<string, mixed> $values Map of variable names to values
     * @return string Formatted prompt
     */
    public function format(array $values): string
    {
        $prompt = $this->template;

        foreach ($values as $key => $value) {
            $placeholder = '{' . $key . '}';
            $prompt = str_replace($placeholder, (string) $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Get required variables.
     *
     * @return array<string>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Validate that all variables are provided.
     *
     * @param array<string, mixed> $values
     * @throws ValidationException If required variables missing
     */
    public function validate(array $values): void
    {
        $missing = [];
        foreach ($this->variables as $var) {
            if (! isset($values[$var])) {
                $missing[] = $var;
            }
        }

        if (! empty($missing)) {
            $available = array_keys($values);

            throw new ValidationException(
                sprintf(
                    'Missing required variable(s): %s. Available variables: %s. ' .
                    "Please provide all required variables in the format: ['%s' => 'value']",
                    implode(', ', $missing),
                    empty($available) ? '(none)' : implode(', ', $available),
                    implode("' => 'value', '", $missing)
                )
            );
        }
    }

    /**
     * Extract variable names from template.
     */
    private function extractVariables(): void
    {
        if (preg_match_all('/\{(\w+)\}/', $this->template, $matches)) {
            $this->variables = array_unique($matches[1]);
        }
    }

    /**
     * Get the template string.
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Create a partial template with some variables filled in.
     *
     * @param array<string, mixed> $values Partial values
     * @return PromptTemplate New template with substitutions
     */
    public function partial(array $values): self
    {
        $newTemplate = $this->format($values);

        return new self($newTemplate);
    }
}
