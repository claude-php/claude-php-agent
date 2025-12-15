# UtilityBasedAgent Documentation

## Overview

The `UtilityBasedAgent` is a decision-making agent that evaluates and selects actions by maximizing a utility function. It supports multi-objective optimization, constraint-based filtering, and sophisticated trade-off analysis to make optimal decisions in complex scenarios.

## Key Features

- **Utility Function Optimization**: Select actions that maximize utility scores
- **Multi-Objective Support**: Balance multiple competing objectives with weighted scores
- **Constraint Filtering**: Enforce hard constraints to filter infeasible actions
- **Trade-off Analysis**: Make informed decisions when objectives conflict
- **Flexible Configuration**: Custom utility functions and objectives
- **Action Generation**: Automatically generates possible actions using Claude
- **Comprehensive Evaluation**: Detailed analysis of all alternatives

## Architecture

```
┌─────────────────────────────────────┐
│       UtilityBasedAgent             │
├─────────────────────────────────────┤
│ - ClaudePhp client                  │
│ - Utility function                  │
│ - Objectives (weighted)             │
│ - Constraints (boolean predicates)  │
└──────────┬──────────────────────────┘
           │
           ├─── Action Generation
           │    └─── Claude generates possible actions
           │
           ├─── Constraint Filtering
           │    └─── Filter actions that violate constraints
           │
           ├─── Utility Evaluation
           │    ├─── Single utility function
           │    └─── Multi-objective weighted sum
           │
           ├─── Action Selection
           │    └─── Choose action with highest utility
           │
           └─── Result Formatting
                └─── Detailed decision report
```

## Classes

### UtilityBasedAgent

The main agent class that implements utility-based decision making.

**Namespace**: `ClaudeAgents\Agents`

**Implements**: `AgentInterface`

**Properties**:
- `client` - Claude API client
- `name` - Agent name
- `utilityFunction` - Callable to compute utility scores
- `objectives` - Array of objective functions with weights
- `constraints` - Array of constraint predicates
- `logger` - PSR-3 logger

## Usage

### Basic Usage

```php
use ClaudeAgents\Agents\UtilityBasedAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new UtilityBasedAgent($client);

// Run a decision-making task
$result = $agent->run('Choose the best database for our application');

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    // Access metadata
    $metadata = $result->getMetadata();
    echo "Actions evaluated: {$metadata['actions_evaluated']}\n";
    echo "Best utility: {$metadata['best_utility']}\n";
}
```

### Configuration Options

```php
$agent = new UtilityBasedAgent($client, [
    // Optional
    'name' => 'decision_maker',                    // Agent name
    'utility_function' => fn($action) => 0.0,      // Custom utility function
    'objectives' => [],                             // Initial objectives
    'constraints' => [],                            // Initial constraints
    'logger' => $psrLogger,                         // PSR-3 logger
]);
```

### Setting a Custom Utility Function

When no objectives are defined, the agent uses a single utility function:

```php
$agent->setUtilityFunction(function($action) {
    $value = $action['estimated_value'] ?? 50;
    $cost = $action['estimated_cost'] ?? 50;
    
    // Maximize value, minimize cost
    $valueScore = $value / 100;
    $costScore = (100 - $cost) / 100;
    
    // Return weighted combination
    return ($valueScore * 0.7) + ($costScore * 0.3);
});

$result = $agent->run('Choose the best option');
```

### Adding Objectives (Multi-Objective Optimization)

For complex decisions with multiple competing goals:

```php
// Add objectives with different weights
$agent->addObjective(
    'value',
    fn($action) => $action['estimated_value'] ?? 50,
    weight: 0.5  // 50% of total utility
);

$agent->addObjective(
    'cost_efficiency',
    fn($action) => 100 - ($action['estimated_cost'] ?? 50),
    weight: 0.3  // 30% of total utility
);

$agent->addObjective(
    'risk_tolerance',
    fn($action) => match($action['risk'] ?? 'medium') {
        'low' => 100,
        'medium' => 60,
        'high' => 30,
    },
    weight: 0.2  // 20% of total utility
);

$result = $agent->run('Select the best cloud provider');
```

