<?php

/**
 * Multiple Listeners Example
 *
 * Demonstrates:
 * - Multiple subscribers to the same flow
 * - Event broadcasting to all listeners
 * - Custom event handlers
 * - Listener management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEvent;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Load environment variables
$apiKey = getenv('ANTHROPIC_API_KEY') ?: throw new RuntimeException('ANTHROPIC_API_KEY not set');

// Create event system
$eventQueue = new EventQueue(maxSize: 200);
$eventManager = new FlowEventManager($eventQueue);
$eventManager->registerDefaultEvents();

// Register custom event with callback
$eventManager->registerEvent('on_custom', 'custom_event', function (FlowEvent $event) {
    echo "   [Custom Handler] Received event: {$event->type}\n";
});

// Listener 1: Token Counter
$tokenCount = 0;
$listener1 = function (FlowEvent $event) use (&$tokenCount) {
    if ($event->isToken()) {
        $tokenCount++;
    }
};
$listener1Id = $eventManager->subscribe($listener1);
echo "✓ Registered Listener 1: Token Counter (ID: {$listener1Id})\n";

// Listener 2: Progress Logger
$listener2 = function (FlowEvent $event) {
    if ($event->isProgress()) {
        $percent = $event->data['percent'] ?? 0;
        echo "   [Progress Logger] " . round($percent, 1) . "% complete\n";
    }
};
$listener2Id = $eventManager->subscribe($listener2);
echo "✓ Registered Listener 2: Progress Logger (ID: {$listener2Id})\n";

// Listener 3: Tool Tracker
$toolCalls = [];
$listener3 = function (FlowEvent $event) use (&$toolCalls) {
    if ($event->isToolEvent()) {
        $toolName = $event->data['tool'] ?? 'unknown';
        if ($event->type === FlowEvent::TOOL_EXECUTION_STARTED) {
            echo "   [Tool Tracker] Tool '{$toolName}' started\n";
        } elseif ($event->type === FlowEvent::TOOL_EXECUTION_COMPLETED) {
            $toolCalls[] = $toolName;
            echo "   [Tool Tracker] Tool '{$toolName}' completed (Total: " . count($toolCalls) . ")\n";
        }
    }
};
$listener3Id = $eventManager->subscribe($listener3);
echo "✓ Registered Listener 3: Tool Tracker (ID: {$listener3Id})\n";

// Listener 4: Error Monitor
$errors = [];
$listener4 = function (FlowEvent $event) use (&$errors) {
    if ($event->isError()) {
        $errorMsg = $event->data['message'] ?? $event->data['error'] ?? 'Unknown error';
        $errors[] = $errorMsg;
        echo "   [Error Monitor] ⚠️ Error detected: {$errorMsg}\n";
    }
};
$listener4Id = $eventManager->subscribe($listener4);
echo "✓ Registered Listener 4: Error Monitor (ID: {$listener4Id})\n";

echo "\nTotal Listeners: {$eventManager->getListenerCount()}\n";
echo "==================================\n\n";

// Create executor
$executor = new StreamingFlowExecutor($eventManager, $eventQueue);

// Create Claude client and agent
$client = new ClaudePhp(apiKey: $apiKey);

$config = new AgentConfig(
    model: 'claude-sonnet-4-5',
    maxTokens: 512,
    maxIterations: 5
);

// Create a simple tool
$echoTool = Tool::create('echo')
    ->description('Echo back the input')
    ->stringParam('message', 'Message to echo', true)
    ->handler(function (array $input): string {
        return "Echo: {$input['message']}";
    });

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$echoTool]);

$task = "Use the echo tool to say 'Hello from multiple listeners!'";

echo "Task: {$task}\n";
echo "==================================\n\n";

try {
    foreach ($executor->executeWithStreaming($agent, $task) as $event) {
        $type = $event['type'];
        
        // Main output
        if ($type === 'token') {
            echo $event['data']['token'] ?? '';
        } elseif ($type === 'flow_started') {
            echo "🚀 Flow started\n\n";
        } elseif ($type === 'end') {
            echo "\n\n✅ Flow completed\n";
        }
    }

    // Summary from all listeners
    echo "\n==================================\n";
    echo "=== Listener Summary ===\n";
    echo "==================================\n";
    echo "Token Count (Listener 1): {$tokenCount}\n";
    echo "Tool Calls (Listener 3): " . count($toolCalls) . "\n";
    if (!empty($toolCalls)) {
        echo "  - " . implode(", ", $toolCalls) . "\n";
    }
    echo "Errors Detected (Listener 4): " . count($errors) . "\n";
    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    // Demonstrate unsubscribing
    echo "\n=== Listener Management ===\n";
    $eventManager->unsubscribe($listener1Id);
    echo "✓ Unsubscribed Listener 1\n";
    echo "Remaining Listeners: {$eventManager->getListenerCount()}\n";

} catch (Exception $e) {
    echo "\n❌ Execution failed: {$e->getMessage()}\n";
    exit(1);
}

echo "\n=== Complete ===\n";
