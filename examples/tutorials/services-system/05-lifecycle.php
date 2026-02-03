<?php

/**
 * Services Tutorial 5: Lifecycle Management
 * 
 * Run: php examples/tutorials/services-system/05-lifecycle.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Cache\CacheServiceFactory;

echo "=== Services Tutorial 5: Lifecycle Management ===\n\n";

$manager = ServiceManager::getInstance();
$manager->registerFactory(new CacheServiceFactory());

// Get service (lazy loaded)
echo "Getting cache service...\n";
$cache = $manager->get(ServiceType::CACHE);

echo "Ready: " . ($cache->isReady() ? 'Yes' : 'No') . "\n\n";

// Initialize all services
echo "Initializing all services...\n";
$manager->initializeAll();

echo "✓ All services initialized\n\n";

// Use the service
$cache->set('test', 'value');
echo "✓ Cache is working\n\n";

// Teardown
echo "Tearing down services...\n";
$manager->teardownAll();

echo "✓ All services torn down\n";

echo "\n✓ Example complete!\n";
