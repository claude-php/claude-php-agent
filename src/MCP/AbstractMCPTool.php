<?php

declare(strict_types=1);

namespace ClaudeAgents\MCP;

use ClaudeAgents\MCP\Contracts\MCPToolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for MCP tools.
 *
 * Provides common functionality and enforces consistent patterns.
 */
abstract class AbstractMCPTool implements MCPToolInterface
{
    protected LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Validate input parameters against schema.
     *
     * @param array<string, mixed> $params
     * @throws \InvalidArgumentException
     */
    protected function validateParams(array $params): void
    {
        $schema = $this->getInputSchema();

        if (!isset($schema['properties'])) {
            return;
        }

        $required = $schema['required'] ?? [];

        // Check required parameters
        foreach ($required as $field) {
            if (!array_key_exists($field, $params)) {
                throw new \InvalidArgumentException("Missing required parameter: {$field}");
            }
        }

        // Validate parameter types
        foreach ($params as $key => $value) {
            if (!isset($schema['properties'][$key])) {
                $this->logger->warning("Unknown parameter: {$key}");
                continue;
            }

            $expectedType = $schema['properties'][$key]['type'] ?? null;
            if ($expectedType && !$this->validateType($value, $expectedType)) {
                throw new \InvalidArgumentException(
                    "Parameter '{$key}' must be of type {$expectedType}"
                );
            }
        }
    }

    /**
     * Validate value type.
     *
     * @param mixed $value
     */
    private function validateType($value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            'null' => is_null($value),
            default => true,
        };
    }

    /**
     * Create success response.
     *
     * @param mixed $data
     * @return array<string, mixed>
     */
    protected function success($data): array
    {
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Create error response.
     *
     * @return array<string, mixed>
     */
    protected function error(string $message, ?string $code = null): array
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($code !== null) {
            $response['code'] = $code;
        }

        return $response;
    }

    /**
     * Default category is 'general'.
     */
    public function getCategory(): string
    {
        return 'general';
    }
}
