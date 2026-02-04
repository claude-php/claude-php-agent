<?php

declare(strict_types=1);

namespace ClaudeAgents\Helpers;

use ClaudePhp\Exceptions\APIConnectionError;
use ClaudePhp\Exceptions\APIStatusError;
use ClaudePhp\Exceptions\AuthenticationError;
use ClaudePhp\Exceptions\RateLimitError;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Production-ready error handling for AI agents
 *
 * Provides comprehensive error handling with retry logic, exponential backoff,
 * and graceful degradation patterns.
 *
 * @deprecated Use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService instead
 * @see \ClaudeAgents\Services\ErrorHandling\ErrorHandlingService
 *
 * Migration Example:
 * ```php
 * // Old:
 * $handler = new ErrorHandler($logger, 3, 1000);
 * $result = $handler->executeWithRetry($fn, 'context');
 *
 * // New:
 * use ClaudeAgents\Services\ServiceManager;
 * use ClaudeAgents\Services\ServiceType;
 *
 * $handler = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
 * $result = $handler->executeWithRetry($fn, 'context');
 * // Plus new features:
 * $userMessage = $handler->convertToUserFriendly($exception);
 * $details = $handler->getErrorDetails($exception);
 * ```
 */
class ErrorHandler
{
    private LoggerInterface $logger;
    private int $maxRetries;
    private int $initialDelayMs;

