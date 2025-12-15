#!/usr/bin/env php
<?php
/**
 * Advanced Environment Simulator Agent Example
 *
 * Demonstrates advanced features:
 * - Multiple sequential simulations
 * - Risk assessment and comparison
 * - Complex state management
 * - Decision-making based on simulation outcomes
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

echo "=== Advanced Environment Simulator Example ===\n\n";

// Scenario: Cloud Infrastructure Migration Planning
echo "SCENARIO: Cloud Infrastructure Migration\n";
echo "=========================================\n\n";

$cloudEnv = new EnvironmentSimulatorAgent($client, [
    'name' => 'cloud_migration_simulator',
    'initial_state' => [
        'infrastructure' => [
            'on_premise_servers' => 50,
            'cloud_servers' => 0,
            'hybrid_mode' => false,
        ],
        'costs' => [
            'monthly_on_premise' => 150000,
            'monthly_cloud' => 0,
        ],
        'performance' => [
            'uptime_percentage' => 99.5,
            'avg_response_time_ms' => 200,
            'scalability_score' => 6,
        ],
        'security' => [
            'compliance_met' => true,
            'encryption_at_rest' => true,
            'encryption_in_transit' => false,
        ],
    ],
]);

echo "Initial State:\n";
echo json_encode($cloudEnv->getState(), JSON_PRETTY_PRINT) . "\n\n";

// Simulation 1: Full Migration
echo "--- Simulation 1: Full Cloud Migration ---\n\n";

$simulation1 = $cloudEnv->simulateAction(
    'Migrate all 50 on-premise servers to cloud infrastructure in one phase'
);

echo "Action: {$simulation1['action']}\n";
echo "Outcome: {$simulation1['outcome']}\n";
echo "Success Probability: " . ($simulation1['success_probability'] * 100) . "%\n";

if (!empty($simulation1['side_effects'])) {
    echo "\nIdentified Risks:\n";
    foreach ($simulation1['side_effects'] as $i => $effect) {
        echo "  " . ($i + 1) . ". {$effect}\n";
    }
}
echo "\n" . str_repeat('-', 60) . "\n\n";

// Simulation 2: Phased Migration
echo "--- Simulation 2: Phased Cloud Migration ---\n\n";

$simulation2 = $cloudEnv->simulateAction(
    'Migrate servers in 5 phases of 10 servers each over 6 months, starting with non-critical systems'
);

echo "Action: {$simulation2['action']}\n";
echo "Outcome: {$simulation2['outcome']}\n";
echo "Success Probability: " . ($simulation2['success_probability'] * 100) . "%\n";

if (!empty($simulation2['side_effects'])) {
    echo "\nIdentified Risks:\n";
    foreach ($simulation2['side_effects'] as $i => $effect) {
        echo "  " . ($i + 1) . ". {$effect}\n";
    }
}
echo "\n" . str_repeat('-', 60) . "\n\n";

// Simulation 3: Hybrid Approach
echo "--- Simulation 3: Hybrid Cloud Strategy ---\n\n";

$simulation3 = $cloudEnv->simulateAction(
    'Keep 20 critical servers on-premise, migrate 30 servers to cloud, maintain hybrid setup'
);

echo "Action: {$simulation3['action']}\n";
echo "Outcome: {$simulation3['outcome']}\n";
echo "Success Probability: " . ($simulation3['success_probability'] * 100) . "%\n";

if (!empty($simulation3['side_effects'])) {
    echo "\nIdentified Risks:\n";
    foreach ($simulation3['side_effects'] as $i => $effect) {
        echo "  " . ($i + 1) . ". {$effect}\n";
    }
}
echo "\n" . str_repeat('-', 60) . "\n\n";

// Compare simulations
echo "=== COMPARISON & RECOMMENDATION ===\n\n";

$simulations = [
    'Full Migration' => $simulation1,
    'Phased Migration' => $simulation2,
    'Hybrid Strategy' => $simulation3,
];

// Sort by success probability
uasort($simulations, function ($a, $b) {
    return $b['success_probability'] <=> $a['success_probability'];
});

echo "Ranked by Success Probability:\n";
foreach ($simulations as $name => $sim) {
    $probability = $sim['success_probability'] * 100;
    $riskCount = count($sim['side_effects']);
    echo sprintf(
        "  %s: %.1f%% success, %d identified risks\n",
        $name,
        $probability,
        $riskCount
    );
}

$recommended = array_key_first($simulations);
echo "\n✓ RECOMMENDED APPROACH: {$recommended}\n";
echo "  Success Probability: " . ($simulations[$recommended]['success_probability'] * 100) . "%\n";
echo "  Outcome: {$simulations[$recommended]['outcome']}\n\n";

// Advanced Example: Testing Disaster Recovery
echo "\n=== DISASTER RECOVERY SIMULATION ===\n\n";

$drEnv = new EnvironmentSimulatorAgent($client, [
    'name' => 'disaster_recovery_simulator',
    'initial_state' => [
        'primary_datacenter' => [
            'status' => 'operational',
            'servers' => 100,
            'active_users' => 50000,
        ],
        'backup_datacenter' => [
            'status' => 'standby',
            'servers' => 100,
            'active_users' => 0,
        ],
        'replication' => [
            'enabled' => true,
            'lag_seconds' => 5,
            'sync_percentage' => 99.9,
        ],
    ],
]);

echo "Testing failover scenarios...\n\n";

// Test 1: Primary datacenter failure
echo "--- Test: Primary Datacenter Failure ---\n";
$drTest1 = $drEnv->simulateAction(
    'Primary datacenter experiences complete power failure. Initiate failover to backup datacenter.'
);

echo $drTest1['description'] . "\n\n";

// Test 2: Network partition
echo "--- Test: Network Partition Between Datacenters ---\n";
$drTest2 = $drEnv->simulateAction(
    'Network connectivity between primary and backup datacenters is lost. Continue operations.'
);

echo $drTest2['description'] . "\n\n";

// Advanced Example: Performance Optimization Testing
echo "\n=== PERFORMANCE OPTIMIZATION SIMULATION ===\n\n";

$perfEnv = new EnvironmentSimulatorAgent($client, [
    'name' => 'performance_simulator',
    'initial_state' => [
        'application' => [
            'response_time_p50' => 150,
            'response_time_p95' => 450,
            'response_time_p99' => 800,
            'throughput_rps' => 1000,
        ],
        'database' => [
            'query_time_avg' => 100,
            'connection_pool' => 50,
            'cache_hit_rate' => 0.70,
        ],
        'infrastructure' => [
            'cpu_usage' => 65,
            'memory_usage' => 70,
            'network_bandwidth_utilization' => 0.45,
        ],
    ],
]);

echo "Current Performance Baseline:\n";
echo json_encode($perfEnv->getState()['application'], JSON_PRETTY_PRINT) . "\n\n";

// Test multiple optimization strategies
$optimizations = [
    'Add Redis caching layer for frequently accessed data',
    'Increase database connection pool from 50 to 200',
    'Enable HTTP/2 and implement connection pooling',
    'Add 5 more application servers and load balance',
    'Optimize database queries and add missing indexes',
];

echo "Testing Optimization Strategies:\n";
echo str_repeat('=', 60) . "\n\n";

$results = [];
foreach ($optimizations as $i => $optimization) {
    echo ($i + 1) . ". Testing: {$optimization}\n";
    
    $sim = $perfEnv->simulateAction($optimization);
    $results[] = [
        'strategy' => $optimization,
        'success_probability' => $sim['success_probability'],
        'outcome' => $sim['outcome'],
        'side_effects' => $sim['side_effects'],
    ];
    
    echo "   Success Probability: " . ($sim['success_probability'] * 100) . "%\n";
    echo "   Outcome: {$sim['outcome']}\n";
    
    if (!empty($sim['side_effects'])) {
        echo "   Trade-offs: " . implode(', ', $sim['side_effects']) . "\n";
    }
    echo "\n";
}

// Find best optimization
usort($results, function ($a, $b) {
    return $b['success_probability'] <=> $a['success_probability'];
});

echo str_repeat('=', 60) . "\n";
echo "RECOMMENDED OPTIMIZATION:\n";
echo "  Strategy: {$results[0]['strategy']}\n";
echo "  Success Probability: " . ($results[0]['success_probability'] * 100) . "%\n";
echo "  Expected Outcome: {$results[0]['outcome']}\n";

if (!empty($results[0]['side_effects'])) {
    echo "  Consider: " . implode(', ', $results[0]['side_effects']) . "\n";
}

echo "\n=== Advanced Simulation Complete ===\n";

