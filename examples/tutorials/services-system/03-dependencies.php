<?php

/**
 * Services Tutorial 3: Service Dependencies
 * 
 * Run: php examples/tutorials/services-system/03-dependencies.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;

echo "=== Services Tutorial 3: Service Dependencies ===\n\n";

$manager = ServiceManager::getInstance();

// Register only one factory
$manager->registerFactory(new SettingsServiceFactory());

// Get the service
$settings = $manager->get(ServiceType::SETTINGS);

echo "✓ Settings service created\n";
echo "  Dependencies auto-resolved: " . 
    (count($manager->getRegisteredTypes()) > 1 ? 'Yes' : 'No') . "\n";

// Use the service
$settings->set('example.key', 'example_value');
$value = $settings->get('example.key');

echo "✓ Service is functional: $value\n";

echo "\n✓ Example complete!\n";
