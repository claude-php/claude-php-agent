<?php

declare(strict_types=1);

namespace ClaudeAgents\Parsers;

use ClaudeAgents\Contracts\ParserInterface;
use ClaudeAgents\Exceptions\ParseException;

/**
 * Parses JSON from LLM responses.
 *
 * Supports JSON extraction from:
 * - Plain JSON objects/arrays
 * - JSON within markdown code blocks (```json ... ```)
 * - JSON embedded in text
 *
 * Optional schema validation for structured output validation.
 */
class JsonParser implements ParserInterface
{
    /**
     * @var array<string, mixed>|null JSON schema for validation
     */
    private ?array $schema = null;

    /**
     * @var bool Whether to return objects instead of arrays
     */
    private bool $asObject = false;

    /**
     * Set JSON schema for validation.
     *
     * @param array<string, mixed> $schema JSON Schema
     * @return self
     */
    public function withSchema(array $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * Return parsed JSON as objects instead of arrays.
     *
     * @return self
     */
    public function asObject(): self
    {
        $this->asObject = true;

        return $this;
    }

    /**
     * Parse JSON from response text.
     *
     * @param string $text The response text
     * @throws ParseException If JSON parsing fails
     * @return array<string, mixed>|object Parsed JSON as array or object
     */
    public function parse(string $text): array|object
    {
        // Try to extract JSON from text
        $json = $this->extractJson($text);

        if ($json === null) {
            throw new ParseException('No JSON found in response', $text, 'json');
        }

        $decoded = json_decode($json, ! $this->asObject);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new ParseException('Failed to parse JSON: ' . json_last_error_msg(), $json, 'json');
        }

        // Validate against schema if provided
        if ($this->schema !== null) {
            $this->validateAgainstSchema($decoded);
        }

        return $decoded;
    }

    /**
     * Try to extract JSON from text.
     *
     * @return string|null The extracted JSON string, or null if not found
     */
    private function extractJson(string $text): ?string
    {
        // Try to find JSON block markers (```json ... ```)
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $text, $matches)) {
            return $matches[1];
        }

        // Try to find raw JSON (starts with { or [)
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate parsed data against JSON schema.
     *
     * @param mixed $data The parsed data
     * @throws ValidationException If validation fails
     */
    private function validateAgainstSchema(mixed $data): void
    {
        if ($this->schema === null) {
            return;
        }

        // Basic schema validation (type checking)
        $schemaType = $this->schema['type'] ?? null;

        if ($schemaType === 'object' && ! is_object($data) && ! is_array($data)) {
            throw new \ClaudeAgents\Exceptions\ValidationException('Expected object, got ' . gettype($data));
        }

        if ($schemaType === 'array' && ! is_array($data)) {
            throw new \ClaudeAgents\Exceptions\ValidationException('Expected array, got ' . gettype($data));
        }

        // Validate required properties for objects
        if ($schemaType === 'object' && isset($this->schema['required'])) {
            $dataArray = is_object($data) ? get_object_vars($data) : $data;
            foreach ($this->schema['required'] as $required) {
                if (! array_key_exists($required, $dataArray)) {
                    throw new \ClaudeAgents\Exceptions\ValidationException("Missing required property: {$required}", $required);
                }
            }
        }
    }

    /**
     * Get format instructions for the LLM.
     *
     * @return string
     */
    public function getFormatInstructions(): string
    {
        $instructions = 'Return your response as valid JSON.';

        if ($this->schema !== null) {
            $schemaJson = json_encode($this->schema, JSON_PRETTY_PRINT);
            $instructions .= "\n\nFollow this JSON schema:\n```json\n{$schemaJson}\n```";
        } else {
            $instructions .= ' You can wrap it in a ```json code block if needed.';
        }

        return $instructions;
    }

    /**
     * Get parser type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'json';
    }

    /**
     * Extract all JSON blocks from text.
     *
     * @param string $text The text containing JSON
     * @return array<array<string, mixed>> Array of parsed JSON objects
     */
    public function extractAll(string $text): array
    {
        $results = [];

        // Find all JSON code blocks
        if (preg_match_all('/```json\s*([\s\S]*?)\s*```/', $text, $matches)) {
            foreach ($matches[1] as $json) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $results[] = $decoded;
                }
            }
        }

        // Find all raw JSON objects
        if (preg_match_all('/\{[\s\S]*?\}/', $text, $matches)) {
            foreach ($matches[0] as $json) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $results[] = $decoded;
                }
            }
        }

        return $results;
    }
}
