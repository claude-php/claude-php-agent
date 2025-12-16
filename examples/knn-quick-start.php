#!/usr/bin/env php
<?php
/**
 * k-NN Learning Quick Start
 * 
 * Minimal example showing k-NN enhanced Adaptive Agent Service
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/load-env.php';

use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Tools\BuiltIn\CalculatorTool;
use ClaudePhp\ClaudePhp;

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "Error: ANTHROPIC_API_KEY not set.\n";
    echo "Set it via environment variable or .env file.\n";
    echo "Example: export ANTHROPIC_API_KEY='your-key-here'\n";
    exit(1);
}

$client = new ClaudePhp(apiKey: $apiKey);

// 1. Create service with k-NN enabled
$service = new AdaptiveAgentService($client, [
    'enable_knn' => true,  // 🧠 Learning enabled!
]);

// 2. Register agents
$reactAgent = new ReactAgent($client, [
    'name' => 'react_agent',
    'tools' => [CalculatorTool::create()],
    'max_iterations' => 5
]);
$service->registerAgent('react_agent', $reactAgent, [
    'type' => 'react',
    'complexity_level' => 'medium',
]);

// 3. Execute tasks - learning happens automatically!
echo "Task 1 (First time - rule-based selection):\n";
$result = $service->run('Calculate 15% of 240');
echo "Selected: " . $result->getMetadata()['final_agent'] . "\n\n";

echo "Task 2 (Similar task - k-NN selection!):\n";
$result = $service->run('Calculate 20% of 500');
echo "Selected: " . $result->getMetadata()['final_agent'] . "\n";
echo "Method: k-NN based on history!\n\n";

// 4. Check learning stats
$stats = $service->getHistoryStats();
echo "📊 Learned from {$stats['total_records']} tasks\n";
echo "Success rate: " . number_format($stats['success_rate'] * 100, 1) . "%\n";

