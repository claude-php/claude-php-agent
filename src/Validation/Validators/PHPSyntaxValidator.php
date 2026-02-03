<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Validators;

use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use ClaudeAgents\Validation\ValidationResult;

/**
 * Validates PHP syntax using php -l (lint).
 *
 * This is a fast, lightweight validator that catches basic syntax errors.
 */
class PHPSyntaxValidator implements ValidatorInterface
{
    private string $phpBinary;
    private int $priority;

    /**
     * @param array<string, mixed> $options Configuration options:
     *   - php_binary: Path to PHP binary (default: 'php')
     *   - priority: Validator priority (default: 10)
     */
    public function __construct(array $options = [])
    {
        $this->phpBinary = $options['php_binary'] ?? 'php';
        $this->priority = $options['priority'] ?? 10;
    }

    public function validate(string $code, array $context = []): ValidationResult
    {
        // Create temporary file for syntax checking
        $tempFile = tempnam(sys_get_temp_dir(), 'php_syntax_');
        if ($tempFile === false) {
            return ValidationResult::failure(['Failed to create temporary file for syntax check']);
        }

        try {
            file_put_contents($tempFile, $code);

            // Run php -l on the file
            $command = escapeshellcmd($this->phpBinary) . ' -l ' . escapeshellarg($tempFile) . ' 2>&1';
            $output = [];
            $returnCode = 0;

            exec($command, $output, $returnCode);

            $outputText = implode("\n", $output);

            if ($returnCode === 0) {
                return ValidationResult::success(
                    metadata: [
                        'validator' => 'php_syntax',
                        'output' => $outputText,
                    ]
                );
            }

            // Parse error message
            $errors = $this->parseErrors($outputText, $tempFile);

            return ValidationResult::failure(
                errors: $errors,
                metadata: [
                    'validator' => 'php_syntax',
                    'output' => $outputText,
                    'return_code' => $returnCode,
                ]
            );
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function getName(): string
    {
        return 'php_syntax';
    }

    public function canHandle(string $code): bool
    {
        // Can handle anything that looks like PHP code
        return str_starts_with(trim($code), '<?php') || str_contains($code, '<?php');
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Parse error messages from php -l output.
     *
     * @return array<string>
     */
    private function parseErrors(string $output, string $tempFile): array
    {
        $errors = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Remove temp file path from error messages
            $line = str_replace($tempFile, '[code]', $line);

            // Skip "Errors parsing" summary line
            if (str_starts_with($line, 'Errors parsing')) {
                continue;
            }

            // Skip "No syntax errors" messages
            if (str_contains($line, 'No syntax errors')) {
                continue;
            }

            $errors[] = $line;
        }

        if (empty($errors)) {
            $errors[] = 'PHP syntax check failed';
        }

        return $errors;
    }
}
