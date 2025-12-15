#!/usr/bin/env php
<?php
/**
 * Reflection Agent Example
 *
 * Demonstrates the generate-reflect-refine pattern for iterative
 * quality improvement.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;

// Load environment
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? throw new RuntimeException('ANTHROPIC_API_KEY not set');
$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    Reflection Agent Example                                ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create a reflection agent for code generation
$agent = new ReflectionAgent($client, [
    'name' => 'code_reflection_agent',
    'max_refinements' => 3,
    'quality_threshold' => 8,
    'criteria' => 'correctness, code quality, documentation, edge case handling, and PHP best practices',
]);

// Task: Generate a PHP function with reflection
$task = "Write a PHP function called `validateEmail` that validates an email address. It should check for proper format, common typos in domain names (like gmail.con), and return an array with 'valid' (bool) and 'message' (string) keys.";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Task:\n{$task}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Running reflection agent (may take a moment)...\n\n";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "✅ Final Output:\n";
    echo str_repeat("-", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $metadata = $result->getMetadata();
    
    // Show reflection history
    if (isset($metadata['reflections']) && !empty($metadata['reflections'])) {
        echo "📝 Reflection History:\n";
        foreach ($metadata['reflections'] as $reflection) {
            echo "  Iteration {$reflection['iteration']}: Score {$reflection['score']}/10\n";
        }
        echo "\n";
    }
    
    echo "📊 Stats:\n";
    echo "  • Final Score: " . ($metadata['final_score'] ?? 'N/A') . "/10\n";
    echo "  • Iterations: {$result->getIterations()}\n";
    
    $usage = $result->getTokenUsage();
    echo "  • Tokens: {$usage['total']} total\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n\n";

// Example 2: Writing improvement
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Writing Improvement\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$writingAgent = new ReflectionAgent($client, [
    'name' => 'writing_agent',
    'max_refinements' => 2,
    'quality_threshold' => 8,
    'criteria' => 'clarity, engagement, grammar, structure, and persuasiveness',
]);

$writingTask = "Write a short paragraph (3-4 sentences) explaining why learning to code is valuable in today's world.";

echo "Task: {$writingTask}\n\n";

$result = $writingAgent->run($writingTask);

if ($result->isSuccess()) {
    echo "✅ Final Output:\n{$result->getAnswer()}\n\n";
    
    $metadata = $result->getMetadata();
    echo "Final Score: " . ($metadata['final_score'] ?? 'N/A') . "/10\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "Reflection agent example completed!\n";

