# Production Patterns Tutorial: Deploy AI Agents in Production

## Introduction

This tutorial will guide you through production-ready patterns for deploying AI agents, including error handling, logging, observability, caching, security, and monitoring.

By the end of this tutorial, you'll be able to:

- Implement robust error handling and recovery
- Set up structured logging and observability
- Configure caching for performance
- Secure your AI agents properly
- Monitor health and performance
- Deploy agents at scale

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of production systems
- Familiarity with Docker (optional)
- Completed [Services System Tutorial](./ServicesSystem_Tutorial.md) (recommended)

## Table of Contents

1. [Understanding Production Requirements](#understanding-production-requirements)
2. [Setup and Installation](#setup-and-installation)
3. [Tutorial 1: Error Handling](#tutorial-1-error-handling)
4. [Tutorial 2: Logging](#tutorial-2-logging)
5. [Tutorial 3: Observability](#tutorial-3-observability)
6. [Tutorial 4: Caching](#tutorial-4-caching)
7. [Tutorial 5: Security](#tutorial-5-security)
8. [Tutorial 6: Monitoring](#tutorial-6-monitoring)
9. [Tutorial 7: Deployment](#tutorial-7-deployment)
10. [Common Patterns](#common-patterns)
11. [Troubleshooting](#troubleshooting)
12. [Next Steps](#next-steps)

## Understanding Production Requirements

Production AI systems need:

- **Reliability** - Handle failures gracefully
- **Observability** - Track what's happening
- **Performance** - Respond quickly, cache effectively
- **Security** - Protect API keys and data
- **Scalability** - Handle growing load
- **Maintainability** - Easy to debug and update

### Production vs Development

| Aspect | Development | Production |
|--------|-------------|------------|
| Error Handling | Exceptions OK | Must recover gracefully |
| Logging | echo/var_dump | Structured logs |
| Caching | Optional | Required for performance |
| Security | Relaxed | Strict validation |
| Monitoring | None | Required |
| Retries | Manual | Automatic |

## Setup and Installation

### Install Framework

```bash
composer require claude-php/agent:^0.8.0
composer require monolog/monolog      # Logging
composer require predis/predis        # Redis cache
```

### Environment Configuration

Create `.env`:

```env
APP_ENV=production
ANTHROPIC_API_KEY=your-api-key-here

# Logging
LOG_LEVEL=info
LOG_CHANNEL=stack

# Cache
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Monitoring
METRICS_ENABLED=true
HEALTH_CHECK_INTERVAL=60
```

## Tutorial 1: Error Handling

Implement robust error handling.

### Step 1: Basic Error Handling

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;

function runAgentSafely(string $query): ?string
{
    try {
        $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
        $agent = Agent::create($client);
        
        $result = $agent->run($query);
        return $result->getAnswer();
        
    } catch (\ClaudePhp\Exceptions\ApiException $e) {
        // API errors (rate limit, auth, etc.)
        error_log("API Error: " . $e->getMessage());
        return null;
        
    } catch (\ClaudeAgents\Exceptions\AgentException $e) {
        // Agent-specific errors
        error_log("Agent Error: " . $e->getMessage());
        return null;
        
    } catch (\Throwable $e) {
        // Unexpected errors
        error_log("Unexpected Error: " . $e->getMessage());
        return null;
    }
}

$answer = runAgentSafely('What is 2+2?');
echo $answer ?? 'Failed to get answer';
```

### Step 2: Retry Logic with Exponential Backoff

```php
use ClaudeAgents\Helpers\CircuitBreaker;

class ResilientAgent
{
    private Agent $agent;
    private int $maxRetries = 3;
    
    public function run(string $query): string
    {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $this->maxRetries) {
            try {
                $result = $this->agent->run($query);
                return $result->getAnswer();
                
            } catch (\Exception $e) {
                $lastError = $e;
                $attempt++;
                
                if ($attempt < $this->maxRetries) {
                    // Exponential backoff
                    $delay = pow(2, $attempt) * 1000000; // microseconds
                    usleep($delay);
                    
                    error_log("Retry attempt $attempt after error: " . $e->getMessage());
                }
            }
        }
        
        throw new \RuntimeException(
            "Failed after {$this->maxRetries} attempts",
            0,
            $lastError
        );
    }
}
```

### Step 3: Circuit Breaker Pattern

```php
use ClaudeAgents\Helpers\CircuitBreaker;

$circuitBreaker = new CircuitBreaker(
    failureThreshold: 5,    // Open after 5 failures
    recoveryTime: 60,       // Try again after 60s
    timeout: 30             // 30s timeout
);

function callAgentWithCircuitBreaker(string $query): ?string
{
    global $circuitBreaker;
    
    if ($circuitBreaker->isOpen()) {
        error_log('Circuit breaker is open, skipping call');
        return null;
    }
    
    try {
        $result = $agent->run($query);
        $circuitBreaker->recordSuccess();
        return $result->getAnswer();
        
    } catch (\Exception $e) {
        $circuitBreaker->recordFailure();
        throw $e;
    }
}
```

## Tutorial 2: Logging

Set up structured logging.

### Step 1: Configure Monolog

```php
<?php
require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;

$logger = new Logger('agent');

// Console handler for development
$logger->pushHandler(
    new StreamHandler('php://stdout', Logger::INFO)
);

// File handler for production
$fileHandler = new RotatingFileHandler(
    'logs/agent.log',
    30,           // Keep 30 days
    Logger::INFO
);
$fileHandler->setFormatter(new JsonFormatter());
$logger->pushHandler($fileHandler);

// Add context processors
$logger->pushProcessor(new IntrospectionProcessor());
$logger->pushProcessor(new MemoryUsageProcessor());
```

### Step 2: Integrate with Agents

```php
use ClaudeAgents\Agent;

$agent = Agent::create($client);

// Log agent lifecycle
$agent->onUpdate(function ($update) use ($logger) {
    $logger->info('Agent update', [
        'type' => $update->getType(),
        'data' => $update->getData(),
    ]);
});

// Log before execution
$logger->info('Starting agent execution', [
    'query' => $query,
    'agent_type' => get_class($agent),
]);

$result = $agent->run($query);

// Log after execution
$logger->info('Agent execution completed', [
    'success' => true,
    'answer_length' => strlen($result->getAnswer()),
]);
```

### Step 3: Contextual Logging

```php
class AgentLogger
{
    private Logger $logger;
    private string $requestId;
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->requestId = uniqid('req_', true);
    }
    
    private function withContext(array $context = []): array
    {
        return array_merge([
            'request_id' => $this->requestId,
            'timestamp' => time(),
            'memory_mb' => memory_get_usage(true) / 1024 / 1024,
        ], $context);
    }
    
    public function logExecution(string $query, callable $fn): mixed
    {
        $this->logger->info('Execution started', $this->withContext([
            'query' => $query,
        ]));
        
        $start = microtime(true);
        
        try {
            $result = $fn();
            
            $duration = microtime(true) - $start;
            $this->logger->info('Execution completed', $this->withContext([
                'duration_ms' => $duration * 1000,
                'success' => true,
            ]));
            
            return $result;
            
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $this->logger->error('Execution failed', $this->withContext([
                'duration_ms' => $duration * 1000,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]));
            
            throw $e;
        }
    }
}
```

## Tutorial 3: Observability

Track metrics, traces, and costs.

### Step 1: Basic Metrics

```php
use ClaudeAgents\Observability\Metrics;
use ClaudeAgents\Observability\Tracer;

$metrics = new Metrics();
$tracer = new Tracer();

// Track agent execution
$span = $tracer->startSpan('agent.execution');

try {
    $result = $agent->run($query);
    
    // Record metrics
    $metrics->increment('agent.executions.success');
    $metrics->histogram('agent.duration', $span->getDuration());
    $metrics->gauge('agent.tokens', $result->getUsage()['total_tokens'] ?? 0);
    
} catch (\Exception $e) {
    $metrics->increment('agent.executions.failure');
} finally {
    $span->finish();
}
```

### Step 2: Cost Tracking

```php
use ClaudeAgents\Observability\CostTracker;

$costTracker = new CostTracker();

// Track token usage and costs
$result = $agent->run($query);

$costTracker->recordUsage(
    model: 'claude-3-5-sonnet-20241022',
    inputTokens: $result->getUsage()['input_tokens'] ?? 0,
    outputTokens: $result->getUsage()['output_tokens'] ?? 0,
);

// Get cost summary
$summary = $costTracker->getSummary();
echo "Total cost: $" . $summary['total_cost_usd'] . "\n";
echo "Total tokens: " . $summary['total_tokens'] . "\n";
```

### Step 3: Distributed Tracing

```php
use ClaudeAgents\Observability\Exporters\OpenTelemetryExporter;

$exporter = new OpenTelemetryExporter([
    'endpoint' => 'http://jaeger:4318/v1/traces',
    'service_name' => 'claude-php-agent',
]);

$tracer = new Tracer($exporter);

// Trace agent execution
$span = $tracer->startSpan('agent.run', [
    'agent.type' => 'ReactAgent',
    'query' => $query,
]);

$result = $agent->run($query);

$span->setAttributes([
    'response.length' => strlen($result->getAnswer()),
    'tokens.total' => $result->getUsage()['total_tokens'] ?? 0,
]);

$span->finish();
```

## Tutorial 4: Caching

Implement caching for performance.

### Step 1: Setup Redis Cache

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

$manager = ServiceManager::getInstance();
$manager->registerFactory(new CacheServiceFactory());

// Get cache service
$cache = $manager->get(ServiceType::CACHE);

// Configure for Redis (or file, array)
// Set via environment or config
```

### Step 2: Cache Agent Responses

```php
class CachedAgent
{
    private Agent $agent;
    private CacheService $cache;
    private int $ttl = 3600; // 1 hour
    
    public function run(string $query): string
    {
        // Generate cache key
        $cacheKey = 'agent:' . md5($query);
        
        // Check cache
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        
        // Execute agent
        $result = $this->agent->run($query);
        $answer = $result->getAnswer();
        
        // Store in cache
        $this->cache->set($cacheKey, $answer, $this->ttl);
        
        return $answer;
    }
}
```

### Step 3: Smart Cache Invalidation

```php
class SmartCachedAgent
{
    public function run(string $query, array $options = []): string
    {
        $cacheKey = $this->generateCacheKey($query, $options);
        
        // Check if cache should be bypassed
        if ($options['bypass_cache'] ?? false) {
            return $this->executeAgent($query);
        }
        
        // Get from cache
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            // Verify cache is still valid
            if ($this->isCacheValid($cached)) {
                return $cached['answer'];
            }
            
            // Invalidate stale cache
            $this->cache->delete($cacheKey);
        }
        
        // Execute and cache
        $answer = $this->executeAgent($query);
        
        $this->cache->set($cacheKey, [
            'answer' => $answer,
            'timestamp' => time(),
            'version' => $this->getAgentVersion(),
        ], $this->ttl);
        
        return $answer;
    }
}
```

## Tutorial 5: Security

Secure your AI agents.

### Step 1: API Key Management

```php
// Never hardcode API keys!
// ❌ BAD
$client = new ClaudePhp(apiKey: 'sk-ant-api03-...');

// ✓ GOOD - Environment variable
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// ✓ GOOD - Config file
$config = require 'config/api.php';
$client = new ClaudePhp(apiKey: $config['anthropic_key']);

// ✓ GOOD - Secrets manager
$secrets = new SecretsManager();
$client = new ClaudePhp(apiKey: $secrets->get('anthropic_api_key'));
```

### Step 2: Input Validation

```php
class SecureAgent
{
    public function run(string $query): string
    {
        // Validate input length
        if (strlen($query) > 10000) {
            throw new \InvalidArgumentException('Query too long (max 10,000 chars)');
        }
        
        // Sanitize input
        $query = $this->sanitizeQuery($query);
        
        // Rate limiting
        if (!$this->checkRateLimit()) {
            throw new \RuntimeException('Rate limit exceeded');
        }
        
        // Execute agent
        return $this->agent->run($query)->getAnswer();
    }
    
    private function sanitizeQuery(string $query): string
    {
        // Remove potential injection attempts
        $query = strip_tags($query);
        $query = preg_replace('/[^\w\s\?\.\!\-]/', '', $query);
        return trim($query);
    }
    
    private function checkRateLimit(): bool
    {
        $key = 'ratelimit:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $count = (int) $this->cache->get($key, 0);
        
        if ($count >= 100) { // 100 requests per hour
            return false;
        }
        
        $this->cache->set($key, $count + 1, 3600);
        return true;
    }
}
```

### Step 3: Output Sanitization

```php
class OutputSanitizer
{
    public function sanitizeAgentOutput(string $output): string
    {
        // Remove sensitive patterns
        $patterns = [
            '/sk-ant-api03-[\w-]+/',           // API keys
            '/\b\d{16}\b/',                     // Credit cards
            '/\b\d{3}-\d{2}-\d{4}\b/',         // SSNs
        ];
        
        $replacements = [
            '[REDACTED_API_KEY]',
            '[REDACTED_CC]',
            '[REDACTED_SSN]',
        ];
        
        return preg_replace($patterns, $replacements, $output);
    }
}
```

## Tutorial 6: Monitoring

Monitor agent health and performance.

### Step 1: Health Checks

```php
use ClaudeAgents\Observability\HealthCheck;

$healthCheck = new HealthCheck();

// Register checks
$healthCheck->addCheck('api_connection', function () use ($client) {
    try {
        // Quick API test
        $response = $client->messages()->create([
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 10,
            'messages' => [['role' => 'user', 'content' => 'test']],
        ]);
        return ['status' => 'healthy'];
    } catch (\Exception $e) {
        return ['status' => 'unhealthy', 'error' => $e->getMessage()];
    }
});

$healthCheck->addCheck('cache', function () use ($cache) {
    try {
        $cache->set('health_check', 'ok', 10);
        $value = $cache->get('health_check');
        return ['status' => $value === 'ok' ? 'healthy' : 'unhealthy'];
    } catch (\Exception $e) {
        return ['status' => 'unhealthy', 'error' => $e->getMessage()];
    }
});

// Run health checks
$status = $healthCheck->check();
echo json_encode($status, JSON_PRETTY_PRINT);
```

### Step 2: Performance Monitoring

```php
class PerformanceMonitor
{
    private Metrics $metrics;
    
    public function monitorAgent(Agent $agent, string $query): AgentResult
    {
        $start = microtime(true);
        $memoryBefore = memory_get_usage(true);
        
        try {
            $result = $agent->run($query);
            
            $duration = microtime(true) - $start;
            $memoryUsed = memory_get_usage(true) - $memoryBefore;
            
            // Record success metrics
            $this->metrics->timing('agent.execution.duration', $duration);
            $this->metrics->gauge('agent.execution.memory_mb', $memoryUsed / 1024 / 1024);
            $this->metrics->increment('agent.executions.success');
            
            return $result;
            
        } catch (\Exception $e) {
            $this->metrics->increment('agent.executions.failure');
            throw $e;
        }
    }
}
```

### Step 3: Alert Integration

```php
use ClaudeAgents\Agents\AlertAgent;

class MonitoredAgent
{
    private AlertAgent $alertAgent;
    private float $errorThreshold = 0.1; // 10% error rate
    
    public function checkAndAlert(): void
    {
        $errorRate = $this->calculateErrorRate();
        
        if ($errorRate > $this->errorThreshold) {
            $this->alertAgent->sendAlert(
                title: 'High Error Rate Detected',
                message: "Agent error rate is {$errorRate}%",
                severity: 'high',
                channels: ['slack', 'email']
            );
        }
    }
    
    private function calculateErrorRate(): float
    {
        $total = $this->metrics->get('agent.executions.total');
        $failures = $this->metrics->get('agent.executions.failure');
        
        return $total > 0 ? ($failures / $total) * 100 : 0;
    }
}
```

## Tutorial 7: Deployment

Deploy agents in production.

### Step 1: Docker Compose Setup

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  agent-api:
    build: .
    ports:
      - "8080:8080"
    environment:
      - ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY}
      - REDIS_HOST=redis
      - APP_ENV=production
    depends_on:
      - redis
    restart: always
    
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis-data:/data
    restart: always
    
  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    restart: always

volumes:
  redis-data:
```

### Step 2: Kubernetes Deployment

Create `k8s/deployment.yaml`:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: claude-agent
spec:
  replicas: 3
  selector:
    matchLabels:
      app: claude-agent
  template:
    metadata:
      labels:
        app: claude-agent
    spec:
      containers:
      - name: agent
        image: your-registry/claude-agent:latest
        env:
        - name: ANTHROPIC_API_KEY
          valueFrom:
            secretKeyRef:
              name: api-secrets
              key: anthropic-key
        resources:
          requests:
            memory: "512Mi"
            cpu: "250m"
          limits:
            memory: "1Gi"
            cpu: "500m"
        livenessProbe:
          httpGet:
            path: /health
            port: 8080
          initialDelaySeconds: 30
          periodSeconds: 10
```

### Step 3: Load Balancing

```php
class LoadBalancedAgentPool
{
    private array $agents = [];
    private int $currentIndex = 0;
    
    public function __construct(int $poolSize)
    {
        for ($i = 0; $i < $poolSize; $i++) {
            $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
            $this->agents[] = Agent::create($client);
        }
    }
    
    public function run(string $query): string
    {
        // Round-robin selection
        $agent = $this->agents[$this->currentIndex];
        $this->currentIndex = ($this->currentIndex + 1) % count($this->agents);
        
        $result = $agent->run($query);
        return $result->getAnswer();
    }
}

// Use it
$pool = new LoadBalancedAgentPool(poolSize: 5);
$answer = $pool->run($query);
```

## Common Patterns

### Pattern 1: Production Agent Wrapper

```php
class ProductionAgent
{
    private Agent $agent;
    private Logger $logger;
    private Metrics $metrics;
    private CacheService $cache;
    private CircuitBreaker $circuitBreaker;
    
    public function run(string $query): string
    {
        // Check circuit breaker
        if ($this->circuitBreaker->isOpen()) {
            throw new \RuntimeException('Service temporarily unavailable');
        }
        
        // Check cache
        $cacheKey = 'query:' . md5($query);
        if ($cached = $this->cache->get($cacheKey)) {
            $this->metrics->increment('cache.hits');
            return $cached;
        }
        
        // Log start
        $this->logger->info('Agent execution started', ['query' => $query]);
        
        try {
            // Execute with monitoring
            $result = $this->executeWithMetrics($query);
            
            // Cache result
            $this->cache->set($cacheKey, $result, 3600);
            
            // Record success
            $this->circuitBreaker->recordSuccess();
            $this->metrics->increment('executions.success');
            
            return $result;
            
        } catch (\Exception $e) {
            // Log error
            $this->logger->error('Agent execution failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            
            // Record failure
            $this->circuitBreaker->recordFailure();
            $this->metrics->increment('executions.failure');
            
            throw $e;
        }
    }
}
```

### Pattern 2: Graceful Degradation

```php
class DegradableAgent
{
    public function run(string $query): string
    {
        try {
            // Try primary agent (most capable)
            return $this->primaryAgent->run($query);
            
        } catch (\Exception $e) {
            $this->logger->warning('Primary agent failed, using fallback');
            
            try {
                // Fallback to simpler model
                return $this->fallbackAgent->run($query);
                
            } catch (\Exception $e2) {
                // Last resort: cached response or error message
                return $this->getCachedOrDefault($query);
            }
        }
    }
}
```

## Troubleshooting

### Problem: High latency in production

**Solutions:**
```php
// 1. Add caching
$cache->set($key, $result, 3600);

// 2. Use faster model
$client = new ClaudePhp(
    model: 'claude-3-haiku-20240307'
);

// 3. Implement timeout
set_time_limit(30);

// 4. Load balance across multiple instances
```

### Problem: Memory leaks in long-running processes

**Solutions:**
```php
// 1. Clear caches periodically
if ($iterations % 100 === 0) {
    gc_collect_cycles();
}

// 2. Restart workers periodically
// In supervisor.conf:
// autorestart=true
// stopwaitsecs=10
```

## Next Steps

### Related Tutorials

- **[Services System Tutorial](./ServicesSystem_Tutorial.md)** - Service management
- **[MCP Server Tutorial](./MCPServer_Tutorial.md)** - MCP deployment
- **[Testing Strategies Tutorial](./TestingStrategies_Tutorial.md)** - Production testing

### Further Reading

- [Observability Guide](../Observability.md)
- [Best Practices](../BestPractices.md)
- [Services Documentation](../services/README.md)

### Example Code

All examples from this tutorial are available in:
- `examples/tutorials/production-patterns/`
- `examples/production_patterns_example.php`

### What You've Learned

✓ Robust error handling and recovery
✓ Structured logging with Monolog
✓ Observability with metrics and tracing
✓ Performance caching strategies
✓ Security best practices
✓ Health monitoring and alerts
✓ Production deployment patterns
✓ Docker and Kubernetes deployment

**Ready for more?** Continue with the [Testing Strategies Tutorial](./TestingStrategies_Tutorial.md)!

---

*Tutorial Version: 1.0*
*Framework Version: v0.8.0+*
*Last Updated: February 2026*