    /**
     * @deprecated Use ErrorHandlingService via ServiceManager instead
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        int $maxRetries = 3,
        int $initialDelayMs = 1000
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->maxRetries = $maxRetries;
        $this->initialDelayMs = $initialDelayMs;
    }

    /**
     * Execute a function with comprehensive error handling and retry logic
     *
     * @param callable $fn Function to execute
     * @param string $context Context description for logging
     * @throws Exception if all retries fail or non-retryable error occurs
     * @return mixed Result from function
     */
    public function executeWithRetry(callable $fn, string $context = 'API call'): mixed
    {
        $attempt = 0;
        $delay = $this->initialDelayMs;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                return $fn();
            } catch (RateLimitError $e) {
                $this->logger->warning("{$context}: Rate limited (attempt {$attempt}/{$this->maxRetries})", [
                    'error' => $e->getMessage(),
                    'status_code' => $e->status_code,
                ]);

                if ($attempt >= $this->maxRetries) {
                    $this->logger->error("{$context}: Max retries exceeded for rate limit");

                    throw $e;
                }

                // Check for retry-after header
                $retryAfter = null;
                if (method_exists($e->response, 'getHeaderLine')) {
                    $retryAfter = $e->response->getHeaderLine('retry-after');
                }

                $waitTime = $retryAfter ? (int)$retryAfter : ($delay / 1000);

                $this->logger->info("Waiting {$waitTime}s before retry...");
                sleep($waitTime);
                $delay *= 2; // Exponential backoff

            } catch (APIConnectionError $e) {
                $this->logger->warning("{$context}: Connection error (attempt {$attempt}/{$this->maxRetries})", [
                    'error' => $e->getMessage(),
                ]);

                if ($attempt >= $this->maxRetries) {
                    $this->logger->error("{$context}: Max retries exceeded for connection error");

                    throw $e;
                }

                $waitTimeMs = $delay;
                $this->logger->info("Waiting {$waitTimeMs}ms before retry...");
                usleep($delay * 1000);
                $delay *= 2;

            } catch (AuthenticationError $e) {
                // Don't retry authentication errors
                $this->logger->error("{$context}: Authentication failed", [
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            } catch (APIStatusError $e) {
                $statusCode = $e->status_code;

                // Retry 5xx errors, fail on 4xx
                if ($statusCode >= 500) {
                    $this->logger->warning("{$context}: Server error {$statusCode} (attempt {$attempt}/{$this->maxRetries})", [
                        'error' => $e->getMessage(),
                        'status_code' => $statusCode,
                    ]);

                    if ($attempt >= $this->maxRetries) {
                        $this->logger->error("{$context}: Max retries exceeded for server error");

                        throw $e;
                    }

                    usleep($delay * 1000);
                    $delay *= 2;
                } else {
                    // 4xx errors - don't retry
                    $this->logger->error("{$context}: Client error {$statusCode}", [
                        'error' => $e->getMessage(),
                        'status_code' => $statusCode,
                    ]);

                    throw $e;
                }

            } catch (Exception $e) {
                $this->logger->error("{$context}: Unexpected error", [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);

                throw $e;
            }
        }

        throw new Exception("{$context}: Failed after {$this->maxRetries} attempts");
    }

    /**
     * Execute a tool safely with error handling
     *
     * @param callable $toolFn Tool execution function
     * @param string $toolName Tool name for logging
     * @param array $input Tool input parameters
     * @return array Result with keys: success, content, is_error
     */
    public function executeToolSafely(callable $toolFn, string $toolName, array $input): array
    {
        try {
            $result = $toolFn($input);

            $this->logger->debug("Tool {$toolName} executed successfully", [
                'tool' => $toolName,
                'result_length' => is_string($result) ? strlen($result) : null,
            ]);

            return [
                'success' => true,
                'content' => $result,
                'is_error' => false,
            ];
        } catch (Exception $e) {
            $this->logger->error("Tool {$toolName} failed", [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            return [
                'success' => false,
                'content' => 'Error: ' . $e->getMessage(),
                'is_error' => true,
            ];
        }
    }

    /**
     * Execute a tool with fallback value on error
     *
     * @param callable $toolFn Tool execution function
     * @param string $toolName Tool name for logging
     * @param array $input Tool input parameters
     * @param string $fallback Fallback value on error
     * @return string Result or fallback
     */
    public function executeToolWithFallback(
        callable $toolFn,
        string $toolName,
        array $input,
        string $fallback = 'Tool temporarily unavailable. Please try again later.'
    ): string {
        try {
            return $toolFn($input);
        } catch (Exception $e) {
            $this->logger->error("Tool {$toolName} failed, using fallback", [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'fallback' => $fallback,
            ]);

            return $fallback;
        }
    }

    /**
     * Validate tool input against a schema
     *
     * @param array $input Input to validate
     * @param array $schema Schema with 'required' and 'properties' keys
     * @return array Validation result with keys: valid, errors
     */
    public function validateToolInput(array $input, array $schema): array
    {
        $errors = [];

        // Check required fields
        $required = $schema['required'] ?? [];
        foreach ($required as $field) {
            if (! isset($input[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Check types if properties defined
        $properties = $schema['properties'] ?? [];
        foreach ($input as $key => $value) {
            if (isset($properties[$key])) {
                $expectedType = $properties[$key]['type'] ?? null;
                if ($expectedType) {
                    $actualType = gettype($value);

                    // Map PHP types to JSON schema types
                    $typeMap = [
                        'string' => 'string',
                        'integer' => 'integer',
                        'double' => 'number',
                        'boolean' => 'boolean',
                        'array' => 'array',
                        'object' => 'object',
                        'NULL' => 'null',
                    ];

                    $mappedType = $typeMap[$actualType] ?? $actualType;

                    if ($mappedType !== $expectedType && ! ($expectedType === 'number' && $mappedType === 'integer')) {
                        $errors[] = "Field '{$key}' expected type '{$expectedType}', got '{$mappedType}'";
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Create a rate limiter to prevent overwhelming the API
     *
     * @param int $minIntervalMs Minimum milliseconds between requests
     * @return callable Throttle function to call before each request
     */
    public static function createRateLimiter(int $minIntervalMs = 100): callable
    {
        $lastRequest = 0;

        return function () use (&$lastRequest, $minIntervalMs) {
            $now = (int)(microtime(true) * 1000);
            $elapsed = $now - $lastRequest;

            if ($elapsed < $minIntervalMs) {
                usleep(($minIntervalMs - $elapsed) * 1000);
            }

            $lastRequest = (int)(microtime(true) * 1000);
        };
    }
}
