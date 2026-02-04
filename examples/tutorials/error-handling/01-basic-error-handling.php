<?php

declare(strict_types=1);

/**
 * Tutorial 1: Basic Error Handling
 *
 * This example demonstrates the fundamentals of using the ErrorHandlingService
 * to convert technical API errors into user-friendly messages.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\ErrorHandling\ErrorHandlingServiceFactory;
use ClaudePhp\ClaudePhp;

echo "Tutorial 1: Basic Error Handling\n";
echo str_repeat('=', 70) . "\n\n";

// Step 1: Get the ErrorHandlingService from ServiceManager
echo "Step 1: Getting ErrorHandlingService...\n";
$serviceManager = ServiceManager::getInstance();
$serviceManager->registerFactory(new ErrorHandlingServiceFactory());

$errorService = $serviceManager->get(ServiceType::ERROR_HANDLING);
echo "✓ Service retrieved successfully\n\n";

// Step 2: Test with invalid API key (triggers authentication error)
echo "Step 2: Testing Authentication Error Conversion...\n";
echo str_repeat('-', 70) . "\n";

$client = new ClaudePhp(apiKey: 'invalid_test_key');

try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 100,
        'messages' => [
            ['role' => 'user', 'content' => 'Hello!'],
        ],
    ]);
} catch (\Throwable $e) {
    // Technical error (what developers see)
    echo "Technical Error:\n";
    echo "  Exception: " . get_class($e) . "\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "\n";
    
    // User-friendly message (what end users see)
    $userMessage = $errorService->convertToUserFriendly($e);
    echo "User-Friendly Message:\n";
    echo "  {$userMessage}\n";
    echo "\n";
}

// Step 3: Get detailed error information
echo "Step 3: Extracting Detailed Error Information...\n";
echo str_repeat('-', 70) . "\n";

try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 100,
        'messages' => [['role' => 'user', 'content' => 'Test']],
    ]);
} catch (\Throwable $e) {
    $details = $errorService->getErrorDetails($e);
    
    echo "Detailed Error Information:\n";
    echo "  Exception Class: {$details['exception_class']}\n";
    echo "  Error Code: {$details['code']}\n";
    echo "  Location: {$details['file']}:{$details['line']}\n";
    
    if (isset($details['status_code'])) {
        echo "  HTTP Status: {$details['status_code']}\n";
    }
    
    if (isset($details['suggested_action'])) {
        echo "  Suggested Action: {$details['suggested_action']}\n";
    }
    
    echo "\n";
}

// Step 4: Test with valid API key (if available)
echo "Step 4: Testing with Valid API Key...\n";
echo str_repeat('-', 70) . "\n";

if ($apiKey = getenv('ANTHROPIC_API_KEY')) {
    $validClient = new ClaudePhp(apiKey: $apiKey);
    
    try {
        $response = $validClient->messages()->create([
            'model' => 'claude-sonnet-4',
            'max_tokens' => 20,
            'messages' => [
                ['role' => 'user', 'content' => 'Say hello in one word'],
            ],
        ]);
        
        echo "✓ Request succeeded!\n";
        echo "  Response: " . $response->content[0]->text . "\n";
    } catch (\Throwable $e) {
        echo "✗ Request failed:\n";
        echo "  " . $errorService->convertToUserFriendly($e) . "\n";
    }
} else {
    echo "⚠ Set ANTHROPIC_API_KEY to test with valid credentials\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Tutorial 1 Complete!\n\n";

echo "Key Takeaways:\n";
echo "• Use ServiceManager to get the ErrorHandlingService\n";
echo "• convertToUserFriendly() transforms technical errors into readable messages\n";
echo "• getErrorDetails() provides rich debugging information\n";
echo "• The service handles all Claude SDK exceptions automatically\n";
