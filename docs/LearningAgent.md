# LearningAgent Documentation

## Overview

The `LearningAgent` is an adaptive agent that learns from experience and feedback, continuously improving its performance over time. It uses reinforcement learning principles including experience replay, strategy selection, and performance tracking to optimize task execution.

## Key Features

- **Experience Replay**: Maintains a buffer of past experiences for learning
- **Multiple Strategies**: Supports multiple problem-solving strategies
- **Adaptive Behavior**: Learns which strategies work best for different tasks
- **Performance Tracking**: Monitors success rates and rewards per strategy
- **Epsilon-Greedy Selection**: Balances exploration and exploitation
- **Feedback Loop**: Improves based on explicit feedback
- **Pattern Recognition**: Identifies successful patterns in task execution
- **Strategy Evolution**: Adds/removes strategies based on performance
- **Parameter Tuning**: Self-adjusts learning parameters

## Architecture

```
┌─────────────────────────────────────┐
│       LearningAgent                 │
├─────────────────────────────────────┤
│ - ClaudePhp client                  │
│ - Experience buffer                 │
│ - Strategy performance tracking     │
│ - Learning rate                     │
│ - Strategy selection (ε-greedy)     │
└──────────┬──────────────────────────┘
           │
           ├─── Experience Replay
           │    ├─── Task
           │    ├─── Strategy used
           │    ├─── Result
           │    ├─── Reward
           │    └─── Feedback
           │
           ├─── Performance Metrics
           │    ├─── Attempts
           │    ├─── Successes
           │    ├─── Total reward
           │    └─── Avg reward
           │
           └─── Learning Process
                ├─── Pattern recognition
                ├─── Strategy evolution
                ├─── Parameter tuning
                └─── Experience pruning
```

## Classes

### LearningAgent

The main agent class that implements adaptive learning behavior.

**Namespace**: `ClaudeAgents\Agents`

**Implements**: `AgentInterface`

**Properties**:
- `client` - Claude API client
- `name` - Agent name
- `experiences` - Experience replay buffer
- `performance` - Performance metrics per strategy
- `strategies` - Available strategies
- `learningRate` - Learning rate (0-1)
- `replayBufferSize` - Max experiences to keep
- `logger` - PSR-3 logger

## Usage

### Basic Usage

```php
use ClaudeAgents\Agents\LearningAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new LearningAgent($client, [
    'initial_strategies' => ['analytical', 'creative', 'systematic'],
]);

// Run a task
$result = $agent->run('Solve this complex problem');

if ($result->isSuccess()) {
    // Provide feedback
    $expId = $result->getMetadata()['experience_id'];
    $agent->provideFeedback($expId, reward: 0.9, success: true);
}
```

### Configuration Options

```php
$agent = new LearningAgent($client, [
    // Optional
    'name' => 'my_learner',                    // Agent name
    'learning_rate' => 0.1,                     // Learning rate (0-1)
    'replay_buffer_size' => 1000,               // Max experiences
    'initial_strategies' => [                   // Starting strategies
        'default',
        'analytical',
        'creative',
    ],
    'logger' => $psrLogger,                     // PSR-3 logger
]);
```

### Providing Feedback

The agent learns through explicit feedback on its performance:

```php
// Run a task
$result = $agent->run('Calculate compound interest');
$experienceId = $result->getMetadata()['experience_id'];

// Provide feedback
$agent->provideFeedback(
    experienceId: $experienceId,
    reward: 0.85,              // -1 to 1 (negative = bad, positive = good)
    success: true,             // Was it successful?
    feedback: [                // Optional additional data
        'accuracy' => 'high',
        'speed' => 'fast',
    ]
);
```

### Managing Strategies

```php
// Add new strategy
$agent->addStrategy('collaborative');

// View all strategies and their performance
$performance = $agent->getPerformance();
foreach ($performance as $strategy => $perf) {
    echo "$strategy:\n";
    echo "  Attempts: {$perf['attempts']}\n";
    echo "  Success Rate: " . ($perf['successes'] / max(1, $perf['attempts']) * 100) . "%\n";
    echo "  Avg Reward: {$perf['avg_reward']}\n";
}
```

### Experience Replay

