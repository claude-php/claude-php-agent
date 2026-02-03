<?php

/**
 * Basic Streaming Flow Execution Example
 *
 * Demonstrates:
 * - Setting up StreamingFlowExecutor with ServiceManager
 * - Executing an agent with real-time token streaming
 * - Handling different event types
 * - Progress tracking
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Execution\StreamingFlowExecutor;
use ClaudeAgents\Services\Execution\FlowEventManagerServiceFactory;
use ClaudeAgents\Services\Execution\StreamingFlowExecutorServiceFactory;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Load environment variables
$apiKey = getenv('ANTHROPIC_API_KEY') ?: throw new RuntimeException('ANTHROPIC_API_KEY not set');

// Initialize ServiceManager and register flow execution services
$serviceManager = ServiceManager::getInstance();
$serviceManager
    ->registerFactory(new FlowEventManagerServiceFactory())
    ->registerFactory(new StreamingFlowExecutorServiceFactory());

// Get the streaming flow executor
/** @var StreamingFlowExecutor $executor */
$executor = $serviceManager->get(ServiceType::FLOW_EXECUTOR);

// Create Claude client and agent
$client = new ClaudePhp(apiKey: $apiKey);

$config = new AgentConfig(
    model: 'claude-sonnet-4-5',
    maxTokens: 1024,
    maxIterations: 5
);

// Create a simple calculator tool
$calculatorTool = Tool::create('calculator')
    ->description('Perform basic arithmetic calculations')
    ->stringParam('operation', 'The operation to perform', true, ['add', 'subtract', 'multiply', 'divide'])
    ->numberParam('a', 'First number', true)
    ->numberParam('b', 'Second number', true)
    ->handler(function (array $input): string {
        $a = $input['a'];
        $b = $input['b'];
        $operation = $input['operation'];

        $result = match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b !== 0 ? $a / $b : 'Error: Division by zero',
            default => 'Error: Unknown operation',
        };

        return "Result: {$result}";
    });

$agent = Agent::create($client)
    ->withConfig($config)
    ->withTools([$calculatorTool]);

// Task for the agent
$task = "Calculate 123 + 456 and then multiply the result by 2";

echo "=== Basic Streaming Flow Execution ===\n";
echo "Task: {$task}\n";
echo "======================================\n\n";

// Execute with streaming
$fullResponse = '';

try {
    foreach ($executor->executeWithStreaming($agent, $task) as $event) {
        $type = $event['type'];
        $data = $event['data'];

        switch ($type) {
            case 'flow_started':
                echo "🚀 Flow started with agent: {$data['agent']}\n\n";
                break;

            case 'token':
                // Stream tokens in real-time
                $token = $data['token'] ?? '';
                echo $token;
                $fullResponse .= $token;
                break;

            case 'iteration_start':
                $iteration = $data['iteration'] ?? 0;
                echo "\n\n[Iteration {$iteration} started]\n";
                break;

            case 'iteration_end':
                $iteration = $data['iteration'] ?? 0;
                echo "\n[Iteration {$iteration} completed]\n";
                break;

            case 'tool_start':
                $toolName = $data['tool'] ?? 'unknown';
                echo "\n🔧 Executing tool: {$toolName}\n";
                echo "   Input: " . json_encode($data['input'] ?? []) . "\n";
                break;

            case 'tool_end':
                $toolName = $data['tool'] ?? 'unknown';
                $result = $data['result'] ?? 'no result';
                echo "   ✅ Tool '{$toolName}' completed: {$result}\n\n";
                break;

            case 'progress':
                $percent = round($data['progress_percent'] ?? 0, 1);
                $currentStep = $data['current_step'] ?? 'unknown';
                echo "\n📊 Progress: {$percent}% - Step: {$currentStep}\n";
                break;

            case 'error':
                echo "\n❌ Error: {$data['error']}\n";
                break;

            case 'result':
                echo "\n\n=== Final Result ===\n";
                echo "Output: {$data['output']}\n";
                echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
                echo "Iterations: {$data['iterations']}\n";
                break;

            case 'end':
                echo "\n✅ Flow completed: {$data['status']}\n";
                break;
        }
    }

    // Get final progress
    $progress = $executor->getCurrentProgress();
    if ($progress) {
        echo "\n=== Execution Statistics ===\n";
        echo "Duration: {$progress['formatted_duration']}\n";
        echo "Iterations: {$progress['current_iteration']}/{$progress['total_iterations']}\n";
        echo "Steps completed: {$progress['completed_step_count']}/{$progress['step_count']}\n";
    }

} catch (Exception $e) {
    echo "\n❌ Execution failed: {$e->getMessage()}\n";
    exit(1);
}

echo "\n=== Execution Complete ===\n";
