<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\ModelBasedAgent;
use ClaudePhp\ClaudePhp;

// Load environment variables
$dotenvFile = __DIR__ . '/../.env';
if (file_exists($dotenvFile)) {
    $lines = file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');
if (empty($apiKey)) {
    throw new RuntimeException('ANTHROPIC_API_KEY not set in environment or .env file');
}

echo "=================================================================\n";
echo "Model-Based Agent Example\n";
echo "=================================================================\n\n";

echo "This example demonstrates a Model-Based Agent that:\n";
echo "1. Maintains an internal model of the world state\n";
echo "2. Tracks state transitions and history\n";
echo "3. Uses the model to predict outcomes of actions\n";
echo "4. Plans sequences of actions to achieve goals\n\n";

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

// =================================================================
// Example 1: Basic World Model with State Transitions
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 1: Robot Navigation with State Model\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

// Create a robot navigation agent with initial state
$robot = new ModelBasedAgent($client, [
    'name' => 'robot_navigator',
    'initial_state' => [
        'position' => ['x' => 0, 'y' => 0],
        'battery' => 100,
        'items_collected' => [],
    ],
]);

echo "Initial State:\n";
echo json_encode($robot->getState(), JSON_PRETTY_PRINT) . "\n\n";

// Define transition rules for robot actions
$robot->addTransitionRule('move_north', function (array $state): array {
    $state['position']['y'] += 1;
    $state['battery'] -= 5;
    return $state;
});

$robot->addTransitionRule('move_south', function (array $state): array {
    $state['position']['y'] -= 1;
    $state['battery'] -= 5;
    return $state;
});

$robot->addTransitionRule('move_east', function (array $state): array {
    $state['position']['x'] += 1;
    $state['battery'] -= 5;
    return $state;
});

$robot->addTransitionRule('move_west', function (array $state): array {
    $state['position']['x'] -= 1;
    $state['battery'] -= 5;
    return $state;
});

$robot->addTransitionRule('collect_item', function (array $state): array {
    $state['items_collected'][] = 'item_' . count($state['items_collected']);
    $state['battery'] -= 2;
    return $state;
});

echo "Predicting outcome of moving north:\n";
$predictedState = $robot->predictNextState('move_north');
echo "Predicted position: ({$predictedState['position']['x']}, {$predictedState['position']['y']})\n";
echo "Predicted battery: {$predictedState['battery']}%\n\n";

echo "Actually moving north:\n";
$robot->updateState([
    'position' => ['x' => 0, 'y' => 1],
    'battery' => 95,
]);
echo "Current state after move:\n";
echo json_encode($robot->getState(), JSON_PRETTY_PRINT) . "\n\n";

echo "Collecting an item:\n";
$robot->updateState([
    'items_collected' => ['item_0'],
    'battery' => 93,
]);
echo "Items collected: " . count($robot->getState()['items_collected']) . "\n";
echo "Battery remaining: {$robot->getState()['battery']}%\n\n";

echo "State history entries: " . count($robot->getStateHistory()) . "\n\n";

// =================================================================
// Example 2: Goal-Oriented Planning
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 2: Goal-Oriented Planning\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

// Create a new agent for planning
$planner = new ModelBasedAgent($client, [
    'name' => 'task_planner',
    'initial_state' => [
        'location' => 'home',
        'has_groceries' => false,
        'dinner_cooked' => false,
    ],
]);

echo "Initial State:\n";
echo json_encode($planner->getState(), JSON_PRETTY_PRINT) . "\n\n";

echo "Setting goal: Plan to cook dinner\n\n";

$result = $planner->run('Goal: Make dinner starting from home without groceries');

if ($result->isSuccess()) {
    echo "✓ Plan Created!\n\n";
    echo "{$result->getAnswer()}\n\n";
    
    $metadata = $result->getMetadata();
    echo "Goal: {$metadata['goal']}\n";
    echo "Number of planned steps: " . count($metadata['planned_actions']) . "\n\n";
    
    echo "Detailed Plan:\n";
    foreach ($metadata['planned_actions'] as $i => $action) {
        echo "  Step " . ($i + 1) . ": {$action}\n";
    }
} else {
    echo "✗ Planning failed: {$result->getError()}\n";
}

echo "\n";

// =================================================================
// Example 3: Observation and State Updates
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 3: Learning from Observations\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

// Create agent for environment monitoring
$monitor = new ModelBasedAgent($client, [
    'name' => 'environment_monitor',
    'initial_state' => [
        'temperature' => 72,
        'humidity' => 45,
        'air_quality' => 'good',
    ],
]);

echo "Initial Environment State:\n";
echo json_encode($monitor->getState(), JSON_PRETTY_PRINT) . "\n\n";

echo "Observation 1: Temperature sensor shows 75 degrees\n";
$result1 = $monitor->run('Temperature sensor shows 75 degrees');

if ($result1->isSuccess()) {
    echo "✓ State updated\n";
    echo "New temperature: {$monitor->getState()['temperature']}\n\n";
}

echo "Observation 2: Humidity increased due to rain\n";
$result2 = $monitor->run('It started raining, humidity is now 70%');

if ($result2->isSuccess()) {
    echo "✓ State updated\n";
    echo "New humidity: {$monitor->getState()['humidity']}\n\n";
}

echo "State History:\n";
echo "Total state transitions: " . count($monitor->getStateHistory()) . "\n\n";

// =================================================================
// Example 4: Complex Multi-Step Planning
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 4: Project Planning with Dependencies\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$projectAgent = new ModelBasedAgent($client, [
    'name' => 'project_planner',
    'initial_state' => [
        'phase' => 'not_started',
        'requirements' => 'none',
        'design' => 'none',
        'implementation' => 'none',
        'testing' => 'none',
    ],
]);

echo "Project Initial State: Not Started\n\n";

$result = $projectAgent->run('Goal: Plan and complete a software project from requirements to testing');

if ($result->isSuccess()) {
    echo "✓ Project Plan Created!\n\n";
    echo "{$result->getAnswer()}\n\n";
    
    $metadata = $result->getMetadata();
    
    echo "Execution Plan:\n";
    foreach ($metadata['planned_actions'] as $i => $action) {
        echo sprintf("  Phase %d: %s\n", $i + 1, $action);
    }
    
    echo "\n";
    echo "Total phases: " . count($metadata['planned_actions']) . "\n";
}

echo "\n";

// =================================================================
// Example 5: Predictive Modeling
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 5: Predicting Multiple Action Sequences\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$simulator = new ModelBasedAgent($client, [
    'name' => 'action_simulator',
    'initial_state' => [
        'energy' => 100,
        'health' => 100,
        'score' => 0,
    ],
]);

// Define game-like transition rules
$simulator->addTransitionRule('rest', function (array $state): array {
    $state['energy'] = min(100, $state['energy'] + 30);
    $state['health'] = min(100, $state['health'] + 10);
    return $state;
});

$simulator->addTransitionRule('work', function (array $state): array {
    $state['energy'] -= 20;
    $state['score'] += 10;
    return $state;
});

$simulator->addTransitionRule('exercise', function (array $state): array {
    $state['energy'] -= 15;
    $state['health'] = min(100, $state['health'] + 5);
    return $state;
});

echo "Simulating different action sequences:\n\n";

echo "Current state:\n";
echo "Energy: {$simulator->getState()['energy']}, ";
echo "Health: {$simulator->getState()['health']}, ";
echo "Score: {$simulator->getState()['score']}\n\n";

echo "Scenario 1: If we work...\n";
$workState = $simulator->predictNextState('work');
echo "  Energy: {$workState['energy']}, Score: {$workState['score']}\n\n";

echo "Scenario 2: If we rest...\n";
$restState = $simulator->predictNextState('rest');
echo "  Energy: {$restState['energy']}, Health: {$restState['health']}\n\n";

echo "Scenario 3: If we exercise...\n";
$exerciseState = $simulator->predictNextState('exercise');
echo "  Energy: {$exerciseState['energy']}, Health: {$exerciseState['health']}\n\n";

echo "Agent can predict outcomes without actually changing state!\n";
echo "Actual state remains: Energy={$simulator->getState()['energy']}\n\n";

// =================================================================
// Key Takeaways
// =================================================================

echo "=================================================================\n";
echo "Key Features Demonstrated:\n";
echo "=================================================================\n\n";

echo "✓ Maintain internal world model with state variables\n";
echo "✓ Track complete state transition history\n";
echo "✓ Define deterministic transition rules for known actions\n";
echo "✓ Use LLM to predict unknown action outcomes\n";
echo "✓ Extract state changes from natural language observations\n";
echo "✓ Generate action plans to achieve stated goals\n";
echo "✓ Simulate outcomes without modifying actual state\n\n";

echo "Model-Based Agents are ideal for:\n";
echo "  • Robotics and navigation\n";
echo "  • Game AI and simulations\n";
echo "  • Planning and scheduling\n";
echo "  • Monitoring and control systems\n";
echo "  • What-if scenario analysis\n\n";

