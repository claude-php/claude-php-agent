<?php

/**
 * Services Tutorial 7: Service Patterns
 * 
 * Run: php examples/tutorials/services-system/07-service-patterns.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Cache\CacheServiceFactory;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;

echo "=== Services Tutorial 7: Service Patterns ===\n\n";

// Pattern 1: Service Locator
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
            ->registerFactory(new SettingsServiceFactory());
    }
    
    public function run(): void
    {
        // Use services
        $cache = $this->services->get(ServiceType::CACHE);
        $settings = $this->services->get(ServiceType::SETTINGS);
        
        $settings->set('app.initialized', true);
        $cache->set('app:status', 'running');
        
        echo "✓ Application using services\n";
        echo "  Status: " . $cache->get('app:status') . "\n";
        echo "  Initialized: " . ($settings->get('app.initialized') ? 'Yes' : 'No') . "\n";
    }
}

// Run application
$app = new Application();
$app->run();

echo "\n✓ Example complete!\n";
