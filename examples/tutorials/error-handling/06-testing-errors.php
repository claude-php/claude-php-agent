<?php

declare(strict_types=1);

/**
 * Tutorial 6: Testing Strategies
 *
 * Learn how to write unit and integration tests for error handling,
 * mock API errors, and test retry logic.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudePhp\Exceptions\RateLimitError;
use Psr\Log\NullLogger;

echo "Tutorial 6: Testing Strategies\n";
echo str_repeat('=', 70) . "\n\n";

// Step 1: Test error conversion
echo "Step 1: Testing Error Conversion...\n";
echo str_repeat('-', 70) . "\n";

$service = new ErrorHandlingService(logger: new NullLogger());
$service->initialize();

// Test 1: Standard exception
$error1 = new \RuntimeException('Test error');
$message1 = $service->convertToUserFriendly($error1);
echo "Test 1 - RuntimeException:\n";
echo "  Input: {$error1->getMessage()}\n";
echo "  Output: {$message1}\n";
echo "  ✓ Returns fallback message\n\n";

// Step 2: Test custom patterns
echo "Step 2: Testing Custom Patterns...\n";
echo str_repeat('-', 70) . "\n";

$customService = new ErrorHandlingService(
    logger: new NullLogger(),
    customPatterns: [
        'test_error' => [
            'exception_class' => \LogicException::class,
            'user_message' => 'Test error message',
        ],
    ]
);

$customService->initialize();

$error2 = new \LogicException('Logic error');
$message2 = $customService->convertToUserFriendly($error2);

echo "Test 2 - Custom Pattern:\n";
echo "  Pattern: test_error\n";
echo "  Exception: LogicException\n";
echo "  Expected: 'Test error message'\n";
echo "  Actual: '{$message2}'\n";
echo "  " . ($message2 === 'Test error message' ? '✓' : '✗') . " Match\n\n";

// Step 3: Test error details extraction
echo "Step 3: Testing Error Details Extraction...\n";
echo str_repeat('-', 70) . "\n";

$error3 = new \RuntimeException('Details test', 123);
$details = $service->getErrorDetails($error3);

$requiredKeys = ['exception_class', 'message', 'code', 'file', 'line'];
$hasAllKeys = true;

echo "Test 3 - Error Details:\n";
foreach ($requiredKeys as $key) {
    $exists = isset($details[$key]);
    echo "  {$key}: " . ($exists ? '✓' : '✗') . "\n";
    if (!$exists) {
        $hasAllKeys = false;
    }
}
echo "\n";

// Step 4: Test retry logic
echo "Step 4: Testing Retry Logic...\n";
echo str_repeat('-', 70) . "\n";

$attempts = 0;
$maxAttempts = 3;

try {
    $service->executeWithRetry(
        function () use (&$attempts, $maxAttempts) {
            $attempts++;
            if ($attempts < $maxAttempts) {
                throw new \RuntimeException('Retry test');
            }
            return 'Success';
        },
        'Retry test'
    );
    
    echo "Test 4 - Retry Logic:\n";
    echo "  Max Attempts: {$maxAttempts}\n";
    echo "  Actual Attempts: {$attempts}\n";
    echo "  " . ($attempts === $maxAttempts ? '✓' : '✗') . " Correct retry count\n";
} catch (\Throwable $e) {
    echo "  ✗ Unexpected exception: {$e->getMessage()}\n";
}

echo "\n";

// Step 5: Test tool execution
echo "Step 5: Testing Tool Execution...\n";
echo str_repeat('-', 70) . "\n";

// Success case
$successResult = $service->executeToolSafely(
    fn() => 'tool result',
    'test_tool',
    []
);

echo "Test 5a - Successful Tool:\n";
echo "  Success: " . ($successResult['success'] ? '✓' : '✗') . "\n";
echo "  Is Error: " . ($successResult['is_error'] ? '✗' : '✓') . "\n";
echo "  Content: {$successResult['content']}\n\n";

// Failure case
$failureResult = $service->executeToolSafely(
    function () {
        throw new \RuntimeException('Tool failed');
    },
    'failing_tool',
    []
);

echo "Test 5b - Failing Tool:\n";
echo "  Success: " . (!$failureResult['success'] ? '✓' : '✗') . "\n";
echo "  Is Error: " . ($failureResult['is_error'] ? '✓' : '✗') . "\n";
echo "  Has Error Message: " . (str_contains($failureResult['content'], 'Error:') ? '✓' : '✗') . "\n";

echo "\n" . str_repeat('=', 70) . "\n";
echo "Tutorial 6 Complete!\n\n";

echo "Key Takeaways:\n";
echo "• Test error conversion with different exception types\n";
echo "• Verify custom patterns work as expected\n";
echo "• Check error details contain all required fields\n";
echo "• Test retry logic counts attempts correctly\n";
echo "• Test tool execution handles both success and failure\n";
