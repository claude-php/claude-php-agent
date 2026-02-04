<?php

declare(strict_types=1);

namespace ClaudeAgents\Services\ErrorHandling;

use ClaudeAgents\Services\ServiceInterface;
use ClaudePhp\Exceptions\APIConnectionError;
use ClaudePhp\Exceptions\APIStatusError;
use ClaudePhp\Exceptions\APITimeoutError;
use ClaudePhp\Exceptions\AuthenticationError;
use ClaudePhp\Exceptions\BadRequestError;
use ClaudePhp\Exceptions\InternalServerError;
use ClaudePhp\Exceptions\OverloadedError;
use ClaudePhp\Exceptions\PermissionDeniedError;
use ClaudePhp\Exceptions\RateLimitError;
use ClaudePhp\Exceptions\UnprocessableEntityError;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Error Handling Service inspired by Langflow's user-friendly error conversion.
 *
 * Converts technical API errors into user-friendly messages while preserving
 * the existing retry logic and error handling capabilities.
 */
class ErrorHandlingService implements ServiceInterface
{
    private bool $ready = false;
    private LoggerInterface $logger;
    private int $maxRetries;
    private int $initialDelayMs;
    private array $errorPatterns;

    /**
     * Default error patterns mapping exceptions to user-friendly messages.
     */
    private const DEFAULT_PATTERNS = [
        'rate_limit' => [
            'exception_class' => RateLimitError::class,
            'user_message' => 'Rate limit exceeded. Please wait before retrying.',
            'suggested_action' => 'Wait 60 seconds before making another request.',
        ],
        'authentication' => [
            'exception_class' => AuthenticationError::class,
            'user_message' => 'Authentication failed. Please check your API key.',
            'suggested_action' => 'Verify your ANTHROPIC_API_KEY is valid.',
        ],
        'permission' => [
            'exception_class' => PermissionDeniedError::class,
            'user_message' => 'Permission denied. Check your API key permissions.',
            'suggested_action' => 'Ensure your API key has the required permissions.',
        ],
        'timeout' => [
            'exception_class' => APITimeoutError::class,
            'user_message' => 'Request timed out. Please try again.',
            'suggested_action' => 'Check your network connection or increase timeout.',
        ],
        'connection' => [
            'exception_class' => APIConnectionError::class,
            'user_message' => 'Connection error. Check your network.',
            'suggested_action' => 'Verify your internet connection and try again.',
        ],
        'overloaded' => [
            'exception_class' => OverloadedError::class,
            'user_message' => 'Service temporarily overloaded. Please retry.',
            'suggested_action' => 'Wait a few moments and try again.',
        ],
        'bad_request' => [
            'exception_class' => BadRequestError::class,
            'user_message' => 'Invalid request. Please check your parameters.',
            'suggested_action' => 'Review your request parameters and format.',
        ],
        'server_error' => [
            'exception_class' => InternalServerError::class,
            'user_message' => 'Server error occurred. Please try again later.',
            'suggested_action' => 'This is a temporary server issue. Retry in a few minutes.',
        ],
        'validation' => [
            'exception_class' => UnprocessableEntityError::class,
            'user_message' => 'Request validation failed. Check your input.',
            'suggested_action' => 'Verify all required fields are provided correctly.',
        ],
    ];

