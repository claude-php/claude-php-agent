<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Loops\ReactLoop;
use ClaudeAgents\Loops\PlanExecuteLoop;
use ClaudeAgents\Loops\ReflectionLoop;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

/**
 * Loop Strategies Demonstration
 * 
 * This example demonstrates the different loop strategies available:
 * 1. ReactLoop - Standard reason-act-observe pattern
 * 2. PlanExecuteLoop - Plan first, then execute systematically
 * 3. ReflectionLoop - Generate, reflect, and refine iteratively
 */

// Initialize Claude client
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    die("Please set ANTHROPIC_API_KEY environment variable\n");
}

$client = ClaudePhp::create($apiKey);

// Create a simple calculator tool for demonstrations
$calculator = Tool::create(
    name: 'calculator',
    description: 'Performs basic arithmetic operations',
    inputSchema: [
        'type' => 'object',
        'properties' => [
            'operation' => [
                'type' => 'string',
                'enum' => ['add', 'subtract', 'multiply', 'divide'],
                'description' => 'The operation to perform',
            ],
            'a' => ['type' => 'number', 'description' => 'First number'],
            'b' => ['type' => 'number', 'description' => 'Second number'],
        ],
        'required' => ['operation', 'a', 'b'],
    ],
    handler: function (array $input): string {
        $a = $input['a'];
        $b = $input['b'];
        
        return match ($input['operation']) {
            'add' => (string)($a + $b),
            'subtract' => (string)($a - $b),
            'multiply' => (string)($a * $b),
            'divide' => $b != 0 ? (string)($a / $b) : 'Error: Division by zero',
            default => 'Error: Unknown operation',
        };
    }
);

echo "=== Loop Strategies Demonstration ===\n\n";

// =================================================================
// Example 1: ReactLoop (Standard Pattern)
// =================================================================
echo "1. ReactLoop - Standard Reason-Act-Observe Pattern\n";
echo str_repeat("-", 60) . "\n";

$reactLoop = new ReactLoop();
$reactLoop->onIteration(function ($iteration, $response, $context) {
    echo "  [Iteration {$iteration}] ";
    $stopReason = $response->stop_reason ?? 'unknown';
    echo "Stop reason: {$stopReason}\n";
});

$agent1 = Agent::create($client)
    ->withName('react-agent')
    ->withTool($calculator)
    ->withLoopStrategy($reactLoop)
    ->maxIterations(5);

$task1 = "Calculate (15 + 7) × 3";
echo "\nTask: {$task1}\n\n";

$result1 = $agent1->run($task1);

echo "\nResult: {$result1->getAnswer()}\n";
echo "Success: " . ($result1->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Iterations: {$result1->getIterations()}\n";
echo "Tokens: " . json_encode($result1->getTokenUsage()) . "\n\n";

// =================================================================
// Example 2: PlanExecuteLoop
// =================================================================
echo "\n2. PlanExecuteLoop - Plan First, Then Execute\n";
echo str_repeat("-", 60) . "\n";

$planExecuteLoop = new PlanExecuteLoop(allowReplan: true);

$planExecuteLoop->onPlanCreated(function ($steps, $context) {
    echo "\n  Plan created with " . count($steps) . " steps:\n";
    foreach ($steps as $i => $step) {
        echo "    " . ($i + 1) . ". {$step}\n";
    }
    echo "\n";
});

$planExecuteLoop->onStepComplete(function ($stepNumber, $description, $result) {
    echo "  [Step {$stepNumber}] {$description}\n";
    echo "    → " . substr($result, 0, 80) . "...\n";
});

$agent2 = Agent::create($client)
    ->withName('plan-execute-agent')
    ->withTool($calculator)
    ->withLoopStrategy($planExecuteLoop)
    ->maxIterations(10);

$task2 = "Calculate the total cost: 3 items at \$12.99 each, plus 8% tax";
echo "\nTask: {$task2}\n";

$result2 = $agent2->run($task2);

echo "\nResult: {$result2->getAnswer()}\n";
echo "Success: " . ($result2->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Iterations: {$result2->getIterations()}\n";
echo "Tokens: " . json_encode($result2->getTokenUsage()) . "\n\n";

// =================================================================
// Example 3: ReflectionLoop
// =================================================================
echo "\n3. ReflectionLoop - Generate, Reflect, and Refine\n";
echo str_repeat("-", 60) . "\n";

$reflectionLoop = new ReflectionLoop(
    maxRefinements: 2,
    qualityThreshold: 8,
    criteria: 'clarity, accuracy, and completeness'
);

$reflectionLoop->onReflection(function ($refinement, $score, $feedback) {
    echo "  [Refinement {$refinement}] Quality Score: {$score}/10\n";
    echo "    Feedback: " . substr($feedback, 0, 100) . "...\n\n";
});

$agent3 = Agent::create($client)
    ->withName('reflection-agent')
    ->withLoopStrategy($reflectionLoop)
    ->maxIterations(10);

$task3 = "Explain the concept of loop strategies in AI agents in simple terms";
echo "\nTask: {$task3}\n\n";

$result3 = $agent3->run($task3);

echo "\nFinal Result:\n";
echo str_repeat("-", 60) . "\n";
echo $result3->getAnswer() . "\n";
echo str_repeat("-", 60) . "\n";
echo "Success: " . ($result3->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Iterations: {$result3->getIterations()}\n";
echo "Tokens: " . json_encode($result3->getTokenUsage()) . "\n";

$metadata = $result3->getMetadata();
if (isset($metadata['final_score'])) {
    echo "Final Quality Score: {$metadata['final_score']}/10\n";
}
if (isset($metadata['reflections'])) {
    echo "Refinement History:\n";
    foreach ($metadata['reflections'] as $reflection) {
        echo "  - Iteration {$reflection['iteration']}: Score {$reflection['score']}/10\n";
    }
}

// =================================================================
// Comparison Summary
// =================================================================
echo "\n\n=== Loop Strategy Comparison ===\n";
echo str_repeat("=", 60) . "\n\n";

echo "ReactLoop:\n";
echo "  ✓ Best for: General-purpose tasks with tools\n";
echo "  ✓ Pattern: Reason → Act → Observe (repeat)\n";
echo "  ✓ Use when: You need flexibility and tool execution\n\n";

echo "PlanExecuteLoop:\n";
echo "  ✓ Best for: Complex multi-step tasks\n";
echo "  ✓ Pattern: Plan → Execute steps → Synthesize\n";
echo "  ✓ Use when: Tasks benefit from upfront planning\n\n";

echo "ReflectionLoop:\n";
echo "  ✓ Best for: Quality-focused outputs\n";
echo "  ✓ Pattern: Generate → Reflect → Refine (repeat)\n";
echo "  ✓ Use when: Output quality matters more than speed\n\n";

echo "All examples completed successfully!\n";

