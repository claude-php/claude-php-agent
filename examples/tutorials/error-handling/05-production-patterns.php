<?php

declare(strict_types=1);

/**
 * Tutorial 5: Production Patterns
 *
 * Learn production deployment patterns including circuit breaker,
 * rate limiting, and health checks.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Helpers\CircuitBreaker;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\ErrorHandling\ErrorHandlingService;
use ClaudeAgents\Services\ErrorHandling\ErrorHandlingServiceFactory;

echo "Tutorial 5: Production Patterns\n";
echo str_repeat('=', 70) . "\n\n";

// Register factory
ServiceManager::getInstance()->registerFactory(new ErrorHandlingServiceFactory());

// Step 1: Circuit Breaker Pattern
echo "Step 1: Implementing Circuit Breaker...\n";
echo str_repeat('-', 70) . "\n";

class ResilientService
{
    private CircuitBreaker $breaker;
    private $errorService;

    public function __construct()
    {
        $this->breaker = new CircuitBreaker(
            failureThreshold: 3,
            resetTimeout: 5
        );
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
    }

    public function makeRequest(callable $fn): mixed
    {
        if ($this->breaker->isOpen()) {
            throw new \RuntimeException('Circuit breaker open: Service unavailable');
        }

        try {
            $result = $fn();
            $this->breaker->recordSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->breaker->recordFailure();
            throw $e;
        }
    }

    public function getHealth(): array
    {
        return [
            'state' => $this->breaker->getState(),
            'failures' => $this->breaker->getFailureCount(),
            'available' => !$this->breaker->isOpen(),
        ];
    }
}

$resilient = new ResilientService();

// Simulate failures
for ($i = 0; $i < 5; $i++) {
    try {
        $resilient->makeRequest(function () use ($i) {
            if ($i < 3) {
                throw new \RuntimeException("Failure {$i}");
            }
            return "Success";
        });
        echo "✓ Request {$i}: Success\n";
    } catch (\Throwable $e) {
        echo "✗ Request {$i}: {$e->getMessage()}\n";
    }
}

$health = $resilient->getHealth();
echo "\nCircuit Breaker State: {$health['state']}\n";
echo "Failure Count: {$health['failures']}\n";
echo "Available: " . ($health['available'] ? 'Yes' : 'No') . "\n\n";

// Step 2: Rate Limiting
echo "Step 2: Implementing Rate Limiting...\n";
echo str_repeat('-', 70) . "\n";

$rateLimiter = ErrorHandlingService::createRateLimiter(200); // 200ms between requests

echo "Making rate-limited requests:\n";
$startTime = microtime(true);

for ($i = 0; $i < 3; $i++) {
    $rateLimiter();
    echo "  Request {$i} at " . round((microtime(true) - $startTime) * 1000) . "ms\n";
}

echo "\n";

// Step 3: Health Check
echo "Step 3: Implementing Health Check...\n";
echo str_repeat('-', 70) . "\n";

class HealthChecker
{
    private $errorService;

    public function __construct()
    {
        $this->errorService = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
    }

    public function checkServices(): array
    {
        $checks = [];
        
        // Check error service
        try {
            $service = ServiceManager::getInstance()->get(ServiceType::ERROR_HANDLING);
            $checks['error_service'] = [
                'status' => 'healthy',
                'ready' => $service->isReady(),
            ];
        } catch (\Throwable $e) {
            $checks['error_service'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
        
        return [
            'status' => 'healthy',
            'timestamp' => time(),
            'checks' => $checks,
        ];
    }
}

$healthChecker = new HealthChecker();
$healthStatus = $healthChecker->checkServices();

echo "Health Check Status: {$healthStatus['status']}\n";
echo "Timestamp: {$healthStatus['timestamp']}\n";
echo "Checks:\n";
foreach ($healthStatus['checks'] as $name => $check) {
    echo "  {$name}: {$check['status']}\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Tutorial 5 Complete!\n\n";

echo "Key Takeaways:\n";
echo "• Use circuit breaker to prevent cascading failures\n";
echo "• Implement rate limiting with createRateLimiter()\n";
echo "• Create health check endpoints for monitoring\n";
echo "• Combine patterns for production-ready systems\n";
