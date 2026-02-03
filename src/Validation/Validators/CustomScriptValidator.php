<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Validators;

use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use ClaudeAgents\Validation\ValidationResult;

/**
 * Validates code by executing a custom script or command.
 *
 * Can run unit tests, integration tests, or any custom validation script.
 */
class CustomScriptValidator implements ValidatorInterface
{
    private string $command;
    private ?string $workingDirectory;
    private int $priority;
    private int $timeout;

    /**
     * @param string $command Command to execute for validation
     * @param array<string, mixed> $options Configuration options:
     *   - working_directory: Directory to run command in (default: null)
     *   - priority: Validator priority (default: 30)
     *   - timeout: Command timeout in seconds (default: 60)
     */
    public function __construct(string $command, array $options = [])
    {
        $this->command = $command;
        $this->workingDirectory = $options['working_directory'] ?? null;
        $this->priority = $options['priority'] ?? 30;
        $this->timeout = $options['timeout'] ?? 60;
    }

    public function validate(string $code, array $context = []): ValidationResult
    {
        // Save code to temporary file if needed
        $tempFile = null;
        $needsTempFile = str_contains($this->command, '{file}');

        if ($needsTempFile) {
            $tempFile = tempnam(sys_get_temp_dir(), 'custom_validation_');
            if ($tempFile === false) {
                return ValidationResult::failure(['Failed to create temporary file']);
            }
            file_put_contents($tempFile, $code);
        }

        try {
            // Replace placeholders in command
            $command = $this->command;
            if ($tempFile !== null) {
                $command = str_replace('{file}', escapeshellarg($tempFile), $command);
            }

            // Execute command
            $output = [];
            $returnCode = 0;

            $oldDir = null;
            if ($this->workingDirectory !== null && is_dir($this->workingDirectory)) {
                $oldDir = getcwd();
                chdir($this->workingDirectory);
            }

            try {
                exec($command . ' 2>&1', $output, $returnCode);
            } finally {
                if ($oldDir !== null) {
                    chdir($oldDir);
                }
            }

            $outputText = implode("\n", $output);

            // Success if return code is 0
            if ($returnCode === 0) {
                return ValidationResult::success(
                    metadata: [
                        'validator' => 'custom_script',
                        'command' => $this->command,
                        'output' => $outputText,
                    ]
                );
            }

            // Parse errors from output
            $errors = $this->parseErrors($outputText);

            return ValidationResult::failure(
                errors: $errors,
                metadata: [
                    'validator' => 'custom_script',
                    'command' => $this->command,
                    'output' => $outputText,
                    'return_code' => $returnCode,
                ]
            );
        } finally {
            // Clean up temp file
            if ($tempFile !== null && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function getName(): string
    {
        return 'custom_script';
    }

    public function canHandle(string $code): bool
    {
        // Can handle anything - depends on the custom script
        return true;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Parse error messages from command output.
     *
     * @return array<string>
     */
    private function parseErrors(string $output): array
    {
        if (empty(trim($output))) {
            return ['Validation script failed with no output'];
        }

        // Split output into lines and filter out empty lines
        $lines = array_filter(
            array_map('trim', explode("\n", $output)),
            fn ($line) => ! empty($line)
        );

        if (empty($lines)) {
            return ['Validation script failed'];
        }

        return array_values($lines);
    }

    /**
     * Create a validator for PHPUnit tests.
     */
    public static function phpunit(string $testFile, array $options = []): self
    {
        $command = 'vendor/bin/phpunit ' . escapeshellarg($testFile);
        return new self($command, array_merge(['priority' => 30], $options));
    }

    /**
     * Create a validator for Pest tests.
     */
    public static function pest(string $testFile, array $options = []): self
    {
        $command = 'vendor/bin/pest ' . escapeshellarg($testFile);
        return new self($command, array_merge(['priority' => 30], $options));
    }

    /**
     * Create a validator for a custom PHP script.
     */
    public static function phpScript(string $scriptPath, array $options = []): self
    {
        $command = 'php ' . escapeshellarg($scriptPath) . ' {file}';
        return new self($command, array_merge(['priority' => 30], $options));
    }
}
