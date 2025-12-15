# EnvironmentSimulatorAgent Tutorial

## Introduction

Welcome to the EnvironmentSimulatorAgent tutorial! In this guide, you'll learn how to use AI-powered environment simulation to predict outcomes, assess risks, and make better decisions before taking action in real systems.

### What You'll Learn

1. Creating basic environment simulators
2. Simulating actions and predicting outcomes
3. Managing environment state
4. Assessing risks and side effects
5. Comparing multiple strategies
6. Advanced simulation patterns

### Prerequisites

- PHP 8.1 or higher
- Composer installed
- Anthropic API key (for live examples)
- Basic understanding of PHP and the Claude API

### Time Required

Approximately 40-50 minutes

---

## Chapter 1: Your First Environment Simulation

Let's start by simulating a simple web server scaling operation.

### Step 1: Set Up Your Environment

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\EnvironmentSimulatorAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
```

### Step 2: Create Your First Simulator

```php
$simulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'web_server_simulator',
    'initial_state' => [
        'servers' => 3,
        'cpu_usage_percent' => 75,
        'memory_usage_percent' => 60,
        'requests_per_second' => 500,
    ],
]);

echo "Simulator created: {$simulator->getName()}\n";
echo "Initial State:\n";
echo json_encode($simulator->getState(), JSON_PRETTY_PRINT) . "\n";
```

### Step 3: Run Your First Simulation

```php
$result = $simulator->run('Scale up from 3 servers to 5 servers');

if ($result->isSuccess()) {
    echo "\n✓ Simulation completed successfully!\n\n";
    echo $result->getAnswer() . "\n";
} else {
    echo "\n✗ Simulation failed: {$result->getError()}\n";
}
```

### Step 4: Examine the Results

```php
// Get detailed metadata
$metadata = $result->getMetadata();

echo "\nDetailed Results:\n";
echo "=================\n";
echo "Action: {$metadata['action']}\n";
echo "Outcome: {$metadata['outcome']}\n\n";

echo "Initial State:\n";
echo json_encode($metadata['initial_state'], JSON_PRETTY_PRINT) . "\n\n";

echo "Predicted State After Action:\n";
echo json_encode($metadata['resulting_state'], JSON_PRETTY_PRINT) . "\n";
```

### What Just Happened?

1. **Simulator Created**: We created an agent configured to simulate a web server environment
2. **State Defined**: We specified the current state of our infrastructure
3. **Action Simulated**: The AI predicted what would happen if we scaled to 5 servers
4. **Results Returned**: We got predicted outcomes, state changes, and potential risks

### Try It Yourself

Modify the simulation to test different scenarios:

```php
// Scenario 1: Scaling down
$result = $simulator->run('Reduce from 3 servers to 1 server');

// Scenario 2: Configuration change
$result = $simulator->run('Enable caching layer with Redis');

// Scenario 3: Load increase
$result = $simulator->run('Handle sudden traffic spike to 2000 requests per second');
```

---

## Chapter 2: Understanding Simulation Results

Simulations provide detailed information to help you make informed decisions.

### Step 1: Access Full Simulation Details

```php
$simulation = $simulator->simulateAction(
    'Increase CPU allocation by 50% on all servers'
);

// Every simulation returns this structure:
print_r($simulation);
/*
Array (
    [initial_state] => Array (...)
    [action] => 'Increase CPU allocation...'
    [resulting_state] => Array (...)
    [outcome] => 'Description of what happens'
    [side_effects] => Array (...)
    [success_probability] => 0.85
    [description] => 'Formatted output'
)
*/
```

### Step 2: Evaluate Success Probability

```php
$probability = $simulation['success_probability'];

echo "Success Probability: " . ($probability * 100) . "%\n\n";

