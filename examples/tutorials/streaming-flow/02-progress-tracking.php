<?php

/**
 * Tutorial 2: Progress Tracking
 * 
 * Learn how to:
 * - Track execution progress in real-time
 * - Display progress bars
 * - Estimate time remaining
 * - Monitor queue statistics
 * 
 * Estimated time: 10 minutes
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

echo "===============================\n";
echo "Tutorial 2: Progress Tracking\n";
echo "===============================\n\n";

// Helper function to display progress bar
function displayProgressBar(float $percent): void {
    $width = 40;
    $filled = (int)($width * $percent / 100);
    $empty = $width - $filled;
    
    $bar = '[' . str_repeat('=', $filled) . str_repeat(' ', $empty) . ']';
    printf("\r%s %.1f%%", $bar, $percent);
}

// Step 1: Setup
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "❌ Error: ANTHROPIC_API_KEY not set\n";
    echo "   Run: export ANTHROPIC_API_KEY='your-key'\n";
    exit(1);
}

echo "Step 1: Creating event system\n";
echo "------------------------------\n";

$eventQueue = new EventQueue(maxSize: 100);
$eventManager = new FlowEventManager($eventQueue);
$eventManager->registerDefaultEvents();

echo "✅ Event queue created (max size: 100)\n";
echo "✅ Event manager configured\n\n";

// Step 2: Create executor
echo "Step 2: Creating executor\n";
echo "-------------------------\n";

$executor = new StreamingFlowExecutor($eventManager, $eventQueue);

echo "✅ Streaming executor ready\n\n";

// Step 3: Create agent with tools
echo "Step 3: Creating agent\n";
echo "----------------------\n";

$client = new ClaudePhp(apiKey: $apiKey);
$config = new AgentConfig(
    model: 'claude-3-5-sonnet-20241022',
    maxTokens: 512,
    maxIterations: 5
);

// Simulate slow operations for better progress visualization
$searchTool = Tool::create('search')
    ->description('Search for information')
    ->stringParam('query', 'Search query', true)
    ->handler(function (array $input): string {
        usleep(500000); // 0.5 second delay
        return "Search results for: {$input['query']}";
    });

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$searchTool]);

echo "✅ Agent created with search tool\n\n";

// Step 4: Execute with progress tracking
echo "Step 4: Executing with progress tracking\n";
echo "-----------------------------------------\n";

$task = "Search for 'PHP streaming' and summarize the results";
echo "Task: {$task}\n\n";

$startTime = microtime(true);
$currentProgress = 0;
$showProgressBar = true;

try {
    foreach ($executor->executeWithStreaming($agent, $task, ['track_progress' => true]) as $event) {
        $type = $event['type'];
        $data = $event['data'];

        switch ($type) {
            case 'flow_started':
                echo "🚀 Starting execution...\n\n";
                $showProgressBar = true;
                break;
            
            case 'iteration_start':
                if ($showProgressBar) {
                    echo "\n";
                    $showProgressBar = false;
                }
                $iteration = $data['iteration'] ?? 0;
                $elapsed = round(microtime(true) - $startTime, 2);
                echo "⏱️  Iteration {$iteration} (Elapsed: {$elapsed}s)\n";
                break;
            
            case 'tool_start':
                if ($showProgressBar) {
                    echo "\n";
                    $showProgressBar = false;
                }
                echo "   🔧 Using: {$data['tool']}\n";
                break;
            
            case 'tool_end':
                echo "   ✅ Completed\n";
                $showProgressBar = true;
                break;
            
            case 'progress':
                $percent = $data['progress_percent'] ?? 0;
                $currentProgress = $percent;
                
                if ($showProgressBar) {
                    displayProgressBar($percent);
                }
                
                if ($percent > 0 && fmod($percent, 25.0) < 5.0) {
                    if ($showProgressBar) {
                        echo "\n";
                    }
                    $current = $data['current_iteration'] ?? 0;
                    $total = $data['total_iterations'] ?? 0;
                    $duration = $data['formatted_duration'] ?? '0s';
                    
                    echo "   📊 Progress: {$current}/{$total} iterations\n";
                    echo "   ⏱️  Duration: {$duration}\n";
                    
                    if (isset($data['estimated_remaining'])) {
                        $eta = round($data['estimated_remaining'], 1);
                        echo "   ⏰ ETA: {$eta}s\n";
                    }
                    
                    $showProgressBar = true;
                }
                break;
            
            case 'token':
                // Skip token output for cleaner progress display
                break;
            
            case 'end':
                if ($showProgressBar) {
                    echo "\n";
                }
                echo "\n✅ Execution completed!\n";
                break;
        }
    }

} catch (Exception $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    exit(1);
}

// Step 5: Display statistics
echo "\n";
echo "Step 5: Execution Statistics\n";
echo "----------------------------\n";

$totalDuration = round(microtime(true) - $startTime, 2);
echo "Total Duration: {$totalDuration}s\n";
echo "Final Progress: {$currentProgress}%\n";

// Queue statistics
$queueStats = $eventQueue->getStats();
echo "\nQueue Statistics:\n";
echo "  Events in queue: {$queueStats['size']}\n";
echo "  Max queue size: {$queueStats['max_size']}\n";
echo "  Queue utilization: " . round($queueStats['utilization'], 1) . "%\n";
echo "  Dropped events: {$queueStats['dropped_events']}\n";

if ($queueStats['dropped_events'] > 0) {
    echo "\n⚠️  Warning: Some events were dropped. Consider increasing queue size.\n";
}

// Current executor progress
$progress = $executor->getCurrentProgress();
if ($progress) {
    echo "\nDetailed Progress:\n";
    echo "  Completed steps: {$progress['completed_step_count']}\n";
    echo "  Total steps: {$progress['step_count']}\n";
}

echo "\n";
echo "===============================\n";
echo "Tutorial Complete! ✅\n";
echo "===============================\n\n";

echo "What you learned:\n";
echo "- How to display progress bars\n";
echo "- How to track execution time\n";
echo "- How to estimate time remaining\n";
echo "- How to monitor queue statistics\n\n";

echo "Next: Try tutorial 03-event-listeners.php\n";
