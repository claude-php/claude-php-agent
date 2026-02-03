# Migration Guide: Service Layer Integration

This guide helps you migrate existing claude-php-agent code to use the new Service Layer architecture.

## Overview

The Service Layer provides centralized management of application services with automatic dependency injection. Migration is optional and can be done gradually.

## Benefits of Migration

- **Cleaner Code** - No manual dependency management
- **Better Testing** - Easy service mocking
- **Observability** - Built-in tracing and telemetry
- **Configuration** - Centralized settings management
- **Consistency** - Standardized patterns across services

## Migration Steps

### Step 1: Initialize ServiceManager

Add ServiceManager initialization at your application entry point:

```php
// Before: Direct instantiation
$cache = new ArrayCache();
$settings = loadSettings();

// After: ServiceManager
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

$serviceManager = ServiceManager::getInstance();
$serviceManager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory());
```

### Step 2: Update Cache Usage

**Before:**
```php
use ClaudeAgents\Cache\ArrayCache;

class MyClass
{
    private ArrayCache $cache;
    
    public function __construct()
    {
        $this->cache = new ArrayCache();
    }
}
```

**After:**
```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

class MyClass
{
    private CacheService $cache;
    
    public function __construct(?ServiceManager $serviceManager = null)
    {
        $serviceManager = $serviceManager ?? ServiceManager::getInstance();
        $this->cache = $serviceManager->get(ServiceType::CACHE);
    }
}
```

### Step 3: Update StateManager Integration

The StateManager can now use StorageService for persistence:

**Before:**
```php
$stateManager = new StateManager('./storage/state.json');
```

**After:**
```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$serviceManager = ServiceManager::getInstance();
$storage = $serviceManager->get(ServiceType::STORAGE);

// StateManager can now use storage service
$stateManager = new StateManager(
    stateFile: './storage/state.json',
    options: ['storage' => $storage]
);
```

### Step 4: Add Observability

Integrate tracing and telemetry into your agents:

**Before:**
```php
class Agent
{
    public function run(string $input): string
    {
        // Agent logic
        return $result;
    }
}
```

**After:**
```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

class Agent
{
    private ServiceManager $services;
    
    public function __construct(?ServiceManager $services = null)
    {
        $this->services = $services ?? ServiceManager::getInstance();
    }
    
    public function run(string $input): string
    {
        $traceId = uniqid('trace_', true);
        $startTime = microtime(true);
        
        // Start tracing
        $tracing = $this->services->get(ServiceType::TRACING);
        $tracing->startTrace($traceId, 'agent.run');
        
        try {
            // Agent logic with span recording
            $result = $tracing->recordSpan('process', function() use ($input) {
                return $this->process($input);
            });
            
            // Record metrics
            $duration = (microtime(true) - $startTime) * 1000;
            $telemetry = $this->services->get(ServiceType::TELEMETRY);
            $telemetry->recordAgentRequest(
                success: true,
                tokensInput: 100,
                tokensOutput: 50,
                duration: $duration
            );
            
            $tracing->endTrace($traceId, ['result' => $result]);
            
            return $result;
        } catch (\Throwable $e) {
            $tracing->endTrace($traceId, ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

### Step 5: Manage Secrets with VariableService

Replace hardcoded API keys with encrypted variables:

**Before:**
```php
$apiKey = getenv('ANTHROPIC_API_KEY');
```

**After:**
```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Variable\VariableType;

$serviceManager = ServiceManager::getInstance();
$variables = $serviceManager->get(ServiceType::VARIABLE);

// Store API key (do this once during setup)
$variables->setVariable(
    'system',
    'anthropic_api_key',
    getenv('ANTHROPIC_API_KEY'),
    VariableType::CREDENTIAL
);

// Retrieve API key (encrypted at rest)
$apiKey = $variables->getVariable('system', 'anthropic_api_key');
```

### Step 6: Update Tests

Use service mocking for cleaner tests:

**Before:**
```php
class AgentTest extends TestCase
{
    public function testAgent(): void
    {
        $cache = new ArrayCache();
        $agent = new Agent($cache);
        
        // Test agent
    }
}
```

**After:**
```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Cache\ArrayCache;

class AgentTest extends TestCase
{
    private ServiceManager $serviceManager;
    
