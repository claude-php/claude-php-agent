<?php

declare(strict_types=1);

/**
 * Tutorial 3: Integration with Agents
 *
 * Learn how to integrate the ErrorHandlingService with the Agent class
 * for robust, production-ready AI applications.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\ErrorHandling\ErrorHandlingServiceFactory;
use ClaudePhp\ClaudePhp;

echo "Tutorial 3: Integration with Agents\n";
echo str_repeat('=', 70) . "\n\n";

// Register factory
ServiceManager::getInstance()->registerFactory(new ErrorHandlingServiceFactory());

$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "⚠ Set ANTHROPIC_API_KEY to run this tutorial\n";
    exit(1);
}

// Step 1: Create an agent with error handling
echo "Step 1: Creating Agent with Error Handling...\n";
echo str_repeat('-', 70) . "\n";

class ErrorSafeAgent
{
    private Agent $agent;
    private $errorService;

    public function __construct(string $apiKey)
    {
        $client = new ClaudePhp(apiKey: $apiKey);
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
        $this->agent = Agent::create($client);
    }

    public function run(string $task): array
    {
        try {
            $result = $this->agent->run($task);
            
            return [
                'success' => true,
                'result' => $result->getAnswer(),
            ];
        } catch (\Throwable $e) {
            // Log detailed error
            $details = $this->errorService->getErrorDetails($e);
            error_log(json_encode($details));
            
            // Return user-friendly message
            return [
                'success' => false,
                'error' => $this->errorService->convertToUserFriendly($e),
            ];
        }
    }
}

$agent = new ErrorSafeAgent($apiKey);
echo "✓ Agent created with error handling\n\n";

// Step 2: Create error-safe tools
echo "Step 2: Creating Error-Safe Tool...\n";
echo str_repeat('-', 70) . "\n";

$errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);

$calculatorTool = Tool::create('calculate')
    ->description('Perform calculations safely')
    ->parameter('expression', 'string', 'Math expression')
    ->required('expression')
    ->handler(function (array $input) use ($errorService) {
        return $errorService->executeToolSafely(
            function ($in) {
                $expr = preg_replace('/[^0-9+\-*\/().\s]/', '', $in['expression']);
                return (string) eval("return {$expr};");
            },
            'calculate',
            $input
        )['content'];
    });

echo "✓ Tool created with executeToolSafely()\n\n";

// Step 3: Test agent execution
echo "Step 3: Testing Agent with Error Handling...\n";
echo str_repeat('-', 70) . "\n";

$result = $agent->run('What is 2+2?');

if ($result['success']) {
    echo "✓ Success: {$result['result']}\n";
} else {
    echo "✗ Error: {$result['error']}\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Tutorial 3 Complete!\n\n";

echo "Key Takeaways:\n";
echo "• Wrap agent.run() in try-catch with error service\n";
echo "• Use executeToolSafely() for tool error handling\n";
echo "• Log detailed errors, return user-friendly messages\n";
echo "• Build error-resilient agent workflows\n";
