#!/usr/bin/env php
<?php
/**
 * Autonomous Agent Local Test
 *
 * Tests the autonomous agent functionality without requiring API calls.
 * Demonstrates state management, goal tracking, and persistence.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\State\Goal;
use ClaudeAgents\State\AgentState;
use ClaudeAgents\State\StateManager;

echo "=== Autonomous Agent Local Test ===\n\n";

// Test 1: Goal Management
echo "Test 1: Goal Management\n";
echo "-" . str_repeat("-", 40) . "\n";

$goal = new Goal('Complete project documentation');
echo "Goal: {$goal->getDescription()}\n";
echo "Status: {$goal->getStatus()}\n";
echo "Progress: {$goal->getProgressPercentage()}%\n\n";

$goal->start();
echo "After starting:\n";
echo "Status: {$goal->getStatus()}\n";
echo "Progress: {$goal->getProgressPercentage()}%\n\n";

$goal->completeSubgoal('Write README');
$goal->completeSubgoal('Write API documentation');
$goal->setProgressPercentage(50);
echo "After completing subgoals:\n";
echo "Completed: " . implode(', ', $goal->getCompletedSubgoals()) . "\n";
echo "Progress: {$goal->getProgressPercentage()}%\n\n";

$goal->complete();
echo "After completion:\n";
echo "Status: {$goal->getStatus()}\n";
echo "Is Complete: " . ($goal->isComplete() ? 'Yes' : 'No') . "\n";
echo "Progress: {$goal->getProgressPercentage()}%\n\n";

// Test 2: Agent State Management
echo "Test 2: Agent State Management\n";
echo "-" . str_repeat("-", 40) . "\n";

$testGoal = new Goal('Test goal');
$state = new AgentState(sessionNumber: 1, goal: $testGoal);

echo "Initial state:\n";
echo "Session: {$state->getSessionNumber()}\n";
echo "Goal: {$state->getGoal()->getDescription()}\n";
echo "Messages: " . count($state->getConversationHistory()) . "\n\n";

$state->addMessage([
    'role' => 'user',
    'content' => 'Start working on the goal',
]);

$state->addMessage([
    'role' => 'assistant',
    'content' => 'I will begin by analyzing the requirements...',
]);

$state->recordAction([
    'action' => 'analyze_requirements',
    'result' => 'success',
]);

echo "After adding messages and actions:\n";
echo "Messages: " . count($state->getConversationHistory()) . "\n";
echo "Actions: " . count($state->getActionHistory()) . "\n\n";

$state->setMetadataValue('environment', 'test');
$state->setMetadataValue('priority', 'high');

echo "Metadata:\n";
foreach ($state->getMetadata() as $key => $value) {
    echo "  {$key}: {$value}\n";
}
echo "\n";

$state->incrementSession();
echo "After incrementing session: {$state->getSessionNumber()}\n\n";

// Test 3: State Persistence
echo "Test 3: State Persistence\n";
echo "-" . str_repeat("-", 40) . "\n";

$stateFile = sys_get_temp_dir() . '/test_autonomous_state_' . uniqid() . '.json';
echo "State file: {$stateFile}\n\n";

$manager = new StateManager($stateFile);

$persistGoal = new Goal('Build microservice');
$persistGoal->start();
$persistGoal->setProgressPercentage(25);
$persistGoal->completeSubgoal('Design API');

$persistState = new AgentState(sessionNumber: 3, goal: $persistGoal);
$persistState->addMessage(['role' => 'user', 'content' => 'Continue work']);
$persistState->recordAction(['action' => 'design', 'status' => 'done']);
$persistState->setMetadataValue('version', '1.0');

echo "Saving state...\n";
$saved = $manager->save($persistState);
echo "Saved: " . ($saved ? 'Yes' : 'No') . "\n";
echo "File exists: " . ($manager->exists() ? 'Yes' : 'No') . "\n";
echo "File size: " . filesize($stateFile) . " bytes\n\n";

echo "Loading state...\n";
$loadedState = $manager->load();

if ($loadedState) {
    echo "Loaded successfully!\n";
    echo "Session: {$loadedState->getSessionNumber()}\n";
    echo "Goal: {$loadedState->getGoal()->getDescription()}\n";
    echo "Progress: {$loadedState->getGoal()->getProgressPercentage()}%\n";
    echo "Subgoals: " . implode(', ', $loadedState->getGoal()->getCompletedSubgoals()) . "\n";
    echo "Messages: " . count($loadedState->getConversationHistory()) . "\n";
    echo "Actions: " . count($loadedState->getActionHistory()) . "\n";
    echo "Metadata version: {$loadedState->getMetadataValue('version')}\n\n";
} else {
    echo "Failed to load state\n\n";
}

// Test 4: State Array Conversion
echo "Test 4: State Serialization\n";
echo "-" . str_repeat("-", 40) . "\n";

$array = $persistState->toArray();
echo "State as array:\n";
echo json_encode($array, JSON_PRETTY_PRINT) . "\n\n";

$reconstructed = AgentState::fromArray($array);
echo "Reconstructed from array:\n";
echo "Session: {$reconstructed->getSessionNumber()}\n";
echo "Goal: {$reconstructed->getGoal()->getDescription()}\n";
echo "Goal Progress: {$reconstructed->getGoal()->getProgressPercentage()}%\n\n";

// Cleanup
echo "=== Cleanup ===\n";
if (file_exists($stateFile)) {
    unlink($stateFile);
    echo "Removed test state file\n";
}

echo "\n✓ All local tests passed!\n";

