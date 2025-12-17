<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../load-env.php';

use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\ML\EnsembleLearning;
use ClaudeAgents\Tools\BuiltIn\CalculatorTool;
use ClaudePhp\ClaudePhp;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

loadEnv(__DIR__ . '/../..');

$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    die('ANTHROPIC_API_KEY not found');
}

$client = new ClaudePhp($apiKey);
$logger = new \Psr\Log\NullLogger();

echo "=== EnsembleLearning - Combining Multiple Agents ===\n\n";

$ensemble = new EnsembleLearning([
    'logger' => $logger,
    'client' => $client,
    'history_store_path' => __DIR__ . '/../../storage/ensemble_history.json',
]);

// Create a diverse set of agents with different characteristics
$agents = [
    'fast' => new ReactAgent($client, [
        'name' => 'FastAgent',
        'max_iterations' => 3,
        'tools' => [CalculatorTool::create()],
    ]),
    'balanced' => new ReactAgent($client, [
        'name' => 'BalancedAgent',
        'max_iterations' => 5,
        'tools' => [CalculatorTool::create()],
    ]),
    'thorough' => new ReactAgent($client, [
        'name' => 'ThoroughAgent',
        'max_iterations' => 10,
        'tools' => [CalculatorTool::create()],
    ]),
];

echo "Ensemble Agents:\n";
foreach (array_keys($agents) as $agentId) {
    echo "  - {$agentId}\n";
}
echo "\n";

$tasks = [
    "Calculate: (15 + 25) * 3",
    "What is 20% of 450?",
    "If I have 3 apples and buy 5 more, then give away 2, how many do I have?",
];

// Demonstrate different ensemble strategies
$strategies = [
    'voting' => 'Simple majority voting',
    'weighted_voting' => 'Weighted by historical performance',
    'best_of_n' => 'Select single best result',
];

foreach ($tasks as $taskNum => $task) {
    echo "\n=== Task " . ($taskNum + 1) . " ===\n";
    echo "Question: {$task}\n\n";

    foreach ($strategies as $strategy => $description) {
        echo "Strategy: {$strategy} ({$description})\n";
        
        $result = $ensemble->combine($task, $agents, ['strategy' => $strategy]);
        
        if ($result->isSuccess()) {
            $metadata = $result->getMetadata();
            echo "Answer: {$result->getAnswer()}\n";
            echo "Confidence: " . ($metadata['confidence'] ?? 'N/A') . "\n";
            
            if (isset($metadata['selected_agent'])) {
                echo "Selected Agent: {$metadata['selected_agent']}\n";
            } elseif (isset($metadata['voting_agents'])) {
                echo "Voting Agents: " . implode(', ', $metadata['voting_agents']) . "\n";
            }
        } else {
            echo "Error: {$result->getError()}\n";
        }
        
        echo "---\n";
    }
}

echo "\n=== Statistics ===\n";
$stats = $ensemble->getStatistics();
echo "Total Ensemble Runs: {$stats['total_tasks']}\n";
echo "Success Rate: " . round($stats['success_rate'] * 100) . "%\n";
echo "Average Quality: " . round($stats['avg_quality'], 2) . "/10\n";

echo "\n--- Ensemble Learning Complete ---\n";
echo "Multiple agents combined for improved accuracy and reliability!\n";

