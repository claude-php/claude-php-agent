# EnvironmentSimulatorAgent Documentation

## Overview

The `EnvironmentSimulatorAgent` is a specialized agent that models external environments and predicts the outcomes of actions before they are executed. It's particularly useful for planning, testing, risk assessment, and decision-making by simulating how actions will affect an environment.

## Key Features

- **Action Simulation**: Predict outcomes of actions without executing them
- **State Management**: Maintain and update environment state
- **Risk Assessment**: Identify side effects and potential problems
- **Success Probability**: Quantify likelihood of successful outcomes
- **Multi-Environment Support**: Simulate various types of environments
- **Non-Destructive Testing**: Test actions safely before implementation

## Architecture

```
┌─────────────────────────────────────┐
│   EnvironmentSimulatorAgent         │
├─────────────────────────────────────┤
│ - ClaudePhp client                  │
│ - Environment state                 │
│ - Simulation history                │
│ - Logger                            │
└──────────┬──────────────────────────┘
           │
           ├─── State Management
           │    ├─── Current state
           │    ├─── State updates
           │    └─── State queries
           │
           └─── Simulation Engine
                ├─── Action prediction
                ├─── Outcome analysis
                ├─── Risk assessment
                └─── Probability calculation
```

## Use Cases

### 1. Infrastructure Planning
Simulate changes to infrastructure before implementation:
- Scaling servers
- Load balancer configuration
- Network topology changes
- Database modifications

### 2. Disaster Recovery Testing
Test disaster scenarios without causing actual disruptions:
- Datacenter failures
- Network outages
- Data loss scenarios
- Failover procedures

### 3. Performance Optimization
Predict the impact of optimization strategies:
- Caching implementations
- Query optimizations
- Architecture changes
- Resource allocation

### 4. IoT and Smart Systems
Model physical environment changes:
- Temperature control
- Lighting systems
- Security systems
- Energy management

### 5. Cloud Migration Planning
Assess migration strategies:
- Full migration vs. phased
- Hybrid approaches
- Cost predictions
- Risk analysis

## Classes

### EnvironmentSimulatorAgent

The main agent class that performs environment simulations.

**Namespace**: `ClaudeAgents\Agents`

**Implements**: `AgentInterface`

**Properties**:
- `client` - ClaudePhp client instance
- `name` - Agent identifier
- `environmentState` - Current environment state
- `simulationHistory` - Record of all simulations
- `logger` - PSR-3 logger instance

## Usage

### Basic Usage

```php
use ClaudeAgents\Agents\EnvironmentSimulatorAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create simulator with initial state
$simulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'web_app_simulator',
    'initial_state' => [
        'servers' => 5,
        'cpu_usage' => 60,
        'requests_per_second' => 1000,
    ],
]);

// Simulate an action
$result = $simulator->run('Scale to 10 servers');

if ($result->isSuccess()) {
    echo "Simulation Result:\n";
    echo $result->getAnswer() . "\n";
    
    // Access detailed metadata
    $metadata = $result->getMetadata();
    echo "Initial State: " . json_encode($metadata['initial_state']) . "\n";
    echo "Resulting State: " . json_encode($metadata['resulting_state']) . "\n";
    echo "Outcome: {$metadata['outcome']}\n";
}
```

### Configuration Options

```php
$simulator = new EnvironmentSimulatorAgent($client, [
    // Optional - Agent identifier
    'name' => 'my_simulator',
    
    // Optional - Initial environment state
    'initial_state' => [
        'key' => 'value',
        // ... your environment state
    ],
    
    // Optional - PSR-3 logger
    'logger' => $psrLogger,
]);
```

### State Management

```php
// Get current state
$currentState = $simulator->getState();
echo json_encode($currentState, JSON_PRETTY_PRINT);

// Update state
$newState = [
    'servers' => 10,
    'load' => 'high',
    'status' => 'operational',
];
$simulator->setState($newState);

// State can be updated between simulations
$result1 = $simulator->run('Action 1');
$simulator->setState(['updated' => true]);
$result2 = $simulator->run('Action 2');
```

