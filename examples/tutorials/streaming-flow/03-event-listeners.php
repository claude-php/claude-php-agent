<?php

/**
 * Tutorial 3: Event Listeners
 * 
 * Learn how to:
 * - Subscribe multiple listeners to events
 * - Create custom event handlers
 * - Manage listener lifecycle
 * - Broadcast events to multiple consumers
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

echo "===========================\n";
echo "Tutorial 3: Event Listeners\n";
echo "===========================\n\n";

// Step 1: Setup
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "❌ Error: ANTHROPIC_API_KEY not set\n";
    exit(1);
}

echo "Step 1: Creating event system\n";
echo "------------------------------\n";

$eventQueue = new EventQueue(maxSize: 200);
$eventManager = new FlowEventManager($eventQueue);
$eventManager->registerDefaultEvents();

echo "✅ Event system ready\n\n";

// Step 2: Create custom listeners
echo "Step 2: Creating custom listeners\n";
echo "----------------------------------\n";

// Listener 1: Token Counter
$tokenCount = 0;
$tokenListener = function (FlowEvent $event) use (&$tokenCount) {
    if ($event->isToken()) {
        $tokenCount++;
    }
};
$listener1Id = $eventManager->subscribe($tokenListener);
echo "✅ Listener 1: Token Counter (ID: {$listener1Id})\n";

// Listener 2: Performance Monitor
$performanceData = ['iterations' => 0, 'tools_used' => []];
$perfListener = function (FlowEvent $event) use (&$performanceData) {
    if ($event->type === FlowEvent::ITERATION_COMPLETED) {
        $performanceData['iterations']++;
    } elseif ($event->type === FlowEvent::TOOL_EXECUTION_COMPLETED) {
        $tool = $event->data['tool'] ?? 'unknown';
        if (!isset($performanceData['tools_used'][$tool])) {
            $performanceData['tools_used'][$tool] = 0;
        }
        $performanceData['tools_used'][$tool]++;
    }
};
$listener2Id = $eventManager->subscribe($perfListener);
echo "✅ Listener 2: Performance Monitor (ID: {$listener2Id})\n";

// Listener 3: Error Tracker
$errors = [];
$errorListener = function (FlowEvent $event) use (&$errors) {
    if ($event->isError()) {
        $errors[] = [
            'message' => $event->data['message'] ?? $event->data['error'] ?? 'Unknown',
            'timestamp' => $event->timestamp,
        ];
    }
};
$listener3Id = $eventManager->subscribe($errorListener);
echo "✅ Listener 3: Error Tracker (ID: {$listener3Id})\n";

// Listener 4: Event Logger (logs everything)
$eventLog = [];
$loggerListener = function (FlowEvent $event) use (&$eventLog) {
    $eventLog[] = [
        'type' => $event->type,
        'timestamp' => $event->timestamp,
    ];
};
$listener4Id = $eventManager->subscribe($loggerListener);
echo "✅ Listener 4: Event Logger (ID: {$listener4Id})\n";

echo "\nTotal listeners registered: {$eventManager->getListenerCount()}\n\n";

// Step 3: Register custom event with callback
echo "Step 3: Registering custom event\n";
echo "---------------------------------\n";

$customEventCalled = false;
$eventManager->registerEvent('on_custom', 'custom.milestone', function(FlowEvent $event) use (&$customEventCalled) {
    echo "   🎯 Custom milestone reached: " . ($event->data['message'] ?? 'N/A') . "\n";
    $customEventCalled = true;
});

echo "✅ Custom event 'on_custom' registered\n\n";

// Step 4: Create agent and executor
echo "Step 4: Setting up executor\n";
echo "---------------------------\n";

$client = new ClaudePhp(apiKey: $apiKey);
$config = new AgentConfig(
    model: 'claude-3-5-sonnet-20241022',
    maxTokens: 512,
    maxIterations: 3
);

$echoTool = Tool::create('echo')
    ->description('Echo back the input')
    ->stringParam('message', 'Message to echo', true)
    ->handler(function (array $input): string {
        return "Echo: {$input['message']}";
    });

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$echoTool]);
$executor = new StreamingFlowExecutor($eventManager, $eventQueue);

echo "✅ Agent and executor ready\n\n";

// Step 5: Execute and observe listeners
echo "Step 5: Executing with multiple listeners\n";
echo "------------------------------------------\n";

$task = "Use the echo tool to say 'Hello from listeners!'";
echo "Task: {$task}\n\n";

try {
    $executionEvents = 0;
    
    foreach ($executor->executeWithStreaming($agent, $task) as $event) {
        $executionEvents++;
        
        // Only show main flow events (not tokens) for clarity
        if (in_array($event['type'], ['flow_started', 'tool_start', 'tool_end', 'end'])) {
            $type = $event['type'];
            switch ($type) {
                case 'flow_started':
                    echo "🚀 Flow started\n";
                    break;
                case 'tool_start':
                    printf("   🔧 Tool: %s\n", $event['data']['tool']);
                    break;
                case 'tool_end':
                    printf("   ✅ Result: %s\n", $event['data']['result']);
                    break;
                case 'end':
                    echo "✅ Flow completed\n";
                    break;
            }
        }
    }
    
    echo "\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    exit(1);
}

// Step 6: Display listener results
echo "Step 6: Listener Results\n";
echo "------------------------\n\n";

echo "Listener 1 (Token Counter):\n";
echo "  Tokens counted: {$tokenCount}\n\n";

echo "Listener 2 (Performance Monitor):\n";
echo "  Iterations completed: {$performanceData['iterations']}\n";
echo "  Tools used:\n";
foreach ($performanceData['tools_used'] as $tool => $count) {
    echo "    - {$tool}: {$count} time(s)\n";
}
echo "\n";

echo "Listener 3 (Error Tracker):\n";
if (empty($errors)) {
    echo "  ✅ No errors detected\n\n";
} else {
    echo "  ⚠️  Errors found:\n";
    foreach ($errors as $error) {
        echo "    - {$error['message']}\n";
    }
    echo "\n";
}

echo "Listener 4 (Event Logger):\n";
echo "  Total events logged: " . count($eventLog) . "\n";
echo "  Event types:\n";
$eventTypes = array_count_values(array_column($eventLog, 'type'));
arsort($eventTypes);
foreach (array_slice($eventTypes, 0, 5) as $type => $count) {
    echo "    - {$type}: {$count}\n";
}
echo "\n";

// Step 7: Manage listeners
echo "Step 7: Managing listeners\n";
echo "--------------------------\n";

echo "Current listener count: {$eventManager->getListenerCount()}\n";

// Unsubscribe one listener
$eventManager->unsubscribe($listener4Id);
echo "Unsubscribed listener 4 (Event Logger)\n";
echo "New listener count: {$eventManager->getListenerCount()}\n\n";

// Clear all listeners
$eventManager->clearListeners();
echo "All listeners cleared\n";
echo "Final listener count: {$eventManager->getListenerCount()}\n";

echo "\n";
echo "===========================\n";
echo "Tutorial Complete! ✅\n";
echo "===========================\n\n";

echo "What you learned:\n";
echo "- How to create and subscribe multiple listeners\n";
echo "- How to track different types of events\n";
echo "- How to manage listener lifecycle\n";
echo "- How listeners receive broadcasts simultaneously\n\n";

echo "Next: Try tutorial 04-sse-streaming.php\n";
