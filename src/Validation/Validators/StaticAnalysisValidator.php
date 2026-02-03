<?php

declare(strict_types=1);

namespace ClaudeAgents\Validation\Validators;

use ClaudeAgents\Validation\Contracts\ValidatorInterface;
use ClaudeAgents\Validation\ValidationResult;

/**
 * Validates code using static analysis tools (PHPStan, Psalm).
 *
 * Catches type errors, undefined variables, and other static analysis issues.
 */
class StaticAnalysisValidator implements ValidatorInterface
{
    private string $tool;
    private int $level;
    private int $priority;
    private ?string $configFile;

    public const TOOL_PHPSTAN = 'phpstan';
    public const TOOL_PSALM = 'psalm';

    /**
     * @param array<string, mixed> $options Configuration options:
     *   - tool: 'phpstan' or 'psalm' (default: 'phpstan')
     *   - level: Analysis level 0-9 for PHPStan, 1-8 for Psalm (default: 6)
     *   - priority: Validator priority (default: 20)
     *   - config_file: Path to config file (optional)
     */
    public function __construct(array $options = [])
    {
        $this->tool = $options['tool'] ?? self::TOOL_PHPSTAN;
        $this->level = $options['level'] ?? 6;
        $this->priority = $options['priority'] ?? 20;
        $this->configFile = $options['config_file'] ?? null;
    }

    public function validate(string $code, array $context = []): ValidationResult
    {
        // Create temporary file for analysis
        $tempFile = tempnam(sys_get_temp_dir(), 'static_analysis_');
        if ($tempFile === false) {
            return ValidationResult::failure(['Failed to create temporary file']);
        }

        // Add .php extension
        $phpFile = $tempFile . '.php';
        rename($tempFile, $phpFile);

        try {
            file_put_contents($phpFile, $code);

            $command = $this->buildCommand($phpFile);
            $output = [];
            $returnCode = 0;

            exec($command . ' 2>&1', $output, $returnCode);

            $outputText = implode("\n", $output);

            // For PHPStan, return code 0 = success
            // For Psalm, return code 0 = success
            if ($returnCode === 0) {
                return ValidationResult::success(
                    metadata: [
                        'validator' => 'static_analysis',
                        'tool' => $this->tool,
                        'level' => $this->level,
                        'output' => $outputText,
                    ]
                );
            }

            // Parse errors from output
            $errors = $this->parseErrors($outputText, $phpFile);
            $warnings = $this->parseWarnings($outputText);

            return ValidationResult::failure(
                errors: $errors,
                warnings: $warnings,
                metadata: [
                    'validator' => 'static_analysis',
                    'tool' => $this->tool,
                    'level' => $this->level,
                    'output' => $outputText,
                    'return_code' => $returnCode,
                ]
            );
        } finally {
            // Clean up temp file
            if (file_exists($phpFile)) {
                unlink($phpFile);
            }
        }
    }

    public function getName(): string
    {
        return "static_analysis_{$this->tool}";
    }

    public function canHandle(string $code): bool
    {
        // Can handle PHP code
        return str_starts_with(trim($code), '<?php') || str_contains($code, '<?php');
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Build the command for static analysis.
     */
    private function buildCommand(string $file): string
    {
        $command = match ($this->tool) {
            self::TOOL_PHPSTAN => $this->buildPHPStanCommand($file),
            self::TOOL_PSALM => $this->buildPsalmCommand($file),
            default => throw new \InvalidArgumentException("Unknown tool: {$this->tool}"),
        };

        return $command;
    }

    /**
     * Build PHPStan command.
     */
    private function buildPHPStanCommand(string $file): string
    {
        $command = 'vendor/bin/phpstan analyse';
        $command .= ' --level=' . $this->level;
        $command .= ' --no-progress';
        $command .= ' --error-format=raw';

        if ($this->configFile !== null) {
            $command .= ' --configuration=' . escapeshellarg($this->configFile);
        }

        $command .= ' ' . escapeshellarg($file);

        return $command;
    }

    /**
     * Build Psalm command.
     */
    private function buildPsalmCommand(string $file): string
    {
        $command = 'vendor/bin/psalm';
        $command .= ' --show-info=false';

        if ($this->configFile !== null) {
            $command .= ' --config=' . escapeshellarg($this->configFile);
        }

        $command .= ' ' . escapeshellarg($file);

        return $command;
    }

    /**
     * Parse error messages from output.
     *
     * @return array<string>
     */
    private function parseErrors(string $output, string $tempFile): array
    {
        if (empty(trim($output))) {
            return ['Static analysis failed with no output'];
        }

        $errors = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Remove temp file path from error messages
            $line = str_replace($tempFile, '[code]', $line);

            // Skip summary lines
            if (str_starts_with($line, '[OK]') || 
                str_starts_with($line, 'No errors') ||
                str_contains($line, 'found 0 errors')) {
                continue;
            }

            // Skip informational lines
            if (str_starts_with($line, 'Note:') || 
                str_starts_with($line, 'Psalm')) {
                continue;
            }

            $errors[] = $line;
        }

        if (empty($errors)) {
            $errors[] = 'Static analysis failed';
        }

        return $errors;
    }

    /**
     * Parse warning messages from output.
     *
     * @return array<string>
     */
    private function parseWarnings(string $output): array
    {
        $warnings = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim($line);

            // Look for warning indicators
            if (str_contains(strtolower($line), 'warning') ||
                str_contains(strtolower($line), 'deprecated')) {
                $warnings[] = $line;
            }
        }

        return $warnings;
    }

    /**
     * Create a PHPStan validator.
     */
    public static function phpstan(int $level = 6, array $options = []): self
    {
        return new self(array_merge(['tool' => self::TOOL_PHPSTAN, 'level' => $level], $options));
    }

    /**
     * Create a Psalm validator.
     */
    public static function psalm(array $options = []): self
    {
        return new self(array_merge(['tool' => self::TOOL_PSALM], $options));
    }
}
