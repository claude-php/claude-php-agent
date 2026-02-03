<?php

/**
 * Code Generation Tutorial 3: Retry Logic
 * 
 * Run: php examples/tutorials/code-generation/03-retry-logic.php
 * Requires: ANTHROPIC_API_KEY
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudePhp\ClaudePhp;

echo "=== Code Generation Tutorial 3: Retry Logic ===\n\n";

if (!getenv('ANTHROPIC_API_KEY')) {
    die("❌ ANTHROPIC_API_KEY not set\n");
}

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$coordinator = new ValidationCoordinator();
$coordinator->addValidator(new PHPSyntaxValidator());

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
    'max_validation_retries' => 3,
]);

// Track retries
$retryCount = 0;
$agent->onRetry(function (int $attempt, array $errors) use (&$retryCount) {
    $retryCount++;
    echo "⚠ Retry #$attempt after errors:\n";
    foreach (array_slice($errors, 0, 2) as $error) {
        echo "  - $error\n";
    }
});

echo "Generating component (may retry if needed)...\n\n";

$result = $agent->generateComponent('Create a simple User class');

echo "\n✓ Generated after " . ($retryCount + 1) . " attempt(s)\n";
echo ($result->isValid() ? '✓' : '✗') . " Final result: " . 
    ($result->isValid() ? 'Valid' : 'Invalid') . "\n";

echo "\n✓ Example complete!\n";
