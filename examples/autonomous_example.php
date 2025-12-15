#!/usr/bin/env php
<?php
/**
 * Autonomous Agent Example
 *
 * Demonstrates goal-oriented autonomous agent with state persistence.
 * Shows how agents can maintain state across multiple sessions and
 * track progress towards a defined goal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\AutonomousAgent;
use ClaudePhp\ClaudePhp;

// Check for API key
$apiKey = getenv('ANTHROPIC_API_KEY');
if (empty($apiKey)) {
    echo "Error: ANTHROPIC_API_KEY environment variable is not set.\n";
    echo "Please set your API key: export ANTHROPIC_API_KEY='your-key-here'\n";
    exit(1);
}

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

echo "=== Autonomous Agent Example ===\n\n";

// Create autonomous agent with goal
$agent = new AutonomousAgent($client, [
    'goal' => 'Plan a project for building a PHP REST API',
    'state_file' => './agent_state.json',
    'max_actions_per_session' => 5,
]);

echo "Goal: " . $agent->getGoal() . "\n";
echo "Progress: " . $agent->getProgress() . "%\n\n";

// Run first session
echo "--- Session 1 ---\n";
$result1 = $agent->runSession('Start planning the API project. Define the scope and objectives.');

if ($result1->isSuccess()) {
    echo "Agent Action:\n" . substr($result1->getAnswer(), 0, 300) . "...\n\n";
    echo "Progress: " . $result1->getMetadata()['goal_progress'] . "%\n";
} else {
    echo "Failed: " . $result1->getError() . "\n";
}

// Show state persistence
echo "\n--- State Persistence ---\n";
$state = $agent->getState();
echo "Session Number: " . $state->getSessionNumber() . "\n";
echo "Conversation Messages: " . count($state->getConversationHistory()) . "\n";
echo "Action History: " . count($state->getActionHistory()) . "\n";

echo "\nSuccess: " . ($result1->isSuccess() ? 'Yes' : 'No') . "\n";
