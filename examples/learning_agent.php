#!/usr/bin/env php
<?php
/**
 * Learning Agent Example
 *
 * Demonstrates an agent that learns from feedback and adapts its strategies
 * based on experience. Shows how the agent improves over time by tracking
 * performance and adjusting its behavior.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\LearningAgent;
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

echo "=== Learning Agent Example ===\n\n";

// Create learning agent with multiple strategies
$agent = new LearningAgent($client, [
    'name' => 'adaptive_problem_solver',
    'learning_rate' => 0.1,
    'replay_buffer_size' => 100,
    'initial_strategies' => ['analytical', 'creative', 'systematic'],
]);

echo "Agent: {$agent->getName()}\n";
echo "Initial Strategies: " . implode(', ', array_keys($agent->getPerformance())) . "\n\n";

// Example 1: Solve a math problem
echo "--- Example 1: Math Problem ---\n";
$result1 = $agent->run('Calculate the area of a circle with radius 5');

if ($result1->isSuccess()) {
    echo "Response: " . substr($result1->getAnswer(), 0, 200) . "...\n";
    echo "Strategy Used: {$result1->getMetadata()['strategy_used']}\n";
    $expId1 = $result1->getMetadata()['experience_id'];
    
    // Provide positive feedback
    $agent->provideFeedback($expId1, 0.9, true, ['comment' => 'Correct answer']);
    echo "✓ Feedback recorded: Positive (0.9)\n\n";
} else {
    echo "Failed: {$result1->getError()}\n\n";
}

// Example 2: Creative task
echo "--- Example 2: Creative Task ---\n";
$result2 = $agent->run('Write a short tagline for a coffee shop called "Morning Brew"');

if ($result2->isSuccess()) {
    echo "Response: {$result2->getAnswer()}\n";
    echo "Strategy Used: {$result2->getMetadata()['strategy_used']}\n";
    $expId2 = $result2->getMetadata()['experience_id'];
    
    // Provide positive feedback
    $agent->provideFeedback($expId2, 0.8, true, ['comment' => 'Creative and catchy']);
    echo "✓ Feedback recorded: Positive (0.8)\n\n";
} else {
    echo "Failed: {$result2->getError()}\n\n";
}

// Example 3: Systematic task
echo "--- Example 3: Systematic Task ---\n";
$result3 = $agent->run('List the steps to deploy a web application');

if ($result3->isSuccess()) {
    echo "Response: " . substr($result3->getAnswer(), 0, 300) . "...\n";
    echo "Strategy Used: {$result3->getMetadata()['strategy_used']}\n";
    $expId3 = $result3->getMetadata()['experience_id'];
    
    // Provide positive feedback
    $agent->provideFeedback($expId3, 0.85, true, ['comment' => 'Well-structured steps']);
    echo "✓ Feedback recorded: Positive (0.85)\n\n";
} else {
    echo "Failed: {$result3->getError()}\n\n";
}

// Show learning progress
echo "--- Learning Progress ---\n";
$performance = $agent->getPerformance();
foreach ($performance as $strategy => $perf) {
    if ($perf['attempts'] > 0) {
        $successRate = ($perf['successes'] / $perf['attempts']) * 100;
        echo "$strategy:\n";
        echo "  Attempts: {$perf['attempts']}\n";
        echo "  Success Rate: " . number_format($successRate, 1) . "%\n";
        echo "  Avg Reward: " . number_format($perf['avg_reward'], 3) . "\n";
    }
}

// Show experience replay
echo "\n--- Experience Replay ---\n";
$experiences = $agent->getExperiences(3);
echo "Recent experiences: " . count($experiences) . "\n";
foreach ($experiences as $i => $exp) {
    if ($exp['reward'] !== null) {
        $outcome = $exp['success'] ? '✓' : '✗';
        echo ($i + 1) . ". $outcome Strategy: {$exp['strategy']}, Reward: {$exp['reward']}\n";
    }
}

echo "\n--- Adding New Strategy ---\n";
$agent->addStrategy('adaptive');
echo "Added 'adaptive' strategy\n";
echo "Current strategies: " . implode(', ', array_keys($agent->getPerformance())) . "\n";

// Run a few more tasks to see strategy selection
echo "\n--- Strategy Selection Demonstration ---\n";
for ($i = 1; $i <= 3; $i++) {
    $result = $agent->run("Task $i: Solve a simple problem");
    if ($result->isSuccess()) {
        echo "Task $i - Strategy: {$result->getMetadata()['strategy_used']}\n";
        $expId = $result->getMetadata()['experience_id'];
        $agent->provideFeedback($expId, 0.7, true);
    }
}

echo "\n✓ Learning agent example completed successfully!\n";
echo "Total experiences recorded: " . count($agent->getExperiences()) . "\n";

