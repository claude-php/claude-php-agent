<?php

/**
 * Tutorial 1: Basic Component Validation
 * 
 * This example demonstrates:
 * - Creating a ComponentValidationService
 * - Validating a simple PHP class
 * - Handling validation results
 * 
 * Run: php examples/tutorials/component-validation/01-basic-validation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

echo "=== Tutorial 1: Basic Component Validation ===\n\n";

// Create the validation service
$service = new ComponentValidationService();

// Code to validate - a simple Calculator class
$code = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
    
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}
PHP;

echo "Validating Calculator class...\n";

// Validate the code
$result = $service->validate($code);

// Check result
if ($result->isValid()) {
    echo "✓ Code is valid!\n\n";
    
    // Get metadata
    $metadata = $result->getMetadata();
    echo "Metadata:\n";
    echo "- Class Name: {$metadata['class_name']}\n";
    echo "- Load Strategy: {$metadata['load_strategy']}\n";
    echo "- Instantiation Time: {$metadata['instantiation_time_ms']}ms\n";
    echo "- Instance Class: {$metadata['instance_class']}\n";
} else {
    echo "✗ Validation failed:\n";
    foreach ($result->getErrors() as $error) {
        echo "  - $error\n";
    }
}

echo "\n✓ Example complete!\n";
