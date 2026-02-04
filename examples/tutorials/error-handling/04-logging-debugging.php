<?php

declare(strict_types=1);

/**
 * Tutorial 4: Logging and Debugging
 *
 * Learn how to configure PSR-3 logging, monitor error rates,
 * and debug error patterns effectively.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "Tutorial 4: Logging and Debugging\n";
echo str_repeat('=', 70) . "\n\n";

// Step 1: Setup PSR-3 Logging
echo "Step 1: Setting up PSR-3 Logger...\n";
echo str_repeat('-', 70) . "\n";

$logger = new Logger('error_handling');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$service = new ErrorHandlingService(
    logger: $logger,
    maxRetries: 2,
    initialDelayMs: 100
);

$service->initialize();
echo "✓ Logger configured with Monolog\n\n";

// Step 2: Log errors with context
echo "Step 2: Logging Errors with Rich Context...\n";
echo str_repeat('-', 70) . "\n";

try {
    throw new \RuntimeException('Test error for logging');
} catch (\Throwable $e) {
    $details = $service->getErrorDetails($e);
    
    // Log with additional context
    $logger->error('Operation failed', array_merge($details, [
        'user_id' => 'user_123',
        'request_id' => uniqid(),
        'environment' => 'development',
    ]));
}

echo "\n";

// Step 3: Error rate monitoring
echo "Step 3: Monitoring Error Rates...\n";
echo str_repeat('-', 70) . "\n";

class ErrorMonitor
{
    private array $errors = [];
    private int $totalRequests = 0;

    public function trackRequest(callable $fn, $errorService): void
    {
        $this->totalRequests++;
        
        try {
            $fn();
        } catch (\Throwable $e) {
            $errorType = basename(str_replace('\\', '/', get_class($e)));
            
            if (!isset($this->errors[$errorType])) {
                $this->errors[$errorType] = 0;
            }
            $this->errors[$errorType]++;
            
            // Log for monitoring
            $details = $errorService->getErrorDetails($e);
            error_log(json_encode([
                'error_type' => $errorType,
                'message' => $details['user_friendly_message'],
                'timestamp' => time(),
            ]));
        }
    }

    public function getStats(): array
    {
        $totalErrors = array_sum($this->errors);
        $errorRate = $this->totalRequests > 0 
            ? ($totalErrors / $this->totalRequests) * 100 
            : 0;
        
        return [
            'total_requests' => $this->totalRequests,
            'total_errors' => $totalErrors,
            'error_rate_percent' => round($errorRate, 2),
            'errors_by_type' => $this->errors,
        ];
    }
}

$monitor = new ErrorMonitor();

// Simulate requests
for ($i = 0; $i < 5; $i++) {
    $monitor->trackRequest(function () use ($i) {
        if ($i % 2 === 0) {
            throw new \RuntimeException("Error {$i}");
        }
        return "Success {$i}";
    }, $service);
}

$stats = $monitor->getStats();
echo "Total Requests: {$stats['total_requests']}\n";
echo "Total Errors: {$stats['total_errors']}\n";
echo "Error Rate: {$stats['error_rate_percent']}%\n";

if (!empty($stats['errors_by_type'])) {
    echo "Errors by Type:\n";
    foreach ($stats['errors_by_type'] as $type => $count) {
        echo "  - {$type}: {$count}\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Tutorial 4 Complete!\n\n";

echo "Key Takeaways:\n";
echo "• Configure PSR-3 logger with Monolog or other implementations\n";
echo "• Use getErrorDetails() for structured logging\n";
echo "• Track error rates and types for monitoring\n";
echo "• Add custom context to error logs\n";
