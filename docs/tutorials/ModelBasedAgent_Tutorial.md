# ModelBasedAgent Tutorial

## Introduction

Welcome to the ModelBasedAgent tutorial! In this guide, you'll learn how to build agents that maintain an internal model of the world, predict outcomes of actions, and use that model to make intelligent decisions.

### What You'll Learn

1. Creating and managing world state models
2. Defining state transition rules
3. Predicting action outcomes without executing them
4. Planning action sequences to achieve goals
5. Learning from observations
6. Building real-world applications

### Prerequisites

- PHP 8.1 or higher
- Composer installed
- Anthropic API key
- Basic understanding of PHP and state machines

### Time Required

Approximately 45-60 minutes

---

## Chapter 1: Your First Model-Based Agent

Let's start by creating a simple agent that models a robot's position and battery.

### Step 1: Set Up Your Environment

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\ModelBasedAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
```

### Step 2: Create Your First Agent with Initial State

```php
$robot = new ModelBasedAgent($client, [
    'name' => 'robot_navigator',
    'initial_state' => [
        'x' => 0,
        'y' => 0,
        'battery' => 100,
    ],
]);

echo "Initial State:\n";
print_r($robot->getState());
```

**Output:**
```
Initial State:
Array
(
    [x] => 0
    [y] => 0
    [battery] => 100
)
```

### Step 3: Update the State

```php
// Robot moves to a new position
$robot->updateState([
    'x' => 5,
    'y' => 3,
    'battery' => 95,
]);

echo "After moving:\n";
echo "Position: ({$robot->getState()['x']}, {$robot->getState()['y']})\n";
echo "Battery: {$robot->getState()['battery']}%\n";
```

### What Just Happened?

1. **Agent Created**: We created a model-based agent with initial state
2. **State Stored**: The agent maintains the complete world model internally
3. **State Updated**: We changed multiple state variables at once
4. **History Tracked**: The agent automatically recorded this transition

### Try It Yourself

Create an agent modeling a character in a game:

```php
$player = new ModelBasedAgent($client, [
    'name' => 'player_character',
    'initial_state' => [
        'health' => 100,
        'mana' => 50,
        'level' => 1,
        'experience' => 0,
    ],
]);

// Player gains experience
$player->updateState([
    'experience' => 150,
]);

// Check if we can level up
if ($player->getState()['experience'] >= 100) {
    $player->updateState([
        'level' => $player->getState()['level'] + 1,
        'health' => 100,
        'mana' => 60,
    ]);
}
```

---

## Chapter 2: State Transition Rules

Transition rules let you define how actions transform the world state in deterministic ways.

### Step 1: Define Basic Movement Rules

```php
$robot = new ModelBasedAgent($client, [
    'initial_state' => ['x' => 0, 'y' => 0, 'battery' => 100],
]);

// Define what happens when robot moves north
$robot->addTransitionRule('move_north', function (array $state): array {
    $state['y'] += 1;
    $state['battery'] -= 5;
    return $state;
});

$robot->addTransitionRule('move_south', function (array $state): array {
    $state['y'] -= 1;
    $state['battery'] -= 5;
    return $state;
});

$robot->addTransitionRule('move_east', function (array $state): array {
    $state['x'] += 1;
    $state['battery'] -= 5;
    return $state;
});

$robot->addTransitionRule('move_west', function (array $state): array {
    $state['x'] -= 1;
    $state['battery'] -= 5;
    return $state;
});
```

### Step 2: Use the Rules

```php
// Predict what would happen if we move north
$predictedState = $robot->predictNextState('move_north');

echo "If we move north:\n";
echo "  New position: ({$predictedState['x']}, {$predictedState['y']})\n";
echo "  New battery: {$predictedState['battery']}%\n";

echo "\nCurrent state (unchanged):\n";
echo "  Position: ({$robot->getState()['x']}, {$robot->getState()['y']})\n";
echo "  Battery: {$robot->getState()['battery']}%\n";
```

### Step 3: Apply the Predicted State

```php
// Now actually move north
$robot->updateState($predictedState);

