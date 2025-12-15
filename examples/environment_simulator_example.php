#!/usr/bin/env php
<?php
/**
 * Environment Simulator Agent Example
 *
 * Demonstrates how to use the Environment Simulator Agent to model
 * and predict the outcomes of actions in various environments.
 * Shows basic simulation, state management, and outcome prediction.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\EnvironmentSimulatorAgent;
use ClaudePhp\ClaudePhp;

// Load environment
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
if (empty($apiKey)) {
    echo "Error: ANTHROPIC_API_KEY not found in .env file or environment.\n";
    echo "Please add to .env: ANTHROPIC_API_KEY='your-key-here'\n";
    exit(1);
}

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

echo "=== Environment Simulator Agent Example ===\n\n";

// Example 1: Web Application Environment
echo "--- Example 1: Web Application Scaling ---\n\n";

$webEnv = new EnvironmentSimulatorAgent($client, [
    'name' => 'web_app_simulator',
    'initial_state' => [
        'servers' => 3,
        'cpu_usage' => 45,
        'memory_usage' => 60,
        'requests_per_second' => 500,
        'response_time_ms' => 120,
    ],
]);

echo "Initial State:\n";
echo json_encode($webEnv->getState(), JSON_PRETTY_PRINT) . "\n\n";

// Simulate scaling up servers
$result1 = $webEnv->run('Scale from 3 servers to 10 servers to handle increased traffic');

if ($result1->isSuccess()) {
    echo "Simulation: Scaling Up Servers\n";
    echo "================================\n";
    echo $result1->getAnswer() . "\n\n";
    
    $metadata = $result1->getMetadata();
    echo "Predicted Resulting State:\n";
    echo json_encode($metadata['resulting_state'], JSON_PRETTY_PRINT) . "\n\n";
}

// Example 2: Database Environment
echo "\n--- Example 2: Database Configuration Change ---\n\n";

$dbEnv = new EnvironmentSimulatorAgent($client, [
    'name' => 'database_simulator',
    'initial_state' => [
        'connection_pool_size' => 50,
        'query_cache_enabled' => true,
        'avg_query_time_ms' => 150,
        'active_connections' => 35,
        'cache_hit_rate' => 0.75,
    ],
]);

echo "Initial Database State:\n";
echo json_encode($dbEnv->getState(), JSON_PRETTY_PRINT) . "\n\n";

// Simulate increasing connection pool
$result2 = $dbEnv->run('Increase connection pool size from 50 to 200 connections');

if ($result2->isSuccess()) {
    echo "Simulation: Increasing Connection Pool\n";
    echo "=======================================\n";
    echo $result2->getAnswer() . "\n\n";
}

// Example 3: IoT/Smart Home Environment
echo "\n--- Example 3: Smart Home Temperature Control ---\n\n";

$homeEnv = new EnvironmentSimulatorAgent($client, [
    'name' => 'smart_home_simulator',
    'initial_state' => [
        'temperature_celsius' => 22,
        'humidity_percent' => 55,
        'hvac_mode' => 'auto',
        'target_temperature' => 21,
        'energy_consumption_kwh' => 2.5,
    ],
]);

echo "Initial Home State:\n";
echo json_encode($homeEnv->getState(), JSON_PRETTY_PRINT) . "\n\n";

// Simulate temperature adjustment
$result3 = $homeEnv->run('Turn on heating to reach target temperature of 24 degrees');

if ($result3->isSuccess()) {
    echo "Simulation: Adjusting Temperature\n";
    echo "==================================\n";
    echo $result3->getAnswer() . "\n\n";
}

// Example 4: Network Infrastructure
echo "\n--- Example 4: Network Load Balancing ---\n\n";

$networkEnv = new EnvironmentSimulatorAgent($client, [
    'name' => 'network_simulator',
    'initial_state' => [
        'load_balancers' => 2,
        'backend_servers' => 8,
        'traffic_distribution' => 'round_robin',
        'packet_loss_rate' => 0.001,
        'bandwidth_utilization' => 0.65,
    ],
]);

echo "Initial Network State:\n";
echo json_encode($networkEnv->getState(), JSON_PRETTY_PRINT) . "\n\n";

// Simulate changing load balancing strategy
$result4 = $networkEnv->run('Switch from round_robin to least_connections load balancing strategy');

if ($result4->isSuccess()) {
    echo "Simulation: Changing Load Balancing Strategy\n";
    echo "=============================================\n";
    echo $result4->getAnswer() . "\n\n";
}

// Example 5: State Management
echo "\n--- Example 5: Dynamic State Updates ---\n\n";

$dynamicEnv = new EnvironmentSimulatorAgent($client, [
    'name' => 'dynamic_simulator',
]);

// Start with empty state and update it
echo "Starting with empty state...\n";
echo "Current state: " . json_encode($dynamicEnv->getState()) . "\n\n";

// Set a new state
$newState = [
    'traffic_light' => 'red',
    'waiting_cars' => 12,
    'pedestrians_waiting' => 5,
    'timer_seconds' => 30,
];

$dynamicEnv->setState($newState);
echo "Updated state:\n";
echo json_encode($dynamicEnv->getState(), JSON_PRETTY_PRINT) . "\n\n";

// Simulate traffic light change
$result5 = $dynamicEnv->run('Change traffic light from red to green');

if ($result5->isSuccess()) {
    echo "Simulation: Traffic Light Change\n";
    echo "==================================\n";
    echo $result5->getAnswer() . "\n\n";
    
    // Note: The agent's state doesn't change - simulation is predictive only
    echo "Agent's actual state (unchanged by simulation):\n";
    echo json_encode($dynamicEnv->getState(), JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== Simulation Complete ===\n";

