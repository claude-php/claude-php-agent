#!/usr/bin/env php
<?php
/**
 * Basic Agent Example
 *
 * Demonstrates the simplest way to create an agent with tools.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;
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
echo "║                      Basic Agent Example                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create a calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations. Supports +, -, *, /, parentheses.')
    ->stringParam('expression', 'The mathematical expression to evaluate')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        
        // Validate expression (only allow safe characters)
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid expression characters";
        }
        
        try {
            $result = eval("return {$expr};");
            return (string) $result;
        } catch (Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    });

// Create the agent
$agent = Agent::create($client)
    ->withTool($calculator)
    ->withSystemPrompt('You are a helpful assistant that can perform calculations.')
    ->maxIterations(5);

// Run a simple task
echo "Task: What is (25 * 17) + (100 / 4)?\n\n";

$result = $agent->run('What is (25 * 17) + (100 / 4)?');

if ($result->isSuccess()) {
    echo "✅ Answer: {$result->getAnswer()}\n\n";
    echo "Completed in {$result->getIterations()} iterations\n";
    
    $usage = $result->getTokenUsage();
    echo "Tokens: {$usage['input']} input, {$usage['output']} output\n";
    
    // Show tool calls
    $toolCalls = $result->getToolCalls();
    if (!empty($toolCalls)) {
        echo "\nTool calls made:\n";
        foreach ($toolCalls as $call) {
            echo "  - {$call['tool']}: {$call['result']}\n";
        }
    }
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";

