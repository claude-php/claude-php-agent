#!/usr/bin/env php
<?php
/**
 * Coordinator Agent Example
 *
 * Demonstrates the CoordinatorAgent that intelligently delegates tasks
 * to specialized agents based on their capabilities and workload.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudeAgents\Agents\WorkerAgent;
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
echo "║                     Coordinator Agent Example                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "The Coordinator Agent intelligently delegates tasks to specialized agents\n";
echo "based on their capabilities and current workload.\n\n";

// Create the coordinator
$coordinator = new CoordinatorAgent($client, [
    'name' => 'task_coordinator',
]);

echo "Creating specialized worker agents...\n";

// Create specialized worker agents
$coderAgent = new WorkerAgent($client, [
    'name' => 'coder',
    'specialty' => 'software development, coding, and implementation',
    'system' => 'You are an expert software developer. Write clean, efficient code with best practices.',
]);

$testerAgent = new WorkerAgent($client, [
    'name' => 'tester',
    'specialty' => 'testing, quality assurance, and test automation',
    'system' => 'You are a QA expert. Create comprehensive test cases and identify potential issues.',
]);

$writerAgent = new WorkerAgent($client, [
    'name' => 'writer',
    'specialty' => 'documentation, technical writing, and content creation',
    'system' => 'You are a technical writer. Create clear, comprehensive documentation.',
]);

$researcherAgent = new WorkerAgent($client, [
    'name' => 'researcher',
    'specialty' => 'research, analysis, and information gathering',
    'system' => 'You are a research analyst. Gather and analyze information thoroughly.',
]);

// Register agents with their capabilities
$coordinator->registerAgent('coder', $coderAgent, ['coding', 'implementation', 'programming', 'software']);
$coordinator->registerAgent('tester', $testerAgent, ['testing', 'quality assurance', 'qa', 'test cases']);
$coordinator->registerAgent('writer', $writerAgent, ['documentation', 'writing', 'technical writing']);
$coordinator->registerAgent('researcher', $researcherAgent, ['research', 'analysis', 'investigation']);

echo "\nRegistered agents:\n";
foreach ($coordinator->getAgentIds() as $id) {
    $capabilities = implode(', ', $coordinator->getAgentCapabilities($id));
    echo "  • {$id}: {$capabilities}\n";
}
echo "\n";

// Example tasks
$tasks = [
    "Write a Python function to calculate the Fibonacci sequence",
    "Create test cases for a login authentication system",
    "Write documentation for a REST API endpoint",
    "Research the benefits of microservices architecture",
    "Implement a binary search algorithm in PHP",
    "Design unit tests for a shopping cart feature",
];

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Delegating Tasks\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($tasks as $i => $task) {
    $taskNum = $i + 1;
    echo "Task {$taskNum}: {$task}\n";
    echo str_repeat("-", 80) . "\n";

    $result = $coordinator->run($task);

    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        $delegatedTo = $metadata['delegated_to'] ?? 'unknown';
        $duration = $metadata['duration'] ?? 0;
        
        echo "✓ Delegated to: {$delegatedTo}\n";
        echo "  Duration: " . round($duration, 3) . "s\n";
        echo "  Requirements: " . implode(', ', $metadata['requirements'] ?? []) . "\n";
        echo "\n  Result Preview:\n";
        echo "  " . substr($result->getAnswer(), 0, 150) . "...\n";
    } else {
        echo "✗ Failed: {$result->getError()}\n";
    }
    
    echo "\n";
}

// Show workload distribution
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Workload Distribution (Load Balancing)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$workload = $coordinator->getWorkload();
$maxLoad = max($workload);

foreach ($workload as $agent => $load) {
    $bar = str_repeat('█', (int)(($load / max($maxLoad, 1)) * 30));
    echo sprintf("%-15s [%-30s] %d tasks\n", $agent, $bar, $load);
}

// Show performance metrics
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Performance Metrics\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$performance = $coordinator->getPerformance();

foreach ($performance as $agent => $perf) {
    if ($perf['total_tasks'] > 0) {
        $successRate = ($perf['successful_tasks'] / $perf['total_tasks']) * 100;
        $avgDuration = round($perf['average_duration'], 3);
        
        echo "{$agent}:\n";
        echo "  Total tasks: {$perf['total_tasks']}\n";
        echo "  Success rate: " . round($successRate, 1) . "%\n";
        echo "  Avg duration: {$avgDuration}s\n\n";
    }
}

echo str_repeat("═", 80) . "\n";
echo "Coordinator agent example completed!\n";
echo "\nKey Features Demonstrated:\n";
echo "  ✓ Intelligent task delegation based on capabilities\n";
echo "  ✓ Automatic load balancing across agents\n";
echo "  ✓ Performance tracking and metrics\n";
echo "  ✓ Workload distribution monitoring\n";

