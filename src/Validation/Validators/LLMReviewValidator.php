<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Validators;

use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use ClaudeAgents\Validation\ValidationResult;
use ClaudePhp\ClaudePhp;

/**
 * Validates code using Claude LLM for qualitative review.
 *
 * Checks for best practices, security issues, logic errors, and code quality.
 */
class LLMReviewValidator implements ValidatorInterface
{
    private ClaudePhp $client;
    private string $model;
    private int $maxTokens;
    private int $priority;
    private float $temperature;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration options:
     *   - model: Claude model to use (default: 'claude-sonnet-4-5')
     *   - max_tokens: Max tokens for response (default: 2048)
     *   - priority: Validator priority (default: 100)
     *   - temperature: Sampling temperature (default: 0.0)
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 2048;
        $this->priority = $options['priority'] ?? 100;
        $this->temperature = $options['temperature'] ?? 0.0;
    }

    public function validate(string $code, array $context = []): ValidationResult
    {
        $prompt = $this->buildValidationPrompt($code, $context);

        try {
            $response = $this->client->messages()->create([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            // Extract text content
            $content = $this->extractContent($response);

            // Parse the review
            return $this->parseReview($content);
        } catch (\Throwable $e) {
            return ValidationResult::failure(
                errors: ["LLM review failed: {$e->getMessage()}"],
                metadata: ['validator' => 'llm_review', 'error' => $e->getMessage()]
            );
        }
    }

    public function getName(): string
    {
        return 'llm_review';
    }

    public function canHandle(string $code): bool
    {
        // Can review any code
        return true;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Build the validation prompt.
     *
     * @param array<string, mixed> $context
     */
    private function buildValidationPrompt(string $code, array $context): string
    {
        $contextInfo = '';
        if (! empty($context)) {
            $contextInfo = "\n\nContext:\n" . json_encode($context, JSON_PRETTY_PRINT);
        }

        return <<<PROMPT
Review the following PHP code for issues and provide feedback.

Check for:
1. Syntax errors or bugs
2. Security vulnerabilities
3. Best practices violations
4. Logic errors
5. Code quality issues
6. Performance concerns

Provide your review in the following JSON format:
{
    "valid": true/false,
    "errors": ["list of critical issues that prevent the code from working"],
    "warnings": ["list of non-critical issues or improvements"],
    "summary": "brief overall assessment"
}

Code to review:
```php
{$code}
```
{$contextInfo}

Respond ONLY with the JSON review, no additional text.
PROMPT;
    }

    /**
     * Extract text content from response.
     */
    private function extractContent(object $response): string
    {
        if (! isset($response->content) || ! is_array($response->content)) {
            return '';
        }

        $texts = [];
        foreach ($response->content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Parse the LLM review response.
     */
    private function parseReview(string $content): ValidationResult
    {
        // Try to extract JSON from the response
        $content = trim($content);

        // Remove markdown code blocks if present
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*\n/', '', $content);
            $content = preg_replace('/\n```\s*$/', '', $content);
            $content = trim($content);
        }

        try {
            $review = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ValidationResult::failure(
                errors: ['Failed to parse LLM review response'],
                metadata: [
                    'validator' => 'llm_review',
                    'raw_response' => $content,
                    'parse_error' => $e->getMessage(),
                ]
            );
        }

        $isValid = $review['valid'] ?? true;
        $errors = $review['errors'] ?? [];
        $warnings = $review['warnings'] ?? [];
        $summary = $review['summary'] ?? '';

        return new ValidationResult(
            isValid: $isValid,
            errors: is_array($errors) ? $errors : [$errors],
            warnings: is_array($warnings) ? $warnings : [$warnings],
            metadata: [
                'validator' => 'llm_review',
                'summary' => $summary,
                'model' => $this->model,
            ]
        );
    }
}
