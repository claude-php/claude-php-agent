<?php

/**
 * Tutorial 6: Error Handling in Streaming
 * 
 * Learn how to:
 * - Handle errors in streaming execution
 * - Recover from failures
 * - Track error events
 * - Implement graceful degradation
 * 
 * Estimated time: 15 minutes
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEvent;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

echo "===================================\n";
echo "Tutorial 6: Error Handling\n";
echo "===================================\n\n";

$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "❌ Error: ANTHROPIC_API_KEY not set\n";
    exit(1);
}

// Step 1: Create error-tracking system
echo "Step 1: Setting up error tracking\n";
echo "----------------------------------\n";

$eventQueue = new EventQueue(maxSize: 100);
$eventManager = new FlowEventManager($eventQueue);
$eventManager->registerDefaultEvents();

// Error collector
$errorLog = [];
$errorHandler = function(FlowEvent $event) use (&$errorLog) {
    if ($event->isError()) {
        $errorLog[] = [
            'type' => $event->type,
            'message' => $event->data['message'] ?? $event->data['error'] ?? 'Unknown error',
            'timestamp' => $event->timestamp,
            'data' => $event->data,
        ];
    }
};

$eventManager->subscribe($errorHandler);
echo "✅ Error tracking listener registered\n\n";

// Step 2: Create tools with potential failures
echo "Step 2: Creating tools with error scenarios\n";
echo "--------------------------------------------\n";

$client = new ClaudePhp(apiKey: $apiKey);
$config = new AgentConfig(
    model: 'claude-3-5-sonnet-20241022',
    maxTokens: 512,
    maxIterations: 5
);

// Risky division tool that might fail
$divideTool = Tool::create('divide')
    ->description('Divide two numbers')
    ->numberParam('a', 'Numerator', true)
    ->numberParam('b', 'Denominator', true)
    ->handler(function (array $input): string {
        $a = $input['a'];
        $b = $input['b'];
        
        if ($b == 0) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }
        
        $result = $a / $b;
        return "Result: {$result}";
    });

// Network tool that might timeout
$networkTool = Tool::create('fetch_data')
    ->description('Fetch data from network')
    ->stringParam('url', 'URL to fetch', true)
    ->handler(function (array $input): string {
        // Simulate network request
        usleep(100000); // 0.1s delay
        
        // Simulate occasional failures
        if (rand(1, 10) > 8) {
            throw new RuntimeException('Network timeout');
        }
        
        return "Data fetched from: {$input['url']}";
    });

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$divideTool, $networkTool]);
$executor = new StreamingFlowExecutor($eventManager, $eventQueue);

echo "✅ Agent created with potentially failing tools\n\n";

// Step 3: Execute with error handling
echo "Step 3: Executing with error handling\n";
echo "--------------------------------------\n\n";

// Test 1: Normal execution
echo "Test 1: Normal execution\n";
$task1 = "Divide 100 by 5";
echo "Task: {$task1}\n";

try {
    $eventCount = 0;
    $errorCount = 0;
    
    foreach ($executor->executeWithStreaming($agent, $task1) as $event) {
        $eventCount++;
        
        if ($event['type'] === 'error') {
            $errorCount++;
            echo "❌ Error detected: {$event['data']['error']}\n";
        } elseif ($event['type'] === 'flow_started') {
            echo "▶️  Execution started...\n";
        } elseif ($event['type'] === 'tool_end') {
            echo "✅ Tool completed: {$event['data']['result']}\n";
        } elseif ($event['type'] === 'end') {
            echo "✅ Execution completed\n";
        }
    }
    
    echo "Events: {$eventCount}, Errors: {$errorCount}\n\n";
    
} catch (Exception $e) {
    echo "❌ Fatal error: {$e->getMessage()}\n\n";
}

// Test 2: Division by zero (will error)
echo "Test 2: Error scenario (division by zero)\n";
$task2 = "Divide 100 by 0";
echo "Task: {$task2}\n";

try {
    $errorDetected = false;
    
    foreach ($executor->executeWithStreaming($agent, $task2) as $event) {
        if ($event['type'] === 'error') {
            echo "❌ Error caught in stream: {$event['data']['error']}\n";
            $errorDetected = true;
        } elseif ($event['type'] === 'flow_started') {
            echo "▶️  Execution started...\n";
        } elseif ($event['type'] === 'end') {
            echo "✅ Execution completed (with errors)\n";
        }
    }
    
    if ($errorDetected) {
        echo "✅ Error was properly caught and reported\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: {$e->getMessage()}\n";
    echo "✅ Exception was properly propagated\n\n";
}

// Step 4: Error recovery pattern
echo "Step 4: Implementing error recovery\n";
echo "------------------------------------\n";

function executeWithRetry(
    StreamingFlowExecutor $executor,
    Agent $agent,
    string $task,
    int $maxRetries = 3
): array {
    $attempt = 0;
    $lastError = null;
    
    while ($attempt < $maxRetries) {
        $attempt++;
        echo "Attempt {$attempt}/{$maxRetries}...\n";
        
        try {
            $events = [];
            foreach ($executor->executeWithStreaming($agent, $task) as $event) {
                $events[] = $event;
                
                if ($event['type'] === 'error') {
                    $lastError = $event['data']['error'];
                    throw new RuntimeException($lastError);
                }
            }
            
            echo "✅ Success on attempt {$attempt}\n";
            return $events;
            
        } catch (Exception $e) {
            echo "❌ Failed: {$e->getMessage()}\n";
            
            if ($attempt < $maxRetries) {
                $delay = $attempt * 500000; // Exponential backoff
                echo "   Retrying in " . ($delay / 1000000) . "s...\n";
                usleep($delay);
            }
        }
    }
    
    throw new RuntimeException("All retry attempts failed. Last error: {$lastError}");
}

// Test retry mechanism
try {
    $task3 = "Divide 200 by 4";
    echo "\nTask with retry: {$task3}\n";
    $events = executeWithRetry($executor, $agent, $task3, maxRetries: 3);
    echo "Events received: " . count($events) . "\n\n";
} catch (Exception $e) {
    echo "All retries failed: {$e->getMessage()}\n\n";
}

// Step 5: Error log analysis
echo "Step 5: Error Log Analysis\n";
echo "--------------------------\n\n";

if (empty($errorLog)) {
    echo "✅ No errors logged\n";
} else {
    echo "Errors logged: " . count($errorLog) . "\n\n";
    
    foreach ($errorLog as $i => $error) {
        echo "Error #" . ($i + 1) . ":\n";
        echo "  Type: {$error['type']}\n";
        echo "  Message: {$error['message']}\n";
        echo "  Time: " . date('H:i:s', (int)$error['timestamp']) . "\n\n";
    }
}

// Step 6: Best practices summary
echo "Step 6: Error Handling Best Practices\n";
echo "--------------------------------------\n\n";

echo "✅ Always wrap streaming execution in try-catch\n";
echo "✅ Subscribe error listeners before execution\n";
echo "✅ Check for 'error' event type in the stream\n";
echo "✅ Implement retry logic with exponential backoff\n";
echo "✅ Log all errors for debugging\n";
echo "✅ Provide graceful degradation\n";
echo "✅ Validate tool inputs before execution\n";

echo "\n";
echo "===================================\n";
echo "Tutorial Complete! ✅\n";
echo "===================================\n\n";

echo "What you learned:\n";
echo "- How to define custom event types\n";
echo "- How to register custom event handlers\n";
echo "- How to handle errors in streaming\n";
echo "- How to implement retry logic\n";
echo "- How to track and analyze errors\n\n";

echo "Next: Try tutorial 07-integration.php\n";