if ($probability >= 0.9) {
    echo "✓ HIGH CONFIDENCE - Safe to proceed\n";
} elseif ($probability >= 0.7) {
    echo "⚠ MODERATE RISK - Review carefully\n";
} elseif ($probability >= 0.5) {
    echo "⚠ HIGH RISK - Consider alternatives\n";
} else {
    echo "✗ VERY HIGH RISK - Do not proceed\n";
}
```

### Step 3: Review Side Effects and Risks

```php
if (!empty($simulation['side_effects'])) {
    echo "\nIdentified Side Effects:\n";
    foreach ($simulation['side_effects'] as $i => $effect) {
        echo "  " . ($i + 1) . ". {$effect}\n";
    }
    
    // Check for critical risks
    $criticalKeywords = ['data loss', 'downtime', 'security', 'corruption'];
    
    foreach ($simulation['side_effects'] as $effect) {
        foreach ($criticalKeywords as $keyword) {
            if (stripos($effect, $keyword) !== false) {
                echo "\n🚨 CRITICAL RISK DETECTED: {$effect}\n";
            }
        }
    }
} else {
    echo "\n✓ No significant side effects identified\n";
}
```

### Step 4: Compare States

```php
echo "\nState Comparison:\n";
echo "=================\n\n";

echo "BEFORE:\n";
foreach ($simulation['initial_state'] as $key => $value) {
    echo "  {$key}: {$value}\n";
}

echo "\nAFTER:\n";
foreach ($simulation['resulting_state'] as $key => $value) {
    $before = $simulation['initial_state'][$key] ?? 'N/A';
    $change = '';
    
    if (is_numeric($value) && is_numeric($before)) {
        $diff = $value - $before;
        $change = $diff > 0 ? " (+{$diff})" : " ({$diff})";
    }
    
    echo "  {$key}: {$value}{$change}\n";
}
```

### Understanding the Output

Each field in the simulation result serves a specific purpose:

- **initial_state**: Baseline for comparison
- **resulting_state**: Predicted outcome
- **outcome**: Natural language description
- **side_effects**: Unintended consequences
- **success_probability**: Quantified risk (0.0 to 1.0)
- **description**: Formatted summary for display

---

## Chapter 3: State Management

Learn how to manage and update environment state effectively.

### Step 1: Query Current State

```php
$currentState = $simulator->getState();
echo "Current Environment:\n";
echo json_encode($currentState, JSON_PRETTY_PRINT) . "\n";
```

### Step 2: Update State

```php
// Scenario: Your infrastructure has changed
$newState = [
    'servers' => 5,  // We actually scaled to 5
    'cpu_usage_percent' => 50,  // Usage decreased
    'memory_usage_percent' => 55,
    'requests_per_second' => 750,  // Traffic increased
    'cache_enabled' => true,  // New feature added
];

$simulator->setState($newState);
echo "State updated successfully\n";
```

### Step 3: Simulate from New State

```php
// Now simulate based on the updated state
$result = $simulator->run('Add load balancer to distribute traffic');

echo "Simulation from new state:\n";
echo $result->getAnswer() . "\n";
```

### Step 4: State Immutability

Important: Simulations don't change state automatically!

```php
$beforeState = $simulator->getState();
echo "State before simulation:\n";
print_r($beforeState);

// Run simulation
$simulation = $simulator->simulateAction('Double the servers');

$afterState = $simulator->getState();
echo "\nState after simulation:\n";
print_r($afterState);

// They're the same!
assert($beforeState === $afterState);
echo "✓ State is immutable - simulations don't modify it\n";

// You must explicitly update state if desired
$simulator->setState($simulation['resulting_state']);
echo "✓ Now state is updated\n";
```

### Best Practices for State Management

```php
// ✅ Good: Keep state focused and relevant
$state = [
    'servers' => 10,
    'load' => 'medium',
    'status' => 'healthy',
];

// ❌ Avoid: Overly complex or irrelevant state
$state = [
    'servers' => 10,
    'server_1_cpu_core_1_temp' => 45.3,
    'server_1_cpu_core_2_temp' => 46.1,
    // ... hundreds of detailed metrics
];

// ✅ Good: Structured state for complex environments
$state = [
    'infrastructure' => [
        'servers' => 10,
        'load_balancers' => 2,
    ],
    'performance' => [
        'response_time_ms' => 120,
        'throughput_rps' => 1000,
    ],
    'health' => [
        'status' => 'green',
        'error_rate' => 0.001,
    ],
];
```

---

## Chapter 4: Comparing Multiple Strategies

Use simulations to evaluate and compare different approaches.

### Step 1: Define Multiple Strategies

```php
$simulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => [
        'database_connections' => 50,
        'query_time_avg_ms' => 200,
        'cache_hit_rate' => 0.60,
        'queries_per_second' => 500,
    ],
]);

