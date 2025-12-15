#!/usr/bin/env php
<?php
/**
 * Task Prioritization Agent Example
 *
 * Demonstrates BabyAGI-style task generation, prioritization, and execution.
 * Shows how the agent dynamically creates subtasks, prioritizes them,
 * and works toward achieving a goal.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\TaskPrioritizationAgent;
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

echo "=== Task Prioritization Agent Example ===\n\n";

// Example 1: Basic Task Prioritization
echo "--- Example 1: Project Planning ---\n";
$agent = new TaskPrioritizationAgent($client, [
    'name' => 'project_planner',
]);

$result = $agent->run('Plan a simple blog website with user authentication');

if ($result->isSuccess()) {
    echo "✓ Task prioritization completed!\n\n";
    echo "Answer:\n";
    echo $result->getAnswer() . "\n\n";
    
    echo "Metadata:\n";
    echo "  - Goal: " . $result->getMetadata()['goal'] . "\n";
    echo "  - Tasks Completed: " . $result->getMetadata()['tasks_completed'] . "\n";
    echo "  - Tasks Remaining: " . $result->getMetadata()['tasks_remaining'] . "\n";
    echo "  - Iterations: " . $result->getIterations() . "\n";
} else {
    echo "✗ Task prioritization failed: " . $result->getError() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Example 2: Learning Path
echo "--- Example 2: Learning Path Creation ---\n";
$learningAgent = new TaskPrioritizationAgent($client, [
    'name' => 'learning_planner',
]);

$learningResult = $learningAgent->run('Create a learning path for mastering PHP design patterns');

if ($learningResult->isSuccess()) {
    echo "✓ Learning path created!\n\n";
    
    // Show first 500 characters of the result
    $answer = $learningResult->getAnswer();
    $preview = strlen($answer) > 500 ? substr($answer, 0, 500) . "..." : $answer;
    echo $preview . "\n\n";
    
    echo "Summary:\n";
    echo "  - Tasks Completed: " . $learningResult->getMetadata()['tasks_completed'] . "\n";
    echo "  - Tasks Remaining: " . $learningResult->getMetadata()['tasks_remaining'] . "\n";
} else {
    echo "✗ Learning path creation failed: " . $learningResult->getError() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Example 3: API Development Plan
echo "--- Example 3: API Development Plan ---\n";
$apiAgent = new TaskPrioritizationAgent($client, [
    'name' => 'api_planner',
    'goal' => 'Design and plan a REST API for a task management system',
]);

$apiResult = $apiAgent->run('Design and plan a REST API for a task management system');

if ($apiResult->isSuccess()) {
    echo "✓ API development plan created!\n\n";
    
    // Extract just the goal and task summary from the result
    $answer = $apiResult->getAnswer();
    $lines = explode("\n", $answer);
    
    // Show first 10 lines
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo $lines[$i] . "\n";
    }
    
    if (count($lines) > 10) {
        echo "\n... [truncated for brevity] ...\n";
    }
    
    echo "\nTasks Summary:\n";
    echo "  - Completed: " . $apiResult->getMetadata()['tasks_completed'] . "\n";
    echo "  - Remaining: " . $apiResult->getMetadata()['tasks_remaining'] . "\n";
    echo "  - Total Iterations: " . $apiResult->getIterations() . "\n";
} else {
    echo "✗ API planning failed: " . $apiResult->getError() . "\n";
}

echo "\n=== Examples Complete ===\n";