echo "\nAfter moving:\n";
echo "  Position: ({$robot->getState()['x']}, {$robot->getState()['y']})\n";
echo "  Battery: {$robot->getState()['battery']}%\n";
```

### Understanding Transition Rules

**Key Points:**
- Rules are functions: `array $state -> array $newState`
- Rules are deterministic: same input always produces same output
- Rules don't call the LLM: they're instant
- Rules can be simple or complex

### Try It Yourself

Add a recharge rule:

```php
$robot->addTransitionRule('recharge', function (array $state): array {
    $state['battery'] = 100;
    return $state;
});

// Use it when battery is low
if ($robot->getState()['battery'] < 20) {
    $robot->updateState($robot->predictNextState('recharge'));
}
```

---

## Chapter 3: Predictive Modeling

One of the most powerful features is the ability to simulate actions before committing to them.

### Step 1: Compare Multiple Scenarios

```php
$agent = new ModelBasedAgent($client, [
    'initial_state' => [
        'energy' => 100,
        'health' => 100,
        'score' => 0,
    ],
]);

// Define actions
$agent->addTransitionRule('work', function (array $state): array {
    $state['energy'] -= 20;
    $state['score'] += 10;
    return $state;
});

$agent->addTransitionRule('rest', function (array $state): array {
    $state['energy'] = min(100, $state['energy'] + 30);
    $state['health'] = min(100, $state['health'] + 10);
    return $state;
});

$agent->addTransitionRule('exercise', function (array $state): array {
    $state['energy'] -= 15;
    $state['health'] = min(100, $state['health'] + 5);
    return $state;
});
```

### Step 2: Predict All Options

```php
echo "Current state:\n";
echo "  Energy: {$agent->getState()['energy']}\n";
echo "  Health: {$agent->getState()['health']}\n";
echo "  Score: {$agent->getState()['score']}\n\n";

// Simulate each action
$workState = $agent->predictNextState('work');
$restState = $agent->predictNextState('rest');
$exerciseState = $agent->predictNextState('exercise');

echo "If we work:\n";
echo "  Energy: {$workState['energy']}, Score: {$workState['score']}\n\n";

echo "If we rest:\n";
echo "  Energy: {$restState['energy']}, Health: {$restState['health']}\n\n";

echo "If we exercise:\n";
echo "  Energy: {$exerciseState['energy']}, Health: {$exerciseState['health']}\n\n";
```

### Step 3: Make Smart Decisions

```php
// Choose best action based on current state
$currentState = $agent->getState();

if ($currentState['energy'] < 30) {
    // Too tired, must rest
    $agent->updateState($agent->predictNextState('rest'));
    echo "Decision: Rested (energy was too low)\n";
} elseif ($currentState['health'] < 80) {
    // Health is low, should exercise
    $agent->updateState($agent->predictNextState('exercise'));
    echo "Decision: Exercised (health needed improvement)\n";
} else {
    // Conditions good, work to earn points
    $agent->updateState($agent->predictNextState('work'));
    echo "Decision: Worked (conditions optimal)\n";
}
```

### Real-World Application: Path Planning

```php
$robot = new ModelBasedAgent($client, [
    'initial_state' => [
        'x' => 0,
        'y' => 0,
        'battery' => 100,
        'target' => ['x' => 3, 'y' => 3],
    ],
]);

// Plan path by simulating moves
$path = [];
$simulatedState = $robot->getState();

while ($simulatedState['x'] < $simulatedState['target']['x']) {
    // Simulate moving east
    $tempAgent = new ModelBasedAgent($client, [
        'initial_state' => $simulatedState,
    ]);
    $tempAgent->addTransitionRule('move_east', function ($s) {
        $s['x'] += 1;
        $s['battery'] -= 5;
        return $s;
    });
    
    $simulatedState = $tempAgent->predictNextState('move_east');
    $path[] = 'move_east';
}

echo "Planned path: " . implode(' → ', $path) . "\n";
echo "Predicted battery after path: {$simulatedState['battery']}%\n";
```

---

## Chapter 4: Goal-Oriented Planning

Model-based agents can use Claude to generate action plans to achieve goals.

### Step 1: Set Up a Planning Scenario

```php
$planner = new ModelBasedAgent($client, [
    'name' => 'task_planner',
    'initial_state' => [
        'location' => 'home',
        'has_groceries' => false,
        'dinner_ready' => false,
    ],
]);
```

### Step 2: Request a Plan

```php
$result = $planner->run('Goal: Make dinner starting from home without groceries');

