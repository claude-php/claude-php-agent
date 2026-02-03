<?php

declare(strict_types=1);

namespace ClaudeAgents\Generation;

use ClaudeAgents\Validation\ValidationResult;

/**
 * Result of code generation with validation.
 */
class ComponentResult
{
    /**
     * @param string $code Generated code
     * @param ValidationResult $validation Validation result
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly string $code,
        private readonly ValidationResult $validation,
        private readonly array $metadata = [],
    ) {
    }

    /**
     * Get the generated code.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Get the validation result.
     */
    public function getValidation(): ValidationResult
    {
        return $this->validation;
    }

    /**
     * Get metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if code was validated.
     */
    public function wasValidated(): bool
    {
        return true; // All ComponentResults include validation
    }

    /**
     * Check if the code is valid.
     */
    public function isValid(): bool
    {
        return $this->validation->isValid();
    }

    /**
     * Save code to a file.
     *
     * @param string $path File path to save to
     * @return bool True if saved successfully
     */
    public function saveToFile(string $path): bool
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                return false;
            }
        }

        $result = file_put_contents($path, $this->code);
        return $result !== false;
    }

    /**
     * Get code with line numbers.
     */
    public function getCodeWithLineNumbers(): string
    {
        $lines = explode("\n", $this->code);
        $numbered = [];
        $width = strlen((string) count($lines));

        foreach ($lines as $i => $line) {
            $lineNum = str_pad((string) ($i + 1), $width, ' ', STR_PAD_LEFT);
            $numbered[] = "{$lineNum} | {$line}";
        }

        return implode("\n", $numbered);
    }

    /**
     * Get a summary of the result.
     */
    public function getSummary(): string
    {
        $summary = [];
        $summary[] = "Code: " . strlen($this->code) . " bytes, " . substr_count($this->code, "\n") . " lines";
        $summary[] = "Validation: " . $this->validation->getSummary();

        if ($this->validation->hasErrors()) {
            $summary[] = "Errors: " . implode("; ", array_slice($this->validation->getErrors(), 0, 3));
        }

        if ($this->validation->hasWarnings()) {
            $summary[] = "Warnings: " . $this->validation->getWarningCount();
        }

        return implode("\n", $summary);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'code_length' => strlen($this->code),
            'line_count' => substr_count($this->code, "\n") + 1,
            'validation' => $this->validation->toArray(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create a result from generated code without validation.
     */
    public static function unvalidated(string $code, array $metadata = []): self
    {
        return new self(
            code: $code,
            validation: ValidationResult::success(
                warnings: ['Code was not validated'],
            ),
            metadata: $metadata,
        );
    }
}
