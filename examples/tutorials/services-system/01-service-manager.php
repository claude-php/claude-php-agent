<?php

/**
 * Services Tutorial 1: ServiceManager Basics
 * 
 * Run: php examples/tutorials/services-system/01-service-manager.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;
use ClaudeAgents\Services\ServiceType;

echo "=== Services Tutorial 1: ServiceManager Basics ===\n\n";

// Get singleton instance
$manager = ServiceManager::getInstance();

// Register service factories
echo "Registering services...\n";
$manager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory());

echo "✓ Factories registered\n\n";

// Get a service
echo "Getting cache service...\n";
$cache = $manager->get(ServiceType::CACHE);

echo "✓ Service retrieved\n";
echo "  Name: {$cache->getName()}\n";
echo "  Ready: " . ($cache->isReady() ? 'Yes' : 'No') . "\n\n";

// Check availability
if ($manager->has(ServiceType::CACHE)) {
    echo "✓ Cache service is available\n";
}

echo "\n✓ Example complete!\n";
