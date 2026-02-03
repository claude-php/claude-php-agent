# Services System Tutorial: Enterprise Service Management

## Introduction

This tutorial will guide you through the Services System, a powerful enterprise-grade service management layer with automatic dependency injection, lifecycle management, and type safety for the Claude PHP Agent framework.

By the end of this tutorial, you'll be able to:

- Use ServiceManager for centralized service management
- Register and use built-in services (Cache, Settings)
- Create custom services with dependency injection
- Build service factories
- Manage service lifecycle (initialize, teardown)
- Test services with mocking

## Prerequisites

- PHP 8.1 or higher
- Composer
- Basic understanding of dependency injection
- Familiarity with design patterns (Factory, Singleton)

## Table of Contents

1. [Understanding the Services System](#understanding-the-services-system)
2. [Setup and Installation](#setup-and-installation)
3. [Tutorial 1: ServiceManager Basics](#tutorial-1-servicemanager-basics)
4. [Tutorial 2: Using Built-in Services](#tutorial-2-using-built-in-services)
5. [Tutorial 3: Service Dependencies](#tutorial-3-service-dependencies)
6. [Tutorial 4: Custom Services](#tutorial-4-custom-services)
7. [Tutorial 5: Service Factories](#tutorial-5-service-factories)
8. [Tutorial 6: Lifecycle Management](#tutorial-6-lifecycle-management)
9. [Tutorial 7: Testing with Services](#tutorial-7-testing-with-services)
10. [Common Patterns](#common-patterns)
11. [Troubleshooting](#troubleshooting)
12. [Next Steps](#next-steps)

## Understanding the Services System

The Services System provides:

- **Centralized Management** - Single ServiceManager for all services
- **Automatic DI** - Services resolve dependencies automatically
- **Type Safety** - ServiceType enum prevents errors
- **Lazy Loading** - Services created only when needed
- **Lifecycle Control** - Proper initialization and cleanup
- **Testability** - Easy mocking for unit tests

### Architecture

```
┌─────────────────────────┐
│    ServiceManager       │  ← Singleton
│   (Central Registry)    │
└────────┬────────────────┘
         │
         ├─→ Cache Service
         ├─→ Settings Service
         ├─→ Logging Service
         └─→ Custom Services
              │
              ├─→ Auto-resolved dependencies
              └─→ Lifecycle management
```

### Core Components

1. **ServiceManager** - Central registry and service orchestrator
2. **ServiceInterface** - Contract all services implement
3. **ServiceFactory** - Base class for service factories
4. **ServiceType** - Enum for type-safe service access

## Setup and Installation

The Services System is included in `claude-php/agent` v0.7.0+.

### Basic Setup

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

// Get singleton instance
$manager = ServiceManager::getInstance();

// Verify setup
if ($manager instanceof ServiceManager) {
    echo "✓ ServiceManager ready\n";
}
```

## Tutorial 1: ServiceManager Basics

Learn the fundamentals of ServiceManager.

### Step 1: Initialize ServiceManager

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

// Get singleton instance
$serviceManager = ServiceManager::getInstance();

// Register service factories
$serviceManager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory());

echo "✓ Factories registered\n";
```

### Step 2: Get a Service

```php
use ClaudeAgents\Services\ServiceType;

// Get cache service (created on first access)
$cache = $serviceManager->get(ServiceType::CACHE);

echo "✓ Cache service retrieved\n";
echo "  Type: " . $cache->getName() . "\n";
echo "  Ready: " . ($cache->isReady() ? 'Yes' : 'No') . "\n";
```

### Step 3: Check Service Availability

```php
// Check if service is registered
if ($serviceManager->has(ServiceType::CACHE)) {
    echo "✓ Cache service is available\n";
}

// List all registered services
$registered = $serviceManager->getRegisteredTypes();
echo "Registered services: " . implode(', ', array_map(
    fn($type) => $type->value,
    $registered
)) . "\n";
```

## Tutorial 2: Using Built-in Services

Work with Cache and Settings services.

### Step 1: Cache Service Basics

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

// Store data
$cache->set('user:123', ['name' => 'John Doe', 'email' => 'john@example.com']);
$cache->set('config:api', 'https://api.example.com');

// Retrieve data
$user = $cache->get('user:123');
echo "User: " . $user['name'] . "\n";

// Check existence
if ($cache->has('config:api')) {
    echo "✓ API config exists\n";
}

// Delete
$cache->delete('config:api');

// Clear all
// $cache->clear();
```

### Step 2: Settings Service

```php
use ClaudeAgents\Services\Settings\SettingsServiceFactory;

$manager->registerFactory(new SettingsServiceFactory());

// Get settings service
$settings = $manager->get(ServiceType::SETTINGS);

// Set values
$settings->set('app.name', 'My AI App');
$settings->set('app.debug', true);
$settings->set('api.timeout', 30);

// Get values
$appName = $settings->get('app.name');
$timeout = $settings->get('api.timeout', 60); // Default: 60

echo "App: $appName\n";
echo "Timeout: {$timeout}s\n";

// Check existence
if ($settings->has('app.debug')) {
    echo "✓ Debug setting configured\n";
}

// Get all settings
$allSettings = $settings->all();
print_r($allSettings);
```

### Step 3: Nested Settings

```php
// Set nested configuration
$settings->set('database', [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myapp',
    'username' => 'root',
]);

// Get nested value using dot notation
$dbHost = $settings->get('database.host');
echo "DB Host: $dbHost\n";

// Update nested value
$settings->set('database.port', 5432);
```

## Tutorial 3: Service Dependencies

Services can depend on other services.

### Step 1: Understanding Dependencies

```php
// Some services depend on others
// Example: Logging might depend on Settings

use ClaudeAgents\Services\ServiceInterface;

class LoggingService implements ServiceInterface
{
    private SettingsService $settings;
    
    // Dependency injected via factory
    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }
    
    public function log(string $message): void
    {
        $level = $this->settings->get('logging.level', 'info');
        $format = $this->settings->get('logging.format', 'json');
        
        // Log with configured settings
        echo "[$level] $message\n";
    }
}
```

### Step 2: Factory with Dependencies

```php
use ClaudeAgents\Services\ServiceFactory;

class LoggingServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::LOGGING;
    protected string $serviceClass = LoggingService::class;
    
    public function create(array $dependencies = []): ServiceInterface
    {
        // Get required dependency
        $settings = $dependencies[ServiceType::SETTINGS->value] 
            ?? throw new \RuntimeException('Settings service required');
        
        return new LoggingService($settings);
    }
    
    public function getDependencies(): array
    {
        return [ServiceType::SETTINGS];
    }
}
```

### Step 3: Automatic Dependency Resolution

```php
// ServiceManager automatically resolves dependencies!
$manager = ServiceManager::getInstance();

$manager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new LoggingServiceFactory());

// When you get LoggingService, Settings is auto-created
$logging = $manager->get(ServiceType::LOGGING);

// Settings was automatically created and injected
$logging->log('Application started');
```

## Tutorial 4: Custom Services

Create your own service.

### Step 1: Implement ServiceInterface

```php
<?php

use ClaudeAgents\Services\ServiceInterface;

class EmailService implements ServiceInterface
{
    private bool $initialized = false;
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'smtp_host' => 'localhost',
            'smtp_port' => 25,
            'from' => 'noreply@example.com',
        ], $config);
    }
    
    public function getName(): string
    {
        return 'email';
    }
    
    public function initialize(): void
    {
        // Setup SMTP connection, verify config, etc.
        if (empty($this->config['smtp_host'])) {
            throw new \RuntimeException('SMTP host required');
        }
        
        $this->initialized = true;
    }
    
    public function teardown(): void
    {
        // Close connections, cleanup resources
        $this->initialized = false;
    }
    
    public function isReady(): bool
    {
        return $this->initialized;
    }
    
    public function getSchema(): array
    {
        return [
            'name' => 'email',
            'type' => 'notification',
            'config_schema' => [
                'smtp_host' => 'string',
                'smtp_port' => 'integer',
                'from' => 'string',
            ],
        ];
    }
    
    // Custom methods
    public function send(string $to, string $subject, string $body): bool
    {
        if (!$this->isReady()) {
            throw new \RuntimeException('Service not initialized');
        }
        
        // Send email logic
        error_log("Sending email to $to: $subject");
        return true;
    }
}
```

### Step 2: Add to ServiceType Enum

```php
// In your application, extend ServiceType or create custom registry
// For this example, we'll use a string identifier

class CustomServiceType
{
    public const EMAIL = 'email';
    public const SMS = 'sms';
    public const NOTIFICATION = 'notification';
}
```

### Step 3: Use Custom Service

```php
// Register the service
$manager->register(
    CustomServiceType::EMAIL,
    new EmailService([
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'from' => 'app@example.com',
    ])
);

// Initialize
$manager->initialize(CustomServiceType::EMAIL);

// Use the service
$email = $manager->get(CustomServiceType::EMAIL);
$email->send('user@example.com', 'Welcome', 'Thanks for signing up!');
```

## Tutorial 5: Service Factories

Build custom factories for your services.

### Step 1: Create a Service Factory

```php
<?php

use ClaudeAgents\Services\ServiceFactory;
use ClaudeAgents\Services\ServiceInterface;

class EmailServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::EMAIL;
    protected string $serviceClass = EmailService::class;
    
    public function create(array $dependencies = []): ServiceInterface
    {
        // Get settings dependency if available
        $settings = $dependencies[ServiceType::SETTINGS->value] ?? null;
        
        // Load config from settings or use defaults
        $config = [];
        if ($settings !== null) {
            $config = [
                'smtp_host' => $settings->get('email.smtp_host', 'localhost'),
                'smtp_port' => $settings->get('email.smtp_port', 25),
                'from' => $settings->get('email.from', 'noreply@example.com'),
            ];
        }
        
        return new EmailService($config);
    }
    
    public function getDependencies(): array
    {
        return [ServiceType::SETTINGS]; // Optional dependency
    }
    
    public function getSchema(): array
    {
        return [
            'service_type' => $this->serviceType->value,
            'class' => $this->serviceClass,
            'dependencies' => ['settings'],
            'config_keys' => [
                'email.smtp_host',
                'email.smtp_port',
                'email.from',
            ],
        ];
    }
}
```

### Step 2: Register Factory

```php
$manager = ServiceManager::getInstance();

// Register factory
$manager->registerFactory(new EmailServiceFactory());

// Service is created on-demand
$email = $manager->get(ServiceType::EMAIL);
```

### Step 3: Factory with Complex Dependencies

```php
class NotificationServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::NOTIFICATION;
    protected string $serviceClass = NotificationService::class;
    
    public function create(array $dependencies = []): ServiceInterface
    {
        // Get multiple dependencies
        $email = $dependencies[ServiceType::EMAIL->value] ?? null;
        $sms = $dependencies[CustomServiceType::SMS] ?? null;
        $cache = $dependencies[ServiceType::CACHE->value] ?? null;
        
        return new NotificationService(
            emailService: $email,
            smsService: $sms,
            cache: $cache
        );
    }
    
    public function getDependencies(): array
    {
        return [
            ServiceType::EMAIL,
            CustomServiceType::SMS,
            ServiceType::CACHE,
        ];
    }
}
```

## Tutorial 6: Lifecycle Management

Manage service initialization and cleanup.

### Step 1: Service Initialization

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$manager = ServiceManager::getInstance();

// Register services
$manager->registerFactory(new CacheServiceFactory());

// Service is lazy-loaded (not initialized until accessed)
$cache = $manager->get(ServiceType::CACHE);

// Check if ready
if (!$cache->isReady()) {
    // Initialize manually if needed
    $manager->initialize(ServiceType::CACHE);
}

echo "Cache ready: " . ($cache->isReady() ? 'Yes' : 'No') . "\n";
```

### Step 2: Initialize All Services

```php
// Initialize all registered services at once
$manager->initializeAll();

echo "All services initialized\n";

// List initialized services
$services = $manager->getInitializedServices();
foreach ($services as $type => $service) {
    echo "- $type: " . $service->getName() . "\n";
}
```

### Step 3: Proper Shutdown

```php
// Cleanup at application end
register_shutdown_function(function () use ($manager) {
    echo "Shutting down services...\n";
    $manager->teardownAll();
});

// Or manually
try {
    // Your application code
    $cache->set('key', 'value');
    
} finally {
    // Always cleanup
    $manager->teardownAll();
}
```

## Tutorial 7: Testing with Services

Mock services for testing.

### Step 1: Mock a Service

```php
<?php

use PHPUnit\Framework\TestCase;
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

class MyFeatureTest extends TestCase
{
    private ServiceManager $manager;
    
    protected function setUp(): void
    {
        $this->manager = ServiceManager::getInstance();
        
        // Mock the cache service
        $mockCache = $this->createMock(CacheService::class);
        $mockCache->method('get')->willReturn('mocked_value');
        $mockCache->method('set')->willReturn(true);
        
        $this->manager->mock(ServiceType::CACHE, $mockCache);
    }
    
    public function test_feature_uses_cache(): void
    {
        $cache = $this->manager->get(ServiceType::CACHE);
        
        // This uses the mock
        $value = $cache->get('key');
        
        $this->assertSame('mocked_value', $value);
    }
    
    protected function tearDown(): void
    {
        // Clear mocks
        $this->manager->clearMocks();
    }
}
```

### Step 2: Test Service Initialization

```php
public function test_service_initializes_correctly(): void
{
    $service = new EmailService([
        'smtp_host' => 'test.smtp.com',
        'smtp_port' => 587,
    ]);
    
    $this->assertFalse($service->isReady());
    
    $service->initialize();
    
    $this->assertTrue($service->isReady());
}
```

### Step 3: Test Service Dependencies

```php
public function test_service_resolves_dependencies(): void
{
    $manager = ServiceManager::getInstance();
    
    $manager
        ->registerFactory(new SettingsServiceFactory())
        ->registerFactory(new EmailServiceFactory());
    
    // Getting EmailService should auto-create Settings
    $email = $manager->get(ServiceType::EMAIL);
    
    $this->assertTrue($manager->has(ServiceType::SETTINGS));
    $this->assertInstanceOf(EmailService::class, $email);
}
```

## Common Patterns

### Pattern 1: Service Locator

```php
class Application
{
    private ServiceManager $services;
    
    public function __construct()
    {
        $this->services = ServiceManager::getInstance();
        $this->registerServices();
    }
    
    private function registerServices(): void
    {
        $this->services
            ->registerFactory(new CacheServiceFactory())
            ->registerFactory(new SettingsServiceFactory())
            ->registerFactory(new LoggingServiceFactory());
    }
    
    public function getCache(): CacheService
    {
        return $this->services->get(ServiceType::CACHE);
    }
    
    public function getSettings(): SettingsService
    {
        return $this->services->get(ServiceType::SETTINGS);
    }
}
```

### Pattern 2: Configuration-Based Services

```php
class ServiceBootstrapper
{
    public static function bootstrap(array $config): void
    {
        $manager = ServiceManager::getInstance();
        
        // Register cache
        if ($config['cache']['enabled'] ?? false) {
            $manager->registerFactory(new CacheServiceFactory());
            
            // Configure cache
            $cache = $manager->get(ServiceType::CACHE);
            // Apply config...
        }
        
        // Register settings
        $manager->registerFactory(new SettingsServiceFactory());
        $settings = $manager->get(ServiceType::SETTINGS);
        
        // Load settings from config
        foreach ($config['settings'] ?? [] as $key => $value) {
            $settings->set($key, $value);
        }
    }
}

// Use it
ServiceBootstrapper::bootstrap([
    'cache' => ['enabled' => true],
    'settings' => [
        'app.name' => 'My App',
        'app.env' => 'production',
    ],
]);
```

### Pattern 3: Service Facade

```php
class Cache
{
    private static ?CacheService $instance = null;
    
    public static function instance(): CacheService
    {
        if (self::$instance === null) {
            $manager = ServiceManager::getInstance();
            self::$instance = $manager->get(ServiceType::CACHE);
        }
        
        return self::$instance;
    }
    
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::instance()->get($key, $default);
    }
    
    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return self::instance()->set($key, $value, $ttl);
    }
}

// Use anywhere in your app
Cache::set('user:123', $user);
$user = Cache::get('user:123');
```

## Troubleshooting

### Problem: "Service not found"

**Cause:** Factory not registered for the service type.

**Solution:**
```php
// Check if registered
if (!$manager->has(ServiceType::CACHE)) {
    // Register the factory
    $manager->registerFactory(new CacheServiceFactory());
}

// Then get the service
$cache = $manager->get(ServiceType::CACHE);
```

### Problem: "Circular dependency detected"

**Cause:** Service A depends on B, which depends on A.

**Solution:**
```php
// Break the circle by using setter injection instead of constructor injection
class ServiceA implements ServiceInterface
{
    private ?ServiceB $serviceB = null;
    
    public function setServiceB(ServiceB $b): void
    {
        $this->serviceB = $b;
    }
}

// Or redesign to remove circular dependency
```

### Problem: "Service already registered"

**Cause:** Trying to register a factory for a type that's already registered.

**Solution:**
```php
// Check first
if (!$manager->has(ServiceType::CACHE)) {
    $manager->registerFactory(new CacheServiceFactory());
}

// Or use replace mode (if available)
$manager->registerFactory(new CacheServiceFactory(), replace: true);
```

## Next Steps

### Related Tutorials

- **[Production Patterns Tutorial](./ProductionPatterns_Tutorial.md)** - Deploy services in production
- **[Testing Strategies Tutorial](./TestingStrategies_Tutorial.md)** - Test service-based applications
- **[MCP Server Tutorial](./MCPServer_Tutorial.md)** - Expose services via MCP

### Further Reading

- [Services System Documentation](../services/README.md)
- [Implementation Summary](../services/IMPLEMENTATION_SUMMARY.md)
- [Migration Guide](../services/MIGRATION.md)

### Example Code

All examples from this tutorial are available in:
- `examples/tutorials/services-system/`
- `examples/Services/`

### What You've Learned

✓ Use ServiceManager singleton
✓ Register and use services
✓ Handle service dependencies
✓ Create custom services
✓ Build service factories
✓ Manage service lifecycle
✓ Test with service mocking
✓ Implement common service patterns

**Ready for more?** Continue with the [Production Patterns Tutorial](./ProductionPatterns_Tutorial.md) to learn deployment strategies!

---

*Tutorial Version: 1.0*
*Framework Version: v0.7.0+*
*Last Updated: February 2026*
