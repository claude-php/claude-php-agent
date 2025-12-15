<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\ModelBasedAgent;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
echo "Advanced Model-Based Agent Example\n";
echo "=================================================================\n\n";

echo "This advanced example demonstrates:\n";
echo "1. Complex state space with nested structures\n";
echo "2. Conditional transition rules\n";
echo "3. Multi-agent coordination using shared world model\n";
echo "4. Advanced planning with constraints\n";
echo "5. Real-time state monitoring and logging\n\n";

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

// Setup logging
$logger = new Logger('model_based_agent');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// =================================================================
// Example 1: Smart Home Automation with Complex State
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 1: Smart Home Automation System\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$smartHome = new ModelBasedAgent($client, [
    'name' => 'smart_home_controller',
    'initial_state' => [
        'rooms' => [
            'living_room' => ['temperature' => 72, 'lights' => 'off', 'occupancy' => false],
            'bedroom' => ['temperature' => 70, 'lights' => 'off', 'occupancy' => false],
            'kitchen' => ['temperature' => 68, 'lights' => 'off', 'occupancy' => false],
        ],
        'hvac' => ['mode' => 'auto', 'target_temp' => 72, 'power_consumption' => 0],
        'security' => ['armed' => false, 'motion_detected' => false],
        'time_of_day' => 'morning',
        'energy_usage' => 0,
    ],
    'logger' => $logger,
]);

echo "Smart Home Initial State:\n";
echo json_encode($smartHome->getState(), JSON_PRETTY_PRINT) . "\n\n";

// Complex transition rules with conditions
$smartHome->addTransitionRule('person_enters_room', function (array $state): array {
    // Simulate person entering living room
    $state['rooms']['living_room']['occupancy'] = true;
    $state['rooms']['living_room']['lights'] = 'on';
    $state['energy_usage'] += 10; // Lights use energy
    
    // Adjust HVAC if needed
    if ($state['rooms']['living_room']['temperature'] < $state['hvac']['target_temp'] - 2) {
        $state['hvac']['mode'] = 'heat';
        $state['hvac']['power_consumption'] = 50;
    }
    
    return $state;
});

$smartHome->addTransitionRule('person_leaves_room', function (array $state): array {
    $state['rooms']['living_room']['occupancy'] = false;
    
    // Check if all rooms are empty
    $anyOccupied = false;
    foreach ($state['rooms'] as $room) {
        if ($room['occupancy']) {
            $anyOccupied = true;
            break;
        }
    }
    
    // If no one is home, turn off lights and reduce HVAC
    if (!$anyOccupied) {
        foreach ($state['rooms'] as $roomName => &$room) {
            $room['lights'] = 'off';
        }
        $state['hvac']['mode'] = 'eco';
        $state['hvac']['power_consumption'] = 10;
        $state['energy_usage'] = 10;
    }
    
    return $state;
});

$smartHome->addTransitionRule('night_mode', function (array $state): array {
    $state['time_of_day'] = 'night';
    $state['security']['armed'] = true;
    $state['hvac']['target_temp'] = 68; // Lower temp at night
    
    // Dim lights in occupied rooms
    foreach ($state['rooms'] as $roomName => &$room) {
        if ($room['occupancy']) {
            $room['lights'] = 'dim';
        }
    }
    
    return $state;
});

echo "Simulating: Person enters living room\n";
$newState = $smartHome->predictNextState('person_enters_room');
echo "Predicted lights: {$newState['rooms']['living_room']['lights']}\n";
echo "Predicted energy usage: {$newState['energy_usage']}W\n\n";

// Apply the state change
$smartHome->updateState($newState);

echo "Simulating: Switching to night mode\n";
$nightState = $smartHome->predictNextState('night_mode');
echo "Security armed: " . ($nightState['security']['armed'] ? 'Yes' : 'No') . "\n";
echo "Target temperature: {$nightState['hvac']['target_temp']}°F\n\n";

// =================================================================
// Example 2: Multi-Agent Warehouse Management
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 2: Multi-Agent Warehouse System\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

