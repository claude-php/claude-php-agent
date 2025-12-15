<?php

/**
 * Classical AI Agents Example
 * 
 * Demonstrates ReflexAgent, ModelBasedAgent, UtilityBasedAgent, and LearningAgent.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\ReflexAgent;
use ClaudeAgents\Agents\ModelBasedAgent;
use ClaudeAgents\Agents\UtilityBasedAgent;
use ClaudeAgents\Agents\LearningAgent;
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

echo "Classical AI Agents Demo\n";
echo "========================\n\n";

// 1. Reflex Agent
echo "1. Reflex Agent (Rule-Based)\n";
echo "----------------------------\n";

$reflex = new ReflexAgent($client, ['use_llm_fallback' => true]);

$reflex->addRule('greeting', fn($input) => str_contains(strtolower($input), 'hello'), 
    fn() => 'Hello! How can I help you today?', priority: 10);

$reflex->addRule('goodbye', fn($input) => str_contains(strtolower($input), 'bye'), 
    'Goodbye! Have a great day!', priority: 10);

$reflex->addRule('help', 'help', 
    'I can answer questions and assist with tasks. What do you need?', priority: 5);

$result = $reflex->run("Hello there!");
echo "User: Hello there!\n";
echo "Agent: {$result->getAnswer()}\n\n";

// 2. Model-Based Agent
echo "2. Model-Based Agent (World State)\n";
echo "----------------------------------\n";

$modelBased = new ModelBasedAgent($client, [
    'initial_state' => [
        'location' => 'home',
        'inventory' => ['key', 'phone'],
        'time' => 'morning',
    ],
]);

$result = $modelBased->run("Goal: Go to work");
echo $result->getAnswer() . "\n\n";

// 3. Utility-Based Agent
echo "3. Utility-Based Agent (Decision Making)\n";
echo "----------------------------------------\n";

$utility = new UtilityBasedAgent($client);

// Add objectives
$utility->addObjective('value', fn($action) => $action['estimated_value'] ?? 50, weight: 0.7);
$utility->addObjective('cost', fn($action) => 100 - ($action['estimated_cost'] ?? 50), weight: 0.3);

$result = $utility->run("Choose the best approach for implementing a new feature");
echo $result->getAnswer() . "\n\n";

// 4. Learning Agent
echo "4. Learning Agent (Adaptive)\n";
echo "----------------------------\n";

$learning = new LearningAgent($client, [
    'initial_strategies' => ['analytical', 'creative', 'pragmatic'],
    'learning_rate' => 0.2,
]);

$result = $learning->run("Solve this problem: How to reduce system latency?");
echo "Strategy used: {$result->getMetadata()['strategy_used']}\n";
echo "Answer: " . substr($result->getAnswer(), 0, 200) . "...\n";

// Simulate feedback
$expId = $result->getMetadata()['experience_id'];
$learning->provideFeedback($expId, reward: 0.8, success: true);

echo "\nClassical AI Demo Complete!\n";

