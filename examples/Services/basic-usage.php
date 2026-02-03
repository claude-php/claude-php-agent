<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;
use ClaudeAgents\Services\Settings\SettingsServiceFactory;
use ClaudeAgents\Services\Cache\CacheServiceFactory;
use ClaudeAgents\Services\Storage\StorageServiceFactory;
use ClaudeAgents\Services\Variable\VariableServiceFactory;
use ClaudeAgents\Services\Variable\VariableType;
use ClaudeAgents\Services\Tracing\TracingServiceFactory;
use ClaudeAgents\Services\Telemetry\TelemetryServiceFactory;
use ClaudeAgents\Services\Session\SessionServiceFactory;

/**
 * Basic Service Layer Usage Example
 *
 * Demonstrates how to:
 * 1. Initialize the ServiceManager
 * 2. Register service factories
 * 3. Use services with automatic dependency injection
 */

echo "=== Service Layer Basic Usage ===\n\n";

// 1. Get ServiceManager instance
$serviceManager = ServiceManager::getInstance();

// 2. Register service factories
// Note: Order doesn't matter - dependencies are resolved automatically
echo "Registering services...\n";
$serviceManager
    ->registerFactory(new SettingsServiceFactory())
    ->registerFactory(new CacheServiceFactory())
    ->registerFactory(new StorageServiceFactory())
    ->registerFactory(new VariableServiceFactory())
    ->registerFactory(new TracingServiceFactory())
    ->registerFactory(new TelemetryServiceFactory())
    ->registerFactory(new SessionServiceFactory());

echo "✓ All services registered\n\n";

// 3. Use Settings Service
echo "--- Settings Service ---\n";
$settings = $serviceManager->get(ServiceType::SETTINGS);
$settings->set('app.name', 'Claude PHP Agent');
$settings->set('app.version', '1.0.0');

echo "App Name: " . $settings->get('app.name') . "\n";
echo "App Version: " . $settings->get('app.version') . "\n";
echo "Cache Driver: " . $settings->get('cache.driver', 'array') . "\n\n";

// 4. Use Cache Service
echo "--- Cache Service ---\n";
$cache = $serviceManager->get(ServiceType::CACHE);

// Set values
$cache->set('user:123', ['name' => 'John Doe', 'email' => 'john@example.com']);
$cache->set('counter', 42, 3600);

// Get values
echo "Cached User: " . json_encode($cache->get('user:123')) . "\n";
echo "Cached Counter: " . $cache->get('counter') . "\n";

// Remember pattern
$expensive = $cache->remember('expensive_computation', function () {
    echo "  Computing expensive value...\n";
    return 'computed_result';
}, 3600);
echo "Expensive Result: {$expensive}\n";
echo "  (calling again - should use cache)\n";
$expensive = $cache->remember('expensive_computation', function () {
    echo "  Computing expensive value...\n";
    return 'computed_result';
}, 3600);
echo "Expensive Result: {$expensive}\n\n";

// 5. Use Storage Service
echo "--- Storage Service ---\n";
$storage = $serviceManager->get(ServiceType::STORAGE);

// Save files
$storage->saveFile('user-123', 'profile.json', json_encode([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]));
$storage->saveFile('user-123', 'settings.json', json_encode([
    'theme' => 'dark',
    'language' => 'en',
]));

// List files
$files = $storage->listFiles('user-123');
echo "Files for user-123: " . implode(', ', $files) . "\n";

// Get file
$profile = json_decode($storage->getFile('user-123', 'profile.json'), true);
echo "Profile: " . json_encode($profile) . "\n\n";

// 6. Use Variable Service
echo "--- Variable Service ---\n";
$variables = $serviceManager->get(ServiceType::VARIABLE);

// Store generic variable
$variables->setVariable('user-123', 'theme', 'dark', VariableType::GENERIC);

// Store credential (encrypted)
$variables->setVariable('user-123', 'api_key', 'sk-1234567890', VariableType::CREDENTIAL);

// Retrieve variables
echo "Theme: " . $variables->getVariable('user-123', 'theme') . "\n";
echo "API Key: " . $variables->getVariable('user-123', 'api_key') . "\n";

// List all variables
$varList = $variables->listVariables('user-123');
echo "All variables: " . implode(', ', $varList) . "\n\n";

// 7. Use Session Service
echo "--- Session Service ---\n";
$sessions = $serviceManager->get(ServiceType::SESSION);

// Create session
$sessionId = $sessions->createSession('user-123', [
    'name' => 'John Doe',
    'logged_in_at' => time(),
]);
echo "Created session: {$sessionId}\n";

// Get session data
$sessionData = $sessions->getSession($sessionId);
echo "Session data: " . json_encode($sessionData) . "\n";

// Update session
$sessions->updateSession($sessionId, [
    'last_activity' => time(),
    'page_views' => 5,
]);

// List user sessions
$userSessions = $sessions->listSessions('user-123');
echo "User sessions: " . count($userSessions) . " active\n\n";

// 8. Use Telemetry Service
echo "--- Telemetry Service ---\n";
$telemetry = $serviceManager->get(ServiceType::TELEMETRY);

// Record metrics
$telemetry->recordCounter('api.requests', 1);
$telemetry->recordGauge('memory.usage', memory_get_usage(true) / 1024 / 1024);
$telemetry->recordHistogram('request.duration', 123.45);

// Record agent request
$telemetry->recordAgentRequest(
    success: true,
    tokensInput: 100,
    tokensOutput: 50,
    duration: 250.5
);

// Get summary
$summary = $telemetry->getSummary();
echo "Total requests: " . $summary['total_requests'] . "\n";
echo "Success rate: " . ($summary['success_rate'] * 100) . "%\n\n";

// 9. Tracing Service (when enabled)
echo "--- Tracing Service ---\n";
$tracing = $serviceManager->get(ServiceType::TRACING);

if ($tracing->isEnabled()) {
    // Start trace
    $tracing->startTrace('trace-123', 'example-operation', [
        'user_id' => 'user-123',
        'operation' => 'test',
    ]);

    // Record span
    $result = $tracing->recordSpan('database-query', function () {
        usleep(10000); // Simulate work
        return 'query_result';
    });

    // End trace
    $tracing->endTrace('trace-123', ['result' => $result]);
    echo "Tracing recorded\n";
} else {
    echo "Tracing disabled (enable in settings)\n";
}

echo "\n";

// 10. Cleanup
echo "--- Cleanup ---\n";
$serviceManager->teardownAll();
echo "✓ All services torn down\n";

echo "\n=== Example Complete ===\n";
