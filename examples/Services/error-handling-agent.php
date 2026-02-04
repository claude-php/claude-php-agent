<?php

declare(strict_types=1);

/**
 * Agent Integration Example
 *
 * Demonstrates how to integrate the ErrorHandlingService with
 * the Agent class for robust, production-ready AI applications.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\ErrorHandling\ErrorHandlingServiceFactory;
use ClaudeAgents\Exceptions\AgentException;
use ClaudePhp\ClaudePhp;

echo "=== Error Handling Service - Agent Integration Example ===\n\n";

// Register the ErrorHandlingService factory
ServiceManager::getInstance()->registerFactory(new ErrorHandlingServiceFactory());

/**
 * Production-ready agent with comprehensive error handling
 */
class RobustAgent
{
    private Agent $agent;
    private $errorService;
    private array $metrics = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
    ];

    public function __construct(string $apiKey)
    {
        $client = new ClaudePhp(apiKey: $apiKey);
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
        
        // Create agent with error-safe tools
        $this->agent = Agent::create($client)
            ->withTool($this->createCalculatorTool())
            ->withSystemPrompt('You are a helpful assistant that can perform calculations.');
    }

    /**
     * Create a calculator tool with built-in error handling
     */
    private function createCalculatorTool(): Tool
    {
        return Tool::create('calculate')
            ->description('Safely perform mathematical calculations')
            ->parameter('expression', 'string', 'Mathematical expression')
            ->required('expression')
            ->handler(function (array $input) {
                // Use error service for safe tool execution
                $result = $this->errorService->executeToolSafely(
                    function ($in) {
                        // Validate and evaluate expression
                        $expr = preg_replace('/[^0-9+\-*\/().\s]/', '', $in['expression']);
                        return (string) eval("return {$expr};");
                    },
                    'calculate',
                    $input
                );
                
                return $result['content'];
            });
    }

    /**
     * Run the agent with comprehensive error handling
     */
    public function run(string $task): array
    {
        $this->metrics['total_requests']++;
        $startTime = microtime(true);
        
        try {
            // Execute with automatic retry logic
            $result = $this->errorService->executeWithRetry(
                fn() => $this->agent->run($task),
                "Agent task: {$task}"
            );
            
            $this->metrics['successful_requests']++;
            $duration = microtime(true) - $startTime;
            
            return [
                'success' => true,
                'result' => $result->getAnswer(),
                'duration' => round($duration, 3),
            ];
            
        } catch (\Throwable $e) {
            $this->metrics['failed_requests']++;
            $duration = microtime(true) - $startTime;
            
            // Get detailed error information
            $details = $this->errorService->getErrorDetails($e);
            
            // Log for monitoring (in production, use proper logger)
            error_log(json_encode([
                'event' => 'agent_error',
                'task' => $task,
                'error' => $details['exception_class'],
                'message' => $details['message'],
                'duration' => $duration,
            ]));
            
            // Return user-friendly error
            return [
                'success' => false,
                'error' => $this->errorService->convertToUserFriendly($e),
                'duration' => round($duration, 3),
            ];
        }
    }

    /**
     * Get agent metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}

// Example usage
$apiKey = getenv('ANTHROPIC_API_KEY');

if (!$apiKey) {
    echo "⚠️  Please set ANTHROPIC_API_KEY environment variable\n";
    exit(1);
}

$agent = new RobustAgent($apiKey);

// Test cases
$testCases = [
    'What is 25 * 17 + 100?',
    'Calculate 1000 / 25',
    'What is 2 to the power of 10?',
];

echo "Running test cases...\n";
echo str_repeat('=', 50) . "\n\n";

foreach ($testCases as $i => $task) {
    echo ($i + 1) . ". Task: {$task}\n";
    echo str_repeat('-', 50) . "\n";
    
    $result = $agent->run($task);
    
    if ($result['success']) {
        echo "✅ Success!\n";
        echo "Answer: {$result['result']}\n";
    } else {
        echo "❌ Error!\n";
        echo "Message: {$result['error']}\n";
    }
    
    echo "Duration: {$result['duration']}s\n\n";
}

// Show metrics
echo str_repeat('=', 50) . "\n";
echo "Agent Metrics:\n";
$metrics = $agent->getMetrics();
echo "  Total Requests: {$metrics['total_requests']}\n";
echo "  Successful: {$metrics['successful_requests']}\n";
echo "  Failed: {$metrics['failed_requests']}\n";

if ($metrics['total_requests'] > 0) {
    $successRate = ($metrics['successful_requests'] / $metrics['total_requests']) * 100;
    echo "  Success Rate: " . round($successRate, 2) . "%\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Example completed!\n";