$strategies = [
    'vertical_scaling' => 'Double database server resources (CPU and RAM)',
    'horizontal_scaling' => 'Add 3 read replicas and route read queries to them',
    'caching' => 'Implement Redis caching layer for frequently accessed data',
    'optimization' => 'Optimize slow queries and add database indexes',
    'connection_pooling' => 'Increase connection pool from 50 to 200 connections',
];
```

### Step 2: Simulate Each Strategy

```php
$results = [];

echo "Simulating strategies...\n\n";

foreach ($strategies as $name => $action) {
    echo "Testing: {$name}\n";
    
    $simulation = $simulator->simulateAction($action);
    $results[$name] = $simulation;
    
    echo "  Success: " . ($simulation['success_probability'] * 100) . "%\n";
    echo "  Outcome: {$simulation['outcome']}\n";
    
    if (!empty($simulation['side_effects'])) {
        echo "  Risks: " . count($simulation['side_effects']) . "\n";
    }
    
    echo "\n";
}
```

### Step 3: Rank and Compare

```php
// Sort by success probability
uasort($results, function ($a, $b) {
    return $b['success_probability'] <=> $a['success_probability'];
});

echo "RANKING BY SUCCESS PROBABILITY:\n";
echo "================================\n\n";

$rank = 1;
foreach ($results as $name => $sim) {
    echo "{$rank}. " . strtoupper(str_replace('_', ' ', $name)) . "\n";
    echo "   Success: " . ($sim['success_probability'] * 100) . "%\n";
    echo "   Outcome: {$sim['outcome']}\n";
    
    if (!empty($sim['side_effects'])) {
        echo "   Side Effects:\n";
        foreach ($sim['side_effects'] as $effect) {
            echo "     - {$effect}\n";
        }
    }
    
    echo "\n";
    $rank++;
}
```

### Step 4: Make Informed Decision

```php
$recommended = array_key_first($results);
$recommendedSim = $results[$recommended];

echo "RECOMMENDATION:\n";
echo "===============\n\n";
echo "Strategy: " . strtoupper(str_replace('_', ' ', $recommended)) . "\n";
echo "Confidence: " . ($recommendedSim['success_probability'] * 100) . "%\n";
echo "Expected Outcome: {$recommendedSim['outcome']}\n\n";

// Consider trade-offs
if ($recommendedSim['success_probability'] < 0.8) {
    echo "⚠️  Note: Even the best option has moderate risk\n";
    echo "Consider combining strategies or implementing safeguards\n\n";
}

// Show runner-up
$strategies_array = array_keys($results);
if (count($strategies_array) > 1) {
    $runnerUp = $strategies_array[1];
    echo "Alternative: " . strtoupper(str_replace('_', ' ', $runnerUp)) . "\n";
    echo "Success: " . ($results[$runnerUp]['success_probability'] * 100) . "%\n";
}
```

---

## Chapter 5: Risk Assessment and Decision Making

Learn to use simulations for comprehensive risk assessment.

### Step 1: Simulate Risky Operations

```php
$dbSimulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'database_migration_simulator',
    'initial_state' => [
        'database' => 'mysql_5.7',
        'data_size_gb' => 500,
        'downtime_tolerance_minutes' => 10,
        'backup_age_hours' => 2,
        'replication_enabled' => true,
    ],
]);

// Simulate major version upgrade
$migration = $dbSimulator->simulateAction(
    'Upgrade from MySQL 5.7 to MySQL 8.0 in-place during production'
);
```

### Step 2: Assess Risk Factors

```php
echo "RISK ASSESSMENT\n";
echo "===============\n\n";

// Success probability
$probability = $migration['success_probability'];
$riskLevel = 'UNKNOWN';

if ($probability >= 0.9) {
    $riskLevel = 'LOW';
    $color = '🟢';
} elseif ($probability >= 0.7) {
    $riskLevel = 'MODERATE';
    $color = '🟡';
} elseif ($probability >= 0.5) {
    $riskLevel = 'HIGH';
    $color = '🟠';
} else {
    $riskLevel = 'CRITICAL';
    $color = '🔴';
}

