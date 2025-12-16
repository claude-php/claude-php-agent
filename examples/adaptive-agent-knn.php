#!/usr/bin/env php
<?php
/**
 * Adaptive Agent Service with k-NN Learning Example
 *
 * This example demonstrates the k-NN enhanced Adaptive Agent Service that:
 * 1. Learns from historical task executions
 * 2. Uses k-NN to select agents based on similar past tasks
 * 3. Adapts quality thresholds based on task difficulty
 * 4. Improves selection over time
 *
 * The system gets smarter with each execution!
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/load-env.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Tools\BuiltIn\CalculatorTool;
use ClaudePhp\ClaudePhp;

// Setup
$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "Error: ANTHROPIC_API_KEY not set.\n";
    echo "Set it via environment variable or .env file.\n";
    echo "Example: export ANTHROPIC_API_KEY='your-key-here'\n";
    exit(1);
}

$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   Adaptive Agent Service with k-NN Learning                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Create the Adaptive Agent Service with k-NN enabled
$adaptiveService = new AdaptiveAgentService($client, [
    'name' => 'knn_learning_service',
    'max_attempts' => 3,
    'quality_threshold' => 7.0,
    'enable_knn' => true,
    'history_store_path' => __DIR__ . '/../storage/knn_agent_history.json',
]);

// Register different types of agents
echo "📋 Registering Agents...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 1. React Agent (good for tool use)
$reactAgent = new ReactAgent($client, [
    'name' => 'react_agent',
    'tools' => [CalculatorTool::create()],
    'max_iterations' => 5
]);

$adaptiveService->registerAgent('react_agent', $reactAgent, [
    'type' => 'react',
    'strengths' => ['Tool usage', 'Step-by-step reasoning', 'Action-based tasks'],
    'best_for' => ['calculations', 'API calls', 'multi-step processes'],
    'complexity_level' => 'medium',
    'speed' => 'medium',
    'quality' => 'standard',
]);

echo "✓ Registered: React Agent (tool-based reasoning)\n";

// 2. Reflection Agent (good for quality)
$reflectionAgent = new ReflectionAgent($client, [
    'name' => 'reflection_agent',
    'max_iterations' => 3
]);

$adaptiveService->registerAgent('reflection_agent', $reflectionAgent, [
    'type' => 'reflection',
    'strengths' => ['Self-critique', 'High quality', 'Iterative improvement'],
    'best_for' => ['writing', 'analysis', 'complex reasoning'],
    'complexity_level' => 'complex',
    'speed' => 'slow',
    'quality' => 'extreme',
]);

echo "✓ Registered: Reflection Agent (high-quality output)\n";

// 3. Chain of Thought Agent (good for reasoning)
$cotAgent = new ChainOfThoughtAgent($client, [
    'name' => 'cot_agent'
]);

$adaptiveService->registerAgent('cot_agent', $cotAgent, [
    'type' => 'cot',
    'strengths' => ['Logical reasoning', 'Step-by-step thinking', 'Problem solving'],
    'best_for' => ['puzzles', 'math', 'logical problems'],
    'complexity_level' => 'medium',
    'speed' => 'fast',
    'quality' => 'high',
]);

echo "✓ Registered: Chain-of-Thought Agent (reasoning focused)\n\n";

// Show initial history stats
echo "📊 Initial History Statistics:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$stats = $adaptiveService->getHistoryStats();
print_r($stats);
echo "\n";

// Test tasks that the service will learn from
$testTasks = [
    // Task 1: Calculation (should use React Agent with calculator)
    [
        'task' => 'Calculate the compound interest on $10,000 invested at 5% annual rate for 3 years, compounded annually. Show your calculation.',
        'expected_agent' => 'react_agent',
        'category' => 'Calculation',
    ],
    
    // Task 2: Reasoning (should use CoT Agent)
    [
        'task' => 'If all Bloops are Razzies and all Razzies are Lazzies, are all Bloops definitely Lazzies? Explain your reasoning.',
        'expected_agent' => 'cot_agent',
        'category' => 'Logic Puzzle',
    ],
    
    // Task 3: High-quality writing (should use Reflection Agent)
    [
        'task' => 'Write a professional email apologizing to a client for a delayed project delivery and proposing next steps.',
        'expected_agent' => 'reflection_agent',
        'category' => 'Professional Writing',
    ],
    
    // Task 4: Another calculation
    [
        'task' => 'What is 15% of 240, plus the square root of 144?',
        'expected_agent' => 'react_agent',
        'category' => 'Calculation',
    ],
];

// Execute tasks and build history
echo "\n🎯 Running Test Tasks to Build Learning History...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($testTasks as $index => $taskData) {
    $taskNum = $index + 1;
    echo "Task {$taskNum}: {$taskData['category']}\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    echo "Task: {$taskData['task']}\n\n";
    
    // Get recommendation before execution
    $recommendation = $adaptiveService->recommendAgent($taskData['task']);
    echo "💡 Agent Recommendation:\n";
    echo "   Selected: {$recommendation['agent_id']}\n";
    echo "   Confidence: " . number_format($recommendation['confidence'] * 100, 1) . "%\n";
    echo "   Method: {$recommendation['method']}\n";
    echo "   Reasoning: {$recommendation['reasoning']}\n\n";
    
    // Execute
    echo "⚙️  Executing...\n";
    $result = $adaptiveService->run($taskData['task']);
    
    if ($result->isSuccess()) {
        echo "✅ Success!\n";
        echo "   Quality: {$result->getMetadata()['final_quality']}/10\n";
        echo "   Agent Used: {$result->getMetadata()['final_agent']}\n";
        echo "   Duration: {$result->getMetadata()['total_duration']}s\n";
        echo "   k-NN Enabled: " . ($result->getMetadata()['knn_enabled'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Failed: {$result->getError()}\n";
    }
    
    echo "\n" . str_repeat("─", 65) . "\n\n";
    
    // Small delay between tasks
    sleep(1);
}

// Show updated history stats
echo "\n📊 Updated History Statistics After Learning:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$stats = $adaptiveService->getHistoryStats();
print_r($stats);
echo "\n";

// Now demonstrate the learning effect with a new similar task
echo "\n🧠 Demonstrating Learning Effect with Similar Task...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$similarTask = 'Calculate the final amount after investing $5,000 at 4% interest for 2 years.';

echo "Task: {$similarTask}\n\n";

// Get recommendation (should now use k-NN)
$recommendation = $adaptiveService->recommendAgent($similarTask);
echo "💡 k-NN Based Recommendation:\n";
echo "   Selected: {$recommendation['agent_id']}\n";
echo "   Confidence: " . number_format($recommendation['confidence'] * 100, 1) . "%\n";
echo "   Method: {$recommendation['method']}\n";
echo "   Reasoning: {$recommendation['reasoning']}\n";

if (!empty($recommendation['alternatives'])) {
    echo "   Alternative agents:\n";
    foreach ($recommendation['alternatives'] as $alt) {
        echo "   - {$alt['agent_id']} (score: " . number_format($alt['score'], 2) . ")\n";
    }
}

echo "\n⚙️  Executing with learned preferences...\n";
$result = $adaptiveService->run($similarTask);

if ($result->isSuccess()) {
    echo "✅ Success with learned agent selection!\n";
    echo "   Quality: {$result->getMetadata()['final_quality']}/10\n";
    echo "   Agent Used: {$result->getMetadata()['final_agent']}\n";
    echo "   Duration: {$result->getMetadata()['total_duration']}s\n";
    echo "   Selection Method: k-NN based on historical performance\n\n";
    
    echo "📝 Answer:\n";
    echo "   " . substr($result->getAnswer(), 0, 200) . "...\n\n";
}

// Show agent performance comparison
echo "\n📈 Agent Performance Summary:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$performance = $adaptiveService->getPerformance();
foreach ($performance as $agentId => $perf) {
    echo "\n{$agentId}:\n";
    echo "  Attempts: {$perf['attempts']}\n";
    echo "  Successes: {$perf['successes']}\n";
    
    if ($perf['attempts'] > 0) {
        $successRate = ($perf['successes'] / $perf['attempts']) * 100;
        echo "  Success Rate: " . number_format($successRate, 1) . "%\n";
        echo "  Avg Quality: " . number_format($perf['average_quality'], 1) . "/10\n";
        echo "  Avg Duration: " . number_format($perf['total_duration'] / $perf['attempts'], 2) . "s\n";
    }
}

echo "\n\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Key Takeaways:                                                ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
echo "║  1. First tasks use rule-based selection (no history)          ║\n";
echo "║  2. System learns from each execution                          ║\n";
echo "║  3. Similar tasks trigger k-NN based selection                 ║\n";
echo "║  4. Confidence increases with more data                        ║\n";
echo "║  5. Quality thresholds adapt to task difficulty                ║\n";
echo "║  6. The system gets smarter over time! 🚀                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "💾 History saved to: " . __DIR__ . "/../storage/knn_agent_history.json\n";
echo "Run this script again to see even better agent selection!\n\n";

