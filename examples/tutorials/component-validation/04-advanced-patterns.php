<?php

/**
 * Tutorial 4: Advanced Validation Patterns
 * 
 * This example demonstrates:
 * - Context-based validation
 * - Expected class name verification
 * - Load strategy selection
 * 
 * Run: php examples/tutorials/component-validation/04-advanced-patterns.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

echo "=== Tutorial 4: Advanced Validation Patterns ===\n\n";

$code = <<<'PHP'
<?php

class ConfigurableComponent
{
    private array $config;
    
    public function __construct(array $config)
    {
        if (empty($config['api_key'])) {
            throw new \InvalidArgumentException('API key required');
        }
        $this->config = $config;
    }
}
PHP;

// Example 1: Context-based validation
echo "Example 1: Context-Based Validation\n";
echo str_repeat('-', 50) . "\n";

$service = new ComponentValidationService();

$result = $service->validate($code, [
    'constructor_args' => [
        ['api_key' => 'test_key_123']
    ],
]);

echo ($result->isValid() ? '✓' : '✗') . " Context validation: " . 
    ($result->isValid() ? 'Passed' : 'Failed') . "\n\n";

// Example 2: Expected class name verification
echo "Example 2: Expected Class Name\n";
echo str_repeat('-', 50) . "\n";

$result = $service->validate($code, [
    'expected_class_name' => 'ConfigurableComponent',
    'constructor_args' => [['api_key' => 'test']],
]);

echo ($result->isValid() ? '✓' : '✗') . " Class name verification: " . 
    ($result->isValid() ? 'Passed' : 'Failed') . "\n\n";

// Example 3: Load strategies
echo "Example 3: Load Strategy Selection\n";
echo str_repeat('-', 50) . "\n";

// Temp file strategy (default)
$tempFileService = new ComponentValidationService([
    'load_strategy' => 'temp_file',
]);

$result = $tempFileService->validate($code, [
    'constructor_args' => [['api_key' => 'test']],
]);

echo "Strategy: {$result->getMetadata()['load_strategy']}\n";
echo "Status: " . ($result->isValid() ? 'Valid' : 'Invalid') . "\n";

echo "\n✓ Example complete!\n";