echo "{$color} Risk Level: {$riskLevel}\n";
echo "Success Probability: " . ($probability * 100) . "%\n";
echo "Failure Probability: " . ((1 - $probability) * 100) . "%\n\n";

// Side effects analysis
echo "Identified Risks:\n";
foreach ($migration['side_effects'] as $i => $effect) {
    echo "  " . ($i + 1) . ". {$effect}\n";
}
```

### Step 3: Implement Risk Mitigation

```php
// If risk is too high, simulate safer alternatives
if ($probability < 0.8) {
    echo "\n⚠️  Risk is too high. Testing safer alternatives...\n\n";
    
    $alternatives = [
        'Create replica, upgrade replica, verify, then failover',
        'Dump data, provision new MySQL 8.0 instance, import data',
        'Use database migration tool with rollback capability',
    ];
    
    foreach ($alternatives as $alt) {
        echo "Alternative: {$alt}\n";
        $altSim = $dbSimulator->simulateAction($alt);
        echo "  Success: " . ($altSim['success_probability'] * 100) . "%\n";
        echo "  Outcome: {$altSim['outcome']}\n\n";
    }
}
```

### Step 4: Create Risk Matrix

```php
function getRiskCategory($probability, $sideEffectCount) {
    if ($probability >= 0.9 && $sideEffectCount <= 2) {
        return 'ACCEPTABLE';
    } elseif ($probability >= 0.7 && $sideEffectCount <= 5) {
        return 'MANAGEABLE';
    } elseif ($probability >= 0.5) {
        return 'HIGH';
    } else {
        return 'UNACCEPTABLE';
    }
}

$category = getRiskCategory(
    $migration['success_probability'],
    count($migration['side_effects'])
);

echo "Risk Category: {$category}\n\n";

switch ($category) {
    case 'ACCEPTABLE':
        echo "✓ Proceed with standard approval\n";
        break;
    case 'MANAGEABLE':
        echo "⚠ Proceed with additional safeguards and monitoring\n";
        break;
    case 'HIGH':
        echo "⚠ Requires senior approval and detailed mitigation plan\n";
        break;
    case 'UNACCEPTABLE':
        echo "✗ Do not proceed - find alternative approach\n";
        break;
}
```

---

## Chapter 6: Sequential Simulations

Simulate a sequence of related actions to plan complex operations.

### Step 1: Define a Multi-Step Plan

```php
$deploySimulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'deployment_simulator',
    'initial_state' => [
        'version' => 'v1.0',
        'servers' => 10,
        'traffic_routing' => 'production',
        'canary_enabled' => false,
    ],
]);

$deploymentSteps = [
    'Deploy v2.0 to 2 canary servers (20% of fleet)',
    'Route 5% of traffic to canary servers',
    'Monitor for 30 minutes, check error rates',
    'If stable, increase traffic to canary to 25%',
    'Deploy v2.0 to remaining 8 servers',
    'Route 100% traffic to v2.0',
];
```

### Step 2: Simulate Each Step

```php
$currentState = $deploySimulator->getState();
$stepResults = [];
$overallSuccess = true;

foreach ($deploymentSteps as $i => $step) {
    $stepNum = $i + 1;
    echo "Step {$stepNum}: {$step}\n";
    
    $simulation = $deploySimulator->simulateAction($step);
    $stepResults[] = $simulation;
    
    echo "  Success probability: " . ($simulation['success_probability'] * 100) . "%\n";
    
    // Check if we should proceed
    if ($simulation['success_probability'] < 0.8) {
        echo "  ⚠️  WARNING: Lower success probability\n";
    }
    
    if ($simulation['success_probability'] < 0.6) {
        echo "  🛑 STOP: Risk too high at this step\n";
        $overallSuccess = false;
        break;
    }
    
    // Update state for next simulation
    $deploySimulator->setState($simulation['resulting_state']);
    echo "  ✓ State updated for next step\n\n";
}

