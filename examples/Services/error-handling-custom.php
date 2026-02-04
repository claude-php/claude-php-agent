<?php

declare(strict_types=1);

/**
 * Custom Error Patterns Example
 *
 * Demonstrates how to create custom error patterns and override
 * default messages to match your application's terminology.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudePhp\Exceptions\RateLimitError;
use Psr\Log\NullLogger;

echo "=== Error Handling Service - Custom Patterns Example ===\n\n";

// Create service with custom error patterns
$service = new ErrorHandlingService(
    logger: new NullLogger(),
    maxRetries: 3,
    initialDelayMs: 1000,
    customPatterns: [
        // Add custom application-specific errors
        'database_error' => [
            'exception_class' => \PDOException::class,
            'user_message' => 'Database connection failed. Please try again later.',
            'suggested_action' => 'Our team has been notified. Please retry in a few minutes.',
        ],
        'file_error' => [
            'exception_class' => \RuntimeException::class,
            'message_pattern' => '/file.*not.*found/i',
            'user_message' => 'Required file is missing. Please contact support.',
            'suggested_action' => 'Quote error code: FILE_404',
        ],
        'quota_exceeded' => [
            'message_pattern' => '/quota.*exceeded|billing.*limit/i',
            'user_message' => 'API usage limit reached. Please upgrade your plan.',
            'suggested_action' => 'Visit your account settings to increase limits.',
        ],
        // Override default rate limit message
        'rate_limit' => [
            'exception_class' => RateLimitError::class,
            'user_message' => 'You\'re making requests too quickly. Please slow down.',
            'suggested_action' => 'Wait 2 minutes before trying again.',
        ],
    ]
);

$service->initialize();

echo "1. Testing Custom Database Error Pattern\n";
echo str_repeat('-', 50) . "\n";

try {
    throw new \PDOException('SQLSTATE[HY000] [2002] Connection refused');
} catch (\Throwable $e) {
    echo "Original: " . $e->getMessage() . "\n";
    echo "User Message: " . $service->convertToUserFriendly($e) . "\n";
    
    $details = $service->getErrorDetails($e);
    if (isset($details['suggested_action'])) {
        echo "Action: " . $details['suggested_action'] . "\n";
    }
}

echo "\n2. Testing Custom File Error Pattern (with regex)\n";
echo str_repeat('-', 50) . "\n";

try {
    throw new \RuntimeException('File not found: /path/to/config.json');
} catch (\Throwable $e) {
    echo "Original: " . $e->getMessage() . "\n";
    echo "User Message: " . $service->convertToUserFriendly($e) . "\n";
}

echo "\n3. Testing Custom Quota Pattern (message matching)\n";
echo str_repeat('-', 50) . "\n";

try {
    throw new \RuntimeException('API quota exceeded for this billing period');
} catch (\Throwable $e) {
    echo "Original: " . $e->getMessage() . "\n";
    echo "User Message: " . $service->convertToUserFriendly($e) . "\n";
}

echo "\n4. Testing Overridden Rate Limit Message\n";
echo str_repeat('-', 50) . "\n";

// Create mock rate limit error
$mockRequest = new class implements \Psr\Http\Message\RequestInterface {
    public function getProtocolVersion() { return '1.1'; }
    public function withProtocolVersion($version) { return $this; }
    public function getHeaders() { return []; }
    public function hasHeader($name) { return false; }
    public function getHeader($name) { return []; }
    public function getHeaderLine($name) { return ''; }
    public function withHeader($name, $value) { return $this; }
    public function withAddedHeader($name, $value) { return $this; }
    public function withoutHeader($name) { return $this; }
    public function getBody() { return null; }
    public function withBody(\Psr\Http\Message\StreamInterface $body) { return $this; }
    public function getRequestTarget() { return '/'; }
    public function withRequestTarget($requestTarget) { return $this; }
    public function getMethod() { return 'POST'; }
    public function withMethod($method) { return $this; }
    public function getUri() { return null; }
    public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false) { return $this; }
};

$mockResponse = new class implements \Psr\Http\Message\ResponseInterface {
    public function getProtocolVersion() { return '1.1'; }
    public function withProtocolVersion($version) { return $this; }
    public function getHeaders() { return []; }
    public function hasHeader($name) { return false; }
    public function getHeader($name) { return []; }
    public function getHeaderLine($name) { return ''; }
    public function withHeader($name, $value) { return $this; }
    public function withAddedHeader($name, $value) { return $this; }
    public function withoutHeader($name) { return $this; }
    public function getBody() { return null; }
    public function withBody(\Psr\Http\Message\StreamInterface $body) { return $this; }
    public function getStatusCode() { return 429; }
    public function withStatus($code, $reasonPhrase = '') { return $this; }
    public function getReasonPhrase() { return 'Too Many Requests'; }
};

try {
    $error = new RateLimitError(
        status_code: 429,
        message: 'Rate limit exceeded',
        request: $mockRequest,
        response: $mockResponse
    );
    throw $error;
} catch (RateLimitError $e) {
    echo "Original: " . $e->getMessage() . "\n";
    echo "Custom Message: " . $service->convertToUserFriendly($e) . "\n";
}

echo "\n5. Adding Pattern Dynamically\n";
echo str_repeat('-', 50) . "\n";

// Add a new pattern at runtime
$service->addErrorPattern('maintenance', [
    'message_pattern' => '/maintenance|temporarily.*unavailable/i',
    'user_message' => 'System maintenance in progress.',
    'suggested_action' => 'Normal service will resume in 30 minutes.',
]);

try {
    throw new \RuntimeException('Service temporarily unavailable for maintenance');
} catch (\Throwable $e) {
    echo "Original: " . $e->getMessage() . "\n";
    echo "Dynamic Pattern: " . $service->convertToUserFriendly($e) . "\n";
}

echo "\n6. Unknown Error Fallback\n";
echo str_repeat('-', 50) . "\n";

try {
    throw new \DomainException('Some unexpected domain error');
} catch (\Throwable $e) {
    echo "Original: " . $e->getMessage() . "\n";
    echo "Fallback: " . $service->convertToUserFriendly($e) . "\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "All configured patterns:\n";
$patterns = $service->getErrorPatterns();
echo "Total patterns: " . count($patterns) . "\n";
foreach (array_keys($patterns) as $name) {
    echo "  - {$name}\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Example completed!\n";