### Detailed Simulation

```php
// Use simulateAction for detailed results
$simulation = $simulator->simulateAction(
    'Increase database connection pool from 50 to 200'
);

// Access all simulation details
echo "Action: {$simulation['action']}\n";
echo "Initial State: " . json_encode($simulation['initial_state']) . "\n";
echo "Resulting State: " . json_encode($simulation['resulting_state']) . "\n";
echo "Outcome: {$simulation['outcome']}\n";
echo "Success Probability: " . ($simulation['success_probability'] * 100) . "%\n";

if (!empty($simulation['side_effects'])) {
    echo "\nSide Effects:\n";
    foreach ($simulation['side_effects'] as $effect) {
        echo "  - {$effect}\n";
    }
}

// Get formatted description
echo "\n{$simulation['description']}";
```

## Simulation Result Structure

Each simulation returns a comprehensive result:

```php
[
    'initial_state' => [...],           // State before action
    'action' => 'string',               // Action description
    'resulting_state' => [...],         // Predicted state after action
    'outcome' => 'string',              // Description of outcome
    'side_effects' => ['...'],          // Array of potential side effects
    'success_probability' => 0.0-1.0,   // Probability of success
    'description' => 'string',          // Formatted description
]
```

## Agent Result

The `run()` method returns an `AgentResult` object:

```php
$result = $simulator->run('action description');

// Standard properties
$result->isSuccess();      // bool
$result->getAnswer();      // Formatted simulation description
$result->getError();       // Error message (if failed)
$result->getIterations();  // Always 1 for simulator

// Metadata contains full simulation details
$metadata = $result->getMetadata();
$metadata['initial_state'];     // State before simulation
$metadata['action'];            // Action that was simulated
$metadata['resulting_state'];   // Predicted state after action
$metadata['outcome'];           // Outcome description
```

## Advanced Patterns

### Pattern 1: Comparing Multiple Strategies

```php
$simulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => $currentInfrastructure,
]);

$strategies = [
    'Scale vertically by doubling server resources',
    'Scale horizontally by adding 10 more servers',
    'Implement caching layer and optimize queries',
];

$results = [];
foreach ($strategies as $strategy) {
    $sim = $simulator->simulateAction($strategy);
    $results[$strategy] = $sim;
}

// Sort by success probability
uasort($results, function ($a, $b) {
    return $b['success_probability'] <=> $a['success_probability'];
});

echo "Recommended strategy: " . array_key_first($results) . "\n";
```

### Pattern 2: Risk-Based Decision Making

```php
$simulation = $simulator->simulateAction($proposedAction);

// Evaluate risk
if ($simulation['success_probability'] < 0.7) {
    echo "WARNING: High risk action (probability: {$simulation['success_probability']})\n";
    
    if (count($simulation['side_effects']) > 3) {
        echo "Multiple side effects identified. Consider alternatives.\n";
        
        // Simulate safer alternative
        $alternative = $simulator->simulateAction($saferAction);
        // Compare and decide...
    }
}
```

### Pattern 3: Sequential Simulation Planning

```php
// Simulate a sequence of actions
$actions = [
    'Step 1: Enable read replicas',
    'Step 2: Migrate read traffic to replicas',
    'Step 3: Scale down primary database',
];

$currentState = $simulator->getState();
$plan = [];

foreach ($actions as $action) {
    $sim = $simulator->simulateAction($action);
    $plan[] = $sim;
    
    // Update state for next simulation
    $simulator->setState($sim['resulting_state']);
    
    // Check if plan is still viable
    if ($sim['success_probability'] < 0.8) {
        echo "Plan becomes risky at: {$action}\n";
        break;
    }
}

// Restore original state if needed
$simulator->setState($currentState);
```

### Pattern 4: What-If Analysis