// Restore original state
$deploySimulator->setState($currentState);
```

### Step 3: Analyze the Plan

```php
if ($overallSuccess) {
    echo "\n✓ PLAN IS VIABLE\n";
    echo "All steps have acceptable success probability\n\n";
    
    $totalProbability = 1.0;
    foreach ($stepResults as $result) {
        $totalProbability *= $result['success_probability'];
    }
    
    echo "Compound Success Probability: " . ($totalProbability * 100) . "%\n";
    echo "(Probability of all steps succeeding)\n";
} else {
    echo "\n✗ PLAN NEEDS REVISION\n";
    echo "One or more steps have unacceptable risk\n";
}
```

### Step 4: Identify Critical Steps

```php
echo "\nCritical Steps (lowest success probability):\n";

// Sort by probability
$sortedSteps = $stepResults;
usort($sortedSteps, function ($a, $b) {
    return $a['success_probability'] <=> $b['success_probability'];
});

for ($i = 0; $i < min(3, count($sortedSteps)); $i++) {
    $step = $sortedSteps[$i];
    echo "  • {$step['action']}\n";
    echo "    Probability: " . ($step['success_probability'] * 100) . "%\n";
    
    if (!empty($step['side_effects'])) {
        echo "    Risks: " . implode(', ', $step['side_effects']) . "\n";
    }
    echo "\n";
}
```

---

## Chapter 7: Domain-Specific Simulations

Apply simulations to different domains and use cases.

### Example 1: IoT / Smart Home

```php
$homeSimulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'smart_home',
    'initial_state' => [
        'temperature_c' => 20,
        'humidity_percent' => 55,
        'hvac_mode' => 'auto',
        'target_temp_c' => 22,
        'occupancy' => true,
        'time_of_day' => '14:00',
    ],
]);

$result = $homeSimulator->run(
    'Increase target temperature to 24°C and switch HVAC to heating mode'
);

echo "Smart Home Simulation:\n";
echo $result->getAnswer() . "\n";
```

### Example 2: Network Infrastructure

```php
$networkSimulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'network_infrastructure',
    'initial_state' => [
        'routers' => 4,
        'switches' => 12,
        'vlans' => 8,
        'bandwidth_utilization' => 0.65,
        'routing_protocol' => 'OSPF',
    ],
]);

$result = $networkSimulator->run(
    'Add redundant router and configure VRRP for high availability'
);

echo "Network Simulation:\n";
echo $result->getAnswer() . "\n";
```

### Example 3: Cloud Cost Optimization

```php
$costSimulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'cloud_cost_optimizer',
    'initial_state' => [
        'monthly_cost_usd' => 15000,
        'compute_instances' => 50,
        'storage_tb' => 100,
        'data_transfer_tb_month' => 50,
        'reserved_instances' => 0,
    ],
]);

$result = $costSimulator->run(
    'Convert 30 instances to 3-year reserved instances and implement S3 lifecycle policies'
);

echo "Cost Optimization Simulation:\n";
echo $result->getAnswer() . "\n";
```

### Example 4: Security Configuration

```php
$securitySimulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'security_config',
    'initial_state' => [
        'firewall_rules' => 25,
        'open_ports' => [80, 443, 22, 3306],
        'encryption' => 'TLS 1.2',
        'authentication' => 'password',
        'mfa_enabled' => false,
    ],
]);

$result = $securitySimulator->run(
    'Close port 3306, enable MFA, upgrade to TLS 1.3, and implement certificate-based auth'
);

echo "Security Enhancement Simulation:\n";
echo $result->getAnswer() . "\n";
```

---

## Chapter 8: Advanced Patterns

### Pattern 1: Disaster Recovery Planning

```php
$drSimulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => [
        'primary_dc' => 'operational',
        'secondary_dc' => 'standby',
        'replication_lag_sec' => 5,
        'rto_minutes' => 15,  // Recovery Time Objective
        'rpo_minutes' => 5,   // Recovery Point Objective
    ],
]);

$disasters = [
    'Primary datacenter loses all power',
    'Ransomware attack encrypts primary storage',
    'Network link between datacenters fails',
    'Database corruption in primary',
];

