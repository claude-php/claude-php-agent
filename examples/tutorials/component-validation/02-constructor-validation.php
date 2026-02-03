<?php

/**
 * Tutorial 2: Constructor Validation
 * 
 * This example demonstrates:
 * - Validating classes with constructor logic
 * - Providing constructor arguments
 * - Handling constructor validation errors
 * 
 * Run: php examples/tutorials/component-validation/02-constructor-validation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

echo "=== Tutorial 2: Constructor Validation ===\n\n";

// Example 1: Valid constructor with arguments
echo "Example 1: Valid Constructor\n";
echo str_repeat('-', 50) . "\n";

$validCode = <<<'PHP'
<?php

class DatabaseConnection
{
    private string $host;
    
    public function __construct(string $host = 'localhost')
    {
        if (empty($host)) {
            throw new \InvalidArgumentException('Host cannot be empty');
        }
        $this->host = $host;
    }
}
PHP;

$service = new ComponentValidationService([
    'constructor_args' => ['localhost'],
]);

$result = $service->validate($validCode);

if ($result->isValid()) {
    echo "✓ Constructor validation passed!\n";
    echo "  Args provided: {$result->getMetadata()['constructor_args_count']}\n";
}

echo "\n";

// Example 2: Invalid constructor arguments
echo "Example 2: Invalid Constructor Args\n";
echo str_repeat('-', 50) . "\n";

$serviceInvalid = new ComponentValidationService([
    'constructor_args' => [''], // Empty string - invalid!
]);

$result = $serviceInvalid->validate($validCode);

if ($result->isFailed()) {
    echo "✗ Constructor validation failed (expected):\n";
    foreach ($result->getErrors() as $error) {
        echo "  - " . substr($error, 0, 100) . "...\n";
    }
}

echo "\n✓ Example complete!\n";
