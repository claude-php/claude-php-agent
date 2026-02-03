<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Exceptions;

use Throwable;

/**
 * Exception thrown when component validation fails.
 *
 * Contains details about the failed validation including the class name,
 * original code, and the underlying exception that caused the failure.
 */
class ComponentValidationException extends ValidationException
{
    private ?string $className;
    private string $originalCode;
    private ?Throwable $originalException;

    /**
     * @param string $message Error message
     * @param string|null $className Class name that failed validation
     * @param string $originalCode The code that failed validation
     * @param Throwable|null $originalException The original exception
     * @param int $code Error code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        ?string $className = null,
        string $originalCode = '',
        ?Throwable $originalException = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        // ValidationException expects: message, errors array, code, previous
        parent::__construct($message, [], $code, $previous);
        $this->className = $className;
        $this->originalCode = $originalCode;
        $this->originalException = $originalException;
    }

    /**
     * Get the class name that failed validation.
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * Get the original code that failed validation.
     */
    public function getOriginalCode(): string
    {
        return $this->originalCode;
    }

    /**
     * Get the original exception that caused the validation failure.
     */
    public function getOriginalException(): ?Throwable
    {
        return $this->originalException;
    }

    /**
     * Get a detailed error message with context.
     */
    public function getDetailedMessage(): string
    {
        $message = $this->getMessage();

        if ($this->className !== null) {
            $message .= " (class: {$this->className})";
        }

        if ($this->originalException !== null) {
            $message .= "\nCaused by: " . get_class($this->originalException) . ': ' . $this->originalException->getMessage();
        }

        return $message;
    }

    /**
     * Get code snippet around the error.
     *
     * @param int $contextLines Number of lines to show before and after
     */
    public function getCodeSnippet(int $contextLines = 3): string
    {
        if (empty($this->originalCode)) {
            return '';
        }

        $lines = explode("\n", $this->originalCode);
        $totalLines = count($lines);

        // If we have the original exception with line info, highlight that line
        if ($this->originalException !== null && method_exists($this->originalException, 'getLine')) {
            $errorLine = $this->originalException->getLine();
            $start = max(0, $errorLine - $contextLines - 1);
            $end = min($totalLines, $errorLine + $contextLines);

            $snippet = [];
            for ($i = $start; $i < $end; $i++) {
                $lineNum = $i + 1;
                $prefix = $lineNum === $errorLine ? '>>>' : '   ';
                $snippet[] = sprintf('%s %3d | %s', $prefix, $lineNum, $lines[$i]);
            }

            return implode("\n", $snippet);
        }

        // Otherwise, just show the first few lines
        $snippet = [];
        $linesToShow = min($contextLines * 2, $totalLines);
        for ($i = 0; $i < $linesToShow; $i++) {
            $snippet[] = sprintf('    %3d | %s', $i + 1, $lines[$i]);
        }

        return implode("\n", $snippet);
    }
}
