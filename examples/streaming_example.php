#!/usr/bin/env php
<?php
/**
 * Streaming Example
 *
 * Demonstrates real-time token streaming from Claude API.
 * Shows how to use StreamingLoop and handlers for progressive output.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Streaming\StreamingLoop;
use ClaudeAgents\Streaming\Handlers\ConsoleHandler;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

echo "=== Streaming Agent Example ===\n\n";

// Create a simple calculator tool
$calculator = Tool::create('calculator')
    ->description('Performs basic arithmetic operations')
    ->stringParam('expression', 'Math expression to evaluate')
    ->handler(function($input) {
        $expr = $input['expression'] ?? '0';
        // Safe evaluation (normally use a proper math library)
        return @eval('return ' . $expr . ';');
    });

// Create agent with streaming
$config = new AgentConfig([
    'model' => 'claude-sonnet-4-5',
    'max_iterations' => 5,
    'max_tokens' => 1024,
]);

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTool($calculator)
    ->maxIterations(3);

// Add streaming loop
$streamingLoop = new StreamingLoop();
$streamingLoop->addHandler(new ConsoleHandler(newline: true));

$agent->withLoopStrategy($streamingLoop);

echo "Running agent with streaming...\n";
echo "Task: Calculate the sum of 25 + 17 + 8\n\n";
echo "Output:\n";
echo "---\n";

$result = $agent->run('Calculate the sum of 25 + 17 + 8');

echo "---\n\n";

echo "Final Answer: " . $result->getAnswer() . "\n";
echo "Success: " . ($result->isSuccess() ? 'Yes' : 'No') . "\n";

