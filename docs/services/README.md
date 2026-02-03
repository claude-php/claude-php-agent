# Service Layer Architecture

The Service Layer provides enterprise-grade service management with automatic dependency injection, lifecycle management, and extensibility for the Claude PHP Agent framework.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Available Services](#available-services)
- [Usage Examples](#usage-examples)
- [Advanced Topics](#advanced-topics)

## Overview

The Service Layer architecture provides:

- **Centralized Service Management** - Single ServiceManager for all services
- **Automatic Dependency Injection** - Services automatically resolve dependencies
- **Lifecycle Management** - Proper initialization and cleanup
- **Type Safety** - ServiceType enum prevents errors
- **Testability** - Easy mocking and service replacement
- **Extensibility** - Create custom services easily

## Quick Start

### 1. Initialize ServiceManager

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

// Get singleton instance
$serviceManager = ServiceManager::getInstance();

// Register services
$serviceManager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory());
```

### 2. Use Services

```php
// Get a service (automatically creates and initializes)
$cache = $serviceManager->get(ServiceType::CACHE);

// Use the service
$cache->set('key', 'value');
$value = $cache->get('key');
```

### 3. Automatic Cleanup

```php
// Teardown all services on shutdown
$serviceManager->teardownAll();
```

## Core Concepts

### Service Interface

All services implement `ServiceInterface`:

```php
interface ServiceInterface
{
    public function getName(): string;
    public function initialize(): void;
    public function teardown(): void;
    public function isReady(): bool;
    public function getSchema(): array;
}
```

### Service Manager

The `ServiceManager` is a singleton that:

1. Registers service factories
2. Creates services on-demand (lazy initialization)
3. Resolves dependencies automatically
4. Manages service lifecycle
5. Provides service mocking for tests

### Service Factory

Factories create service instances with dependency injection:

```php
class CacheServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::CACHE;
    protected string $serviceClass = CacheService::class;

    public function create(array $dependencies = []): ServiceInterface
    {
        return $this->createWithDependencies($dependencies);
    }
}
```

### Service Types

Type-safe enum for all services:

```php
enum ServiceType: string
{
    case SETTINGS = 'settings';
    case CACHE = 'cache';
    case STORAGE = 'storage';
    case VARIABLE = 'variable';
    case TRACING = 'tracing';
    case TELEMETRY = 'telemetry';
    case SESSION = 'session';
    case TRANSACTION = 'transaction';
}
```

## Available Services

### SettingsService

Application-wide configuration management.

**Features:**
- Load from JSON/PHP config files
- Environment variable overrides
- Dot notation for nested values
- Hot reload support

**Usage:**
```php
$settings = $serviceManager->get(ServiceType::SETTINGS);

$settings->set('app.name', 'My App');
$name = $settings->get('app.name');
$debug = $settings->get('app.debug', false);
```

[Full Documentation →](SettingsService.md)

### CacheService

Unified caching with multiple backends.

**Features:**
- Array, file, and Redis backends
- TTL management
- Cache namespacing
- Remember pattern

**Usage:**
```php
$cache = $serviceManager->get(ServiceType::CACHE);

$cache->set('user:123', $userData, 3600);
$user = $cache->get('user:123');

$result = $cache->remember('expensive', function() {
    return computeExpensiveValue();
}, 3600);
```

[Full Documentation →](CacheService.md)

### StorageService

File storage with multiple backends.

**Features:**
- Local file system storage
- User/flow scoped files
- Atomic writes
- Directory management

**Usage:**
```php
$storage = $serviceManager->get(ServiceType::STORAGE);

$storage->saveFile('user-123', 'profile.json', $data);
$data = $storage->getFile('user-123', 'profile.json');
$files = $storage->listFiles('user-123');
```

[Full Documentation →](StorageService.md)

### VariableService

Secure variable and secret management.

**Features:**
- User-scoped variables
- Encryption for credentials
- Multiple backends
- Type distinction (credential vs generic)

**Usage:**
```php
$variables = $serviceManager->get(ServiceType::VARIABLE);

// Generic variable
$variables->setVariable('user', 'theme', 'dark', VariableType::GENERIC);

// Encrypted credential
$variables->setVariable('user', 'api_key', 'sk-...', VariableType::CREDENTIAL);

$apiKey = $variables->getVariable('user', 'api_key');
```

[Full Documentation →](VariableService.md)

### TracingService

Observability platform integration.

**Features:**
- LangSmith integration
- LangFuse integration
- Arize Phoenix integration
- Span recording
- Metric collection

**Usage:**
```php
$tracing = $serviceManager->get(ServiceType::TRACING);

$tracing->startTrace('trace-123', 'agent.run', $metadata);

$result = $tracing->recordSpan('llm.call', function() {
    return callLLM();
});

$tracing->endTrace('trace-123', $outputs);
```

[Full Documentation →](TracingService.md)

### TelemetryService

OpenTelemetry metrics collection.

**Features:**
- Counter metrics
- Gauge metrics
- Histogram metrics
- Integration with existing Metrics class

**Usage:**
```php
$telemetry = $serviceManager->get(ServiceType::TELEMETRY);

$telemetry->recordCounter('api.requests');
$telemetry->recordGauge('memory.usage', memory_get_usage());
$telemetry->recordHistogram('request.duration', $duration);

$summary = $telemetry->getSummary();
```

[Full Documentation →](TelemetryService.md)

### SessionService

User session management.

**Features:**
- Session creation and validation
- Expiration management
- Multi-user support
- Persistence

**Usage:**
```php
$sessions = $serviceManager->get(ServiceType::SESSION);

$sessionId = $sessions->createSession('user-123', $initialData);
$data = $sessions->getSession($sessionId);
$sessions->updateSession($sessionId, $newData);
$sessions->destroySession($sessionId);
```

[Full Documentation →](SessionService.md)

### TransactionService

Database transaction management (future-ready).

**Features:**
- Begin/commit/rollback
- Nested transactions
- Transaction callbacks

**Usage:**
```php
$transactions = $serviceManager->get(ServiceType::TRANSACTION);

$transactions->inTransaction(function() {
    // Perform database operations
    // Auto-commits on success, rolls back on exception
});
```

[Full Documentation →](TransactionService.md)

## Usage Examples

### Basic Service Usage

See [examples/Services/basic-usage.php](../../examples/Services/basic-usage.php)

### Agent Integration

See [examples/Services/agent-integration.php](../../examples/Services/agent-integration.php)

### Testing with Mock Services

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Cache\ArrayCache;

class MyTest extends TestCase
{
    public function testWithMockCache(): void
    {
        $serviceManager = ServiceManager::getInstance();
        
        // Mock the cache service
        $mockCache = new ArrayCache();
        $serviceManager->mock(ServiceType::CACHE, $mockCache);
        
        // Test your code that uses the cache
        // ...
    }
}
```

## Advanced Topics

### Creating Custom Services

1. Implement `ServiceInterface`
2. Create a factory extending `ServiceFactory`
3. Add to `ServiceType` enum
4. Register with ServiceManager

```php
// 1. Service implementation
class MyCustomService implements ServiceInterface
{
    public function getName(): string { return 'custom'; }
    public function initialize(): void { }
    public function teardown(): void { }
    public function isReady(): bool { return true; }
    public function getSchema(): array { return []; }
}

// 2. Factory
class MyCustomServiceFactory extends ServiceFactory
{
    protected ServiceType $serviceType = ServiceType::CUSTOM;
    protected string $serviceClass = MyCustomService::class;
    
    public function create(array $dependencies = []): ServiceInterface
    {
        return new MyCustomService();
    }
}

// 3. Register and use
$serviceManager->registerFactory(new MyCustomServiceFactory());
$custom = $serviceManager->get(ServiceType::CUSTOM);
```

### Dependency Injection

Dependencies are automatically resolved by inspecting constructor parameters:

```php
class MyService implements ServiceInterface
{
    public function __construct(
        private SettingsService $settings,
        private CacheService $cache
    ) {}
}

// ServiceManager automatically injects settings and cache
```

### Configuration

Configure services via SettingsService:

```php
$settings = new SettingsService(configFile: './config/agent.php');
$settings->initialize();

// Or via environment variables
// CLAUDE_AGENT_CACHE_DRIVER=redis
// CLAUDE_AGENT_CACHE_REDIS_HOST=localhost
```

### Lifecycle Management

Services have three lifecycle states:

1. **Registered** - Factory registered, not yet created
2. **Created & Initialized** - Service created and ready
3. **Torn Down** - Resources released, service unusable

```php
$service = $serviceManager->get(ServiceType::CACHE);
// Service is now created and initialized

$serviceManager->teardownAll();
// All services torn down
```

## Migration Guide

For existing projects, see [MIGRATION.md](MIGRATION.md) for step-by-step migration instructions.

## Best Practices

1. **Initialize Early** - Register factories at application startup
2. **Use Dependency Injection** - Let ServiceManager handle dependencies
3. **Teardown on Shutdown** - Call `teardownAll()` when application exits
4. **Mock for Testing** - Use `mock()` method for unit tests
5. **Configure via Settings** - Use SettingsService for all configuration
6. **Type Safety** - Always use ServiceType enum
7. **Lazy Loading** - Services are created only when first accessed

## API Reference

See individual service documentation for detailed API references:

- [SettingsService](SettingsService.md)
- [CacheService](CacheService.md)
- [StorageService](StorageService.md)
- [VariableService](VariableService.md)
- [TracingService](TracingService.md)
- [TelemetryService](TelemetryService.md)
- [SessionService](SessionService.md)
- [TransactionService](TransactionService.md)

## Support

For issues, questions, or contributions:

- GitHub Issues: [github.com/claude-php/agent/issues](https://github.com/claude-php/agent/issues)
- Documentation: [github.com/claude-php/agent/docs](https://github.com/claude-php/agent/docs)
