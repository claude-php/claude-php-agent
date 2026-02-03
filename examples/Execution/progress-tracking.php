<?php

/**
 * Progress Tracking Example
 *
 * Demonstrates:
 * - Real-time progress monitoring
 * - Step-by-step execution tracking
 * - Duration estimates
 * - Metadata collection
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\FlowProgress;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Load environment variables
$apiKey = getenv('ANTHROPIC_API_KEY') ?: throw new RuntimeException('ANTHROPIC_API_KEY not set');

// Create event system
$eventQueue = new EventQueue(maxSize: 100);
$eventManager = new FlowEventManager($eventQueue);
$eventManager->registerDefaultEvents();

// Create executor
$executor = new StreamingFlowExecutor($eventManager, $eventQueue);

// Create Claude client and agent
$client = new ClaudePhp(apiKey: $apiKey);

$config = new AgentConfig(
    model: 'claude-sonnet-4-5',
    maxTokens: 1024,
    maxIterations: 10
);

// Create multiple tools to demonstrate progress tracking
$searchTool = Tool::create('search')
    ->description('Search for information')
    ->stringParam('query', 'Search query', true)
    ->handler(function (array $input): string {
        sleep(1); // Simulate search delay
        return "Search results for: {$input['query']}";
    });

$analyzeTool = Tool::create('analyze')
    ->description('Analyze data')
    ->stringParam('data', 'Data to analyze', true)
    ->handler(function (array $input): string {
        sleep(1); // Simulate analysis delay
        return "Analysis of: {$input['data']}";
    });

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$searchTool, $analyzeTool]);

$task = "Search for 'artificial intelligence' and then analyze the results";

echo "=== Progress Tracking Example ===\n";
echo "Task: {$task}\n";
echo "=================================\n\n";

// Track progress with a progress bar
$progressBar = function(float $percent): string {
    $width = 50;
    $filled = (int)($width * $percent / 100);
    $empty = $width - $filled;
    return '[' . str_repeat('=', $filled) . str_repeat(' ', $empty) . '] ' . round($percent, 1) . '%';
};

$currentIteration = 0;
$startTime = microtime(true);

try {
    foreach ($executor->executeWithStreaming($agent, $task, ['track_progress' => true]) as $event) {
        $type = $event['type'];
        $data = $event['data'];

        switch ($type) {
            case 'flow_started':
                echo "🚀 Starting execution...\n\n";
                break;

            case 'iteration_start':
                $currentIteration = $data['iteration'] ?? 0;
                $elapsed = round(microtime(true) - $startTime, 2);
                echo "\n⏱️  Iteration {$currentIteration} started (Elapsed: {$elapsed}s)\n";
                break;

            case 'tool_start':
                $toolName = $data['tool'] ?? 'unknown';
                echo "   🔧 Executing: {$toolName}...\n";
                break;

            case 'tool_end':
                $toolName = $data['tool'] ?? 'unknown';
                echo "   ✅ Completed: {$toolName}\n";
                break;

            case 'progress':
                $percent = $data['progress_percent'] ?? 0;
                $currentStep = $data['current_step'] ?? 'unknown';
                $duration = $data['formatted_duration'] ?? '0s';
                $estimated = $data['estimated_remaining'];
                
                echo "\n📊 Progress Update:\n";
                echo "   " . $progressBar($percent) . "\n";
                echo "   Current Step: {$currentStep}\n";
                echo "   Duration: {$duration}\n";
                
                if ($estimated !== null) {
                    $estFormatted = round($estimated, 1);
                    echo "   Estimated Remaining: {$estFormatted}s\n";
                }
                
                echo "   Iterations: {$data['current_iteration']}/{$data['total_iterations']}\n";
                echo "   Steps: {$data['completed_step_count']}/{$data['step_count']}\n";
                break;

            case 'token':
                // Don't display tokens to keep output clean for progress tracking
                break;

            case 'result':
                echo "\n\n=== Final Result ===\n";
                echo "Output: {$data['output']}\n";
                echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
                break;

            case 'end':
                echo "\n✅ Execution completed!\n";
                break;
        }
    }

    // Final statistics
    $totalDuration = round(microtime(true) - $startTime, 2);
    $progress = $executor->getCurrentProgress();

    echo "\n=== Execution Statistics ===\n";
    echo "Total Duration: {$totalDuration}s\n";
    echo "Total Iterations: {$currentIteration}\n";
    
    if ($progress) {
        echo "Average Time per Iteration: " . 
             round($totalDuration / max($currentIteration, 1), 2) . "s\n";
        echo "Total Steps Completed: {$progress['completed_step_count']}\n";
    }

    // Queue statistics
    $queueStats = $eventQueue->getStats();
    echo "\n=== Event Queue Statistics ===\n";
    echo "Events in Queue: {$queueStats['size']}\n";
    echo "Queue Utilization: " . round($queueStats['utilization'], 1) . "%\n";
    echo "Dropped Events: {$queueStats['dropped_events']}\n";

} catch (Exception $e) {
    echo "\n❌ Execution failed: {$e->getMessage()}\n";
    exit(1);
}

echo "\n=== Complete ===\n";