if ($result->isSuccess()) {
    echo "✓ Plan created!\n\n";
    echo "{$result->getAnswer()}\n\n";
    
    $metadata = $result->getMetadata();
    echo "Goal: {$metadata['goal']}\n";
    echo "Steps: " . count($metadata['planned_actions']) . "\n\n";
    
    foreach ($metadata['planned_actions'] as $i => $action) {
        echo ($i + 1) . ". {$action}\n";
    }
}
```

**Expected Output:**
```
✓ Plan created!

Plan: Go to grocery store → Buy ingredients → Return home → ...

Goal: Goal: Make dinner starting from home without groceries
Steps: 8

1. Go to grocery store
2. Buy ingredients
3. Return home
4. Prep ingredients
5. Cook meal
6. Set table
7. Serve dinner
8. Clean up
```

### Step 3: Plan with Complex Goals

```php
$projectPlanner = new ModelBasedAgent($client, [
    'initial_state' => [
        'phase' => 'not_started',
        'requirements_done' => false,
        'design_done' => false,
        'code_done' => false,
        'tested' => false,
    ],
]);

$result = $projectPlanner->run(
    'Goal: Complete software project from requirements through testing'
);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    echo "Project Plan ({$metadata['iterations']} phases):\n\n";
    
    foreach ($metadata['planned_actions'] as $i => $action) {
        echo "Phase " . ($i + 1) . ": {$action}\n";
    }
}
```

### Understanding Planning

**How it works:**
1. Agent detects goal keywords: "goal", "plan", "achieve", "reach", etc.
2. Sends current state and goal to Claude
3. Claude generates a sequence of actions
4. Agent returns the plan (doesn't execute it)

**Goal Keywords:**
- "Goal: ..."
- "I want to achieve..."
- "Plan to reach..."
- "How to get to..."
- "Make..."

---

## Chapter 5: Learning from Observations

Agents can extract state changes from natural language descriptions.

### Step 1: Create a Monitoring Agent

```php
$monitor = new ModelBasedAgent($client, [
    'name' => 'environment_monitor',
    'initial_state' => [
        'temperature' => 72,
        'humidity' => 50,
        'air_quality' => 'good',
    ],
]);

echo "Initial environment:\n";
print_r($monitor->getState());
```

### Step 2: Process Observations

```php
// Agent extracts state changes from natural language
$result = $monitor->run('The temperature increased to 75 degrees');

if ($result->isSuccess()) {
    echo "\n✓ Observation processed\n";
    echo "New state:\n";
    print_r($monitor->getState());
}
```

### Step 3: Multiple Observations

```php
$monitor->run('Humidity rose to 65% due to rain');
$monitor->run('Air quality degraded to moderate');

echo "\nFinal environment state:\n";
echo "Temperature: {$monitor->getState()['temperature']}°F\n";
echo "Humidity: {$monitor->getState()['humidity']}%\n";
echo "Air Quality: {$monitor->getState()['air_quality']}\n";
```

### Real-World Application: System Monitoring

```php
$systemMonitor = new ModelBasedAgent($client, [
    'initial_state' => [
        'cpu_usage' => 20,
        'memory_usage' => 30,
        'disk_usage' => 40,
        'status' => 'healthy',
    ],
]);

// Process log entries
$systemMonitor->run('CPU usage spiked to 85% at 14:30');
$systemMonitor->run('Memory consumption increased to 75%');
$systemMonitor->run('Disk usage is now at 60%');

// Check if system needs attention
$state = $systemMonitor->getState();
if ($state['cpu_usage'] > 80 || $state['memory_usage'] > 80) {
    echo "⚠️  System alert: High resource usage detected\n";
}
```

---

## Chapter 6: State History and Analysis

Track how your world model changes over time.

### Step 1: Create an Agent and Make Changes

```php
$tracker = new ModelBasedAgent($client, [
    'initial_state' => ['score' => 0, 'level' => 1],
]);