```php
// Get recent experiences
$experiences = $agent->getExperiences(limit: 10);

foreach ($experiences as $exp) {
    echo "Task: {$exp['task']}\n";
    echo "Strategy: {$exp['strategy']}\n";
    echo "Success: " . ($exp['success'] ? 'Yes' : 'No') . "\n";
    echo "Reward: {$exp['reward']}\n";
    echo "---\n";
}
```

### Strategy Selection

The agent uses an **epsilon-greedy** strategy selection:

- **90% Exploitation**: Choose the best-performing strategy
- **10% Exploration**: Try a random strategy
- **Untested Priority**: Always try untested strategies first

```php
// The agent automatically selects strategies
$result = $agent->run('Some task');

// See which strategy was chosen
$strategyUsed = $result->getMetadata()['strategy_used'];
echo "Agent chose: $strategyUsed\n";
```

## Agent Result

The `run()` method returns an `AgentResult` object:

```php
$result = $agent->run('task');

// Standard properties
$result->isSuccess();      // bool
$result->getAnswer();      // string
$result->getError();       // string (if failed)
$result->getIterations();  // int (always 1 for LearningAgent)

// Metadata
$metadata = $result->getMetadata();
$metadata['strategy_used'];        // Strategy that was used
$metadata['experience_id'];        // ID for feedback
$metadata['performance'];          // Current performance metrics
$metadata['total_experiences'];    // Total experiences recorded
```

## Learning Process

The agent learns through several mechanisms:

### 1. Pattern Recognition

Analyzes successful experiences to identify common patterns:

```php
// Automatically triggered every 10 feedback cycles
$agent->provideFeedback($expId, 0.9, true);  // Triggers learning at multiples of 10
```

The agent:
- Groups experiences by strategy
- Identifies which strategies work best
- Analyzes task patterns (common words, structure)

### 2. Strategy Evolution

Removes poor-performing strategies and suggests new ones:

```php
// After 10+ attempts, strategies with avg_reward < -0.5 are removed
// New strategies are suggested based on task patterns
```

### 3. Parameter Tuning

Self-adjusts the learning rate:

- **Good performance** (avg reward > 0.5): Reduces exploration
- **Poor performance** (avg reward < 0): Increases exploration

### 4. Experience Pruning

Maintains high-quality experience buffer:

- Removes low-value experiences when buffer is 90% full
- Keeps high-reward and recent experiences
- Removes bottom 10% by value score

## Best Practices

### 1. Define Clear Feedback Criteria

```php
// ✅ Good - Clear reward criteria
if ($answerIsCorrect && $responseTime < 2.0) {
    $reward = 0.9;
} elseif ($answerIsCorrect) {
    $reward = 0.6;
} else {
    $reward = -0.3;
}

// ❌ Avoid - Arbitrary rewards
$reward = 0.5;  // Why 0.5? What does it mean?
```

### 2. Provide Consistent Feedback

```php
// Always provide feedback for learning
$result = $agent->run($task);
$expId = $result->getMetadata()['experience_id'];

// Don't skip this step!
$agent->provideFeedback($expId, $reward, $success);
```

### 3. Use Appropriate Strategy Names

```php
// ✅ Good - Descriptive strategy names
'initial_strategies' => [
    'analytical',      // For math, logic, analysis
    'creative',        // For brainstorming, design
    'systematic',      // For step-by-step processes
]

// ❌ Avoid - Generic names
'initial_strategies' => ['strategy1', 'strategy2', 'strategy3']
```

### 4. Monitor Performance Regularly

```php
// Check performance periodically
$performance = $agent->getPerformance();

foreach ($performance as $strategy => $perf) {
    if ($perf['attempts'] >= 10 && $perf['avg_reward'] < 0.3) {
        echo "Warning: $strategy is underperforming\n";
    }
}
```

### 5. Set Appropriate Buffer Size

```php
// For short-term tasks (minutes/hours)
'replay_buffer_size' => 100

// For long-term learning (days/weeks)
'replay_buffer_size' => 1000

// For production systems
'replay_buffer_size' => 5000
```

### 6. Use Logging in Production

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('learning_agent');
$logger->pushHandler(new StreamHandler('/var/log/agent.log', Logger::INFO));

