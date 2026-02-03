<?php

/**
 * Services Tutorial 6: Testing Services
 * 
 * Run: php examples/tutorials/services-system/06-testing-services.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Cache\CacheService;

echo "=== Services Tutorial 6: Testing Services ===\n\n";

// Simulate mocking for tests
class MockCacheService extends CacheService
{
    private array $mockData = [];
    
    public function get(string $key, mixed $default = null): mixed
    {
        echo "Mock: Getting $key\n";
        return $this->mockData[$key] ?? $default;
    }
    
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        echo "Mock: Setting $key\n";
        $this->mockData[$key] = $value;
        return true;
    }
}

// Setup test environment
$manager = ServiceManager::getInstance();

// Mock the cache service
$mockCache = new MockCacheService();
$manager->mock(ServiceType::CACHE, $mockCache);

// Use mocked service
$cache = $manager->get(ServiceType::CACHE);
$cache->set('test', 'mocked_value');
$value = $cache->get('test');

echo "\n✓ Retrieved mocked value: $value\n";

// Clear mocks
$manager->clearMocks();

echo "\n✓ Example complete!\n";