// Simulate gameplay
$tracker->updateState(['score' => 100, 'level' => 1]);
$tracker->updateState(['score' => 250, 'level' => 2]);
$tracker->updateState(['score' => 500, 'level' => 3]);
$tracker->updateState(['score' => 1000, 'level' => 4]);
```

### Step 2: Analyze History

```php
$history = $tracker->getStateHistory();

echo "Game progression ({$count($history)} level ups):\n\n";

foreach ($history as $i => $entry) {
    $prev = $entry['previous'];
    $curr = $entry['current'];
    
    echo "Transition " . ($i + 1) . ":\n";
    echo "  Score: {$prev['score']} → {$curr['score']}\n";
    echo "  Level: {$prev['level']} → {$curr['level']}\n";
    echo "  Time: " . date('H:i:s', (int)$entry['timestamp']) . "\n\n";
}
```

### Step 3: Calculate Metrics

```php
$history = $tracker->getStateHistory();

if (!empty($history)) {
    $first = $history[0]['previous'];
    $last = $history[count($history) - 1]['current'];
    
    $scoreGained = $last['score'] - $first['score'];
    $levelsGained = $last['level'] - $first['level'];
    
    echo "Performance Summary:\n";
    echo "  Total score gained: {$scoreGained}\n";
    echo "  Total levels gained: {$levelsGained}\n";
    echo "  Average score per level: " . ($scoreGained / $levelsGained) . "\n";
}
```

### History Limits

By default, agents keep the last 100 state transitions:

```php
$agent = new ModelBasedAgent($client, [
    'max_history' => 50, // Keep only last 50 transitions
    'initial_state' => ['x' => 0],
]);
```

---

## Chapter 7: Complex State Structures

Handle nested and complex state models.

### Example 1: Smart Home System

```php
$smartHome = new ModelBasedAgent($client, [
    'initial_state' => [
        'rooms' => [
            'living_room' => [
                'temperature' => 72,
                'lights' => 'off',
                'occupancy' => false,
            ],
            'bedroom' => [
                'temperature' => 70,
                'lights' => 'off',
                'occupancy' => false,
            ],
            'kitchen' => [
                'temperature' => 68,
                'lights' => 'off',
                'occupancy' => false,
            ],
        ],
        'hvac' => [
            'mode' => 'auto',
            'target_temp' => 72,
        ],
        'security' => [
            'armed' => false,
        ],
    ],
]);
```

### Complex Transition Rule

```php
$smartHome->addTransitionRule('person_enters_living_room', function (array $state): array {
    // Turn on lights
    $state['rooms']['living_room']['lights'] = 'on';
    $state['rooms']['living_room']['occupancy'] = true;
    
    // Adjust HVAC if needed
    $currentTemp = $state['rooms']['living_room']['temperature'];
    $targetTemp = $state['hvac']['target_temp'];
    
    if ($currentTemp < $targetTemp - 2) {
        $state['hvac']['mode'] = 'heat';
    } elseif ($currentTemp > $targetTemp + 2) {
        $state['hvac']['mode'] = 'cool';
    }
    
    return $state;
});
```

### Example 2: Game Character

```php
$character = new ModelBasedAgent($client, [
    'initial_state' => [
        'stats' => [
            'health' => 100,
            'mana' => 50,
            'stamina' => 100,
        ],
        'position' => [
            'x' => 0,
            'y' => 0,
            'zone' => 'starting_area',
        ],
        'inventory' => [
            'weapons' => ['wooden_sword'],
            'armor' => ['cloth_shirt'],
            'items' => ['health_potion', 'health_potion'],
            'gold' => 10,
        ],
        'quests' => [
            ['id' => 'quest_1', 'status' => 'active', 'progress' => 0],
        ],
    ],
]);
```

---

## Chapter 8: Advanced Patterns

### Pattern 1: Conditional Logic

```php
$agent->addTransitionRule('smart_attack', function (array $state): array {
    // Only attack if we have enough mana
    if ($state['mana'] >= 10) {
        $state['mana'] -= 10;
        $state['damage_dealt'] = 50;
    } else {
        // Use weak physical attack instead
        $state['stamina'] -= 5;
        $state['damage_dealt'] = 20;
    }
    
    return $state;
});
```

### Pattern 2: State Validation

```php
$agent->addTransitionRule('move_with_bounds', function (array $state): array {
    $newX = $state['x'] + 1;
    
    // Enforce boundaries
    if ($newX < 0) {
        $newX = 0;
    } elseif ($newX > 100) {
        $newX = 100;
    }
    
    $state['x'] = $newX;
    return $state;
});
```

### Pattern 3: Multi-Agent Coordination

```php
// Shared warehouse state
$warehouseState = [
    'inventory' => ['widgets' => 1000, 'gadgets' => 500],
    'robots' => [
        'robot1' => ['position' => [0, 0], 'carrying' => null],
        'robot2' => ['position' => [5, 5], 'carrying' => null],
    ],
];

