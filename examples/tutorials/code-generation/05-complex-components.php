<?php

/**
 * Code Generation Tutorial 5: Complex Components
 * 
 * Run: php examples/tutorials/code-generation/05-complex-components.php
 * Requires: ANTHROPIC_API_KEY
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudePhp\ClaudePhp;

echo "=== Code Generation Tutorial 5: Complex Components ===\n\n";

if (!getenv('ANTHROPIC_API_KEY')) {
    die("❌ ANTHROPIC_API_KEY not set\n");
}

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$coordinator = new ValidationCoordinator();
$coordinator
    ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
    ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
    'max_validation_retries' => 3,
]);

// Generate complex component
$description = <<<'DESC'
Create a CacheManager class with:
- Constructor with no required parameters
- Methods: set(key, value), get(key), has(key), delete(key), clear()
- Store data in an internal array property
- Return appropriate types for each method
DESC;

echo "Generating complex component...\n";

$result = $agent->generateComponent($description);

if ($result->isValid()) {
    echo "✓ Complex component generated!\n\n";
    echo "Code preview (first 20 lines):\n";
    echo str_repeat('-', 60) . "\n";
    
    $lines = explode("\n", $result->getCode());
    echo implode("\n", array_slice($lines, 0, 20)) . "\n";
    
    if (count($lines) > 20) {
        echo "... (" . (count($lines) - 20) . " more lines)\n";
    }
}

echo "\n✓ Example complete!\n";