    protected function setUp(): void
    {
        $this->serviceManager = ServiceManager::getInstance();
        $this->serviceManager->reset();
        
        // Mock cache service
        $mockCache = new ArrayCache();
        $this->serviceManager->mock(ServiceType::CACHE, $mockCache);
    }
    
    public function testAgent(): void
    {
        $agent = new Agent($this->serviceManager);
        
        // Test agent
    }
}
```

## Backwards Compatibility

The Service Layer is **fully backwards compatible**:

- Existing code continues to work without changes
- You can migrate gradually, service by service
- Services are opt-in - use them when beneficial
- No breaking changes to existing APIs

## Common Patterns

### Pattern 1: Optional Service Manager

Allow both old and new usage:

```php
class MyClass
{
    private CacheInterface $cache;
    
    public function __construct(
        ?CacheInterface $cache = null,
        ?ServiceManager $serviceManager = null
    ) {
        if ($cache !== null) {
            // Old style: direct injection
            $this->cache = $cache;
        } else {
            // New style: from service manager
            $serviceManager = $serviceManager ?? ServiceManager::getInstance();
            $this->cache = $serviceManager->get(ServiceType::CACHE);
        }
    }
}
```

### Pattern 2: Service-Aware Trait

Create a trait for easy service access:

```php
trait ServiceAware
{
    private ?ServiceManager $serviceManager = null;
    
    protected function getServiceManager(): ServiceManager
    {
        if ($this->serviceManager === null) {
            $this->serviceManager = ServiceManager::getInstance();
        }
        return $this->serviceManager;
    }
    
    protected function getService(ServiceType $type): ServiceInterface
    {
        return $this->getServiceManager()->get($type);
    }
}

// Usage
class MyAgent
{
    use ServiceAware;
    
    public function run(): void
    {
        $cache = $this->getService(ServiceType::CACHE);
        // ...
    }
}
```

### Pattern 3: Factory Method

Use static factory methods:

```php
class Agent
{
    private ServiceManager $services;
    
    private function __construct(ServiceManager $services)
    {
        $this->services = $services;
    }
    
    public static function create(?ServiceManager $services = null): self
    {
        $services = $services ?? ServiceManager::getInstance();
        return new self($services);
    }
}

// Usage
$agent = Agent::create();
```

## Configuration

Create a configuration file for your services:

```php
// config/services.php
return [
    'cache' => [
        'driver' => 'redis',
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
    ],
    'storage' => [
        'directory' => './storage',
    ],
    'tracing' => [
        'enabled' => true,
        'providers' => ['langsmith'],
        'langsmith' => [
            'api_key' => getenv('LANGSMITH_API_KEY'),
            'project' => 'my-project',
        ],
    ],
    'telemetry' => [
        'enabled' => true,
    ],
];
```

```php
// bootstrap.php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;

$serviceManager = ServiceManager::getInstance();
$serviceManager->registerFactory(
    new SettingsServiceFactory(configFile: './config/services.php')
);
```

## Troubleshooting

### Issue: Service Not Found

**Error:** `ServiceNotFoundException: No factory registered for service: cache`

**Solution:** Register the factory before accessing the service:

```php
$serviceManager->registerFactory(new CacheServiceFactory());
$cache = $serviceManager->get(ServiceType::CACHE);
```

### Issue: Circular Dependency

**Error:** `Circular dependency detected while creating service: X`

**Solution:** Review service dependencies and refactor to break the cycle. Services should have a clear dependency hierarchy.

### Issue: Service Not Ready

**Error:** Service methods fail or return unexpected results

**Solution:** Ensure service is initialized:

```php
$service = $serviceManager->get(ServiceType::CACHE);
if (!$service->isReady()) {
    $service->initialize();
}
```

## Performance Considerations

- **Lazy Initialization** - Services are created only when first accessed
- **Singleton Pattern** - Each service is created once and reused
- **Minimal Overhead** - ServiceManager adds negligible performance impact
- **Caching** - Dependency resolution is cached

## Next Steps

1. Start with non-critical components
2. Add observability to key workflows
3. Migrate tests to use service mocking
4. Gradually adopt across the codebase
5. Configure services via SettingsService

## Resources

- [Service Layer Overview](README.md)
- [API Documentation](../../README.md)
- [Examples](../../examples/Services/)
- [Tests](../../tests/Unit/Services/)

## Support

Questions? Open an issue on GitHub or consult the documentation.