$agent = new LearningAgent($client, [
    'logger' => $logger,
]);
```

## Advanced Patterns

### Pattern 1: Multi-Task Learning

```php
$taskTypes = [
    'math' => ['Calculate...', 'Solve...', 'Compute...'],
    'creative' => ['Design...', 'Create...', 'Write...'],
    'analysis' => ['Analyze...', 'Compare...', 'Evaluate...'],
];

foreach ($taskTypes as $type => $tasks) {
    foreach ($tasks as $task) {
        $result = $agent->run($task);
        $expId = $result->getMetadata()['experience_id'];
        
        // Reward based on task type match with strategy
        $strategyUsed = $result->getMetadata()['strategy_used'];
        $reward = ($type === $strategyUsed) ? 0.9 : 0.5;
        
        $agent->provideFeedback($expId, $reward, true, ['task_type' => $type]);
    }
}
```

### Pattern 2: A/B Testing Strategies

```php
// Test two different strategies on similar tasks
$tasksToCompare = [
    'Calculate 15% of 200',
    'Calculate 20% of 150',
    'Calculate 10% of 300',
];

$strategyPerformance = [];

foreach ($tasksToCompare as $task) {
    $result = $agent->run($task);
    $strategy = $result->getMetadata()['strategy_used'];
    
    if (!isset($strategyPerformance[$strategy])) {
        $strategyPerformance[$strategy] = [];
    }
    
    $strategyPerformance[$strategy][] = $result;
}

// Compare results
foreach ($strategyPerformance as $strategy => $results) {
    echo "$strategy handled " . count($results) . " tasks\n";
}
```

### Pattern 3: Curriculum Learning

```php
// Start with easy tasks, gradually increase difficulty
$curriculum = [
    'easy' => [
        'tasks' => ['Add 2+2', 'Multiply 3*4'],
        'reward_multiplier' => 1.0,
    ],
    'medium' => [
        'tasks' => ['Calculate 15% of 200', 'Solve 2x + 3 = 9'],
        'reward_multiplier' => 1.2,
    ],
    'hard' => [
        'tasks' => ['Solve quadratic equation', 'Optimize function'],
        'reward_multiplier' => 1.5,
    ],
];

foreach ($curriculum as $level => $data) {
    echo "Level: $level\n";
    
    foreach ($data['tasks'] as $task) {
        $result = $agent->run($task);
        $expId = $result->getMetadata()['experience_id'];
        
        $baseReward = evaluateResult($result->getAnswer());
        $finalReward = $baseReward * $data['reward_multiplier'];
        
        $agent->provideFeedback($expId, $finalReward, $baseReward > 0.5);
    }
}
```

### Pattern 4: Transfer Learning

```php
// Train on one type of task, apply to related tasks
$trainingAgent = new LearningAgent($client, [
    'name' => 'specialist',
    'initial_strategies' => ['analytical'],
]);

// Train on math problems
for ($i = 0; $i < 50; $i++) {
    $result = $trainingAgent->run("Math problem $i");
    $expId = $result->getMetadata()['experience_id'];
    $trainingAgent->provideFeedback($expId, 0.9, true);
}

// Get learned patterns
$learnedPerformance = $trainingAgent->getPerformance();

// Apply to new agent
$newAgent = new LearningAgent($client, [
    'name' => 'generalist',
    'initial_strategies' => array_keys($learnedPerformance),
]);
```

## Performance Metrics

### Key Metrics

```php
$performance = $agent->getPerformance();

foreach ($performance as $strategy => $metrics) {
    // Attempts: Total number of times strategy was used
    echo "Attempts: {$metrics['attempts']}\n";
    
    // Successes: Number of successful outcomes
    echo "Successes: {$metrics['successes']}\n";
    
    // Total Reward: Sum of all rewards
    echo "Total Reward: {$metrics['total_reward']}\n";
    
    // Average Reward: Mean reward per attempt
    echo "Avg Reward: {$metrics['avg_reward']}\n";
    
    // Success Rate: Percentage of successes
    $successRate = ($metrics['successes'] / max(1, $metrics['attempts'])) * 100;
    echo "Success Rate: $successRate%\n";
}
```

### Tracking Improvement Over Time

```php
$performanceHistory = [];

