<?php

declare(strict_types=1);

namespace ClaudeAgents\Prompts;

use ClaudeAgents\Exceptions\ValidationException;

/**
 * Template for multi-turn chat conversations.
 */
class ChatTemplate
{
    /**
     * @var array<array{role: string, content: string}>
     */
    private array $messages = [];

    /**
     * @var array<string> Extracted variables
     */
    private array $variables = [];

    private function __construct()
    {
    }

    /**
     * Create a new chat template.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Add a system message.
     */
    public function system(string $content): self
    {
        $this->messages[] = ['role' => 'system', 'content' => $content];
        $this->extractVariables($content);

        return $this;
    }

    /**
     * Add a user message.
     */
    public function user(string $content): self
    {
        $this->messages[] = ['role' => 'user', 'content' => $content];
        $this->extractVariables($content);

        return $this;
    }

    /**
     * Add an assistant message.
     */
    public function assistant(string $content): self
    {
        $this->messages[] = ['role' => 'assistant', 'content' => $content];
        $this->extractVariables($content);

        return $this;
    }

    /**
     * Add a message.
     *
     * @param string $role The role (system, user, assistant)
     * @param string $content The content
     */
    public function message(string $role, string $content): self
    {
        $this->messages[] = ['role' => $role, 'content' => $content];
        $this->extractVariables($content);

        return $this;
    }

    /**
     * Format the template with values.
     *
     * @param array<string, mixed> $values Map of variable names to values
     * @return array<array<string, mixed>> Formatted messages
     */
    public function format(array $values): array
    {
        return array_map(function ($msg) use ($values) {
            $content = $msg['content'];

            foreach ($values as $key => $value) {
                $placeholder = '{' . $key . '}';
                $content = str_replace($placeholder, (string) $value, $content);
            }

            return ['role' => $msg['role'], 'content' => $content];
        }, $this->messages);
    }

    /**
     * Get all messages without formatting.
     *
     * @return array<array<string, string>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get required variables.
     *
     * @return array<string>
     */
    public function getVariables(): array
    {
        return array_unique($this->variables);
    }

    /**
     * Extract variables from content.
     */
    private function extractVariables(string $content): void
    {
        if (preg_match_all('/\{(\w+)\}/', $content, $matches)) {
            $this->variables = array_merge($this->variables, $matches[1]);
        }
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
        $required = $this->getVariables();

        foreach ($required as $var) {
            if (! isset($values[$var])) {
                $missing[] = $var;
            }
        }

        if (! empty($missing)) {
            $available = array_keys($values);

            throw new ValidationException(
                sprintf(
                    'ChatTemplate validation failed. Missing required variable(s): %s. ' .
                    'Available variables: %s. ' .
                    'Required variables are extracted from your chat messages. ' .
                    "Please provide: ['%s' => 'value']",
                    implode(', ', $missing),
                    empty($available) ? '(none)' : implode(', ', $available),
                    implode("' => 'value', '", $missing)
                )
            );
        }
    }
}
