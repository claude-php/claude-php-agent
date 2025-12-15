#!/usr/bin/env php
<?php
/**
 * Advanced Learning Agent Example
 *
 * Demonstrates advanced features of the learning agent including:
 * - Custom feedback loops
 * - Strategy evolution
 * - Experience replay analysis
 * - Performance optimization
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\LearningAgent;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Check for API key
$apiKey = getenv('ANTHROPIC_API_KEY');
if (empty($apiKey)) {
    echo "Error: ANTHROPIC_API_KEY environment variable is not set.\n";
    echo "Please set your API key: export ANTHROPIC_API_KEY='your-key-here'\n";
    exit(1);
}

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

// Set up logging
$logger = new Logger('learning_agent');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

echo "=== Advanced Learning Agent Example ===\n\n";

// Create learning agent with custom configuration
$agent = new LearningAgent($client, [
    'name' => 'advanced_learner',
    'learning_rate' => 0.15,
    'replay_buffer_size' => 500,
    'initial_strategies' => [
        'analytical',
        'creative',
        'systematic',
        'collaborative',
        'adaptive',
    ],
    'logger' => $logger,
]);

echo "Created advanced learning agent with 5 strategies\n\n";

/**
 * Simulated task scenarios with expected quality
 */
$taskScenarios = [
    // Mathematical/analytical tasks
    ['task' => 'Calculate compound interest on $1000 at 5% for 3 years', 'ideal_strategy' => 'analytical', 'difficulty' => 0.3],
    ['task' => 'Solve the quadratic equation x^2 - 5x + 6 = 0', 'ideal_strategy' => 'analytical', 'difficulty' => 0.4],
    
    // Creative tasks
    ['task' => 'Create a name for a tech startup focused on AI education', 'ideal_strategy' => 'creative', 'difficulty' => 0.5],
    ['task' => 'Write a haiku about programming', 'ideal_strategy' => 'creative', 'difficulty' => 0.6],
    
    // Systematic tasks
    ['task' => 'Outline steps to set up a CI/CD pipeline', 'ideal_strategy' => 'systematic', 'difficulty' => 0.7],
    ['task' => 'List prerequisites for learning machine learning', 'ideal_strategy' => 'systematic', 'difficulty' => 0.5],
    
    // Adaptive tasks
    ['task' => 'How would you explain APIs to a 10-year-old?', 'ideal_strategy' => 'adaptive', 'difficulty' => 0.6],
    ['task' => 'Recommend a solution for both beginners and experts', 'ideal_strategy' => 'adaptive', 'difficulty' => 0.8],
];

echo "--- Training Phase: Running Multiple Task Scenarios ---\n\n";

$results = [];
foreach ($taskScenarios as $i => $scenario) {
    echo "Task " . ($i + 1) . ": " . substr($scenario['task'], 0, 50) . "...\n";
    
    $result = $agent->run($scenario['task']);
    
    if ($result->isSuccess()) {
        $strategyUsed = $result->getMetadata()['strategy_used'];
        echo "  Strategy: $strategyUsed\n";
        
        // Calculate reward based on strategy match and difficulty
        $strategyMatch = ($strategyUsed === $scenario['ideal_strategy']) ? 1.0 : 0.5;
        $reward = $strategyMatch * (1 - $scenario['difficulty']);
        $success = $reward > 0.4;
        
        echo "  Reward: " . number_format($reward, 2) . " | Success: " . ($success ? '✓' : '✗') . "\n";
        
        // Provide feedback
        $expId = $result->getMetadata()['experience_id'];
        $agent->provideFeedback($expId, $reward, $success, [
            'ideal_strategy' => $scenario['ideal_strategy'],
            'difficulty' => $scenario['difficulty'],
        ]);
        
        $results[] = [
            'task' => $scenario['task'],
            'strategy' => $strategyUsed,
            'reward' => $reward,
            'success' => $success,
        ];
    } else {
        echo "  Failed: {$result->getError()}\n";
    }
    
    echo "\n";
}

// Analyze performance
echo "--- Performance Analysis ---\n";
$performance = $agent->getPerformance();

// Sort by average reward
uasort($performance, fn($a, $b) => $b['avg_reward'] <=> $a['avg_reward']);