### Adding Constraints

Constraints filter out infeasible actions before evaluation:

```php
// Budget constraint
$agent->addConstraint(
    'budget_limit',
    fn($action) => ($action['estimated_cost'] ?? 100) <= 70
);

// Risk constraint
$agent->addConstraint(
    'acceptable_risk',
    fn($action) => in_array($action['risk'] ?? 'high', ['low', 'medium'])
);

// Minimum quality constraint
$agent->addConstraint(
    'quality_threshold',
    fn($action) => ($action['estimated_value'] ?? 0) >= 50
);

$result = $agent->run('Choose within budget and risk tolerance');
```

## Agent Result

The `run()` method returns an `AgentResult` object:

```php
$result = $agent->run('task');

// Standard properties
$result->isSuccess();      // bool
$result->getAnswer();      // string (formatted decision)
$result->getError();       // string (if failed)
$result->getIterations();  // int (always 1)

// Metadata
$metadata = $result->getMetadata();
$metadata['actions_evaluated'];    // Number of actions evaluated
$metadata['best_action'];          // The selected action details
$metadata['best_utility'];         // Utility score of selected action
$metadata['all_evaluations'];      // All evaluated actions with scores
```

## Decision-Making Process

### 1. Action Generation

The agent uses Claude to generate 3-5 possible actions for the task:

```
Task: Choose the best database

Generated Actions:
1. PostgreSQL (value: 80, cost: 60, risk: low)
2. MongoDB (value: 75, cost: 50, risk: medium)
3. DynamoDB (value: 85, cost: 70, risk: medium)
```

### 2. Constraint Filtering

Actions are filtered based on constraints:

```php
// With constraint: cost <= 60
$agent->addConstraint('budget', fn($a) => $a['estimated_cost'] <= 60);

// DynamoDB filtered out (cost: 70)
// Remaining: PostgreSQL, MongoDB
```

### 3. Utility Evaluation

Each remaining action is scored:

```php
// With objectives:
// - value (50% weight)
// - cost_efficiency (30% weight)  
// - risk (20% weight)

PostgreSQL: (80 * 0.5) + (40 * 0.3) + (100 * 0.2) = 72
MongoDB: (75 * 0.5) + (50 * 0.3) + (60 * 0.2) = 64.5
```

### 4. Action Selection

The action with the highest utility is selected:

```
Selected: PostgreSQL (utility: 72.0)
```

## Use Cases

### 1. Technology Selection

```php
$agent = new UtilityBasedAgent($client);

$agent->addObjective('features', fn($a) => $a['estimated_value'] ?? 50, 0.4);
$agent->addObjective('cost', fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.3);
$agent->addObjective('maturity', fn($a) => match($a['risk'] ?? 'high') {
    'low' => 90, 'medium' => 60, 'high' => 30
}, 0.3);

$result = $agent->run('Choose a web framework for our startup');
```

### 2. Business Strategy

```php
$agent = new UtilityBasedAgent($client);

$agent->addObjective('market_share', fn($a) => $a['estimated_value'] ?? 50, 0.4);
$agent->addObjective('profit_margin', fn($a) => $a['estimated_value'] ?? 50, 0.3);
$agent->addObjective('speed_to_market', fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.3);

$agent->addConstraint('budget', fn($a) => ($a['estimated_cost'] ?? 100) <= 75);

$result = $agent->run('Choose our go-to-market strategy');
```

### 3. Resource Allocation

```php
$agent = new UtilityBasedAgent($client);

$agent->addObjective('impact', fn($a) => $a['estimated_value'] ?? 50, 0.5);
$agent->addObjective('effort', fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.3);
$agent->addObjective('urgency', fn($a) => 75, 0.2);

$result = $agent->run('Which project should the team work on next?');
```

### 4. Vendor Selection

```php
$agent = new UtilityBasedAgent($client);

$agent->addObjective('features', fn($a) => $a['estimated_value'] ?? 50, 0.25);
$agent->addObjective('pricing', fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.25);
$agent->addObjective('reliability', fn($a) => 80, 0.25);
$agent->addObjective('support', fn($a) => 70, 0.25);

$agent->addConstraint('sla', fn($a) => ($a['risk'] ?? 'high') !== 'high');

$result = $agent->run('Select a payment processing vendor');
```

