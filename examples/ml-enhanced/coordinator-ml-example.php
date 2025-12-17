<?php

/**
 * ML-Enhanced CoordinatorAgent Example
 *
 * Demonstrates how the CoordinatorAgent learns optimal worker selection
 * based on historical performance for similar tasks.
 *
 * The agent will:
 * 1. Start with rule-based agent selection
 * 2. Track which agents perform best on which task types
 * 3. Gradually improve routing decisions based on history
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../load-env.php';

use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudePhp\ClaudePhp;
use Psr\Log\LogLevel;

// Create client
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    die('ANTHROPIC_API_KEY not found in environment');
}
$client = new ClaudePhp($apiKey);

echo "=== ML-Enhanced CoordinatorAgent Example ===\n\n";

// Create coordinator with ML enabled
$coordinator = new CoordinatorAgent($client, [
    'name' => 'ml_coordinator',
    'enable_ml_selection' => true, // Enable ML-based worker selection
    'ml_history_path' => __DIR__ . '/../../storage/coordinator_ml.json',
]);

// Register specialized workers
$codingAgent = new ReactAgent($client, [
    'name' => 'coding_specialist',
    'max_iterations' => 5,
]);

$reasoningAgent = new ChainOfThoughtAgent($client, [
    'name' => 'reasoning_specialist',
]);

$coordinator->registerAgent('coder', $codingAgent, ['coding', 'implementation', 'programming']);
$coordinator->registerAgent('reasoner', $reasoningAgent, ['analysis', 'reasoning', 'logic']);

echo "Registered 2 specialized agents:\n";
echo "  - coder: coding, implementation, programming\n";
echo "  - reasoner: analysis, reasoning, logic\n\n";

// Run multiple tasks to build learning history
$tasks = [
    [
        'task' => 'Analyze the pros and cons of microservices architecture',
        'expected' => 'reasoner',
    ],
    [
        'task' => 'Write a function to calculate Fibonacci numbers',
        'expected' => 'coder',
    ],
    [
        'task' => 'Explain the logic behind binary search algorithm',
        'expected' => 'reasoner',
    ],
    [
        'task' => 'Implement a simple queue data structure in Python',
        'expected' => 'coder',
    ],
];

echo "Running tasks to build learning history...\n\n";

foreach ($tasks as $i => $taskInfo) {
    echo "Task " . ($i + 1) . ": " . substr($taskInfo['task'], 0, 60) . "...\n";
    
    $result = $coordinator->run($taskInfo['task']);
    
    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        $delegatedTo = $metadata['delegated_to'] ?? 'unknown';
        $duration = $metadata['duration'] ?? 0;
        
        $match = $delegatedTo === $taskInfo['expected'] ? '✓' : '✗';
        
        echo "  → Delegated to: {$delegatedTo} {$match}\n";
        echo "  → Duration: " . round($duration, 2) . "s\n";
        echo "  → ML enabled: " . ($metadata['ml_enabled'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "  → Failed: " . $result->getError() . "\n";
    }
    
    echo "\n";
    
    // Small delay to avoid rate limiting
    usleep(500000); // 0.5 seconds
}

// Show learning statistics
if (method_exists($coordinator, 'getLearningStats')) {
    $stats = $coordinator->getLearningStats();
    
    echo "=== Learning Statistics ===\n";
    echo "Total records: " . ($stats['total_records'] ?? 0) . "\n";
    echo "Success rate: " . round(($stats['success_rate'] ?? 0) * 100, 1) . "%\n";
    echo "Average quality: " . round($stats['avg_quality'] ?? 0, 2) . "\n";
    echo "\n";
}

// Show workload distribution
echo "=== Workload Distribution ===\n";
$workload = $coordinator->getWorkload();
foreach ($workload as $agentId => $count) {
    echo "{$agentId}: {$count} tasks\n";
}
echo "\n";

// Show performance metrics
echo "=== Agent Performance ===\n";
$performance = $coordinator->getPerformance();
foreach ($performance as $agentId => $metrics) {
    echo "{$agentId}:\n";
    echo "  Total tasks: " . $metrics['total_tasks'] . "\n";
    echo "  Success rate: " . round(($metrics['successful_tasks'] / max(1, $metrics['total_tasks'])) * 100, 1) . "%\n";
    echo "  Avg duration: " . round($metrics['average_duration'], 2) . "s\n";
}

echo "\n=== Example Complete ===\n";
echo "The coordinator has learned from these tasks.\n";
echo "Future similar tasks will benefit from this learning!\n";