foreach ($performance as $strategy => $perf) {
    if ($perf['attempts'] > 0) {
        $successRate = ($perf['successes'] / $perf['attempts']) * 100;
        $stars = str_repeat('★', (int)($perf['avg_reward'] * 5));
        
        echo "\n$strategy:\n";
        echo "  Attempts: {$perf['attempts']}\n";
        echo "  Successes: {$perf['successes']} (" . number_format($successRate, 1) . "%)\n";
        echo "  Avg Reward: " . number_format($perf['avg_reward'], 3) . " $stars\n";
    }
}

// Experience replay analysis
echo "\n--- Experience Replay Analysis ---\n";
$experiences = $agent->getExperiences();
$successfulExperiences = array_filter($experiences, fn($exp) => $exp['success'] === true);
$failedExperiences = array_filter($experiences, fn($exp) => $exp['success'] === false);

echo "Total Experiences: " . count($experiences) . "\n";
echo "Successful: " . count($successfulExperiences) . " (" . 
     number_format((count($successfulExperiences) / count($experiences)) * 100, 1) . "%)\n";
echo "Failed: " . count($failedExperiences) . " (" . 
     number_format((count($failedExperiences) / count($experiences)) * 100, 1) . "%)\n";

// Strategy distribution
$strategyDistribution = [];
foreach ($experiences as $exp) {
    $strategy = $exp['strategy'];
    $strategyDistribution[$strategy] = ($strategyDistribution[$strategy] ?? 0) + 1;
}

echo "\nStrategy Usage Distribution:\n";
arsort($strategyDistribution);
foreach ($strategyDistribution as $strategy => $count) {
    $percentage = ($count / count($experiences)) * 100;
    $bar = str_repeat('█', (int)($percentage / 5));
    echo "  $strategy: $count ($bar " . number_format($percentage, 1) . "%)\n";
}

// Testing adaptive learning
echo "\n--- Testing Adaptive Behavior ---\n";
echo "Running similar tasks to see strategy adaptation...\n\n";

$repeatTasks = [
    'Calculate the area of a triangle with base 10 and height 5',
    'Calculate the volume of a cube with side length 3',
    'Calculate the perimeter of a rectangle 8x12',
];

foreach ($repeatTasks as $i => $task) {
    $result = $agent->run($task);
    if ($result->isSuccess()) {
        echo "Task " . ($i + 1) . ": " . substr($task, 0, 40) . "...\n";
        echo "  Strategy: {$result->getMetadata()['strategy_used']}\n";
        
        // High reward for math problems
        $expId = $result->getMetadata()['experience_id'];
        $agent->provideFeedback($expId, 0.95, true);
    }
}

// Final performance report
echo "\n--- Final Performance Report ---\n";
$finalPerf = $agent->getPerformance();
$totalAttempts = array_sum(array_column($finalPerf, 'attempts'));
$totalSuccesses = array_sum(array_column($finalPerf, 'successes'));
$overallSuccessRate = ($totalSuccesses / $totalAttempts) * 100;

echo "Overall Statistics:\n";
echo "  Total Attempts: $totalAttempts\n";
echo "  Total Successes: $totalSuccesses\n";
echo "  Success Rate: " . number_format($overallSuccessRate, 1) . "%\n";

// Find best strategy
$bestStrategy = array_keys($finalPerf)[0];
$bestReward = $finalPerf[$bestStrategy]['avg_reward'];
foreach ($finalPerf as $strategy => $perf) {
    if ($perf['avg_reward'] > $bestReward && $perf['attempts'] >= 2) {
        $bestStrategy = $strategy;
        $bestReward = $perf['avg_reward'];
    }
}

echo "\nBest Performing Strategy: $bestStrategy\n";
echo "  Average Reward: " . number_format($bestReward, 3) . "\n";

// Strategy recommendations
echo "\n--- Strategy Recommendations ---\n";
echo "Based on learned experience:\n";
foreach ($finalPerf as $strategy => $perf) {
    if ($perf['attempts'] >= 3) {
        if ($perf['avg_reward'] > 0.7) {
            echo "  ✓ $strategy: Excellent - Use frequently\n";
        } elseif ($perf['avg_reward'] > 0.4) {
            echo "  • $strategy: Good - Use for suitable tasks\n";
        } else {
            echo "  ✗ $strategy: Needs improvement\n";
        }
    }
}

echo "\n✓ Advanced learning agent example completed!\n";
echo "The agent has learned from " . count($experiences) . " experiences.\n";