// Shared world model for warehouse
$warehouseState = [
    'inventory' => [
        'A1' => ['item' => 'widget', 'quantity' => 100, 'location' => ['x' => 0, 'y' => 0]],
        'A2' => ['item' => 'gadget', 'quantity' => 50, 'location' => ['x' => 0, 'y' => 1]],
        'B1' => ['item' => 'tool', 'quantity' => 75, 'location' => ['x' => 1, 'y' => 0]],
    ],
    'robots' => [
        'robot1' => ['position' => ['x' => 0, 'y' => 0], 'carrying' => null, 'battery' => 100],
        'robot2' => ['position' => ['x' => 1, 'y' => 1], 'carrying' => null, 'battery' => 100],
    ],
    'orders' => [
        ['id' => 'ORD001', 'item' => 'widget', 'quantity' => 5, 'status' => 'pending'],
        ['id' => 'ORD002', 'item' => 'gadget', 'quantity' => 3, 'status' => 'pending'],
    ],
    'shipping_dock' => ['x' => 2, 'y' => 2],
];

// Robot 1 agent with shared model
$robot1 = new ModelBasedAgent($client, [
    'name' => 'warehouse_robot_1',
    'initial_state' => $warehouseState,
    'logger' => $logger,
]);

// Robot 2 agent with same shared model
$robot2 = new ModelBasedAgent($client, [
    'name' => 'warehouse_robot_2',
    'initial_state' => $warehouseState,
    'logger' => $logger,
]);

echo "Warehouse Initial State:\n";
echo "Active Orders: " . count($warehouseState['orders']) . "\n";
echo "Available Robots: " . count($warehouseState['robots']) . "\n";
echo "Inventory Locations: " . count($warehouseState['inventory']) . "\n\n";

echo "Robot 1 Planning:\n";
$robot1Result = $robot1->run('Goal: Pick order ORD001 (5 widgets) from location A1 and deliver to shipping dock');

if ($robot1Result->isSuccess()) {
    echo "✓ Robot 1 Plan:\n";
    $metadata = $robot1Result->getMetadata();
    foreach ($metadata['planned_actions'] as $i => $action) {
        echo "  Step " . ($i + 1) . ": {$action}\n";
    }
}

echo "\n";

echo "Robot 2 Planning:\n";
$robot2Result = $robot2->run('Goal: Pick order ORD002 (3 gadgets) from location A2 and deliver to shipping dock');

if ($robot2Result->isSuccess()) {
    echo "✓ Robot 2 Plan:\n";
    $metadata = $robot2Result->getMetadata();
    foreach ($metadata['planned_actions'] as $i => $action) {
        echo "  Step " . ($i + 1) . ": {$action}\n";
    }
}

echo "\n";

// =================================================================
// Example 3: Financial Trading with State Constraints
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 3: Financial Trading Decision System\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$tradingAgent = new ModelBasedAgent($client, [
    'name' => 'trading_agent',
    'initial_state' => [
        'portfolio' => [
            'cash' => 10000,
            'stocks' => [
                'AAPL' => ['shares' => 10, 'avg_price' => 150],
                'GOOGL' => ['shares' => 5, 'avg_price' => 2800],
            ],
        ],
        'market' => [
            'AAPL' => ['price' => 155, 'trend' => 'up'],
            'GOOGL' => ['price' => 2750, 'trend' => 'down'],
            'MSFT' => ['price' => 380, 'trend' => 'stable'],
        ],
        'risk_tolerance' => 'moderate',
        'total_value' => 24750,
    ],
    'logger' => $logger,
]);

// Trading rules with constraints
$tradingAgent->addTransitionRule('buy_stock', function (array $state): array {
    // Example: Buy MSFT if we have cash
    $symbol = 'MSFT';
    $price = $state['market'][$symbol]['price'];
    $shares = 5;
    $cost = $price * $shares;
    
    if ($state['portfolio']['cash'] >= $cost) {
        $state['portfolio']['cash'] -= $cost;
        $state['portfolio']['stocks'][$symbol] = [
            'shares' => $shares,
            'avg_price' => $price,
        ];
        $state['total_value'] = $state['portfolio']['cash'];
        foreach ($state['portfolio']['stocks'] as $stock => $data) {
            $state['total_value'] += $data['shares'] * $state['market'][$stock]['price'];
        }
    }
    
    return $state;
});

$tradingAgent->addTransitionRule('sell_stock', function (array $state): array {
    // Example: Sell GOOGL (downward trend)
    $symbol = 'GOOGL';
    if (isset($state['portfolio']['stocks'][$symbol])) {
        $shares = $state['portfolio']['stocks'][$symbol]['shares'];
        $price = $state['market'][$symbol]['price'];
        $revenue = $shares * $price;
        
        $state['portfolio']['cash'] += $revenue;
        unset($state['portfolio']['stocks'][$symbol]);
        
        $state['total_value'] = $state['portfolio']['cash'];
        foreach ($state['portfolio']['stocks'] as $stock => $data) {
            $state['total_value'] += $data['shares'] * $state['market'][$stock]['price'];
        }
    }
    
    return $state;
});

