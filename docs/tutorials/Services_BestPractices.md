# Service Layer Best Practices

A comprehensive guide to best practices when using the Service Layer architecture.

## Table of Contents

- [Architecture Principles](#architecture-principles)
- [Service Registration](#service-registration)
- [Dependency Management](#dependency-management)
- [Configuration](#configuration)
- [Testing](#testing)
- [Performance](#performance)
- [Security](#security)
- [Monitoring](#monitoring)
- [Common Pitfalls](#common-pitfalls)

## Architecture Principles

### 1. Single Responsibility

Each service should have one clear purpose:

```php
// ✅ Good - Focused responsibility
class CacheService {
    // Only handles caching
}

class StorageService {
    // Only handles storage
}

// ❌ Bad - Mixed responsibilities
class DataService {
    // Handles both caching AND storage AND database
}
```

### 2. Dependency Injection

Let the ServiceManager inject dependencies:

```php
// ✅ Good - Dependencies injected
class MyService implements ServiceInterface
{
    public function __construct(
        private SettingsService $settings,
        private CacheService $cache
    ) {}
}

// ❌ Bad - Manual instantiation
class MyService implements ServiceInterface
{
    private $settings;
    
    public function __construct()
    {
        $this->settings = new SettingsService(); // Don't do this!
    }
}
```

### 3. Interface-Based Design

Depend on interfaces, not implementations:

```php
// ✅ Good - Depends on interface
class MyAgent
{
    public function __construct(
        private ServiceInterface $cache
    ) {}
}

// ❌ Bad - Depends on concrete class
class MyAgent
{
    public function __construct(
        private ArrayCache $cache  // Too specific
    ) {}
}
```

## Service Registration

### 1. Register Early

Register all services during application bootstrap:

```php
// bootstrap.php
$manager = ServiceManager::getInstance();

// Register all services at startup
$manager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory())
    ->registerFactory(new StorageServiceFactory())
    ->registerFactory(new VariableServiceFactory())
    ->registerFactory(new TracingServiceFactory())
    ->registerFactory(new TelemetryServiceFactory())
    ->registerFactory(new SessionServiceFactory());

return $manager;
```

### 2. Register Only What You Need

Don't register services you won't use:

```php
// ✅ Good - Only needed services
$manager
    ->registerFactory(new CacheServiceFactory())
    ->registerFactory(new StorageServiceFactory());

// ❌ Bad - Registering everything
$manager->registerFactory(new TracingServiceFactory()); // Not using tracing
```

### 3. Use Configuration for Service Selection

```php
// config/services.php
return [
    'enabled_services' => [
        'cache',
        'storage',
        'telemetry',
    ],
];

// bootstrap.php
$settings = $manager->get(ServiceType::SETTINGS);
$enabledServices = $settings->get('enabled_services', []);

$serviceFactories = [
    'cache' => new CacheServiceFactory(),
    'storage' => new StorageServiceFactory(),
    'telemetry' => new TelemetryServiceFactory(),
    // ...
];

foreach ($enabledServices as $service) {
    if (isset($serviceFactories[$service])) {
        $manager->registerFactory($serviceFactories[$service]);
    }
}
```

## Dependency Management

### 1. Avoid Circular Dependencies

```php
// ❌ Bad - Circular dependency
class ServiceA {
    public function __construct(ServiceB $b) {}
}

class ServiceB {
    public function __construct(ServiceA $a) {} // Creates cycle!
}

// ✅ Good - Break the cycle
class ServiceA {
    public function __construct(SettingsService $settings) {}
}

class ServiceB {
    public function __construct(SettingsService $settings) {}
}
```

### 2. Minimize Dependencies

```php
// ✅ Good - Minimal dependencies
class MyService implements ServiceInterface
{
    public function __construct(
        private SettingsService $settings
    ) {}
}

// ❌ Bad - Too many dependencies
class MyService implements ServiceInterface
{
    public function __construct(
        private SettingsService $settings,
        private CacheService $cache,
        private StorageService $storage,
        private VariableService $variables,
        private TracingService $tracing,
        private TelemetryService $telemetry
    ) {}
}
```

### 3. Optional Dependencies

Use optional parameters for non-critical dependencies:

```php
class MyService implements ServiceInterface
{
    public function __construct(
        private SettingsService $settings,
        private ?TracingService $tracing = null  // Optional
    ) {}
    
    public function doWork(): void
    {
        if ($this->tracing !== null) {
            $this->tracing->startTrace('work', 'operation');
        }
        
        // Do work...
        
        if ($this->tracing !== null) {
            $this->tracing->endTrace('work');
        }
    }
}
```

## Configuration

### 1. Use Environment-Specific Configs

```php
// config/services.dev.php
return [
    'cache' => ['driver' => 'array'],
    'tracing' => ['enabled' => false],
];

// config/services.prod.php
return [
    'cache' => ['driver' => 'redis'],
    'tracing' => ['enabled' => true],
];

// bootstrap.php
$env = getenv('APP_ENV') ?: 'dev';
$configFile = __DIR__ . "/config/services.{$env}.php";

$manager->registerFactory(
    new SettingsServiceFactory(configFile: $configFile)
);
```

### 2. Validate Configuration

```php
class SettingsValidator
{
    public static function validate(array $config): void
    {
        $required = ['cache.driver', 'storage.directory'];
        
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new \RuntimeException("Missing required config: {$key}");
            }
        }
    }
}

// Use after loading config
$settings = $manager->get(ServiceType::SETTINGS);
SettingsValidator::validate($settings->all());
```

### 3. Provide Sensible Defaults

```php
class SettingsService implements ServiceInterface
{
    private array $defaults = [
        'cache.driver' => 'array',
        'cache.ttl' => 3600,
        'storage.directory' => './storage',
        'tracing.enabled' => false,
    ];
    
    public function get(string $key, mixed $default = null): mixed
    {
        // Check config, then defaults, then parameter
        return $this->config[$key] 
            ?? $this->defaults[$key] 
            ?? $default;
    }
}
```

## Testing

### 1. Mock Services in Tests

```php
class MyFeatureTest extends TestCase
{
    private ServiceManager $manager;
    
    protected function setUp(): void
    {
        $this->manager = ServiceManager::getInstance();
        $this->manager->reset();
        
        // Mock cache with in-memory implementation
        $settings = new SettingsService();
        $settings->initialize();
        $mockCache = new CacheService($settings, new ArrayCache());
        $mockCache->initialize();
        
        $this->manager->mock(ServiceType::CACHE, $mockCache);
    }
    
    public function testFeature(): void
    {
        $feature = new MyFeature();
        // Test uses mocked cache
    }
}
```

### 2. Test Service Isolation

```php
class CacheServiceTest extends TestCase
{
    public function testCacheOperations(): void
    {
        // Test service in complete isolation
        $settings = new SettingsService();
        $settings->initialize();
        
        $cache = new CacheService($settings, new ArrayCache());
        $cache->initialize();
        
        // Test without ServiceManager
        $cache->set('key', 'value');
        $this->assertSame('value', $cache->get('key'));
    }
}
```

### 3. Integration Tests

```php
class ServiceIntegrationTest extends TestCase
{
    public function testServicesWorkTogether(): void
    {
        $manager = ServiceManager::getInstance();
        $manager->reset();
        
        // Register real factories
        $manager
            ->registerFactory(new SettingsServiceFactory())
            ->registerFactory(new CacheServiceFactory())
            ->registerFactory(new StorageServiceFactory());
        
        // Test actual integration
        $cache = $manager->get(ServiceType::CACHE);
        $storage = $manager->get(ServiceType::STORAGE);
        
        // Both services should work together
        $cache->set('key', 'value');
        $storage->saveFile('test', 'file.txt', 'data');
        
        $this->assertSame('value', $cache->get('key'));
        $this->assertSame('data', $storage->getFile('test', 'file.txt'));
    }
}
```

## Performance

### 1. Lazy Initialization

Services are created only when needed:

```php
// ✅ Good - Services created on demand
$manager->registerFactory(new CacheServiceFactory());
// No service created yet

$cache = $manager->get(ServiceType::CACHE);
// Now created and initialized
```

### 2. Reuse Service Instances

```php
// ✅ Good - Reuse singleton
$cache1 = $manager->get(ServiceType::CACHE);
$cache2 = $manager->get(ServiceType::CACHE);
// $cache1 === $cache2 (same instance)

// ❌ Bad - Creating new instances
$cache = new CacheService($settings, new ArrayCache());
// Don't create services manually
```

### 3. Cache Expensive Operations

```php
class ExpensiveService
{
    private CacheService $cache;
    
    public function getExpensiveData(): array
    {
        return $this->cache->remember('expensive_data', function() {
            // Only computed once, then cached
            return $this->computeExpensiveData();
        }, 3600);
    }
}
```

## Security

### 1. Encrypt Sensitive Data

```php
// ✅ Good - Use CREDENTIAL type
$variables->setVariable(
    'user-123',
    'api_key',
    'sk-secret',
    VariableType::CREDENTIAL  // Encrypted
);

// ❌ Bad - Storing secrets as plain text
$cache->set('api_key', 'sk-secret'); // Not encrypted!
```

### 2. Sanitize Trace Data

```php
// TracingService automatically removes sensitive fields
$tracing->startTrace($id, 'operation', [
    'user_id' => '123',
    'api_key' => 'sk-xxx',      // Automatically removed
    'password' => 'secret',      // Automatically removed
    'data' => 'safe data',       // Kept
]);
```

### 3. Validate Input

```php
class StorageService
{
    private function sanitizePath(string $path): string
    {
        // Prevent directory traversal
        $path = str_replace(['..', '\\'], ['', '/'], $path);
        return trim($path, '/');
    }
}
```

### 4. Use Environment Variables

```php
// ❌ Bad - Hardcoded secrets
return [
    'cache' => [
        'redis' => [
            'password' => 'my-redis-password',
        ],
    ],
];

// ✅ Good - From environment
return [
    'cache' => [
        'redis' => [
            'password' => getenv('REDIS_PASSWORD'),
        ],
    ],
];
```

## Monitoring

### 1. Use Telemetry

```php
class MonitoredAgent
{
    private TelemetryService $telemetry;
    
    public function run(string $input): string
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->execute($input);
            
            // Record success
            $duration = (microtime(true) - $startTime) * 1000;
            $this->telemetry->recordAgentRequest(
                success: true,
                tokensInput: 100,
                tokensOutput: 50,
                duration: $duration
            );
            
            return $result;
        } catch (\Exception $e) {
            // Record failure
            $this->telemetry->recordCounter('agent.errors');
            throw $e;
        }
    }
}
```

### 2. Log Important Events

```php
class ServiceManager
{
    public function get(ServiceType $type): ServiceInterface
    {
        $this->logger->debug("Creating service: {$type->value}");
        
        $service = $this->createService($type);
        
        $this->logger->info("Service ready: {$type->value}");
        
        return $service;
    }
}
```

### 3. Health Checks

```php
class ServiceHealthCheck
{
    public function check(): array
    {
        $manager = ServiceManager::getInstance();
        $status = [];
        
        foreach (ServiceType::cases() as $type) {
            if ($manager->has($type)) {
                $service = $manager->get($type);
                $status[$type->value] = $service->isReady();
            }
        }
        
        return $status;
    }
}
```

## Common Pitfalls

### 1. Forgetting to Teardown

```php
// ❌ Bad - No cleanup
function runApp() {
    $manager = ServiceManager::getInstance();
    // ... use services ...
} // Services never cleaned up!

// ✅ Good - Proper cleanup
function runApp() {
    $manager = ServiceManager::getInstance();
    
    try {
        // ... use services ...
    } finally {
        $manager->teardownAll();
    }
}
```

### 2. Creating Services Manually

```php
// ❌ Bad - Manual creation
$cache = new CacheService($settings, new ArrayCache());

// ✅ Good - Via ServiceManager
$cache = ServiceManager::getInstance()->get(ServiceType::CACHE);
```

### 3. Not Checking Service Availability

```php
// ❌ Bad - Assuming service exists
$cache = $manager->get(ServiceType::CACHE); // Throws if not registered

// ✅ Good - Check first
if ($manager->has(ServiceType::CACHE)) {
    $cache = $manager->get(ServiceType::CACHE);
} else {
    // Handle missing service
}
```

### 4. Mixing Service Versions

```php
// ❌ Bad - Multiple ServiceManager instances
$manager1 = new ServiceManager(); // Don't do this!
$manager2 = new ServiceManager();

// ✅ Good - Use singleton
$manager = ServiceManager::getInstance();
```

### 5. Ignoring Errors

```php
// ❌ Bad - Swallowing errors
try {
    $service->initialize();
} catch (\Exception $e) {
    // Silent failure
}

// ✅ Good - Log and handle
try {
    $service->initialize();
} catch (\Exception $e) {
    $logger->error("Service initialization failed", [
        'service' => $service->getName(),
        'error' => $e->getMessage(),
    ]);
    throw $e;
}
```

## Summary

Follow these best practices for production-ready services:

✅ Single responsibility per service  
✅ Use dependency injection  
✅ Register services early  
✅ Avoid circular dependencies  
✅ Environment-specific configuration  
✅ Mock services in tests  
✅ Lazy initialization  
✅ Encrypt sensitive data  
✅ Monitor with telemetry  
✅ Always teardown services  

Your service layer will be robust, maintainable, and production-ready!
