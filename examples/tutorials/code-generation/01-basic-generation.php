<?php

/**
 * Code Generation Tutorial 1: Basic Generation
 * 
 * Run: php examples/tutorials/code-generation/01-basic-generation.php
 * Requires: ANTHROPIC_API_KEY
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudePhp\ClaudePhp;

echo "=== Code Generation Tutorial 1: Basic Generation ===\n\n";

// Check API key
if (!getenv('ANTHROPIC_API_KEY')) {
    die("❌ ANTHROPIC_API_KEY not set\n");
}

// Initialize
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$agent = new CodeGenerationAgent($client);

// Generate code
echo "Generating Calculator class...\n";

$description = 'Create a Calculator class with add, subtract, multiply, and divide methods';

$result = $agent->generateComponent($description);

// Display result
if ($result->isValid()) {
    echo "✓ Code generated successfully!\n\n";
    echo "Generated Code:\n";
    echo str_repeat('=', 60) . "\n";
    echo $result->getCode();
    echo str_repeat('=', 60) . "\n";
} else {
    echo "✗ Generation failed\n";
}

echo "\n✓ Example complete!\n";