foreach ($disasters as $disaster) {
    echo "\nDisaster: {$disaster}\n";
    
    $sim = $drSimulator->simulateAction(
        "Disaster scenario: {$disaster}. Execute disaster recovery plan."
    );
    
    echo "Recovery Success: " . ($sim['success_probability'] * 100) . "%\n";
    echo "Outcome: {$sim['outcome']}\n";
    
    // Check if RTO/RPO can be met
    if (stripos($sim['outcome'], 'RTO') !== false || 
        stripos($sim['outcome'], 'recovery time') !== false) {
        echo "Check: Review recovery time against 15-minute RTO\n";
    }
}
```

### Pattern 2: A/B Testing Simulation

```php
$abTestSimulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => [
        'variant_a' => 'current_algorithm',
        'variant_b' => 'new_algorithm',
        'traffic_split' => '50/50',
        'conversion_rate_a' => 0.05,
        'performance_a_ms' => 150,
    ],
]);

$sim = $abTestSimulator->simulateAction(
    'Deploy variant B to 50% of users and measure conversion rate and performance'
);

echo "A/B Test Simulation:\n";
echo $sim['description'] . "\n";
```

### Pattern 3: Capacity Planning

```php
$capacitySimulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => [
        'current_users' => 10000,
        'current_infrastructure' => 'described...',
        'growth_rate_monthly' => 0.15,  // 15% per month
    ],
]);

// Simulate 6 months of growth
for ($month = 1; $month <= 6; $month++) {
    $expectedUsers = 10000 * pow(1.15, $month);
    
    $sim = $capacitySimulator->simulateAction(
        "Handle {$expectedUsers} concurrent users (month {$month})"
    );
    
    echo "Month {$month}: {$expectedUsers} users\n";
    echo "  Capacity: " . ($sim['success_probability'] >= 0.9 ? 'Sufficient' : 'Insufficient') . "\n";
    
    if ($sim['success_probability'] < 0.9) {
        echo "  Action needed: Scale infrastructure\n";
    }
}
```

### Pattern 4: What-If Analysis

```php
// Test various scenarios from same baseline
$whatIfSimulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => $baselineState,
]);

$scenarios = [
    'Best case: Traffic increases 50%, infrastructure performs optimally',
    'Expected case: Traffic increases 20%, normal performance',
    'Worst case: Traffic increases 200%, hardware failure on 2 servers',
];

foreach ($scenarios as $scenario) {
    $sim = $whatIfSimulator->simulateAction($scenario);
    echo "\nScenario: {$scenario}\n";
    echo "Result: {$sim['outcome']}\n";
    echo "Success: " . ($sim['success_probability'] * 100) . "%\n";
}
```

---

## Chapter 9: Best Practices

### 1. Define Clear, Comprehensive State

```php
// ✅ Excellent: Well-structured with context
$state = [
    'infrastructure' => [
        'compute' => ['instances' => 10, 'type' => 't3.large'],
        'storage' => ['type' => 'ssd', 'size_gb' => 500],
        'network' => ['bandwidth_gbps' => 10],
    ],
    'performance' => [
        'response_time_p95_ms' => 200,
        'throughput_rps' => 1000,
        'error_rate' => 0.001,
    ],
    'costs' => [
        'monthly_usd' => 5000,
        'compute_pct' => 60,
        'storage_pct' => 30,
    ],
];
```

### 2. Write Specific Action Descriptions

```php
// ✅ Good: Specific and actionable
$simulator->run(
    'Migrate the user authentication service from PostgreSQL to Redis, ' .
    'maintaining data consistency and zero downtime using blue-green deployment'
);

// ❌ Poor: Too vague
$simulator->run('improve database');
```

### 3. Always Check Side Effects

```php
$simulation = $simulator->simulateAction($action);

