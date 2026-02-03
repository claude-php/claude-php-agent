# CacheService Tutorial

Learn how to use the CacheService for efficient data caching in your applications.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [Cache Backends](#cache-backends)
- [Cache Patterns](#cache-patterns)
- [Namespacing](#namespacing)
- [TTL Management](#ttl-management)
- [Advanced Techniques](#advanced-techniques)
- [Best Practices](#best-practices)

## Overview

The CacheService provides a unified caching interface with support for multiple backends (Array, File, Redis).

**Features:**
- Multiple backend support
- TTL (Time To Live) management
- Cache namespacing
- Remember pattern for lazy computation
- PSR-compatible interface

## Basic Usage

### Setup

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

$manager = ServiceManager::getInstance();
$manager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory());

$cache = $manager->get(ServiceType::CACHE);
```

### Set and Get

```php
// Store a value
$cache->set('user:123', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Retrieve a value
$user = $cache->get('user:123');
// ['name' => 'John Doe', 'email' => 'john@example.com']

// Non-existent key returns null
$value = $cache->get('nonexistent'); // null
```

### Check and Delete

```php
// Check if key exists
if ($cache->has('user:123')) {
    echo "User is cached";
}

// Delete a key
$cache->delete('user:123');

// Clear all cache
$cache->clear();
```

## Cache Backends

### Array Backend (Default)

In-memory caching, fast but not persistent:

```php
// config/services.php
return [
    'cache' => [
        'driver' => 'array',
    ],
];
```

**Use Cases:**
- Development and testing
- Request-scoped caching
- Non-critical caching

### File Backend

Persistent file-based caching:

```php
// config/services.php
return [
    'cache' => [
        'driver' => 'file',
        'path' => './storage/cache',
    ],
];
```

**Use Cases:**
- Single-server applications
- Persistent caching needs
- When Redis isn't available

### Redis Backend

Distributed caching with Redis:

```php
// config/services.php
return [
    'cache' => [
        'driver' => 'redis',
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            'password' => null,
        ],
    ],
];
```

**Use Cases:**
- Multi-server applications
- High-performance caching
- Distributed systems

## Cache Patterns

### Remember Pattern

Lazy computation with automatic caching:

```php
// First call: executes callback and caches result
$users = $cache->remember('all_users', function() {
    return User::all(); // Expensive database query
}, 3600);

// Subsequent calls: returns cached value without executing callback
$users = $cache->remember('all_users', function() {
    return User::all(); // Not executed
}, 3600);
```

### Cache-Aside Pattern

Manual cache management:

```php
function getUser(int $id) use ($cache, $db) {
    $cacheKey = "user:{$id}";
    
    // Try cache first
    if ($cache->has($cacheKey)) {
        return $cache->get($cacheKey);
    }
    
    // Load from database
    $user = $db->query("SELECT * FROM users WHERE id = ?", [$id]);
    
    // Store in cache
    $cache->set($cacheKey, $user, 3600);
    
    return $user;
}
```

### Write-Through Pattern

Update cache and storage together:

```php
function updateUser(int $id, array $data) use ($cache, $db) {
    // Update database
    $db->update('users', $data, ['id' => $id]);
    
    // Update cache
    $cache->set("user:{$id}", $data, 3600);
}
```

### Cache Invalidation

```php
function deleteUser(int $id) use ($cache, $db) {
    // Delete from database
    $db->delete('users', ['id' => $id]);
    
    // Invalidate cache
    $cache->delete("user:{$id}");
}
```

## Namespacing

Use namespaces to organize cache keys:

```php
// Set namespace
$cache->setNamespace('users');

// Keys are automatically prefixed
$cache->set('123', $userData);     // Stored as "users:123"
$cache->set('456', $userData2);    // Stored as "users:456"

// Switch namespace
$cache->setNamespace('products');
$cache->set('123', $productData);  // Stored as "products:123"

// Clear namespace
$cache->setNamespace('');          // Back to no namespace
```

### Multiple Namespaces

```php
class UserCache
{
    private CacheService $cache;
    
    public function __construct(CacheService $cache)
    {
        $this->cache = clone $cache;
        $this->cache->setNamespace('users');
    }
    
    public function get(int $id): ?array
    {
        return $this->cache->get((string)$id);
    }
}

class ProductCache
{
    private CacheService $cache;
    
    public function __construct(CacheService $cache)
    {
        $this->cache = clone $cache;
        $this->cache->setNamespace('products');
    }
    
    public function get(int $id): ?array
    {
        return $this->cache->get((string)$id);
    }
}
```

## TTL Management

### Set TTL on Write

```php
// Cache for 1 hour (3600 seconds)
$cache->set('key', 'value', 3600);

// Cache for 1 day
$cache->set('key', 'value', 86400);

// No expiration (0 = permanent until manually deleted)
$cache->set('key', 'value', 0);
```

### Default TTL

Configure default TTL in settings:

```php
// config/services.php
return [
    'cache' => [
        'ttl' => 3600, // Default 1 hour
    ],
];
```

### TTL Strategies

**Short TTL for frequently changing data:**
```php
// User online status (30 seconds)
$cache->set("user:{$id}:online", true, 30);
```

**Long TTL for static data:**
```php
// Configuration (1 day)
$cache->set('app:config', $config, 86400);
```

**No expiration for reference data:**
```php
// Country codes (permanent)
$cache->set('ref:countries', $countries, 0);
```

## Advanced Techniques

### Multi-Level Caching

```php
class MultiLevelCache
{
    private CacheService $l1; // Fast (array)
    private CacheService $l2; // Persistent (redis)
    
    public function get(string $key): mixed
    {
        // Try L1 first (fast)
        if ($this->l1->has($key)) {
            return $this->l1->get($key);
        }
        
        // Try L2 (slower but persistent)
        if ($this->l2->has($key)) {
            $value = $this->l2->get($key);
            // Promote to L1
            $this->l1->set($key, $value, 300);
            return $value;
        }
        
        return null;
    }
}
```

### Cache Stampede Prevention

```php
function getCachedData(string $key, callable $loader) use ($cache) {
    // Try cache first
    if ($cache->has($key)) {
        return $cache->get($key);
    }
    
    // Use a lock to prevent multiple processes from loading simultaneously
    $lockKey = "{$key}:lock";
    
    if (!$cache->has($lockKey)) {
        // Acquire lock
        $cache->set($lockKey, true, 5); // 5 second lock
        
        try {
            // Load data
            $data = $loader();
            
            // Cache it
            $cache->set($key, $data, 3600);
            
            return $data;
        } finally {
            // Release lock
            $cache->delete($lockKey);
        }
    }
    
    // Wait for lock holder to finish
    sleep(1);
    return getCachedData($key, $loader);
}
```

### Partial Cache Invalidation

```php
// Tag-based invalidation
class TaggedCache
{
    private CacheService $cache;
    
    public function set(string $key, $value, array $tags, int $ttl = 0): void
    {
        $this->cache->set($key, $value, $ttl);
        
        // Store tags
        foreach ($tags as $tag) {
            $tagKey = "tag:{$tag}";
            $keys = $this->cache->get($tagKey) ?? [];
            $keys[] = $key;
            $this->cache->set($tagKey, array_unique($keys), 0);
        }
    }
    
    public function invalidateTag(string $tag): void
    {
        $tagKey = "tag:{$tag}";
        $keys = $this->cache->get($tagKey) ?? [];
        
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
        
        $this->cache->delete($tagKey);
    }
}

// Usage
$taggedCache = new TaggedCache($cache);

// Cache with tags
$taggedCache->set('user:123', $user, ['users', 'user:123']);
$taggedCache->set('user:456', $user2, ['users', 'user:456']);

// Invalidate all users
$taggedCache->invalidateTag('users');
```

### Cache Warming

```php
class CacheWarmer
{
    private CacheService $cache;
    
    public function warmPopularItems(): void
    {
        $popularIds = [1, 2, 3, 5, 8];
        
        foreach ($popularIds as $id) {
            if (!$this->cache->has("product:{$id}")) {
                $product = $this->loadProduct($id);
                $this->cache->set("product:{$id}", $product, 3600);
            }
        }
    }
}
```

## Best Practices

### 1. Use Descriptive Keys

```php
// ❌ Bad
$cache->set('u123', $user);

// ✅ Good
$cache->set('user:123', $user);
$cache->set('user:123:profile', $profile);
$cache->set('user:123:preferences', $preferences);
```

### 2. Cache Serializable Data

```php
// ✅ Good - Simple types and arrays
$cache->set('key', ['id' => 1, 'name' => 'John']);
$cache->set('key', 'string value');
$cache->set('key', 42);

// ⚠️ Caution - Objects (may not serialize well)
$cache->set('key', $object); // Depends on backend
```

### 3. Set Appropriate TTL

```php
// Frequently changing data: short TTL
$cache->set('stock:price', $price, 60); // 1 minute

// Moderately changing: medium TTL
$cache->set('user:profile', $profile, 3600); // 1 hour

// Rarely changing: long TTL
$cache->set('config:app', $config, 86400); // 1 day
```

### 4. Handle Cache Misses Gracefully

```php
$user = $cache->get('user:123');

if ($user === null) {
    // Cache miss - load from database
    $user = $this->loadUserFromDatabase(123);
    
    if ($user !== null) {
        $cache->set('user:123', $user, 3600);
    }
}
```

### 5. Invalidate on Updates

```php
function updateUser(int $id, array $data): void
{
    // Update database
    $this->db->update('users', $data, ['id' => $id]);
    
    // Invalidate related caches
    $this->cache->delete("user:{$id}");
    $this->cache->delete("user:{$id}:profile");
    $this->cache->delete("user:{$id}:permissions");
}
```

### 6. Monitor Cache Performance

```php
class MonitoredCache
{
    private CacheService $cache;
    private TelemetryService $telemetry;
    
    public function get(string $key): mixed
    {
        $value = $this->cache->get($key);
        
        if ($value !== null) {
            $this->telemetry->recordCounter('cache.hits');
        } else {
            $this->telemetry->recordCounter('cache.misses');
        }
        
        return $value;
    }
}
```

## Summary

You've learned:

✅ Basic cache operations (set, get, delete)  
✅ Multiple cache backends (Array, File, Redis)  
✅ Cache patterns (Remember, Cache-Aside, Write-Through)  
✅ Namespacing for organization  
✅ TTL management strategies  
✅ Advanced techniques (multi-level, stampede prevention)  
✅ Best practices for production use  

Next: Check out [VariableService Tutorial](Services_Variables.md) for secure secret management!
