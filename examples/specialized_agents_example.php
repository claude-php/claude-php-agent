<?php

/**
 * Specialized Role Agents Example
 * 
 * Demonstrates EnvironmentSimulatorAgent, SolutionDiscriminatorAgent, 
 * and MemoryManagerAgent.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\EnvironmentSimulatorAgent;
use ClaudeAgents\Agents\SolutionDiscriminatorAgent;
use ClaudeAgents\Agents\MemoryManagerAgent;
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

echo "Specialized Role Agents Demo\n";
echo "============================\n\n";

// 1. Environment Simulator Agent
echo "1. Environment Simulator Agent\n";
echo "------------------------------\n";

$simulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => [
        'server_count' => 3,
        'load_balancer' => 'active',
        'database' => 'online',
        'cache' => 'empty',
    ],
]);

$simulation = $simulator->simulateAction("Add 2 more servers and enable caching");
echo "Action: Add 2 more servers and enable caching\n";
echo "Initial State: " . json_encode($simulation['initial_state']) . "\n";
echo "Resulting State: " . json_encode($simulation['resulting_state']) . "\n";
echo "Success Probability: " . ($simulation['success_probability'] * 100) . "%\n";
echo "Outcome: {$simulation['outcome']}\n\n";

// 2. Solution Discriminator Agent
echo "2. Solution Discriminator Agent\n";
echo "--------------------------------\n";

$discriminator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['correctness', 'efficiency', 'maintainability'],
]);

$solutions = [
    ['id' => 'A', 'description' => 'Use bubble sort algorithm'],
    ['id' => 'B', 'description' => 'Use quicksort algorithm'],
    ['id' => 'C', 'description' => 'Use built-in sort function'],
];

$evaluations = $discriminator->evaluateSolutions($solutions, 'Sort an array of 10,000 integers');

echo "Evaluated solutions:\n";
foreach ($evaluations as $eval) {
    echo "Solution {$eval['solution_id']}: Score = {$eval['total_score']}\n";
    echo "  Scores: " . json_encode($eval['scores']) . "\n";
}
echo "\n";

// 3. Memory Manager Agent
echo "3. Memory Manager Agent\n";
echo "-----------------------\n";

$memory = new MemoryManagerAgent($client);

// Store memories
$id1 = $memory->store("The database server IP is 192.168.1.100");
$id2 = $memory->store("API rate limit is 1000 requests per hour");
$id3 = $memory->store("Deployment happens every Friday at 6 PM");

echo "Stored 3 memories\n";
echo "Memory count: {$memory->getMemoryCount()}\n\n";

// Retrieve a memory
$content = $memory->retrieve($id1);
echo "Retrieved memory {$id1}: {$content}\n\n";

// Search memories
$searchResults = $memory->search("What is the rate limit?");
echo "Search results for 'What is the rate limit?':\n";
foreach ($searchResults as $result) {
    echo "  [{$result['id']}] {$result['content']}\n";
}

echo "\nSpecialized Agents Demo Complete!\n";