// Each robot has same view of warehouse
$robot1 = new ModelBasedAgent($client, [
    'name' => 'robot1',
    'initial_state' => $warehouseState,
]);

$robot2 = new ModelBasedAgent($client, [
    'name' => 'robot2',
    'initial_state' => $warehouseState,
]);

// Coordinate actions
$plan1 = $robot1->run('Goal: Pick widgets from location A');
$plan2 = $robot2->run('Goal: Pick gadgets from location B');
```

### Pattern 4: Rollback/Undo

```php
// Save checkpoint
$checkpoint = $agent->getState();

// Make risky changes
$agent->updateState(['experimental_feature' => true]);
$agent->updateState(['value' => 999]);

// Rollback if needed
if ($someConditionFailed) {
    $agent->updateState($checkpoint);
    echo "Rolled back to checkpoint\n";
}
```

---

## Chapter 9: Real-World Application - Trading Bot

Let's build a complete financial trading decision system.

### Step 1: Set Up the Agent

```php
$trader = new ModelBasedAgent($client, [
    'name' => 'trading_bot',
    'initial_state' => [
        'portfolio' => [
            'cash' => 10000,
            'positions' => [
                'AAPL' => ['shares' => 10, 'avg_price' => 150],
                'GOOGL' => ['shares' => 5, 'avg_price' => 2800],
            ],
        ],
        'market' => [
            'AAPL' => ['price' => 155, 'trend' => 'up', 'volatility' => 'low'],
            'GOOGL' => ['price' => 2750, 'trend' => 'down', 'volatility' => 'high'],
            'MSFT' => ['price' => 380, 'trend' => 'stable', 'volatility' => 'low'],
        ],
        'risk_tolerance' => 'moderate',
    ],
]);
```

### Step 2: Define Trading Rules

```php
$trader->addTransitionRule('buy_stock', function (array $state, array $params): array {
    $symbol = $params['symbol'];
    $shares = $params['shares'];
    $price = $state['market'][$symbol]['price'];
    $cost = $price * $shares;
    
    // Check if we have enough cash
    if ($state['portfolio']['cash'] >= $cost) {
        $state['portfolio']['cash'] -= $cost;
        
        if (isset($state['portfolio']['positions'][$symbol])) {
            // Add to existing position
            $existing = $state['portfolio']['positions'][$symbol];
            $totalShares = $existing['shares'] + $shares;
            $avgPrice = (($existing['shares'] * $existing['avg_price']) + $cost) / $totalShares;
            
            $state['portfolio']['positions'][$symbol] = [
                'shares' => $totalShares,
                'avg_price' => $avgPrice,
            ];
        } else {
            // New position
            $state['portfolio']['positions'][$symbol] = [
                'shares' => $shares,
                'avg_price' => $price,
            ];
        }
    }
    
    return $state;
});

$trader->addTransitionRule('sell_stock', function (array $state, array $params): array {
    $symbol = $params['symbol'];
    $shares = $params['shares'];
    
    if (isset($state['portfolio']['positions'][$symbol])) {
        $position = $state['portfolio']['positions'][$symbol];
        
        if ($position['shares'] >= $shares) {
            $price = $state['market'][$symbol]['price'];
            $revenue = $price * $shares;
            
            $state['portfolio']['cash'] += $revenue;
            $state['portfolio']['positions'][$symbol]['shares'] -= $shares;
            
            // Remove position if fully sold
            if ($state['portfolio']['positions'][$symbol]['shares'] == 0) {
                unset($state['portfolio']['positions'][$symbol]);
            }
        }
    }
    
    return $state;
});
```

### Step 3: Analyze and Execute Strategy

```php
// Calculate current portfolio value
function calculatePortfolioValue($state): float {
    $value = $state['portfolio']['cash'];
    
    foreach ($state['portfolio']['positions'] as $symbol => $position) {
        $currentPrice = $state['market'][$symbol]['price'];
        $value += $position['shares'] * $currentPrice;
    }
    
    return $value;
}

