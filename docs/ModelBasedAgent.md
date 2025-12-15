# ModelBasedAgent Documentation

## Overview

The `ModelBasedAgent` is an advanced agent that maintains an internal model of the world state, tracks state transitions over time, and uses this model to predict action outcomes and plan sequences of actions to achieve goals. Unlike reactive agents that only respond to immediate inputs, model-based agents leverage their understanding of how the world works to make informed decisions.

## Key Features

- **Internal World Model**: Maintains a complete state representation
- **State Transition Rules**: Define deterministic rules for known actions
- **State History Tracking**: Complete history of all state transitions
- **Predictive Modeling**: Simulate action outcomes without changing state
- **Goal-Oriented Planning**: Generate action sequences to achieve objectives
- **Observation Learning**: Extract state changes from natural language
- **LLM-Enhanced Prediction**: Use Claude for complex state transitions

## Architecture

```
┌─────────────────────────────────────┐
│      ModelBasedAgent                │
├─────────────────────────────────────┤
│ - ClaudePhp client                  │
│ - World state (current)             │
│ - State history                     │
│ - Transition rules                  │
│ - Goal tracking                     │
└──────────┬──────────────────────────┘
           │
           ├─── World State
           │    ├─── Current variables
           │    ├─── Nested structures
           │    └─── Custom properties
           │
           ├─── Transition Rules
           │    ├─── Action → Transformer
           │    ├─── Deterministic logic
           │    └─── State validation
           │
           └─── State History
                ├─── Timestamp
                ├─── Previous state
                └─── Current state
```

## Classes

### ModelBasedAgent

The main agent class that manages world models and state transitions.

**Namespace**: `ClaudeAgents\Agents`

**Implements**: `AgentInterface`

## Usage

### Basic State Management

```php
use ClaudeAgents\Agents\ModelBasedAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create agent with initial state
$agent = new ModelBasedAgent($client, [
    'name' => 'robot',
    'initial_state' => [
        'position' => ['x' => 0, 'y' => 0],
        'battery' => 100,
    ],
]);

// Get current state
$state = $agent->getState();
echo "Position: ({$state['position']['x']}, {$state['position']['y']})\n";

// Update state
$agent->updateState([
    'position' => ['x' => 5, 'y' => 3],
    'battery' => 95,
]);
```

### Defining Transition Rules

Transition rules define how actions transform the world state:

```php
// Define deterministic transition rules
$agent->addTransitionRule('move_north', function (array $state): array {
    $state['position']['y'] += 1;
    $state['battery'] -= 5;
    return $state;
});

$agent->addTransitionRule('move_east', function (array $state): array {
    $state['position']['x'] += 1;
    $state['battery'] -= 5;
    return $state;
});

$agent->addTransitionRule('recharge', function (array $state): array {
    $state['battery'] = 100;
    return $state;
});
```

### Predictive Modeling

Predict outcomes without changing actual state:

```php
// Predict what would happen if we move north
$predictedState = $agent->predictNextState('move_north');
echo "If we move north, battery will be: {$predictedState['battery']}\n";

// Current state remains unchanged
echo "Current battery: {$agent->getState()['battery']}\n";

// Can compare multiple scenarios
$moveNorth = $agent->predictNextState('move_north');
$moveEast = $agent->predictNextState('move_east');
$recharge = $agent->predictNextState('recharge');

// Choose best action based on predictions
if ($agent->getState()['battery'] < 20) {
    $agent->updateState($recharge);
} else {
    $agent->updateState($moveNorth);
}
```

### Goal-Oriented Planning

Generate action plans to achieve objectives:

```php
$planner = new ModelBasedAgent($client, [
    'initial_state' => [
        'location' => 'home',
        'has_groceries' => false,
        'dinner_ready' => false,
    ],
]);

// Agent will create a plan to achieve this goal
$result = $planner->run('Goal: Make dinner starting from home');

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    echo "Goal: {$metadata['goal']}\n";
    echo "Plan:\n";
    
    foreach ($metadata['planned_actions'] as $i => $action) {
        echo "  " . ($i + 1) . ". {$action}\n";
    }
}
```

### Learning from Observations

Extract state changes from natural language:

```php
$monitor = new ModelBasedAgent($client, [
    'initial_state' => [
        'temperature' => 72,
        'humidity' => 50,
    ],
]);

// Agent extracts state changes from observation
$result = $monitor->run('The temperature rose to 75 degrees');

echo "New temperature: {$monitor->getState()['temperature']}\n";

$result = $monitor->run('Humidity increased to 65% due to rain');

echo "New humidity: {$monitor->getState()['humidity']}\n";
```

### State History

Track all state transitions:

```php
// Make several state changes
$agent->updateState(['x' => 1]);
$agent->updateState(['x' => 2]);
$agent->updateState(['x' => 3]);

// Get complete history
$history = $agent->getStateHistory();

echo "Total transitions: " . count($history) . "\n";

foreach ($history as $entry) {
    echo "Time: {$entry['timestamp']}\n";
    echo "Previous: x={$entry['previous']['x']}\n";
    echo "Current: x={$entry['current']['x']}\n";
}
```

## Constructor Options

```php
$agent = new ModelBasedAgent($client, [
    'name' => 'my_agent',              // Agent identifier
    'initial_state' => [],             // Starting world state
    'max_history' => 100,              // Maximum history entries (default: 100)
    'logger' => $logger,               // PSR-3 logger instance
]);
```

## Methods

### Public Methods

#### `run(string $task): AgentResult`

Execute a task. The agent determines if the task is a goal statement or observation:

- **Goal statements** (contains: "goal", "achieve", "plan", "reach", etc.) trigger planning
- **Observations** extract state changes from the input

**Parameters:**
- `$task` - Goal statement or observation

**Returns:** `AgentResult` with plan or updated state

#### `getName(): string`

Get the agent's name.

#### `getState(): array`

Get current world state.

**Returns:** Current state as associative array

#### `getStateHistory(): array`

Get complete state transition history.

**Returns:** Array of history entries with timestamp, previous, and current states

#### `setGoal(string $goal): void`

Explicitly set the agent's goal.

**Parameters:**
- `$goal` - Goal description

#### `updateState(array $state): void`

Update world state. New values are merged with existing state.

**Parameters:**
- `$state` - State changes to apply

#### `addTransitionRule(string $action, callable $transformer): void`

Define a transition rule for an action.

**Parameters:**
- `$action` - Action name
- `$transformer` - Function that takes current state and returns new state

#### `predictNextState(string $action): array`

Predict resulting state after an action without modifying actual state.

**Parameters:**
- `$action` - Action to simulate

**Returns:** Predicted state

## Advanced Patterns

### Multi-Agent Coordination

Multiple agents can share a world model:

```php
$sharedState = [
    'warehouse' => ['inventory' => 1000],
    'robots' => [
        'robot1' => ['position' => [0, 0]],
        'robot2' => ['position' => [5, 5]],
    ],
];

$robot1 = new ModelBasedAgent($client, [
    'name' => 'robot1',
    'initial_state' => $sharedState,
]);

$robot2 = new ModelBasedAgent($client, [
    'name' => 'robot2',
    'initial_state' => $sharedState,
]);

// Each agent can plan independently
$plan1 = $robot1->run('Goal: Move to location A');
$plan2 = $robot2->run('Goal: Move to location B');
```

### Conditional Transition Rules

Rules can include complex logic:

```php
$agent->addTransitionRule('smart_move', function (array $state): array {
    // Move only if battery is sufficient
    if ($state['battery'] >= 10) {
        $state['position']['x'] += 1;
        $state['battery'] -= 10;
    } else {
        // Automatically recharge if battery low
        $state['battery'] = 100;
    }
    
    return $state;
});
```

### Nested State Structures

Handle complex state hierarchies:

```php
$agent = new ModelBasedAgent($client, [
    'initial_state' => [
        'player' => [
            'stats' => ['health' => 100, 'mana' => 50],
            'position' => ['x' => 0, 'y' => 0, 'zone' => 'safe'],
            'inventory' => [
                'weapons' => ['sword', 'bow'],
                'items' => ['potion', 'key'],
            ],
        ],
        'world' => [
            'time' => 'day',
            'weather' => 'clear',
        ],
    ],
]);
```

### State Validation

Validate state changes:

```php
$agent->addTransitionRule('validated_move', function (array $state): array {
    $newX = $state['position']['x'] + 1;
    
    // Validate boundaries
    if ($newX < 0 || $newX > 100) {
        throw new \InvalidArgumentException('Position out of bounds');
    }
    
    $state['position']['x'] = $newX;
    return $state;
});
```

## Use Cases

### 1. Robotics and Navigation

Model robot position, battery, sensors, and environment:

```php
$robot = new ModelBasedAgent($client, [
    'initial_state' => [
        'position' => ['x' => 0, 'y' => 0],
        'orientation' => 0, // degrees
        'battery' => 100,
        'sensors' => ['front' => 'clear', 'back' => 'clear'],
    ],
]);
```

### 2. Game AI

Model game state for strategic planning:

```php
$gameAI = new ModelBasedAgent($client, [
    'initial_state' => [
        'player' => ['health' => 100, 'level' => 1],
        'enemies' => [
            ['type' => 'goblin', 'health' => 30, 'position' => [5, 5]],
        ],
        'items' => ['position' => [3, 3], 'type' => 'health_potion'],
    ],
]);
```

### 3. Smart Home Automation

Model home state for automation decisions:

```php
$smartHome = new ModelBasedAgent($client, [
    'initial_state' => [
        'rooms' => [
            'living_room' => ['temp' => 72, 'lights' => 'off', 'occupied' => false],
            'bedroom' => ['temp' => 70, 'lights' => 'off', 'occupied' => false],
        ],
        'hvac' => ['mode' => 'auto', 'target' => 72],
        'security' => ['armed' => false],
    ],
]);
```

### 4. Financial Trading

Model market and portfolio state:

```php
$trader = new ModelBasedAgent($client, [
    'initial_state' => [
        'portfolio' => ['cash' => 10000, 'stocks' => []],
        'market' => [
            'AAPL' => ['price' => 150, 'trend' => 'up'],
            'GOOGL' => ['price' => 2800, 'trend' => 'down'],
        ],
        'risk_tolerance' => 'moderate',
    ],
]);
```

### 5. Process Automation

Model workflow state:

```php
$workflow = new ModelBasedAgent($client, [
    'initial_state' => [
        'phase' => 'requirements',
        'tasks' => ['requirements' => 'not_started', 'design' => 'not_started'],
        'resources' => ['developers' => 5, 'budget' => 100000],
    ],
]);
```

## Best Practices

### 1. Keep State Flat When Possible

```php
// Good: Flat structure
['x' => 5, 'y' => 3, 'battery' => 95]

// Avoid deep nesting unless necessary
['data' => ['state' => ['position' => ['coords' => ['x' => 5]]]]]
```

### 2. Use Transition Rules for Known Actions

Define rules for deterministic state changes:

```php
// Deterministic actions should use rules
$agent->addTransitionRule('increment_x', fn($s) => ['x' => $s['x'] + 1]);

// Use LLM predictions for complex/context-dependent changes
$result = $agent->run('The robot moved carefully around the obstacle');
```

### 3. Validate State Changes

Ensure state remains valid:

```php
$agent->addTransitionRule('safe_update', function (array $state): array {
    $state['value'] = max(0, min(100, $state['value'])); // Clamp to range
    return $state;
});
```

### 4. Use Meaningful State Keys

```php
// Good: Clear, descriptive keys
['battery_percentage' => 75, 'is_moving' => true]

// Avoid: Ambiguous keys
['b' => 75, 'flag' => true]
```

### 5. Log State Transitions

Use a logger to track changes:

```php
$agent = new ModelBasedAgent($client, [
    'logger' => $myLogger,
    // ... other options
]);
```

### 6. Limit History Size

Control memory usage:

```php
$agent = new ModelBasedAgent($client, [
    'max_history' => 50, // Keep last 50 transitions
]);
```

## Performance Considerations

### LLM Calls

- Transition rules are instant (no API calls)
- Predictions without rules use LLM (API call)
- Observations use LLM to extract changes
- Planning uses LLM to generate action sequences

### State Size

- Large states are stored in memory
- History grows with state size × number of transitions
- Consider state cleanup for long-running agents

### Optimization Tips

```php
// Define rules for frequent actions
$agent->addTransitionRule('frequent_action', $fastFunction);

// Use simple state structures
$state = ['x' => 0]; // Fast
// vs
$state = ['data' => ['nested' => ['deep' => ['x' => 0]]]]; // Slower

// Clear old history if not needed
// (automatically limited to 100 entries by default)
```

## Error Handling

```php
try {
    $result = $agent->run('Goal: invalid goal');
    
    if (!$result->isSuccess()) {
        echo "Error: {$result->getError()}\n";
    }
} catch (\Throwable $e) {
    echo "Exception: {$e->getMessage()}\n";
}
```

## Integration with Other Agents

Model-based agents work well with:

- **ReactAgent**: Use model for planning, React for execution
- **CoordinatorAgent**: Coordinate multiple model-based agents
- **LearningAgent**: Learn better state transition models over time
- **AutonomousAgent**: Long-term goal pursuit with world model

## Examples

See the examples directory for complete working examples:

- `examples/model_based_agent.php` - Basic usage and patterns
- `examples/advanced_model_based_agent.php` - Complex real-world scenarios

## Related Documentation

- [Agent Selection Guide](agent-selection-guide.md)
- [AutonomousAgent](AutonomousAgent.md)
- [CoordinatorAgent](CoordinatorAgent.md)
- [Tutorial: Model-Based Agent](tutorials/ModelBasedAgent_Tutorial.md)