// Create an approval workflow based on risk
if (empty($simulation['side_effects']) && $simulation['success_probability'] >= 0.95) {
    // Auto-approve low-risk changes
    proceedWithAction($action);
} elseif ($simulation['success_probability'] >= 0.80) {
    // Require peer review
    requestPeerReview($action, $simulation);
} else {
    // Require senior approval
    escalateToSeniorEngineer($action, $simulation);
}
```

### 4. Use Logging and Audit Trails

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('simulations');
$logger->pushHandler(new StreamHandler('/var/log/simulations.log'));

$simulator = new EnvironmentSimulatorAgent($client, [
    'logger' => $logger,
]);

// All simulations are now logged
$result = $simulator->run($action);

// Log decisions
$logger->info('Simulation completed', [
    'action' => $action,
    'success_probability' => $result->getMetadata()['outcome'],
    'decision' => 'approved',
]);
```

### 5. Combine with Real Monitoring

```php
// Before making a change
$beforeState = getCurrentSystemMetrics();
$simulator->setState($beforeState);

$simulation = $simulator->simulateAction($proposedChange);

if ($simulation['success_probability'] >= 0.8) {
    // Execute the change
    executeChange($proposedChange);
    
    // Wait and measure actual results
    sleep(300); // 5 minutes
    $afterState = getCurrentSystemMetrics();
    
    // Compare prediction vs reality
    compareResults($simulation['resulting_state'], $afterState);
}
```

---

## Chapter 10: Real-World Example

Let's put it all together with a complete real-world scenario.

### Scenario: Production Database Migration

