<?php

declare(strict_types=1);

/**
 * Tutorial 2: Custom Error Patterns
 *
 * Learn how to create custom error patterns, override default messages,
 * and use message pattern matching for application-specific errors.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudePhp\Exceptions\RateLimitError;
use Psr\Log\NullLogger;

echo "Tutorial 2: Custom Error Patterns\n";
echo str_repeat('=', 70) . "\n\n";

// Step 1: Create service with custom patterns
echo "Step 1: Creating Service with Custom Patterns...\n";
echo str_repeat('-', 70) . "\n";

$service = new ErrorHandlingService(
    logger: new NullLogger(),
    customPatterns: [
        'database_error' => [
            'exception_class' => \PDOException::class,
            'user_message' => 'Database connection failed. Please try again.',
        ],
        'file_not_found' => [
            'message_pattern' => '/file.*not.*found/i',
            'user_message' => 'Required file is missing. Contact support.',
        ],
    ]
);

$service->initialize();
echo "✓ Service created with 2 custom patterns\n\n";

// Step 2: Test custom database error pattern
echo "Step 2: Testing Custom Database Error Pattern...\n";
echo str_repeat('-', 70) . "\n";

try {
    throw new \PDOException('SQLSTATE[HY000] Connection refused');
} catch (\PDOException $e) {
    echo "Original Error: {$e->getMessage()}\n";
    echo "User Message: " . $service->convertToUserFriendly($e) . "\n\n";
}

// Step 3: Test pattern with regex matching
echo "Step 3: Testing Pattern with Regex Matching...\n";
echo str_repeat('-', 70) . "\n";

try {
    throw new \RuntimeException('Configuration file not found in /etc/config/');
} catch (\RuntimeException $e) {
    echo "Original Error: {$e->getMessage()}\n";
    echo "User Message: " . $service->convertToUserFriendly($e) . "\n\n";
}

// Step 4: Override default pattern
echo "Step 4: Overriding Default Error Pattern...\n";
echo str_repeat('-', 70) . "\n";

$customService = new ErrorHandlingService(
    customPatterns: [
        'rate_limit' => [
            'exception_class' => RateLimitError::class,
            'user_message' => 'Too many requests. Please wait 2 minutes.',
        ],
    ]
);

$customService->initialize();
echo "✓ Created service with overridden rate_limit pattern\n\n";

// Step 5: Add pattern dynamically
echo "Step 5: Adding Pattern Dynamically...\n";
echo str_repeat('-', 70) . "\n";

$service->addErrorPattern('maintenance', [
    'message_pattern' => '/maintenance|under.*maintenance/i',
    'user_message' => 'System maintenance in progress.',
    'suggested_action' => 'Service resumes in 30 minutes.',
]);

try {
    throw new \RuntimeException('Service under maintenance');
} catch (\RuntimeException $e) {
    echo "Original Error: {$e->getMessage()}\n";
    echo "User Message: " . $service->convertToUserFriendly($e) . "\n";
    
    $details = $service->getErrorDetails($e);
    if (isset($details['suggested_action'])) {
        echo "Suggested Action: {$details['suggested_action']}\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Tutorial 2 Complete!\n\n";

echo "Key Takeaways:\n";
echo "• Create custom patterns via constructor customPatterns parameter\n";
echo "• Use exception_class for type-based matching\n";
echo "• Use message_pattern for regex-based matching\n";
echo "• Override defaults by using same pattern name\n";
echo "• Add patterns dynamically with addErrorPattern()\n";