### 5. Feature Prioritization

```php
$agent = new UtilityBasedAgent($client);

$agent->addObjective('business_value', fn($a) => $a['estimated_value'] ?? 50, 0.4);
$agent->addObjective('user_impact', fn($a) => $a['estimated_value'] ?? 50, 0.3);
$agent->addObjective('ease_of_implementation', fn($a) => 100 - ($a['estimated_cost'] ?? 50), 0.3);

$agent->addConstraint('time_limit', fn($a) => ($a['estimated_cost'] ?? 100) <= 70);

$result = $agent->run('Which feature should we build first?');
```

## Best Practices

### 1. Define Clear Objectives

```php
// ✅ Good - Specific, measurable objectives
$agent->addObjective('conversion_rate', fn($a) => $a['estimated_value'] ?? 0, 0.4);
$agent->addObjective('customer_satisfaction', fn($a) => $a['quality_score'] ?? 0, 0.3);

// ❌ Avoid - Vague objectives
$agent->addObjective('general_goodness', fn($a) => 50, 1.0);
```

### 2. Balance Objective Weights

```php
// ✅ Good - Weights sum to 1.0 (or close to it)
$agent->addObjective('speed', fn($a) => ..., 0.4);
$agent->addObjective('quality', fn($a) => ..., 0.35);
$agent->addObjective('cost', fn($a) => ..., 0.25);

// ❌ Avoid - Weights don't reflect priorities
$agent->addObjective('speed', fn($a) => ..., 1.0);
$agent->addObjective('quality', fn($a) => ..., 1.0);
```

### 3. Use Constraints for Hard Requirements

```php
// ✅ Good - Hard constraints as constraints
$agent->addConstraint('legal', fn($a) => $a['compliant'] ?? false);
$agent->addConstraint('budget', fn($a) => ($a['cost'] ?? 1000) <= 500);

// ❌ Avoid - Hard requirements as objectives
$agent->addObjective('maybe_legal', fn($a) => $a['compliant'] ? 100 : 0, 0.1);
```

### 4. Normalize Objective Scores

```php
// ✅ Good - Scores normalized to 0-100 range
$agent->addObjective('performance', fn($action) => {
    $score = $action['benchmark_result'] ?? 0;
    return min(100, max(0, $score * 10));
}, 0.5);

// ❌ Avoid - Unnormalized scores
$agent->addObjective('performance', fn($a) => $a['raw_score'] ?? 0, 0.5);
```

### 5. Test Edge Cases

```php
// Test with missing action fields
$testAction = ['description' => 'Test'];
$utility = $utilityFunction($testAction);  // Should not crash

// Test with extreme values
$extremeAction = [
    'estimated_value' => 1000,
    'estimated_cost' => -50,
];
```

### 6. Use Logging in Production

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('utility_agent');
$logger->pushHandler(new StreamHandler('/var/log/agent.log', Logger::INFO));

$agent = new UtilityBasedAgent($client, [
    'logger' => $logger,
]);
```

## Advanced Patterns

### Pattern 1: Dynamic Constraints

```php
class DynamicConstraintAgent
{
    private UtilityBasedAgent $agent;
    private float $budgetLimit = 100;
    
    public function adjustBudget(float $newLimit): void
    {
        $this->budgetLimit = $newLimit;
        
        // Re-add constraint with new limit
        $this->agent->addConstraint(
            'budget',
            fn($a) => ($a['estimated_cost'] ?? 100) <= $this->budgetLimit
        );
    }
}
```

### Pattern 2: Contextual Objectives

```php
function createAgentForContext(string $context): UtilityBasedAgent
{
    global $client;
    $agent = new UtilityBasedAgent($client);
    
    if ($context === 'startup') {
        $agent->addObjective('speed', fn($a) => ..., 0.5);
        $agent->addObjective('cost', fn($a) => ..., 0.5);
    } elseif ($context === 'enterprise') {
        $agent->addObjective('reliability', fn($a) => ..., 0.4);
        $agent->addObjective('security', fn($a) => ..., 0.4);
        $agent->addObjective('scalability', fn($a) => ..., 0.2);
    }
    
    return $agent;
}
```

### Pattern 3: Hierarchical Decisions

```php
// First decide on approach
$strategicAgent = new UtilityBasedAgent($client);
$strategicAgent->addObjective('alignment', fn($a) => ..., 1.0);

