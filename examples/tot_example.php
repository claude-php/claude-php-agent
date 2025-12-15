#!/usr/bin/env php
<?php
/**
 * Tree of Thoughts (ToT) Example
 *
 * Demonstrates tree-based exploration for complex problem solving.
 * Shows different search strategies with branching and evaluation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    die("Error: ANTHROPIC_API_KEY environment variable not set\n");
}

$client = new ClaudePhp(apiKey: $apiKey);

echo "=== Tree-of-Thoughts Examples ===\n\n";

// Example 1: Best-first search
echo "--- Example 1: Best-First Search ---\n";
echo "Strategy: Explores the most promising branches first\n\n";

$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
    'name' => 'tot_best_first',
]);

$task = "Use the numbers 3, 5, 7, 11 with basic operations (+, -, *, /) to make 24";

echo "Task: {$task}\n\n";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "Result:\n" . $result->getAnswer() . "\n";
    
    $metadata = $result->getMetadata();
    echo "\nMetadata:\n";
    echo "  Strategy: " . $metadata['strategy'] . "\n";
    echo "  Total Nodes: " . $metadata['total_nodes'] . "\n";
    echo "  Max Depth: " . $metadata['max_depth'] . "\n";
    echo "  Path Length: " . $metadata['path_length'] . "\n";
    echo "  Best Score: " . round($metadata['best_score'], 2) . "\n";
    echo "  Tokens Used: " . ($metadata['tokens']['input'] + $metadata['tokens']['output']) . "\n";
} else {
    echo "Error: " . $result->getError() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Example 2: Breadth-first search
echo "--- Example 2: Breadth-First Search ---\n";
echo "Strategy: Explores all branches at each level before going deeper\n\n";

$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 2,
    'search_strategy' => 'breadth_first',
    'name' => 'tot_breadth_first',
]);

$task = "Design a simple mobile app for grocery shopping. What are the key features?";

echo "Task: {$task}\n\n";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "Result:\n" . $result->getAnswer() . "\n";
    
    $metadata = $result->getMetadata();
    echo "\nMetadata:\n";
    echo "  Strategy: " . $metadata['strategy'] . "\n";
    echo "  Total Nodes: " . $metadata['total_nodes'] . "\n";
    echo "  Max Depth: " . $metadata['max_depth'] . "\n";
} else {
    echo "Error: " . $result->getError() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Example 3: Depth-first search
echo "--- Example 3: Depth-First Search ---\n";
echo "Strategy: Explores one branch fully before moving to others\n\n";

$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 3,
    'search_strategy' => 'depth_first',
    'name' => 'tot_depth_first',
]);

$task = "Plan a 3-day trip to San Francisco. What should I prioritize?";

echo "Task: {$task}\n\n";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "Result:\n" . $result->getAnswer() . "\n";
    
    $metadata = $result->getMetadata();
    echo "\nMetadata:\n";
    echo "  Strategy: " . $metadata['strategy'] . "\n";
    echo "  Total Nodes: " . $metadata['total_nodes'] . "\n";
} else {
    echo "Error: " . $result->getError() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Example 4: Complex problem solving
echo "--- Example 4: Complex Problem Solving ---\n";
echo "Strategy: Best-first with deeper exploration\n\n";

$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 4,
    'search_strategy' => 'best_first',
    'name' => 'tot_complex',
]);

$task = "How can a small e-commerce business reduce shipping costs while maintaining customer satisfaction?";

echo "Task: {$task}\n\n";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "Result:\n" . $result->getAnswer() . "\n";
    
    $metadata = $result->getMetadata();
    echo "\nMetadata:\n";
    echo "  Strategy: " . $metadata['strategy'] . "\n";
    echo "  Total Nodes: " . $metadata['total_nodes'] . "\n";
    echo "  Max Depth: " . $metadata['max_depth'] . "\n";
    echo "  Path Length: " . $metadata['path_length'] . "\n";
    echo "  Best Score: " . round($metadata['best_score'], 2) . "/10\n";
} else {
    echo "Error: " . $result->getError() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

echo "=== Tree-of-Thoughts Complete ===\n\n";

echo "Key Takeaways:\n";
echo "  - Best-first: Most efficient for finding optimal solutions\n";
echo "  - Breadth-first: Good for exploring all options at each level\n";
echo "  - Depth-first: Useful for following one line of reasoning deeply\n";
echo "  - Branch count and max depth control exploration vs. token cost\n";
echo "\n";

