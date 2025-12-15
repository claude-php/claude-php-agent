#!/usr/bin/env php
<?php
/**
 * Hierarchical Agent Example
 *
 * Demonstrates the master-worker pattern with specialized agents
 * coordinated by a master agent.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\HierarchicalAgent;
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
echo "║                   Hierarchical Agent Example                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create specialized worker agents
$mathAgent = new WorkerAgent($client, [
    'name' => 'math_agent',
    'specialty' => 'mathematical calculations, statistics, and numerical analysis',
    'system' => 'You are a mathematics expert. Provide precise calculations and clear explanations of mathematical concepts.',
]);

$writingAgent = new WorkerAgent($client, [
    'name' => 'writing_agent',
    'specialty' => 'writing, editing, and content creation',
    'system' => 'You are a professional writer. Create clear, engaging, and well-structured content.',
]);

$researchAgent = new WorkerAgent($client, [
    'name' => 'research_agent',
    'specialty' => 'research, analysis, and information synthesis',
    'system' => 'You are a research analyst. Gather information, analyze data, and provide comprehensive insights.',
]);

// Create the master agent
$master = new HierarchicalAgent($client, [
    'name' => 'master_coordinator',
]);

// Register workers
$master->registerWorker('math_agent', $mathAgent);
$master->registerWorker('writing_agent', $writingAgent);
$master->registerWorker('research_agent', $researchAgent);

echo "Registered workers:\n";
foreach ($master->getWorkerNames() as $name) {
    $worker = $master->getWorker($name);
    echo "  • {$name}: {$worker->getSpecialty()}\n";
}
echo "\n";

// Complex task that requires multiple specialists
$task = "Calculate the average of 45, 67, 89, and 123, then write a brief paragraph explaining what an average represents and why it's useful in everyday life.";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Complex Task:\n{$task}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$result = $master->run($task);

if ($result->isSuccess()) {
    echo "✅ Final Answer:\n";
    echo str_repeat("-", 80) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "📊 Execution Stats:\n";
    echo "  • Iterations: {$result->getIterations()}\n";
    echo "  • Subtasks: " . ($metadata['subtasks'] ?? 'N/A') . "\n";
    echo "  • Workers used: " . implode(', ', $metadata['workers_used'] ?? []) . "\n";
    echo "  • Duration: " . ($metadata['duration_seconds'] ?? 'N/A') . " seconds\n";
    
    $usage = $result->getTokenUsage();
    echo "  • Tokens: {$usage['total']} total ({$usage['input']} in, {$usage['output']} out)\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "Hierarchical agent example completed!\n";