```php
// Test various scenarios from same starting point
$baseState = [
    'traffic' => 1000,
    'servers' => 5,
    'response_time' => 100,
];

$scenarios = [
    '2x traffic increase' => ['traffic' => 2000],
    '5x traffic increase' => ['traffic' => 5000],
    '10x traffic increase' => ['traffic' => 10000],
];

foreach ($scenarios as $name => $changes) {
    $simulator->setState(array_merge($baseState, $changes));
    $sim = $simulator->simulateAction('Handle traffic spike');
    
    echo "{$name}: ";
    echo "Success: " . ($sim['success_probability'] * 100) . "%, ";
    echo "Outcome: {$sim['outcome']}\n";
}
```

### Pattern 5: Disaster Recovery Testing

```php
$drSimulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'disaster_recovery',
    'initial_state' => [
        'primary_dc' => 'online',
        'backup_dc' => 'standby',
        'replication_lag' => 5,
    ],
]);

$disasters = [
    'Primary datacenter loses power',
    'Network link between datacenters fails',
    'Primary database becomes corrupted',
    'Ransomware attack on primary systems',
];

foreach ($disasters as $disaster) {
    $sim = $drSimulator->simulateAction(
        "Disaster: {$disaster}. Initiate recovery procedures."
    );
    
    echo "\nScenario: {$disaster}\n";
    echo "Recovery Success: " . ($sim['success_probability'] * 100) . "%\n";
    echo "Outcome: {$sim['outcome']}\n";
    
    if ($sim['success_probability'] < 0.9) {
        echo "⚠️  Recovery plan needs improvement!\n";
    }
}
```

## Best Practices

### 1. Define Clear Initial State

```php
// ✅ Good - Comprehensive state definition
'initial_state' => [
    'infrastructure' => [
        'servers' => 10,
        'load_balancers' => 2,
    ],
    'performance' => [
        'cpu_usage' => 65,
        'memory_usage' => 70,
    ],
    'metrics' => [
        'requests_per_second' => 1000,
        'error_rate' => 0.001,
    ],
]

// ❌ Avoid - Vague or incomplete state
'initial_state' => ['some_value' => 123]
```

### 2. Write Descriptive Actions

```php
// ✅ Good - Specific and detailed
$simulator->run(
    'Scale horizontally from 5 to 10 web servers, update load balancer, ' .
    'and ensure database connection pool can handle increased load'
);

// ❌ Avoid - Too vague
$simulator->run('scale up');
```

### 3. Consider Success Probability

```php
$simulation = $simulator->simulateAction($action);

if ($simulation['success_probability'] >= 0.9) {
    echo "✓ High confidence - proceed with action\n";
} elseif ($simulation['success_probability'] >= 0.7) {
    echo "⚠ Moderate risk - review side effects\n";
} else {
    echo "✗ High risk - consider alternatives\n";
}
```

### 4. Review Side Effects

```php
$simulation = $simulator->simulateAction($action);

if (!empty($simulation['side_effects'])) {
    echo "Action has side effects:\n";
    foreach ($simulation['side_effects'] as $effect) {
        echo "  - {$effect}\n";
    }
    
    // Decide if side effects are acceptable
    $acceptableRisks = ['temporary performance degradation', 'brief downtime'];
    $unacceptableRisks = ['data loss', 'security vulnerability'];
    
    foreach ($simulation['side_effects'] as $effect) {
        foreach ($unacceptableRisks as $risk) {
            if (stripos($effect, $risk) !== false) {
                echo "CRITICAL: Unacceptable risk detected!\n";
                return; // Don't proceed
            }
        }
    }
}
```

### 5. Use Logging in Production

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('simulator');
$logger->pushHandler(new StreamHandler('/var/log/simulations.log', Logger::INFO));

$simulator = new EnvironmentSimulatorAgent($client, [
    'name' => 'production_simulator',
    'initial_state' => $state,
    'logger' => $logger,
]);

// All simulations will be logged
$result = $simulator->run($action);
```

### 6. State Immutability for Comparisons

```php
// Save original state for comparison
$originalState = $simulator->getState();

// Run multiple simulations
$sim1 = $simulator->simulateAction('Strategy 1');
$sim2 = $simulator->simulateAction('Strategy 2');