for ($session = 1; $session <= 10; $session++) {
    // Run tasks
    for ($i = 0; $i < 10; $i++) {
        $result = $agent->run("Task $i in session $session");
        $expId = $result->getMetadata()['experience_id'];
        $agent->provideFeedback($expId, mt_rand(50, 100) / 100, true);
    }
    
    // Record performance
    $performanceHistory[$session] = $agent->getPerformance();
}

// Analyze improvement
foreach ($performanceHistory as $session => $perf) {
    $avgReward = array_sum(array_column($perf, 'avg_reward')) / count($perf);
    echo "Session $session - Avg Reward: " . number_format($avgReward, 3) . "\n";
}
```

## Troubleshooting

### Agent Not Learning

**Problem**: Performance not improving over time.

**Solutions**:
```php
// 1. Check feedback frequency
$experiences = $agent->getExperiences();
$withFeedback = array_filter($experiences, fn($e) => $e['reward'] !== null);
echo "Feedback rate: " . (count($withFeedback) / count($experiences) * 100) . "%\n";

// 2. Verify reward variance
$rewards = array_column($withFeedback, 'reward');
echo "Reward range: " . min($rewards) . " to " . max($rewards) . "\n";

// 3. Increase learning rate
$agent = new LearningAgent($client, ['learning_rate' => 0.2]);
```

### Strategy Stagnation

**Problem**: Agent always uses the same strategy.

**Solutions**:
```php
// 1. Check strategy distribution
$experiences = $agent->getExperiences();
$strategies = array_count_values(array_column($experiences, 'strategy'));
print_r($strategies);

// 2. Force exploration by adding new strategies
$agent->addStrategy('experimental');

// 3. Reset poorly performing strategies
// (Automatically done when avg_reward < -0.5 after 10 attempts)
```

### Memory Issues

**Problem**: Too many experiences causing memory problems.

**Solutions**:
```php
// 1. Reduce buffer size
'replay_buffer_size' => 100

// 2. Experience pruning happens automatically at 90% capacity
// Check current capacity
echo "Experiences: " . count($agent->getExperiences()) . " / " . 
     $agent->getReplayBufferSize() . "\n";
```

## Testing

### Unit Tests

The LearningAgent has comprehensive unit tests:

```bash
./vendor/bin/phpunit tests/Unit/LearningAgentTest.php
```

### Integration Tests

```bash
# Basic example
php examples/learning_agent.php

# Advanced features
php examples/advanced_learning_agent.php
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
- `name` (string): Agent name (default: 'learning_agent')
- `learning_rate` (float): Learning rate 0-1 (default: 0.1)
- `replay_buffer_size` (int): Max experiences (default: 1000)
- `initial_strategies` (array): Starting strategies (default: ['default'])
- `logger` (LoggerInterface): PSR-3 logger (default: NullLogger)

### Methods

#### run(string $task): AgentResult

Execute a task using the selected strategy.

**Returns**: `AgentResult` with metadata including `strategy_used`, `experience_id`, `performance`, `total_experiences`

#### provideFeedback(string $experienceId, float $reward, bool $success, array $feedback = []): void

Provide feedback on a previous experience.

**Parameters**:
- `experienceId`: Experience ID from result metadata
- `reward`: Reward value from -1 to 1
- `success`: Whether outcome was successful
- `feedback`: Additional feedback data (optional)

#### addStrategy(string $strategy): void

Add a new strategy.

#### getPerformance(): array

Get performance statistics for all strategies.

**Returns**: Array of performance metrics per strategy

#### getExperiences(int $limit = 100): array

Get recent experiences.

**Returns**: Array of experience records

#### getName(): string

Get the agent name.

## Examples

See the `/examples` directory for complete working examples:

- `learning_agent.php` - Basic learning agent usage
- `advanced_learning_agent.php` - Advanced patterns and features

## Related Documentation

- [Agent Selection Guide](agent-selection-guide.md)
- [AutonomousAgent Documentation](AutonomousAgent.md)
- [AlertAgent Documentation](AlertAgent.md)
- [Examples README](../examples/README.md)

## License

MIT License - See [LICENSE](../LICENSE) file for details.

