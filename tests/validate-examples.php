#!/usr/bin/env php
<?php

/**
 * Example Validation Test
 *
 * Tests all example files for:
 * - Valid PHP syntax
 * - Required classes and methods exist
 * - Proper error handling
 * - Documentation completeness
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

class ExampleValidator
{
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    public function validateAll(string $examplesDir): void
    {
        echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                     Examples Validation Test                               ║\n";
        echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

        $examples = glob($examplesDir . '/*.php');
        sort($examples);

        foreach ($examples as $example) {
            $this->validateExample($example);
        }

        $this->printSummary();
    }

    private function validateExample(string $file): void
    {
        $basename = basename($file);
        echo "Testing: {$basename}\n";

        $checks = [
            'syntax' => $this->checkSyntax($file),
            'autoload' => $this->checkAutoload($file),
            'error_handling' => $this->checkErrorHandling($file),
            'documentation' => $this->checkDocumentation($file),
            'api_key_handling' => $this->checkApiKeyHandling($file),
        ];

        $allPassed = ! in_array(false, $checks, true);

        if ($allPassed) {
            echo "  ✅ PASSED\n";
            $this->passed++;
        } else {
            echo "  ❌ FAILED\n";
            $this->failed++;
            foreach ($checks as $check => $result) {
                if (! $result) {
                    echo "    - Failed: {$check}\n";
                }
            }
        }

        $this->results[$basename] = [
            'passed' => $allPassed,
            'checks' => $checks,
        ];

        echo "\n";
    }

    private function checkSyntax(string $file): bool
    {
        $output = [];
        $returnCode = 0;
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }

    private function checkAutoload(string $file): bool
    {
        $content = file_get_contents($file);

        return str_contains($content, "require_once __DIR__ . '/../vendor/autoload.php'") ||
               str_contains($content, 'require_once __DIR__ . "/../vendor/autoload.php"');
    }

    private function checkErrorHandling(string $file): bool
    {
        $content = file_get_contents($file);

        // Check for try-catch or error checking patterns
        $hasTryCatch = str_contains($content, 'try') && str_contains($content, 'catch');
        $hasErrorChecking = str_contains($content, 'isSuccess()') ||
                           str_contains($content, 'isError()') ||
                           str_contains($content, 'getError()');

        return $hasTryCatch || $hasErrorChecking;
    }

    private function checkDocumentation(string $file): bool
    {
        $content = file_get_contents($file);

        // Check for header comments
        $hasDocBlock = str_contains($content, '/**') || str_contains($content, '/*');

        // Check for descriptive comments
        $hasDescription = preg_match('/\* (Demonstrates?|Example|Shows)/i', $content);

        return $hasDocBlock && $hasDescription;
    }

    private function checkApiKeyHandling(string $file): bool
    {
        $content = file_get_contents($file);

        // Check for API key handling
        return str_contains($content, 'ANTHROPIC_API_KEY') ||
               str_contains($content, 'apiKey:');
    }

    private function printSummary(): void
    {
        echo str_repeat('═', 80) . "\n";
        echo "Summary\n";
        echo str_repeat('═', 80) . "\n\n";

        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        echo "Total Examples: {$total}\n";
        echo "Passed: {$this->passed} ✅\n";
        echo "Failed: {$this->failed} ❌\n";
        echo "Success Rate: {$percentage}%\n\n";

        if ($this->failed === 0) {
            echo "🎉 All examples passed validation!\n";
        } else {
            echo "⚠️  Some examples need attention.\n";
        }

        echo "\n";
    }

    public function getResults(): array
    {
        return $this->results;
    }
}

// Run validation
$validator = new ExampleValidator();
$examplesDir = __DIR__ . '/../examples';

$validator->validateAll($examplesDir);

// Exit with appropriate code
$results = $validator->getResults();
$allPassed = ! in_array(false, array_column($results, 'passed'));
exit($allPassed ? 0 : 1);