// State hasn't changed - simulations are non-destructive
assert($simulator->getState() === $originalState);

// Only change state when you want to
$simulator->setState($sim1['resulting_state']);
```

## Testing

### Unit Tests

Run the comprehensive unit test suite:

```bash
./vendor/bin/phpunit tests/Unit/Agents/EnvironmentSimulatorAgentTest.php
```

The test suite covers:
- Constructor and initialization
- State management
- Simulation execution
- Error handling
- JSON parsing
- Complex state scenarios
- Success probability ranges
- Side effects handling

### Integration Testing

```php
// Test with mock state
$simulator = new EnvironmentSimulatorAgent($client, [
    'initial_state' => $testState,
]);

$result = $simulator->run($testAction);
assert($result->isSuccess());
assert(isset($result->getMetadata()['outcome']));
```

## Examples

See the `/examples` directory for complete working examples:

- `environment_simulator_example.php` - Basic usage across various domains
- `advanced_environment_simulator.php` - Advanced patterns and decision-making

Run examples:

```bash
export ANTHROPIC_API_KEY='your-key-here'
php examples/environment_simulator_example.php
php examples/advanced_environment_simulator.php
```

## Troubleshooting

### Simulation Returns Unexpected Results

```php
// Enable detailed logging
$simulator = new EnvironmentSimulatorAgent($client, [
    'logger' => $verboseLogger,
]);

// Review the full simulation
$sim = $simulator->simulateAction($action);
print_r($sim); // Inspect all fields
```

### Invalid JSON Responses

The agent handles invalid JSON gracefully:

```php
// If Claude returns invalid JSON, defaults are used:
[
    'resulting_state' => $initial_state,  // No change
    'outcome' => 'Unknown outcome',
    'side_effects' => [],
    'success_probability' => 0.5,  // Neutral probability
]
```

### State Not Updating

Remember: simulations don't change the agent's state automatically.

```php
$sim = $simulator->simulateAction($action);
// State hasn't changed yet

// Manually apply the change if desired
$simulator->setState($sim['resulting_state']);
// Now state is updated
```

## API Reference

### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
)
```

**Options:**
- `name` (optional): Agent identifier (default: 'environment_simulator')
- `initial_state` (optional): Initial environment state (default: [])
- `logger` (optional): PSR-3 logger instance (default: NullLogger)

### Methods

#### run(string $task): AgentResult

Simulate an action and return formatted result.

**Parameters:**
- `$task`: Description of action to simulate

**Returns:** `AgentResult` with simulation details in metadata

#### simulateAction(string $action): array

Perform detailed simulation and return raw result.

**Parameters:**
- `$action`: Description of action to simulate

**Returns:** Array with keys:
- `initial_state`: State before action
- `action`: Action description
- `resulting_state`: Predicted state after action
- `outcome`: Outcome description
- `side_effects`: Array of side effects
- `success_probability`: Float between 0.0 and 1.0
- `description`: Formatted description

#### setState(array $state): void

Update the environment state.

**Parameters:**
- `$state`: New environment state

#### getState(): array

Get current environment state.

**Returns:** Current state array

#### getName(): string

Get agent name.

**Returns:** Agent identifier string

## Performance Considerations

### API Calls

Each simulation makes one API call to Claude. For multiple simulations:

```php
// Sequential simulations
foreach ($actions as $action) {
    $sim = $simulator->simulateAction($action);
    // Process result...
}

// Consider rate limiting for many simulations
usleep(100000); // 100ms delay between calls
```

### State Complexity

Large state objects increase token usage:

```php
// ✅ Good - Relevant state only
'initial_state' => [
    'servers' => 5,
    'cpu' => 60,
]

// ⚠️ Consider - Very large state
'initial_state' => [
    'detailed_logs' => [...1000 entries...],  // May be too detailed
]
```

## Related Documentation

- [Agent Selection Guide](agent-selection-guide.md)
- [AutonomousAgent Documentation](AutonomousAgent.md)
- [Examples README](../examples/README.md)

## License

MIT License - See [LICENSE](../LICENSE) file for details.

