<?php

/**
 * Code Generation Tutorial 2: With Validation
 * 
 * Run: php examples/tutorials/code-generation/02-with-validation.php
 * Requires: ANTHROPIC_API_KEY
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudePhp\ClaudePhp;

echo "=== Code Generation Tutorial 2: With Validation ===\n\n";

if (!getenv('ANTHROPIC_API_KEY')) {
    die("❌ ANTHROPIC_API_KEY not set\n");
}

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Setup validation
$coordinator = new ValidationCoordinator();
$coordinator
    ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
    ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));

// Create agent with validation
$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
]);

echo "Generating Logger class with validation...\n";

$result = $agent->generateComponent('Create a Logger class with info and error methods');

if ($result->isValid()) {
    echo "✓ Code generated and validated!\n\n";
    
    $validation = $result->getValidation();
    echo "Validation Stats:\n";
    echo "- Validators Run: {$validation->getMetadata()['validator_count']}\n";
    echo "- Duration: {$validation->getMetadata()['duration_ms']}ms\n";
    echo "- Errors: {$validation->getErrorCount()}\n";
}

echo "\n✓ Example complete!\n";
