<?php

/**
 * Services Tutorial 2: Using Built-in Services
 * 
 * Run: php examples/tutorials/services-system/02-using-services.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Cache\CacheServiceFactory;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;

echo "=== Services Tutorial 2: Using Built-in Services ===\n\n";

$manager = ServiceManager::getInstance();
$manager
    ->registerFactory(new CacheServiceFactory())
    ->registerFactory(new SettingsServiceFactory());

// Cache service
echo "Cache Service:\n";
$cache = $manager->get(ServiceType::CACHE);

$cache->set('user:123', ['name' => 'John Doe', 'email' => 'john@example.com']);
$user = $cache->get('user:123');

echo "✓ Stored and retrieved user: {$user['name']}\n\n";

// Settings service
echo "Settings Service:\n";
$settings = $manager->get(ServiceType::SETTINGS);

$settings->set('app.name', 'My AI App');
$settings->set('app.debug', true);

$appName = $settings->get('app.name');
echo "✓ App name: $appName\n";

echo "\n✓ Example complete!\n";
