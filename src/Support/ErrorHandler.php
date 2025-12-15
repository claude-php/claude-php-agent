<?php

declare(strict_types=1);

namespace ClaudeAgents\Support;

/**
 * Error and exception handling utilities.
 */
class ErrorHandler
{
    /**
     * Wrap an exception with additional context.
     *
     * @param \Throwable $exception Original exception
     * @param string $message Additional context message
     * @param array<string, mixed> $context Additional context data
     * @return \RuntimeException Wrapped exception
     */
    public static function wrap(\Throwable $exception, string $message, array $context = []): \RuntimeException
    {
        $fullMessage = $message . ': ' . $exception->getMessage();

        if (! empty($context)) {
            $fullMessage .= ' | Context: ' . json_encode($context);
        }

        return new \RuntimeException($fullMessage, $exception->getCode(), $exception);
    }

    /**
     * Extract and format stack trace from exception.
     *
     * @param \Throwable $exception Exception to extract from
     * @param int $limit Maximum trace depth (0 = unlimited)
     * @return array<array{file: string, line: int, function: string, class?: string}> Formatted trace
     */
    public static function extractTrace(\Throwable $exception, int $limit = 0): array
    {
        $trace = $exception->getTrace();

        if ($limit > 0) {
            $trace = array_slice($trace, 0, $limit);
        }

        return array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }, $trace);
    }

    /**
     * Format exception as string with trace.
     *
     * @param \Throwable $exception Exception to format
     * @param bool $includeTrace Include stack trace
     * @return string Formatted exception
     */
    public static function format(\Throwable $exception, bool $includeTrace = true): string
    {
        $output = get_class($exception) . ': ' . $exception->getMessage() . "\n";
        $output .= 'in ' . $exception->getFile() . ':' . $exception->getLine() . "\n";

        if ($includeTrace) {
            $output .= "\nStack trace:\n";
            foreach ($exception->getTrace() as $i => $frame) {
                $file = $frame['file'] ?? 'unknown';
                $line = $frame['line'] ?? 0;
                $function = $frame['function'] ?? 'unknown';
                $class = isset($frame['class']) ? $frame['class'] . '::' : '';

                $output .= "#{$i} {$file}({$line}): {$class}{$function}()\n";
            }
        }

        return $output;
    }

    /**
     * Remove sensitive data from exception message.
     *
     * @param \Throwable $exception Exception to sanitize
     * @param array<string> $patterns Regex patterns to remove
     * @return string Sanitized message
     */
    public static function sanitize(\Throwable $exception, array $patterns = []): string
    {
        $message = $exception->getMessage();

        // Default patterns for common sensitive data
        $defaultPatterns = [
            '/api[_-]?key[\'"]?\s*[:=]\s*[\'"]?[\w-]+/i',
            '/password[\'"]?\s*[:=]\s*[\'"]?[\w-]+/i',
            '/token[\'"]?\s*[:=]\s*[\'"]?[\w-]+/i',
            '/secret[\'"]?\s*[:=]\s*[\'"]?[\w-]+/i',
        ];

        $allPatterns = array_merge($defaultPatterns, $patterns);

        foreach ($allPatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }

        return $message;
    }

    /**
     * Format exception for logging.
     *
     * @param \Throwable $exception Exception to format
     * @param bool $sanitize Remove sensitive data
     * @return array<string, mixed> Log-friendly array
     */
    public static function formatForLog(\Throwable $exception, bool $sanitize = true): array
    {
        $message = $sanitize ? self::sanitize($exception) : $exception->getMessage();

        return [
            'type' => get_class($exception),
            'message' => $message,
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => self::extractTrace($exception, 5),
        ];
    }

    /**
     * Get the root cause of a chained exception.
     *
     * @param \Throwable $exception Exception chain
     * @return \Throwable Root exception
     */
    public static function getRootCause(\Throwable $exception): \Throwable
    {
        while ($previous = $exception->getPrevious()) {
            $exception = $previous;
        }

        return $exception;
    }

    /**
     * Get all exceptions in the chain.
     *
     * @param \Throwable $exception Top exception
     * @return array<\Throwable> All exceptions in chain
     */
    public static function getExceptionChain(\Throwable $exception): array
    {
        $chain = [$exception];

        while ($previous = $exception->getPrevious()) {
            $chain[] = $previous;
            $exception = $previous;
        }

        return $chain;
    }

    /**
     * Convert exception to HTTP status code.
     *
     * @param \Throwable $exception Exception to convert
     * @return int HTTP status code
     */
    public static function toHttpStatus(\Throwable $exception): int
    {
        $code = $exception->getCode();

        // If code is already a valid HTTP status, use it
        if ($code >= 100 && $code < 600) {
            return $code;
        }

        // Map exception types to HTTP status codes
        return match (true) {
            $exception instanceof \InvalidArgumentException => 400,
            $exception instanceof \UnexpectedValueException => 422,
            $exception instanceof \RuntimeException => 500,
            $exception instanceof \LogicException => 500,
            default => 500,
        };
    }

    /**
     * Create a user-friendly error message.
     *
     * @param \Throwable $exception Exception to process
     * @param bool $includeDetails Include technical details
     * @return string User-friendly message
     */
    public static function toUserMessage(\Throwable $exception, bool $includeDetails = false): string
    {
        $message = 'An error occurred while processing your request.';

        if ($includeDetails) {
            $message .= ' Details: ' . $exception->getMessage();
        }

        return $message;
    }

    /**
     * Execute a callable with error handling.
     *
     * @template T
     * @param callable(): T $callable Function to execute
     * @param callable(\Throwable): T|null $errorHandler Error handler
     * @throws \Throwable If error handler not provided or returns null
     * @return T Result
     */
    public static function handle(callable $callable, ?callable $errorHandler = null): mixed
    {
        try {
            return $callable();
        } catch (\Throwable $e) {
            if ($errorHandler !== null) {
                $result = $errorHandler($e);
                if ($result !== null) {
                    return $result;
                }
            }

            throw $e;
        }
    }

    /**
     * Execute callable and suppress all exceptions.
     *
     * @template T
     * @param callable(): T $callable Function to execute
     * @param T $default Default value on error
     * @return T Result or default
     */
    public static function suppress(callable $callable, mixed $default = null): mixed
    {
        try {
            return $callable();
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Convert PHP errors to exceptions within a callable.
     *
     * @template T
     * @param callable(): T $callable Function to execute
     * @throws \ErrorException If PHP error occurs
     * @return T Result
     */
    public static function convertErrorsToExceptions(callable $callable): mixed
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return $callable();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Check if exception is of specific type(s).
     *
     * @param \Throwable $exception Exception to check
     * @param string|array<string> $types Exception class name(s)
     * @return bool True if matches
     */
    public static function isType(\Throwable $exception, string|array $types): bool
    {
        $types = ArrayHelper::wrap($types);

        foreach ($types as $type) {
            if ($exception instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect multiple exceptions into one.
     *
     * @param array<\Throwable> $exceptions Exceptions to collect
     * @param string $message Aggregate message
     * @return \RuntimeException Aggregate exception
     */
    public static function aggregate(array $exceptions, string $message = 'Multiple errors occurred'): \RuntimeException
    {
        if (empty($exceptions)) {
            return new \RuntimeException($message);
        }

        $messages = array_map(fn ($e) => $e->getMessage(), $exceptions);
        $fullMessage = $message . ': ' . implode('; ', $messages);

        return new \RuntimeException($fullMessage, 0, $exceptions[0]);
    }
}
