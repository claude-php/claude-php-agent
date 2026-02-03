<?php

/**
 * Production Patterns Tutorial 3: Caching
 * 
 * Run: php examples/tutorials/production-patterns/03-caching.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

echo "=== Production Patterns Tutorial 3: Caching ===\n\n";

$manager = ServiceManager::getInstance();
$manager->registerFactory(new CacheServiceFactory());

$cache = $manager->get(ServiceType::CACHE);

echo "Cache Operations:\n";

// Store
$cache->set('user:123', ['name' => 'John', 'email' => 'john@example.com'], 3600);
echo "✓ Stored user data (TTL: 3600s)\n";

// Retrieve
$user = $cache->get('user:123');
echo "✓ Retrieved: {$user['name']}\n";

// Check existence
if ($cache->has('user:123')) {
    echo "✓ Cache hit confirmed\n";
}

// Caching pattern example
function getCachedData(string $key, callable $fetcher, int $ttl = 3600)
{
    global $cache;
    
    if ($cache->has($key)) {
        echo "  → Cache hit: $key\n";
        return $cache->get($key);
    }
    
    echo "  → Cache miss: $key, fetching...\n";
    $data = $fetcher();
    $cache->set($key, $data, $ttl);
    
    return $data;
}

echo "\nUsing cache pattern:\n";
$data = getCachedData('expensive:query', fn() => ['result' => 'computed']);

echo "\n✓ Example complete!\n";