    /**
     * @param LoggerInterface|null $logger PSR-3 logger for error tracking
     * @param int $maxRetries Maximum retry attempts
     * @param int $initialDelayMs Initial delay in milliseconds for retry backoff
     * @param array<string, array<string, string>> $customPatterns Custom error patterns
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        int $maxRetries = 3,
        int $initialDelayMs = 1000,
        array $customPatterns = []
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->maxRetries = $maxRetries;
        $this->initialDelayMs = $initialDelayMs;
        
        // Merge custom patterns with defaults (custom patterns override defaults)
        $this->errorPatterns = array_merge(self::DEFAULT_PATTERNS, $customPatterns);
    }

    public function getName(): string
    {
        return 'error_handling';
    }

    public function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        $this->logger->debug('Initializing error handling service', [
            'max_retries' => $this->maxRetries,
            'initial_delay_ms' => $this->initialDelayMs,
            'custom_patterns' => count(array_diff_key($this->errorPatterns, self::DEFAULT_PATTERNS)),
        ]);

        $this->ready = true;
    }

    public function teardown(): void
    {
        $this->logger->debug('Tearing down error handling service');
        $this->ready = false;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'ready' => $this->ready,
            'methods' => [
                'convertToUserFriendly' => [
                    'parameters' => ['error' => 'Throwable'],
                    'return' => 'string',
                    'description' => 'Convert a technical error to a user-friendly message',
                ],
                'getErrorDetails' => [
                    'parameters' => ['error' => 'Throwable'],
                    'return' => 'array',
                    'description' => 'Get detailed error information for logging',
                ],
                'executeWithRetry' => [
                    'parameters' => ['fn' => 'callable', 'context' => 'string'],
                    'return' => 'mixed',
                    'description' => 'Execute a function with retry logic',
                ],
                'executeToolSafely' => [
                    'parameters' => ['toolFn' => 'callable', 'toolName' => 'string', 'input' => 'array'],
                    'return' => 'array',
                    'description' => 'Execute a tool with error handling',
                ],
            ],
        ];
    }

    /**
     * Convert a technical exception into a user-friendly message.
     *
     * Uses pattern matching to identify the error type and return
     * an appropriate message that end users can understand.
     *
     * @param Throwable $error The exception to convert
     * @return string User-friendly error message
     */
    public function convertToUserFriendly(Throwable $error): string
    {
        // Try to match exception by class
        foreach ($this->errorPatterns as $pattern) {
            if (isset($pattern['exception_class']) && $error instanceof $pattern['exception_class']) {
                return $pattern['user_message'];
            }
        }

        // Try to match by message pattern
        $errorMessage = strtolower($error->getMessage());
        foreach ($this->errorPatterns as $patternName => $pattern) {
            if (isset($pattern['message_pattern'])) {
                if (preg_match($pattern['message_pattern'], $errorMessage)) {
                    return $pattern['user_message'];
                }
            } elseif (str_contains($errorMessage, str_replace('_', ' ', $patternName))) {
                return $pattern['user_message'];
            }
        }

        // Fallback for unknown errors
        return 'An unexpected error occurred. Please try again later.';
    }

    /**
     * Get detailed error information for logging and debugging.
     *
     * Extracts technical details while keeping them separate from
     * user-facing messages.
     *
     * @param Throwable $error The exception to analyze
     * @return array<string, mixed> Detailed error information
     */
    public function getErrorDetails(Throwable $error): array
    {
        $details = [
            'exception_class' => get_class($error),
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'user_friendly_message' => $this->convertToUserFriendly($error),
        ];

        // Add API-specific details if available
        if ($error instanceof APIStatusError) {
            $details['status_code'] = $error->status_code;
            $details['request_id'] = $error->request_id;
            
            if ($error->body !== null) {
                $details['response_body'] = is_string($error->body) 
                    ? substr($error->body, 0, 500) 
                    : $error->body;
            }
        }

        // Find matching pattern for suggested action
        foreach ($this->errorPatterns as $pattern) {
            if (isset($pattern['exception_class']) && $error instanceof $pattern['exception_class']) {
                if (isset($pattern['suggested_action'])) {
                    $details['suggested_action'] = $pattern['suggested_action'];
                }
                break;
            }
        }

        return $details;
    }

    /**
     * Execute a function with comprehensive error handling and retry logic.
     *
     * Preserved from the original ErrorHandler implementation.
     *
     * @param callable $fn Function to execute
     * @param string $context Context description for logging
     * @return mixed Result from function
     * @throws Exception if all retries fail or non-retryable error occurs
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

                $waitTime = $retryAfter ? (int) $retryAfter : ($delay / 1000);

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
     * Execute a tool safely with error handling.
     *
     * Preserved from the original ErrorHandler implementation.
     *
     * @param callable $toolFn Tool execution function
     * @param string $toolName Tool name for logging
     * @param array<string, mixed> $input Tool input parameters
     * @return array<string, mixed> Result with keys: success, content, is_error
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
     * Execute a tool with fallback value on error.
     *
     * Preserved from the original ErrorHandler implementation.
     *
     * @param callable $toolFn Tool execution function
     * @param string $toolName Tool name for logging
     * @param array<string, mixed> $input Tool input parameters
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
     * Get all configured error patterns.
     *
     * @return array<string, array<string, string>>
     */
    public function getErrorPatterns(): array
    {
        return $this->errorPatterns;
    }

    /**
     * Add or update an error pattern.
     *
     * @param string $name Pattern name
     * @param array<string, string> $pattern Pattern configuration
     * @return self
     */
    public function addErrorPattern(string $name, array $pattern): self
    {
        $this->errorPatterns[$name] = $pattern;
        
        return $this;
    }

    /**
     * Create a rate limiter to prevent overwhelming the API.
     *
     * @param int $minIntervalMs Minimum milliseconds between requests
     * @return callable Throttle function to call before each request
     */
    public static function createRateLimiter(int $minIntervalMs = 100): callable
    {
        $lastRequest = 0;

        return function () use (&$lastRequest, $minIntervalMs) {
            $now = (int) (microtime(true) * 1000);
            $elapsed = $now - $lastRequest;

            if ($elapsed < $minIntervalMs) {
                usleep(($minIntervalMs - $elapsed) * 1000);
            }

            $lastRequest = (int) (microtime(true) * 1000);
        };
    }
}
