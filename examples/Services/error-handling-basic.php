<?php

declare(strict_types=1);

/**
 * Basic Error Handling Example
 *
 * Demonstrates basic usage of the ErrorHandlingService to convert
 * technical API errors into user-friendly messages.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\ErrorHandling\ErrorHandlingServiceFactory;
use ClaudePhp\ClaudePhp;

echo "=== Error Handling Service - Basic Example ===\n\n";

// Register the factory and get the error handling service
$serviceManager = ServiceManager::getInstance();
$serviceManager->registerFactory(new ErrorHandlingServiceFactory());

$errorService = $serviceManager->get(ServiceType::ERROR_HANDLING);

echo "Testing Authentication Error...\n";
echo str_repeat('-', 50) . "\n";

// Test with an invalid API key to trigger authentication error
$client = new ClaudePhp(apiKey: 'invalid_key_for_testing');

try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4',
        'max_tokens' => 100,
        'messages' => [
            ['role' => 'user', 'content' => 'Hello!'],
        ],
    ]);

    echo "✅ Success: " . $response->content[0]->text . "\n";

} catch (\Throwable $e) {
    // Show technical error (for developers)
    echo "🔧 Technical Error:\n";
    echo "   Class: " . get_class($e) . "\n";
    echo "   Message: " . $e->getMessage() . "\n";
    
    echo "\n";
    
    // Convert to user-friendly message (for end users)
    $userMessage = $errorService->convertToUserFriendly($e);
    echo "👤 User-Friendly Message:\n";
    echo "   {$userMessage}\n";
    
    echo "\n";
    
    // Get detailed error information (for logging/debugging)
    $details = $errorService->getErrorDetails($e);
    echo "📊 Detailed Information:\n";
    echo "   Exception: {$details['exception_class']}\n";
    echo "   Code: {$details['code']}\n";
    echo "   File: {$details['file']}:{$details['line']}\n";
    
    if (isset($details['status_code'])) {
        echo "   HTTP Status: {$details['status_code']}\n";
    }
    
    if (isset($details['suggested_action'])) {
        echo "   💡 Suggested Action: {$details['suggested_action']}\n";
    }
}

echo "\n" . str_repeat('=', 50) . "\n";

// Test with valid API key (if available)
if ($apiKey = getenv('ANTHROPIC_API_KEY')) {
    echo "\nTesting with Valid API Key...\n";
    echo str_repeat('-', 50) . "\n";
    
    $validClient = new ClaudePhp(apiKey: $apiKey);
    
    try {
        $response = $validClient->messages()->create([
            'model' => 'claude-sonnet-4',
            'max_tokens' => 50,
            'messages' => [
                ['role' => 'user', 'content' => 'Say hello in one word'],
            ],
        ]);
        
        echo "✅ Success!\n";
        echo "Response: " . $response->content[0]->text . "\n";
        
    } catch (\Throwable $e) {
        $userMessage = $errorService->convertToUserFriendly($e);
        echo "❌ Error: {$userMessage}\n";
    }
} else {
    echo "\n⚠️  Set ANTHROPIC_API_KEY environment variable to test with valid credentials\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Example completed!\n";
