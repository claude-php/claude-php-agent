<?php

/**
 * Tutorial 1: Basic Streaming Flow Execution
 * 
 * Learn how to:
 * - Set up the streaming flow executor
 * - Stream token-by-token responses
 * - Handle different event types
 * - Display real-time progress
 * 
 * Estimated time: 10 minutes
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Execution\FlowEventManagerServiceFactory;
use ClaudeAgents\Services\Execution\StreamingFlowExecutorServiceFactory;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

echo "===========================================\n";
echo "Tutorial 1: Basic Streaming Flow Execution\n";
echo "===========================================\n\n";

// Step 1: Setup
echo "Step 1: Setting up the streaming executor\n";
echo "------------------------------------------\n";

$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "❌ Error: ANTHROPIC_API_KEY environment variable not set.\n";
    echo "   Please set it with: export ANTHROPIC_API_KEY='your-key-here'\n";
    exit(1);
}

// Initialize ServiceManager and register streaming services
$serviceManager = ServiceManager::getInstance();
$serviceManager
    ->registerFactory(new FlowEventManagerServiceFactory())
    ->registerFactory(new StreamingFlowExecutorServiceFactory());

echo "✅ ServiceManager initialized\n";
echo "✅ Streaming services registered\n\n";

// Step 2: Get the streaming executor
echo "Step 2: Getting the streaming executor\n";
echo "---------------------------------------\n";

$executor = $serviceManager->get(ServiceType::FLOW_EXECUTOR);
echo "✅ Streaming executor ready\n\n";

// Step 3: Create a simple agent
echo "Step 3: Creating an agent\n";
echo "--------------------------\n";

$client = new ClaudePhp(apiKey: $apiKey);
$config = new AgentConfig(
    model: 'claude-3-5-sonnet-20241022',
    maxTokens: 512,
    maxIterations: 3
);

// Simple calculator tool using fluent API
$calculator = Tool::create('calculator')
    ->description('Perform basic arithmetic calculations')
    ->stringParam('operation', 'The operation to perform', true, ['add', 'subtract', 'multiply', 'divide'])
    ->numberParam('a', 'First number', true)
    ->numberParam('b', 'Second number', true)
    ->handler(function (array $input): string {
        $result = match ($input['operation']) {
            'add' => $input['a'] + $input['b'],
            'subtract' => $input['a'] - $input['b'],
            'multiply' => $input['a'] * $input['b'],
            'divide' => $input['b'] != 0 ? $input['a'] / $input['b'] : 'Error: Division by zero',
            default => 'Error: Unknown operation',
        };
        return "Result: {$result}";
    });

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$calculator]);

echo "✅ Agent created with calculator tool\n\n";

// Step 4: Execute with streaming
echo "Step 4: Executing with streaming\n";
echo "---------------------------------\n";

$task = "Calculate 25 * 4 and then add 100 to the result";
echo "Task: {$task}\n\n";
echo "Streaming output:\n";
echo "─────────────────\n";

$eventCount = ['token' => 0, 'tool' => 0, 'progress' => 0];

try {
    foreach ($executor->executeWithStreaming($agent, $task) as $event) {
        $type = $event['type'];
        $data = $event['data'];

        switch ($type) {
            case 'flow_started':
                printf("🚀 Flow started\n\n");
                break;
            
            case 'token':
                echo $data['token'];
                $eventCount['token']++;
                break;
            
            case 'iteration_start':
                printf("\n\n[Iteration %d started]\n", $data['iteration']);
                break;
            
            case 'tool_start':
                printf("\n🔧 Using tool: %s\n", $data['tool']);
                printf("   Input: %s\n", json_encode($data['input']));
                $eventCount['tool']++;
                break;
            
            case 'tool_end':
                printf("   ✅ Result: %s\n\n", $data['result']);
                break;
            
            case 'progress':
                if ($eventCount['progress'] % 2 === 0) {
                    printf("\n📊 Progress: %.1f%%\n", $data['progress_percent']);
                }
                $eventCount['progress']++;
                break;
            
            case 'end':
                printf("\n\n✅ Flow completed\n");
                break;
        }
    }

    // Step 5: Summary
    echo "\n\n";
    echo "Step 5: Execution Summary\n";
    echo "-------------------------\n";
    echo "Tokens streamed: {$eventCount['token']}\n";
    echo "Tools executed: {$eventCount['tool']}\n";
    echo "Progress updates: {$eventCount['progress']}\n";

} catch (Exception $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    exit(1);
}

echo "\n";
echo "===========================================\n";
echo "Tutorial Complete! ✅\n";
echo "===========================================\n\n";

echo "What you learned:\n";
echo "- How to set up the streaming executor\n";
echo "- How to handle different event types\n";
echo "- How to display real-time streaming output\n";
echo "- How to track execution statistics\n\n";

echo "Next: Try tutorial 02-progress-tracking.php\n";