$strategy = $strategicAgent->run('Choose overall approach');

// Then decide on implementation
$tacticalAgent = new UtilityBasedAgent($client);
$tacticalAgent->addObjective('feasibility', fn($a) => ..., 0.6);
$tacticalAgent->addObjective('timeline', fn($a) => ..., 0.4);

$tactics = $tacticalAgent->run("Implement the {$strategy} strategy");
```

### Pattern 4: Comparative Analysis

```php
function compareOptions(array $options): array
{
    global $client;
    $results = [];
    
    foreach ($options as $option) {
        $agent = new UtilityBasedAgent($client);
        // Configure agent for this option
        $result = $agent->run("Evaluate {$option}");
        $results[$option] = $result->getMetadata()['best_utility'];
    }
    
    arsort($results);
    return $results;
}
```

## Performance Considerations

### Action Generation

- Claude generates 3-5 actions per task
- Generation time: ~1-3 seconds
- Consider caching for repeated decisions

### Evaluation Complexity

- Time complexity: O(n * m) where:
  - n = number of actions
  - m = number of objectives
- Typical: 5 actions × 3 objectives = 15 evaluations
- Very fast (< 1ms total)

### Optimization Tips

```php
// 1. Reduce API calls by caching action generation
$cachedActions = getFromCache($taskHash);
if (!$cachedActions) {
    $result = $agent->run($task);
    saveToCache($taskHash, $result->getMetadata()['all_evaluations']);
}

// 2. Use simpler utility functions when possible
$agent->setUtilityFunction(fn($a) => ($a['value'] ?? 0) - ($a['cost'] ?? 0));

// 3. Add constraints early to filter actions
$agent->addConstraint('quick_filter', fn($a) => ($a['cost'] ?? 100) <= 50);
```

## Testing

### Unit Tests

```bash
./vendor/bin/phpunit tests/Unit/Agents/UtilityBasedAgentTest.php
```

### Example Tests

```bash
# Basic usage
php examples/utility_based_agent.php

# Advanced patterns
php examples/advanced_utility_based_agent.php
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
- `name` (string): Agent name (default: 'utility_agent')
- `utility_function` (callable): Utility function (default: returns 0.0)
- `objectives` (array): Initial objectives (default: [])
- `constraints` (array): Initial constraints (default: [])
- `logger` (LoggerInterface): PSR-3 logger (default: NullLogger)

### Methods

#### run(string $task): AgentResult

Execute decision-making task.

**Returns**: `AgentResult` with metadata including `actions_evaluated`, `best_action`, `best_utility`, `all_evaluations`

#### setUtilityFunction(callable $function): void

Set the utility function.

**Parameters**:
- `function`: Callable with signature `fn(array $action): float`

#### addObjective(string $name, callable $function, float $weight = 1.0): void

Add an objective for multi-objective optimization.

**Parameters**:
- `name`: Objective name
- `function`: Objective function `fn(array $action): float`
- `weight`: Weight in [0,1] (default: 1.0)

#### addConstraint(string $name, callable $predicate): void

Add a constraint.

**Parameters**:
- `name`: Constraint name
- `predicate`: Constraint predicate `fn(array $action): bool`

#### getName(): string

Get the agent name.

## Examples

See the `/examples` directory for complete working examples:

- `utility_based_agent.php` - Basic utility-based decision making
- `advanced_utility_based_agent.php` - Advanced patterns and real-world scenarios

## Related Documentation

- [Agent Selection Guide](agent-selection-guide.md)
- [ModelBasedAgent Documentation](ModelBasedAgent.md)
- [LearningAgent Documentation](LearningAgent.md)
- [Examples README](../examples/README.md)

## License

MIT License - See [LICENSE](../LICENSE) file for details.

