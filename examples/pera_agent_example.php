<?php

/**
 * Plan-Execute-Reflect-Adjust (PERA) Agent Example
 * 
 * Demonstrates the advanced ReAct pattern with planning, execution,
 * reflection, and self-correction capabilities.
 * 
 * Run: php examples/pera_agent_example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteReflectAgent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    die("Error: ANTHROPIC_API_KEY not set\n");
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Plan-Execute-Reflect-Adjust (PERA) Agent Example           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Create logger
$logger = new Logger('pera_agent');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

// Create tools for the agent
$calculatorTool = Tool::create('calculate')
    ->description('Perform mathematical calculations with precision')
    ->stringParam('expression', 'Mathematical expression to evaluate (e.g., "25 * 17 + 100")')
    ->handler(function (array $input): string {
        $expression = $input['expression'];
        
        // Safety check: only allow safe characters
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
            return "Error: Invalid expression";
        }
        
        try {
            // Use a safe evaluation method (eval is shown for simplicity)
            // In production, use a proper math parser library
            $result = eval("return {$expression};");
            return (string)$result;
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    });

$dataTool = Tool::create('analyze_data')
    ->description('Analyze a list of numbers and return statistics')
    ->arrayParam('numbers', 'Array of numbers to analyze')
    ->handler(function (array $input): string {
        $numbers = $input['numbers'];
        
        if (empty($numbers)) {
            return "Error: No numbers provided";
        }
        
        $count = count($numbers);
        $sum = array_sum($numbers);
        $avg = $sum / $count;
        $min = min($numbers);
        $max = max($numbers);
        
        sort($numbers);
        $median = ($count % 2 === 0) 
            ? ($numbers[$count/2 - 1] + $numbers[$count/2]) / 2
            : $numbers[floor($count/2)];
        
        return json_encode([
            'count' => $count,
            'sum' => $sum,
            'average' => round($avg, 2),
            'median' => $median,
            'min' => $min,
            'max' => $max,
        ], JSON_PRETTY_PRINT);
    });

// Configure agent
$config = AgentConfig::fromArray([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 4096,
    'max_iterations' => 10,
]);

// Create PERA agent
$agent = new PlanExecuteReflectAgent(
    client: $client,
    config: $config,
    logger: $logger,
    enableExtendedThinking: true,
    maxCycles: 3,
    thinkingBudget: 10000
);

// Add tools
$agent->withTool($calculatorTool)
      ->withTool($dataTool);

// Example 1: Complex multi-step calculation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Complex Multi-Step Calculation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$task1 = "Calculate the total cost of the following items with tax:\n" .
    "- 5 items at $12.50 each\n" .
    "- 3 items at $8.75 each\n" .
    "- Apply 8% sales tax to the subtotal\n" .
    "- Then apply a 10% discount to the final amount\n" .
    "Show all steps and the final result.";

echo "Task: {$task1}\n\n";

$result1 = $agent->run($task1);

if ($result1['success']) {
    echo "\n✅ Task completed successfully!\n";
    echo "Cycles: {$result1['cycles']}\n\n";
    
    if ($result1['plan']) {
        echo "📋 Plan:\n";
        echo str_repeat("-", 60) . "\n";
        echo $result1['plan'] . "\n\n";
    }
    
    if ($result1['response']) {
        echo "💡 Final Response:\n";
        echo str_repeat("-", 60) . "\n";
        echo \ClaudeAgents\Helpers\AgentHelpers::extractTextContent($result1['response']) . "\n\n";
    }
    
    if ($result1['reflection']) {
        echo "🔍 Reflection:\n";
        echo str_repeat("-", 60) . "\n";
        echo $result1['reflection'] . "\n\n";
    }
} else {
    echo "\n❌ Task failed: " . ($result1['error'] ?? 'Unknown error') . "\n\n";
}

// Example 2: Data analysis with potential errors
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Data Analysis with Self-Correction\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$task2 = "Analyze the following test scores and determine:\n" .
    "1. The average score\n" .
    "2. The median score\n" .
    "3. If the average is above 75, calculate how much extra credit to add to bring failing scores (below 60) up to passing.\n" .
    "Scores: [85, 92, 78, 45, 88, 91, 55, 82, 76, 89]\n" .
    "Show your work and verify your calculations.";

echo "Task: {$task2}\n\n";

$result2 = $agent->run($task2);

if ($result2['success']) {
    echo "\n✅ Task completed successfully!\n";
    echo "Cycles: {$result2['cycles']}\n";
    
    if ($result2['cycles'] > 1) {
        echo "(Agent self-corrected {$result2['cycles']} time(s))\n";
    }
    
    echo "\n💡 Final Response:\n";
    echo str_repeat("-", 60) . "\n";
    echo \ClaudeAgents\Helpers\AgentHelpers::extractTextContent($result2['response']) . "\n\n";
} else {
    echo "\n❌ Task failed: " . ($result2['error'] ?? 'Unknown error') . "\n\n";
}

// Example 3: Simple task (should complete in one cycle)
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Simple Task (Single Cycle)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$task3 = "Calculate 25% of 480 and add 15 to the result.";

echo "Task: {$task3}\n\n";

$result3 = $agent->run($task3);

if ($result3['success']) {
    echo "\n✅ Task completed successfully!\n";
    echo "Cycles: {$result3['cycles']}\n\n";
    
    echo "💡 Final Response:\n";
    echo str_repeat("-", 60) . "\n";
    echo \ClaudeAgents\Helpers\AgentHelpers::extractTextContent($result3['response']) . "\n\n";
} else {
    echo "\n❌ Task failed: " . ($result3['error'] ?? 'Unknown error') . "\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "PERA Agent Example Complete\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

