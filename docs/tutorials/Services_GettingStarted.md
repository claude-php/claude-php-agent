# Getting Started with the Service Layer

Learn how to use the Service Layer architecture in your Claude PHP Agent applications.

## Table of Contents

- [What is the Service Layer?](#what-is-the-service-layer)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Your First Service](#your-first-service)
- [Service Dependencies](#service-dependencies)
- [Configuration](#configuration)
- [Testing](#testing)
- [Next Steps](#next-steps)

## What is the Service Layer?

The Service Layer is an enterprise-grade architecture that provides:

- **Centralized Management** - Single ServiceManager for all services
- **Automatic Dependency Injection** - Services automatically get their dependencies
- **Lifecycle Management** - Proper initialization and cleanup
- **Type Safety** - Compile-time service type checking
- **Easy Testing** - Mock services for unit tests

## Quick Start

### 1. Bootstrap the ServiceManager

Create a bootstrap file for your application:

```php
<?php
// bootstrap.php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

// Get ServiceManager singleton
$serviceManager = ServiceManager::getInstance();

// Register services you need
$serviceManager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory());

return $serviceManager;
```

### 2. Use Services in Your Code

```php
<?php

require_once 'bootstrap.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

// Get the service manager
$serviceManager = ServiceManager::getInstance();

// Get a service (creates it if needed)
$cache = $serviceManager->get(ServiceType::CACHE);

// Use the service
$cache->set('user:123', ['name' => 'John Doe']);
$user = $cache->get('user:123');

echo "User: " . $user['name'];
```

### 3. Clean Up on Shutdown

```php
// At application shutdown
register_shutdown_function(function() {
    ServiceManager::getInstance()->teardownAll();
});
```

## Core Concepts

### ServiceManager

The `ServiceManager` is a singleton that manages all services:

```php
// Get the singleton instance
$manager = ServiceManager::getInstance();

// Register a service factory
$manager->registerFactory(new CacheServiceFactory());

// Get a service (lazy initialization)
$cache = $manager->get(ServiceType::CACHE);

// Check if service exists
if ($manager->has(ServiceType::CACHE)) {
    // Service is registered
}
```

### ServiceType Enum

Use the `ServiceType` enum for type-safe service access:

```php
use ClaudeAgents\Services\ServiceType;

// Available service types:
ServiceType::SETTINGS   // Configuration
ServiceType::CACHE      // Caching
ServiceType::STORAGE    // File storage
ServiceType::VARIABLE   // Variables & secrets
ServiceType::TRACING    // Observability tracing
ServiceType::TELEMETRY  // Metrics
ServiceType::SESSION    // User sessions
ServiceType::TRANSACTION // Database transactions
```

### Service Lifecycle

Services go through three states:

```php
// 1. Registered (factory registered, not created)
$manager->registerFactory(new CacheServiceFactory());

// 2. Initialized (service created and ready)
$cache = $manager->get(ServiceType::CACHE);
assert($cache->isReady()); // true

// 3. Torn down (cleaned up)
$manager->teardownAll();
```

## Your First Service

Let's build a simple application using services:

### Example: User Profile Manager

```php
<?php

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Storage\StorageServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

class UserProfileManager
{
    private $storage;
    private $cache;
    
    public function __construct()
    {
        $manager = ServiceManager::getInstance();
        $this->storage = $manager->get(ServiceType::STORAGE);
        $this->cache = $manager->get(ServiceType::CACHE);
    }
    
    public function getProfile(string $userId): array
    {
        // Try cache first
        $cacheKey = "profile:{$userId}";
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        
        // Load from storage
        $data = $this->storage->getFile('users', "{$userId}.json");
        $profile = json_decode($data, true);
        
        // Cache for 1 hour
        $this->cache->set($cacheKey, $profile, 3600);
        
        return $profile;
    }
    
    public function updateProfile(string $userId, array $data): void
    {
        // Save to storage
        $this->storage->saveFile('users', "{$userId}.json", json_encode($data));
        
        // Invalidate cache
        $this->cache->delete("profile:{$userId}");
    }
}

// Bootstrap
$manager = ServiceManager::getInstance();
$manager
    ->registerFactory(new StorageServiceFactory())
    ->registerFactory(new CacheServiceFactory());

// Use
$profiles = new UserProfileManager();
$profile = $profiles->getProfile('user-123');
```

## Service Dependencies

Services can depend on other services. The ServiceManager automatically resolves dependencies:

```php
// CacheService depends on SettingsService
class CacheService implements ServiceInterface
{
    public function __construct(
        private SettingsService $settings  // Auto-injected!
    ) {}
}

// Just register both factories
$manager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory());

// Dependencies are automatically resolved
$cache = $manager->get(ServiceType::CACHE);
// SettingsService was created first, then injected into CacheService
```

### Dependency Order Doesn't Matter

```php
// These are equivalent:
$manager
    ->registerFactory(new CacheServiceFactory())
    ->registerFactory(new SettingsServiceFactory());

// Or
$manager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory());
```

## Configuration

### Using SettingsService

Create a configuration file:

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
];
```

Load it during bootstrap:

```php
use ClaudeAgents\Services\Settings\SettingsServiceFactory;

$manager->registerFactory(
    new SettingsServiceFactory(configFile: './config/services.php')
);

// Access configuration
$settings = $manager->get(ServiceType::SETTINGS);
$cacheDriver = $settings->get('cache.driver'); // 'redis'
```

### Environment Variables

Override configuration with environment variables:

```bash
# Prefix with CLAUDE_AGENT_
export CLAUDE_AGENT_CACHE_DRIVER=redis
export CLAUDE_AGENT_CACHE_REDIS_HOST=localhost
```

```php
// Automatically loaded from environment
$settings = $manager->get(ServiceType::SETTINGS);
$driver = $settings->get('cache.driver'); // 'redis' from env
```

## Testing

### Mock Services in Tests

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Cache\ArrayCache;
use ClaudeAgents\Services\Cache\CacheService;
use PHPUnit\Framework\TestCase;

class UserProfileManagerTest extends TestCase
{
    private ServiceManager $manager;
    
    protected function setUp(): void
    {
        $this->manager = ServiceManager::getInstance();
        $this->manager->reset();
        
        // Create mock cache
        $settings = new SettingsService();
        $settings->initialize();
        
        $mockCache = new CacheService($settings, new ArrayCache());
        $mockCache->initialize();
        
        // Register mock
        $this->manager->mock(ServiceType::CACHE, $mockCache);
    }
    
    public function testGetProfile(): void
    {
        $profiles = new UserProfileManager();
        
        // Test with mocked cache
        // ...
    }
}
```

### Test Service in Isolation

```php
class CacheServiceTest extends TestCase
{
    public function testSetAndGet(): void
    {
        $settings = new SettingsService();
        $settings->initialize();
        
        $cache = new CacheService($settings, new ArrayCache());
        $cache->initialize();
        
        $cache->set('key', 'value');
        $this->assertSame('value', $cache->get('key'));
    }
}
```

## Next Steps

Now that you understand the basics, explore specific services:

1. **[SettingsService Tutorial](Services_Settings.md)** - Configuration management
2. **[CacheService Tutorial](Services_Cache.md)** - Caching strategies
3. **[StorageService Tutorial](Services_Storage.md)** - File storage
4. **[VariableService Tutorial](Services_Variables.md)** - Secrets management
5. **[TracingService Tutorial](Services_Tracing.md)** - Observability
6. **[TelemetryService Tutorial](Services_Telemetry.md)** - Metrics
7. **[SessionService Tutorial](Services_Sessions.md)** - Session management

### Advanced Topics

- [Service Layer Best Practices](Services_BestPractices.md)
- [Custom Service Creation](Services_CustomServices.md)
- [Migration Guide](../services/MIGRATION.md)

## Common Patterns

### Pattern 1: Service-Aware Class

```php
class MyClass
{
    private ServiceManager $services;
    
    public function __construct(?ServiceManager $services = null)
    {
        $this->services = $services ?? ServiceManager::getInstance();
    }
    
    protected function getCache()
    {
        return $this->services->get(ServiceType::CACHE);
    }
}
```

### Pattern 2: Optional Service Manager

```php
class MyClass
{
    public function __construct(
        private ?CacheService $cache = null
    ) {
        $this->cache = $cache ?? ServiceManager::getInstance()
            ->get(ServiceType::CACHE);
    }
}
```

### Pattern 3: Static Factory

```php
class MyClass
{
    public static function create(?ServiceManager $manager = null): self
    {
        $manager = $manager ?? ServiceManager::getInstance();
        return new self($manager);
    }
}
```

## Troubleshooting

### Service Not Found

```
ServiceNotFoundException: No factory registered for service: cache
```

**Solution:** Register the factory before accessing the service:

```php
$manager->registerFactory(new CacheServiceFactory());
$cache = $manager->get(ServiceType::CACHE);
```

### Circular Dependency

```
RuntimeException: Circular dependency detected while creating service: X
```

**Solution:** Review service dependencies and refactor to break the cycle.

### Service Not Ready

**Solution:** Services are automatically initialized. If you create a service manually, call `initialize()`:

```php
$service = new MyService();
$service->initialize();
```

## Summary

You've learned:

✅ How to bootstrap the ServiceManager  
✅ How to register and use services  
✅ Service lifecycle management  
✅ Dependency injection basics  
✅ Configuration management  
✅ Testing with mock services  

Ready to dive deeper? Check out the service-specific tutorials!