```php
<?php
// Complete example: Plan and validate a database migration

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\EnvironmentSimulatorAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

echo "=== PRODUCTION DATABASE MIGRATION PLAN ===\n\n";

// Step 1: Define current state
$currentState = [
    'database' => [
        'engine' => 'PostgreSQL 12',
        'size_gb' => 750,
        'tables' => 250,
        'indexes' => 380,
    ],
    'performance' => [
        'queries_per_second' => 5000,
        'avg_query_time_ms' => 50,
        'peak_connections' => 200,
    ],
    'availability' => [
        'uptime_requirement' => 0.9999,
        'max_downtime_minutes' => 5,
        'replication' => 'streaming',
        'replicas' => 2,
    ],
    'application' => [
        'services_dependent' => 12,
        'api_calls_per_minute' => 50000,
    ],
];

$migrationSimulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'production_db_migration',
    'initial_state' => $currentState,
]);

// Step 2: Evaluate migration strategies
echo "EVALUATING MIGRATION STRATEGIES\n";
echo str_repeat('=', 60) . "\n\n";

$strategies = [
    'blue_green' => 
        'Set up new PostgreSQL 14 instance, replicate data in real-time, ' .
        'test thoroughly, then switch traffic with instant rollback capability',
    
    'in_place' => 
        'Stop application, upgrade PostgreSQL 12 to 14 in-place using pg_upgrade, ' .
        'restart application',
    
    'logical_replication' => 
        'Use logical replication to sync data to new instance while ' .
        'old instance remains online, then cutover during maintenance window',
    
    'dump_restore' => 
        'Take database dump, provision new PostgreSQL 14, restore data, ' .
        'switch applications after validation',
];

$strategyResults = [];

foreach ($strategies as $name => $description) {
    echo "Strategy: " . strtoupper(str_replace('_', ' ', $name)) . "\n";
    
    $sim = $migrationSimulator->simulateAction(
        "Migration strategy: {$description}"
    );
    
    $strategyResults[$name] = $sim;
    
    echo "  Success: " . ($sim['success_probability'] * 100) . "%\n";
    echo "  Outcome: {$sim['outcome']}\n";
    
    if (!empty($sim['side_effects'])) {
        echo "  Risks (" . count($sim['side_effects']) . "):\n";
        foreach ($sim['side_effects'] as $i => $effect) {
            echo "    " . ($i + 1) . ". {$effect}\n";
        }
    }
    
    echo "\n";
}

// Step 3: Recommend best strategy
echo str_repeat('=', 60) . "\n";
echo "RECOMMENDATION\n";
echo str_repeat('=', 60) . "\n\n";

uasort($strategyResults, function ($a, $b) {
    return $b['success_probability'] <=> $a['success_probability'];
});

$recommended = array_key_first($strategyResults);
$recommendedSim = $strategyResults[$recommended];

echo "✓ RECOMMENDED: " . strtoupper(str_replace('_', ' ', $recommended)) . "\n\n";
echo "Success Probability: " . ($recommendedSim['success_probability'] * 100) . "%\n";
echo "Expected Outcome:\n{$recommendedSim['outcome']}\n\n";

if (!empty($recommendedSim['side_effects'])) {
    echo "Risk Mitigation Required:\n";
    foreach ($recommendedSim['side_effects'] as $i => $effect) {
        echo "  " . ($i + 1) . ". {$effect}\n";
    }
    echo "\n";
}

// Step 4: Simulate the recommended plan step-by-step
echo str_repeat('=', 60) . "\n";
echo "DETAILED EXECUTION PLAN\n";
echo str_repeat('=', 60) . "\n\n";

// Simulate with the recommended strategy
$migrationSimulator->setState($currentState);

$steps = [
    'Provision new PostgreSQL 14 instance with identical specifications',
    'Configure streaming replication from PostgreSQL 12 to 14',
    'Wait for full data sync and monitor replication lag',
    'Run comprehensive tests on PostgreSQL 14 instance',
    'During low-traffic window, pause writes for 30 seconds',
    'Verify replication is fully caught up (lag = 0)',
    'Update application connection strings to point to new instance',
    'Resume normal operations and monitor for issues',
    'Keep old instance running for 24 hours as fallback',
];

$planResults = [];
$planViable = true;

foreach ($steps as $i => $step) {
    $stepNum = $i + 1;
    echo "Step {$stepNum}: {$step}\n";
    
    $stepSim = $migrationSimulator->simulateAction($step);
    $planResults[] = $stepSim;
    
    echo "  Success: " . ($stepSim['success_probability'] * 100) . "%\n";
    
    if ($stepSim['success_probability'] < 0.7) {
        echo "  ⚠️  WARNING: Elevated risk at this step\n";
        $planViable = false;
    }
    
    $migrationSimulator->setState($stepSim['resulting_state']);
    echo "\n";
}

// Step 5: Final decision
echo str_repeat('=', 60) . "\n";
echo "FINAL DECISION\n";
echo str_repeat('=', 60) . "\n\n";

if ($planViable) {
    echo "✅ PLAN APPROVED\n\n";
    echo "The migration plan has acceptable risk levels across all steps.\n";
    
    // Calculate compound probability
    $compoundProbability = 1.0;
    foreach ($planResults as $result) {
        $compoundProbability *= $result['success_probability'];
    }
    
    echo "Overall Success Probability: " . ($compoundProbability * 100) . "%\n";
    echo "\nNext Steps:\n";
    echo "1. Schedule migration for next maintenance window\n";
    echo "2. Prepare rollback procedures\n";
    echo "3. Brief all stakeholders\n";
    echo "4. Set up monitoring and alerts\n";
} else {
    echo "⚠️  PLAN NEEDS REVISION\n\n";
    echo "Some steps have elevated risk. Consider:\n";
    echo "1. Additional testing and validation steps\n";
    echo "2. Longer testing periods\n";
    echo "3. Smaller batch sizes\n";
    echo "4. Alternative migration strategies\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";
```

---

## Conclusion

Congratulations! You've learned how to use the EnvironmentSimulatorAgent to:

1. ✅ Create simulators for various environments
2. ✅ Predict outcomes and assess risks
3. ✅ Compare multiple strategies
4. ✅ Make data-driven decisions
5. ✅ Plan complex operations safely
6. ✅ Apply simulations to real-world scenarios

### Key Takeaways

- **Simulate Before Acting**: Always test changes in simulation first
- **Evaluate Risks**: Pay attention to success probability and side effects
- **Compare Options**: Simulate multiple strategies to find the best approach
- **Plan Thoroughly**: Use sequential simulations for complex operations
- **Monitor Reality**: Compare simulated outcomes with actual results

### Next Steps

- Explore the [EnvironmentSimulatorAgent Documentation](../EnvironmentSimulatorAgent.md)
- Review the [examples directory](../../examples/)
- Try simulating your own infrastructure changes
- Integrate simulations into your change management process

### Additional Resources

- [Agent Selection Guide](../agent-selection-guide.md)
- [API Reference](../EnvironmentSimulatorAgent.md#api-reference)
- [Other Agent Tutorials](.)

Happy simulating! 🚀