echo "Current Portfolio:\n";
echo "Cash: $" . number_format($tradingAgent->getState()['portfolio']['cash']) . "\n";
echo "Total Value: $" . number_format($tradingAgent->getState()['total_value']) . "\n\n";

echo "Market Analysis:\n";
foreach ($tradingAgent->getState()['market'] as $symbol => $data) {
    echo "  {$symbol}: \${$data['price']} (trend: {$data['trend']})\n";
}
echo "\n";

echo "Strategy Planning:\n";
$result = $tradingAgent->run('Goal: Optimize portfolio by selling declining stocks and buying stable ones');

if ($result->isSuccess()) {
    echo "✓ Trading Strategy:\n";
    echo "{$result->getAnswer()}\n\n";
}

echo "Simulating: Selling GOOGL (downward trend)\n";
$afterSell = $tradingAgent->predictNextState('sell_stock');
echo "Predicted cash after sale: $" . number_format($afterSell['portfolio']['cash']) . "\n\n";

echo "Simulating: Buying MSFT (stable)\n";
$tradingAgent->updateState($afterSell);
$afterBuy = $tradingAgent->predictNextState('buy_stock');
echo "Predicted portfolio value: $" . number_format($afterBuy['total_value']) . "\n\n";

// =================================================================
// Example 4: State History Analysis
// =================================================================

echo "─────────────────────────────────────────────────────────────────\n";
echo "Example 4: State History and Trajectory Analysis\n";
echo "─────────────────────────────────────────────────────────────────\n\n";

$tracker = new ModelBasedAgent($client, [
    'name' => 'performance_tracker',
    'initial_state' => [
        'metrics' => ['speed' => 0, 'accuracy' => 0, 'efficiency' => 0],
        'timestamp' => time(),
    ],
    'logger' => $logger,
]);

// Simulate state changes over time
echo "Tracking performance over time:\n\n";

for ($i = 1; $i <= 5; $i++) {
    $tracker->updateState([
        'metrics' => [
            'speed' => 50 + ($i * 10),
            'accuracy' => 70 + ($i * 5),
            'efficiency' => 60 + ($i * 8),
        ],
        'timestamp' => time() + $i,
    ]);
    
    $state = $tracker->getState();
    echo "Time {$i}: Speed={$state['metrics']['speed']}, ";
    echo "Accuracy={$state['metrics']['accuracy']}, ";
    echo "Efficiency={$state['metrics']['efficiency']}\n";
}

echo "\n";

echo "Analyzing State History:\n";
$history = $tracker->getStateHistory();
echo "Total state transitions: " . count($history) . "\n";

if (count($history) > 0) {
    $first = $history[0]['previous']['metrics'];
    $last = $history[count($history) - 1]['current']['metrics'];
    
    echo "\nPerformance Improvement:\n";
    echo "  Speed: " . ($last['speed'] - $first['speed']) . " points\n";
    echo "  Accuracy: " . ($last['accuracy'] - $first['accuracy']) . " points\n";
    echo "  Efficiency: " . ($last['efficiency'] - $first['efficiency']) . " points\n";
}

echo "\n";

// =================================================================
// Key Takeaways
// =================================================================

echo "=================================================================\n";
echo "Advanced Features Demonstrated:\n";
echo "=================================================================\n\n";

echo "✓ Complex nested state structures\n";
echo "✓ Conditional transition rules with business logic\n";
echo "✓ Multi-agent coordination with shared world models\n";
echo "✓ Constraint-based planning and validation\n";
echo "✓ State history tracking and trajectory analysis\n";
echo "✓ Real-world applications: Smart home, warehouse, trading\n";
echo "✓ Integration with logging for observability\n\n";

echo "Best Practices:\n";
echo "  • Keep state structure flat when possible\n";
echo "  • Use transition rules for deterministic changes\n";
echo "  • Leverage LLM for complex, context-dependent decisions\n";
echo "  • Track history for debugging and learning\n";
echo "  • Share models across agents for coordination\n";
echo "  • Add constraints to prevent invalid states\n\n";

