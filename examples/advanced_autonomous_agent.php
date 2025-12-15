#!/usr/bin/env php
<?php
/**
 * Advanced Autonomous Agent Example
 *
 * Demonstrates advanced features of the autonomous agent:
 * - Running multiple sessions
 * - Tracking subgoals
 * - State persistence and recovery
 * - Custom state file location
 * - Progress monitoring
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

echo "=== Advanced Autonomous Agent Example ===\n\n";

// Define a complex goal
$goal = 'Create a comprehensive plan for building a microservices architecture ' .
        'including service design, API gateway, authentication, and deployment strategy';

// Create autonomous agent with custom configuration
$agent = new AutonomousAgent($client, [
    'goal' => $goal,
    'state_file' => sys_get_temp_dir() . '/autonomous_agent_advanced.json',
    'name' => 'microservices_planner',
    'max_actions_per_session' => 10,
]);

echo "Agent: {$agent->getName()}\n";
echo "Goal: {$agent->getGoal()}\n";
echo "Initial Progress: {$agent->getProgress()}%\n\n";

// Session 1: Initial planning
echo "=== Session 1: Initial Planning ===\n";
$result1 = $agent->runSession(
    'Start by breaking down the microservices architecture into key components and defining the scope.'
);

if ($result1->isSuccess()) {
    echo "✓ Session completed successfully\n";
    echo "Progress: {$result1->getMetadata()['goal_progress']}%\n";
    echo "Actions taken: {$result1->getMetadata()['actions_this_session']}\n\n";
    
    // Show a snippet of the response
    $answer = $result1->getAnswer();
    echo "Agent's response (first 300 chars):\n";
    echo substr($answer, 0, 300) . "...\n\n";
} else {
    echo "✗ Session failed: {$result1->getError()}\n\n";
    exit(1);
}

// Show state information
echo "=== State Information ===\n";
$state = $agent->getState();
echo "Session Number: {$state->getSessionNumber()}\n";
echo "Conversation History: " . count($state->getConversationHistory()) . " messages\n";
echo "Action History: " . count($state->getActionHistory()) . " actions\n";
echo "Created At: " . date('Y-m-d H:i:s', $state->getCreatedAt()) . "\n";
echo "Updated At: " . date('Y-m-d H:i:s', $state->getUpdatedAt()) . "\n\n";

// Session 2: Detailed design
echo "=== Session 2: Service Design ===\n";
$agent->getState()->incrementSession();
$result2 = $agent->runSession(
    'Now focus on designing individual microservices. Define service boundaries and interactions.'
);

if ($result2->isSuccess()) {
    echo "✓ Session completed successfully\n";
    echo "Progress: {$result2->getMetadata()['goal_progress']}%\n";
    echo "Session Number: {$result2->getMetadata()['session_number']}\n\n";
} else {
    echo "✗ Session failed: {$result2->getError()}\n\n";
}

// Demonstrate state persistence
echo "=== Testing State Persistence ===\n";
$stateFile = sys_get_temp_dir() . '/autonomous_agent_advanced.json';
echo "State file: {$stateFile}\n";
echo "State file exists: " . (file_exists($stateFile) ? 'Yes' : 'No') . "\n";

if (file_exists($stateFile)) {
    echo "State file size: " . filesize($stateFile) . " bytes\n";
    
    // Load state in a new agent instance
    $newAgent = new AutonomousAgent($client, [
        'goal' => $goal,
        'state_file' => $stateFile,
    ]);
    
    echo "Loaded agent progress: {$newAgent->getProgress()}%\n";
    echo "State successfully persisted and recovered!\n\n";
}

// Show final status
echo "=== Final Status ===\n";
echo "Goal: {$agent->getGoal()}\n";
echo "Progress: {$agent->getProgress()}%\n";
echo "Goal Complete: " . ($agent->isGoalComplete() ? 'Yes' : 'No') . "\n";
echo "Total Sessions: {$agent->getState()->getSessionNumber()}\n";
echo "Total Actions: " . count($agent->getState()->getActionHistory()) . "\n\n";

// Demonstrate runUntilComplete (commented out to avoid long runs)
echo "=== Run Until Complete (Example - Not Executed) ===\n";
echo "To run the agent until goal completion:\n";
echo "\$results = \$agent->runUntilComplete(maxSessions: 10);\n";
echo "This will run up to 10 sessions or until the goal is complete.\n\n";

// Cleanup option
echo "=== Cleanup ===\n";
echo "To reset the agent state:\n";
echo "// \$agent->reset();\n";
echo "To delete the state file:\n";
echo "// unlink('{$stateFile}');\n\n";

echo "Example completed successfully!\n";