echo "Current Portfolio Value: $" . number_format(calculatePortfolioValue($trader->getState()), 2) . "\n\n";

// Get AI strategy
$result = $trader->run('Goal: Optimize portfolio by reducing risk and improving returns');

if ($result->isSuccess()) {
    echo "Trading Strategy:\n";
    echo $result->getAnswer() . "\n\n";
}

// Simulate selling declining stock (GOOGL)
echo "Simulating: Sell GOOGL (declining trend)\n";
// Note: Would need to enhance the rule to accept parameters
// This is a simplified example
```

---

## Chapter 10: Best Practices and Tips

### Do's ✓

1. **Use Rules for Deterministic Actions**
   ```php
   // Good: Fast, predictable
   $agent->addTransitionRule('increment', fn($s) => ['count' => $s['count'] + 1]);
   ```

2. **Keep State Simple**
   ```php
   // Good: Flat, clear structure
   ['x' => 5, 'y' => 3, 'active' => true]
   ```

3. **Validate State Changes**
   ```php
   $agent->addTransitionRule('safe_update', function ($state) {
       $state['value'] = max(0, min(100, $state['value']));
       return $state;
   });
   ```

4. **Use Predictive Modeling**
   ```php
   // Simulate before committing
   $result = $agent->predictNextState('risky_action');
   if ($result['health'] > 0) {
       $agent->updateState($result);
   }
   ```

### Don'ts ✗

1. **Don't Mutate State Directly**
   ```php
   // Bad: Bypasses history tracking
   $state = $agent->getState();
   $state['x'] = 10; // This won't work!
   
   // Good: Use updateState
   $agent->updateState(['x' => 10]);
   ```

2. **Don't Overuse LLM Predictions**
   ```php
   // Bad: Slow, expensive
   for ($i = 0; $i < 1000; $i++) {
       $agent->predictNextState('unknown_action'); // Calls LLM each time
   }
   
   // Good: Use rules for repeated actions
   $agent->addTransitionRule('known_action', $fastFunction);
   ```

3. **Don't Forget History Limits**
   ```php
   // Bad: Unlimited history growth
   // (Actually capped at 100 by default, but be aware)
   
   // Good: Set appropriate limit
   $agent = new ModelBasedAgent($client, ['max_history' => 50]);
   ```

---

## Conclusion

Congratulations! You've learned how to:

✓ Create and manage world state models  
✓ Define deterministic transition rules  
✓ Predict outcomes before committing  
✓ Generate plans to achieve goals  
✓ Learn from natural language observations  
✓ Track state history and analyze transitions  
✓ Build complex real-world applications  

### Next Steps

1. **Explore Examples**: Run the examples in `examples/model_based_agent.php`
2. **Read Documentation**: Check `docs/ModelBasedAgent.md` for complete API reference
3. **Build Something**: Create your own model-based application
4. **Combine Agents**: Use with ReactAgent, CoordinatorAgent, etc.

### Additional Resources

- [ModelBasedAgent API Documentation](../ModelBasedAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [AutonomousAgent Tutorial](AutonomousAgent_Tutorial.md)
- [CoordinatorAgent Tutorial](CoordinatorAgent_Tutorial.md)

---

## Quick Reference

```php
// Create agent
$agent = new ModelBasedAgent($client, [
    'name' => 'my_agent',
    'initial_state' => ['x' => 0],
]);

// Get/update state
$state = $agent->getState();
$agent->updateState(['x' => 5]);

// Add transition rule
$agent->addTransitionRule('action', fn($s) => ['x' => $s['x'] + 1]);

// Predict without changing state
$predicted = $agent->predictNextState('action');

// Plan to achieve goal
$result = $agent->run('Goal: reach target');

// Learn from observation
$result = $agent->run('The value increased to 10');

// Get history
$history = $agent->getStateHistory();
```

Happy coding! 🚀

