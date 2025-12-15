<?php

/**
 * Async Multi-Agent Example
 * 
 * Demonstrates parallel agent execution using AMPHP.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\MultiAgent\AsyncCollaborationManager;
use ClaudeAgents\MultiAgent\SimpleCollaborativeAgent;
use ClaudePhp\ClaudePhp;

if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    $_ENV['ANTHROPIC_API_KEY'] = $env['ANTHROPIC_API_KEY'] ?? '';
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
if (empty($apiKey)) {
    die("Error: ANTHROPIC_API_KEY not set\n");
}

$client = new ClaudePhp($apiKey);

echo "Async Multi-Agent Collaboration Demo\n";
echo "======================================\n\n";

// 1. Parallel Execution
echo "1. Parallel Agent Execution\n";
echo "---------------------------\n";

$manager = new AsyncCollaborationManager($client, [
    'max_concurrent' => 3,
]);

// Create three specialized agents
$researcher = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'researcher',
    capabilities: ['research'],
    options: ['system_prompt' => 'You research topics quickly and concisely.']
);

$analyst = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'analyst',
    capabilities: ['analysis'],
    options: ['system_prompt' => 'You analyze data efficiently.']
);

$writer = new SimpleCollaborativeAgent(
    client: $client,
    agentId: 'writer',
    capabilities: ['writing'],
    options: ['system_prompt' => 'You write clear summaries.']
);

$manager->registerAgent('researcher', $researcher, ['research']);
$manager->registerAgent('analyst', $analyst, ['analysis']);
$manager->registerAgent('writer', $writer, ['writing']);

// Execute different tasks in parallel
$startTime = microtime(true);

$results = $manager->executeParallel([
    'researcher' => 'What are the key features of PHP 8.3?',
    'analyst' => 'What are the performance benefits of JIT compilation?',
    'writer' => 'Summarize the evolution of PHP from 7.0 to 8.3',
]);

$duration = microtime(true) - $startTime;

echo "Completed {count($results)} tasks in parallel\n";
echo "Total time: " . round($duration, 2) . " seconds\n";

foreach ($results as $agentId => $result) {
    if ($result->isSuccess()) {
        echo "\n{$agentId}: " . substr($result->getAnswer(), 0, 100) . "...\n";
    }
}

// 2. Agent Race
echo "\n\n2. Agent Race (First to Complete)\n";
echo "----------------------------------\n";

$startTime = microtime(true);

$winner = $manager->race([
    'researcher' => 'Quick: What is PHP?',
    'analyst' => 'Quick: What is PHP?',
    'writer' => 'Quick: What is PHP?',
]);

$duration = microtime(true) - $startTime;

echo "Winner: {$winner['agent_id']}\n";
echo "Time: " . round($duration, 2) . " seconds\n";
echo "Answer: " . substr($winner['result']->getAnswer(), 0, 150) . "...\n";

// 3. Parallel Collaboration
echo "\n\n3. Parallel Collaboration\n";
echo "-------------------------\n";

$startTime = microtime(true);

$result = $manager->collaborateParallel(
    task: 'Research PHP 8.3 features, analyze their impact, and write a summary report',
    parallelAgents: 3
);

$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    echo "Collaboration complete in " . round($duration, 2) . " seconds\n";
    echo "Subtasks completed: {$result->getMetadata()['subtasks_completed']}\n";
    echo "Agents used: " . implode(', ', $result->getMetadata()['agents_used']) . "\n";
    echo "\nFinal result:\n";
    echo substr($result->getAnswer(), 0, 300) . "...\n";
}

// 4. Batched Execution
echo "\n\n4. Batched Execution\n";
echo "--------------------\n";

// Create many tasks
$tasks = [];
for ($i = 1; $i <= 9; $i++) {
    $agentId = ['researcher', 'analyst', 'writer'][$i % 3];
    $tasks[$agentId . "_task{$i}"] = "Quick task {$i}: List one benefit of PHP";
}

// Note: Need to register these task-specific agents first
foreach ($tasks as $taskId => $task) {
    [$agentType, ] = explode('_', $taskId);
    if (!isset($registered[$taskId])) {
        $agent = new SimpleCollaborativeAgent($client, $taskId, [$agentType]);
        $manager->registerAgent($taskId, $agent);
        $registered[$taskId] = true;
    }
}

$startTime = microtime(true);
$results = $manager->executeBatched($tasks);
$duration = microtime(true) - $startTime;

echo "Executed " . count($results) . " tasks in batches\n";
echo "Total time: " . round($duration, 2) . " seconds\n";
echo "Success rate: " . round(count(array_filter($results, fn($r) => $r->isSuccess())) / count($results) * 100) . "%\n";

// 5. Shared Memory with Parallel Agents
echo "\n\n5. Shared Memory Coordination\n";
echo "------------------------------\n";

$memory = $manager->getSharedMemory();
$memory->write('counter', 0, 'system');

// Each agent increments the counter
$tasks = [
    'researcher' => 'Increment the shared counter',
    'analyst' => 'Increment the shared counter',
    'writer' => 'Increment the shared counter',
];

$results = $manager->executeParallel($tasks);

// Simulate counter increments during parallel execution
$memory->increment('counter', 'researcher');
$memory->increment('counter', 'analyst');
$memory->increment('counter', 'writer');

$finalCount = $memory->read('counter', 'system');
echo "Final counter value: {$finalCount}\n";

$stats = $memory->getStatistics();
echo "Shared memory operations: {$stats['total_operations']}\n";
echo "Unique agents: {$stats['unique_agents']}\n";

echo "\n\nAsync Multi-Agent Demo Complete!\n";

