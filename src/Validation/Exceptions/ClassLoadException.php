<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Exceptions;

use Throwable;

/**
 * Exception thrown when class loading fails.
 *
 * This exception is thrown when attempting to dynamically load a class
 * from code using either the temp file or eval strategy.
 */
class ClassLoadException extends ValidationException
{
    private ?string $className;
    private string $loadStrategy;
    private ?string $tempFilePath;

    /**
     * @param string $message Error message
     * @param string|null $className Class name that failed to load
     * @param string $loadStrategy Strategy used (temp_file or eval)
     * @param string|null $tempFilePath Path to temp file (if applicable)
     * @param int $code Error code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        ?string $className = null,
        string $loadStrategy = 'unknown',
        ?string $tempFilePath = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        // ValidationException expects: message, errors array, code, previous
        parent::__construct($message, [], $code, $previous);
        $this->className = $className;
        $this->loadStrategy = $loadStrategy;
        $this->tempFilePath = $tempFilePath;
    }

    /**
     * Get the class name that failed to load.
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * Get the load strategy that was used.
     */
    public function getLoadStrategy(): string
    {
        return $this->loadStrategy;
    }

    /**
     * Get the temp file path (if applicable).
     */
    public function getTempFilePath(): ?string
    {
        return $this->tempFilePath;
    }

    /**
     * Get a detailed error message with context.
     */
    public function getDetailedMessage(): string
    {
        $message = $this->getMessage();
        $message .= " (strategy: {$this->loadStrategy})";

        if ($this->className !== null) {
            $message .= " (class: {$this->className})";
        }

        if ($this->tempFilePath !== null) {
            $message .= " (temp file: {$this->tempFilePath})";
        }

        return $message;
    }
}
