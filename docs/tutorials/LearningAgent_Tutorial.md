# LearningAgent Tutorial

## Introduction

Welcome to the LearningAgent tutorial! In this comprehensive guide, you'll learn how to build adaptive AI agents that learn from experience, improve over time, and optimize their problem-solving strategies through feedback.

### What You'll Learn

1. Creating basic learning agents
2. Providing feedback for learning
3. Managing multiple strategies
4. Tracking performance metrics
5. Implementing curriculum learning
6. Advanced patterns like A/B testing and transfer learning
7. Building production-ready adaptive systems

### Prerequisites

- PHP 8.1 or higher
- Composer installed
- Anthropic API key (for live examples)
- Basic understanding of PHP and the Claude API
- Familiarity with reinforcement learning concepts (helpful but not required)

### Time Required

Approximately 45-60 minutes

---

## Chapter 1: Your First Learning Agent

Let's start by creating a simple learning agent that can adapt based on feedback.

### Step 1: Set Up Your Environment

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\LearningAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
```

### Step 2: Create Your First Learning Agent

```php
$agent = new LearningAgent($client, [
    'name' => 'problem_solver',
    'initial_strategies' => ['analytical', 'creative'],
]);

echo "Agent: {$agent->getName()}\n";
echo "Strategies: " . implode(', ', array_keys($agent->getPerformance())) . "\n";
```

### Step 3: Run a Task

```php
$result = $agent->run('What is 15% of 200?');

if ($result->isSuccess()) {
    echo "Answer: {$result->getAnswer()}\n";
    echo "Strategy Used: {$result->getMetadata()['strategy_used']}\n";
}
```

### Step 4: Provide Feedback

This is where the learning happens!

```php
// Get the experience ID
$experienceId = $result->getMetadata()['experience_id'];

// Provide feedback
$agent->provideFeedback(
    experienceId: $experienceId,
    reward: 0.9,        // High reward for correct answer
    success: true,      // It was successful
    feedback: [         // Optional metadata
        'comment' => 'Accurate and fast'
    ]
);

echo "✓ Feedback recorded!\n";
```

### What Just Happened?

1. **Agent Created**: We created an agent with two strategies
2. **Task Executed**: The agent chose a strategy and solved the problem
3. **Experience Recorded**: The task, strategy, and result were saved
4. **Feedback Provided**: We told the agent how well it did
5. **Learning Triggered**: The agent updated its performance metrics

### Try It Yourself

Create an agent and run multiple tasks with feedback:

```php
$tasks = [
    'Calculate 20% tip on $45' => 0.9,  // Math task
    'Write a catchy slogan for a bakery' => 0.8,  // Creative task
];

foreach ($tasks as $task => $expectedReward) {
    $result = $agent->run($task);
    
    if ($result->isSuccess()) {
        $expId = $result->getMetadata()['experience_id'];
        $agent->provideFeedback($expId, $expectedReward, true);
    }
}
```

---

## Chapter 2: Understanding Strategies

Strategies are different approaches the agent can use to solve problems.

### Default Strategies

```php
$agent = new LearningAgent($client, [
    'initial_strategies' => [
        'analytical',    // For math, logic, analysis
        'creative',      // For brainstorming, writing
        'systematic',    // For step-by-step processes
        'adaptive',      // For flexible situations
    ],
]);
```

### How Strategy Selection Works

The agent uses **epsilon-greedy** selection:

- **90% Exploitation**: Choose the best-performing strategy
- **10% Exploration**: Try a random strategy
- **Priority for New**: Always try untested strategies first

```php
// Run the same type of task multiple times
for ($i = 1; $i <= 10; $i++) {
    $result = $agent->run("Calculate problem $i");
    echo "Attempt $i - Strategy: {$result->getMetadata()['strategy_used']}\n";
    
    $expId = $result->getMetadata()['experience_id'];
    $agent->provideFeedback($expId, 0.9, true);
}

// The agent will converge on the best strategy for math problems
```

### Adding New Strategies

```php
// Add a new strategy during runtime
$agent->addStrategy('collaborative');

echo "Updated strategies: " . implode(', ', array_keys($agent->getPerformance())) . "\n";
```

### Viewing Strategy Performance

```php
$performance = $agent->getPerformance();

foreach ($performance as $strategy => $metrics) {
    if ($metrics['attempts'] > 0) {
        $successRate = ($metrics['successes'] / $metrics['attempts']) * 100;
        
        echo "$strategy:\n";
        echo "  Attempts: {$metrics['attempts']}\n";
        echo "  Success Rate: " . number_format($successRate, 1) . "%\n";
        echo "  Avg Reward: " . number_format($metrics['avg_reward'], 3) . "\n";
        echo "\n";
    }
}
```

---

## Chapter 3: The Feedback Loop

Feedback is how the agent learns. Let's explore different feedback strategies.

### Reward Scale

Rewards should be between -1 and 1:

- **1.0**: Perfect performance
- **0.8 - 0.9**: Excellent
- **0.5 - 0.7**: Good
- **0.2 - 0.4**: Mediocre
- **0.0**: Neutral
- **-0.2 to -0.5**: Poor
- **-0.8 to -1.0**: Very bad

```php
function calculateReward($answer, $expectedAnswer, $responseTime) {
    // Check correctness
    if ($answer === $expectedAnswer) {
        $baseReward = 1.0;
    } elseif (similar_text($answer, $expectedAnswer) > 0.7) {
        $baseReward = 0.6;
    } else {
        $baseReward = 0.2;
    }
    
    // Adjust for speed
    if ($responseTime < 1.0) {
        $baseReward += 0.1;
    }
    
    return min(1.0, $baseReward);
}
```

### Immediate vs. Delayed Feedback

**Immediate Feedback** (preferred):

```php
$result = $agent->run('Task');
$expId = $result->getMetadata()['experience_id'];

// Provide feedback right away
$agent->provideFeedback($expId, 0.8, true);
```

**Delayed Feedback** (for validation):

```php
// Store experience IDs
$pendingExperiences = [];

$result = $agent->run('Task');
$pendingExperiences[] = [
    'id' => $result->getMetadata()['experience_id'],
    'answer' => $result->getAnswer(),
];

// Later, after validation...
foreach ($pendingExperiences as $pending) {
    $isValid = validateAnswer($pending['answer']);
    $reward = $isValid ? 0.9 : -0.3;
    
    $agent->provideFeedback($pending['id'], $reward, $isValid);
}
```

### Rich Feedback

Include additional context in feedback:

```php
$agent->provideFeedback(
    experienceId: $expId,
    reward: 0.85,
    success: true,
    feedback: [
        'accuracy' => 'high',
        'clarity' => 'excellent',
        'speed' => 'fast',
        'user_rating' => 4.5,
        'timestamp' => time(),
    ]
);
```

---

## Chapter 4: Experience Replay

The agent maintains a buffer of experiences for learning.

### Viewing Experiences

```php
// Get recent experiences
$experiences = $agent->getExperiences(limit: 10);

foreach ($experiences as $i => $exp) {
    echo "Experience " . ($i + 1) . ":\n";
    echo "  Task: {$exp['task']}\n";
    echo "  Strategy: {$exp['strategy']}\n";
    echo "  Timestamp: " . date('Y-m-d H:i:s', (int)$exp['timestamp']) . "\n";
    
    if ($exp['reward'] !== null) {
        echo "  Reward: {$exp['reward']}\n";
        echo "  Success: " . ($exp['success'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "  Status: Awaiting feedback\n";
    }
    
    echo "\n";
}
```

### Analyzing Experience Patterns

```php
$experiences = $agent->getExperiences();

// Success rate
$withFeedback = array_filter($experiences, fn($e) => $e['reward'] !== null);
$successful = array_filter($withFeedback, fn($e) => $e['success'] === true);

$successRate = (count($successful) / count($withFeedback)) * 100;
echo "Overall Success Rate: " . number_format($successRate, 1) . "%\n";

// Strategy distribution
$strategyUsage = [];
foreach ($experiences as $exp) {
    $strategy = $exp['strategy'];
    $strategyUsage[$strategy] = ($strategyUsage[$strategy] ?? 0) + 1;
}

echo "\nStrategy Usage:\n";
foreach ($strategyUsage as $strategy => $count) {
    $percentage = ($count / count($experiences)) * 100;
    echo "  $strategy: $count (" . number_format($percentage, 1) . "%)\n";
}

// Average rewards
$avgRewards = [];
foreach ($experiences as $exp) {
    if ($exp['reward'] !== null) {
        $strategy = $exp['strategy'];
        if (!isset($avgRewards[$strategy])) {
            $avgRewards[$strategy] = [];
        }
        $avgRewards[$strategy][] = $exp['reward'];
    }
}

echo "\nAverage Rewards by Strategy:\n";
foreach ($avgRewards as $strategy => $rewards) {
    $avg = array_sum($rewards) / count($rewards);
    echo "  $strategy: " . number_format($avg, 3) . "\n";
}
```

### Buffer Management

```php
// Configure buffer size
$agent = new LearningAgent($client, [
    'replay_buffer_size' => 500,  // Keep last 500 experiences
]);

// The agent automatically prunes low-value experiences when buffer reaches 90% capacity
```

---

## Chapter 5: Performance Tracking

Monitor your agent's learning progress over time.

### Real-Time Performance Dashboard

```php
function displayPerformanceDashboard($agent) {
    $performance = $agent->getPerformance();
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "PERFORMANCE DASHBOARD\n";
    echo str_repeat('=', 60) . "\n\n";
    
    // Sort by average reward
    uasort($performance, fn($a, $b) => $b['avg_reward'] <=> $a['avg_reward']);
    
    foreach ($performance as $strategy => $metrics) {
        if ($metrics['attempts'] > 0) {
            $successRate = ($metrics['successes'] / $metrics['attempts']) * 100;
            $stars = str_repeat('★', (int)($metrics['avg_reward'] * 5));
            
            echo strtoupper($strategy) . "\n";
            echo str_repeat('-', 40) . "\n";
            echo "Attempts:      {$metrics['attempts']}\n";
            echo "Successes:     {$metrics['successes']} (" . 
                 number_format($successRate, 1) . "%)\n";
            echo "Total Reward:  " . number_format($metrics['total_reward'], 2) . "\n";
            echo "Avg Reward:    " . number_format($metrics['avg_reward'], 3) . " $stars\n";
            echo "\n";
        }
    }
    
    // Overall statistics
    $totalAttempts = array_sum(array_column($performance, 'attempts'));
    $totalSuccesses = array_sum(array_column($performance, 'successes'));
    $overallSuccessRate = ($totalSuccesses / max(1, $totalAttempts)) * 100;
    
    echo str_repeat('=', 60) . "\n";
    echo "OVERALL: $totalAttempts attempts, " . 
         number_format($overallSuccessRate, 1) . "% success rate\n";
    echo str_repeat('=', 60) . "\n\n";
}

// Use it after running tasks
displayPerformanceDashboard($agent);
```

### Learning Curve Analysis

```php
$learningCurve = [];
$batchSize = 10;

for ($batch = 1; $batch <= 10; $batch++) {
    // Run a batch of tasks
    for ($i = 0; $i < $batchSize; $i++) {
        $result = $agent->run("Task " . (($batch - 1) * $batchSize + $i));
        $expId = $result->getMetadata()['experience_id'];
        
        // Provide varying feedback
        $reward = mt_rand(50, 100) / 100;
        $agent->provideFeedback($expId, $reward, $reward > 0.5);
    }
    
    // Record performance
    $performance = $agent->getPerformance();
    $avgReward = 0;
    $count = 0;
    
    foreach ($performance as $metrics) {
        if ($metrics['attempts'] > 0) {
            $avgReward += $metrics['avg_reward'];
            $count++;
        }
    }
    
    $learningCurve[$batch] = $avgReward / max(1, $count);
}

// Display learning curve
echo "Learning Curve:\n";
foreach ($learningCurve as $batch => $avgReward) {
    $bar = str_repeat('█', (int)($avgReward * 50));
    echo "Batch $batch: $bar " . number_format($avgReward, 3) . "\n";
}
```

---

## Chapter 6: Advanced Learning Patterns

### Pattern 1: Curriculum Learning

Start with easy tasks and gradually increase difficulty:

```php
$curriculum = [
    [
        'name' => 'Basic Math',
        'difficulty' => 1,
        'tasks' => [
            'Add 5 + 3',
            'Subtract 10 - 4',
            'Multiply 6 * 7',
        ],
    ],
    [
        'name' => 'Intermediate Math',
        'difficulty' => 2,
        'tasks' => [
            'Calculate 15% of 200',
            'Solve for x: 2x + 5 = 13',
            'Find the area of a circle with radius 4',
        ],
    ],
    [
        'name' => 'Advanced Math',
        'difficulty' => 3,
        'tasks' => [
            'Solve the quadratic equation x² - 5x + 6 = 0',
            'Calculate compound interest on $1000 at 5% for 3 years',
            'Find the derivative of x³ + 2x² - 5x + 1',
        ],
    ],
];

foreach ($curriculum as $level) {
    echo "\n=== {$level['name']} ===\n";
    
    foreach ($level['tasks'] as $task) {
        $result = $agent->run($task);
        
        if ($result->isSuccess()) {
            echo "Task: $task\n";
            echo "Strategy: {$result->getMetadata()['strategy_used']}\n";
            
            // Reward scales with difficulty
            $baseReward = 0.7;
            $reward = $baseReward + (0.1 * $level['difficulty']);
            
            $expId = $result->getMetadata()['experience_id'];
            $agent->provideFeedback($expId, $reward, true);
            
            echo "Reward: " . number_format($reward, 2) . "\n\n";
        }
    }
}
```

### Pattern 2: A/B Testing Strategies

Compare different strategies on similar tasks:

```php
// Force specific strategies for comparison
$testTasks = [
    'Task 1: Analyze sales data',
    'Task 2: Analyze customer feedback',
    'Task 3: Analyze market trends',
];

$strategyResults = [
    'analytical' => [],
    'systematic' => [],
];

// Note: Direct strategy selection isn't exposed, but we can analyze natural selection
foreach ($testTasks as $task) {
    $result = $agent->run($task);
    $strategy = $result->getMetadata()['strategy_used'];
    
    // Record which strategy was used
    if (isset($strategyResults[$strategy])) {
        $strategyResults[$strategy][] = $result;
        
        // Provide feedback
        $expId = $result->getMetadata()['experience_id'];
        $agent->provideFeedback($expId, 0.8, true);
    }
}

// Compare results
echo "Strategy Comparison:\n";
foreach ($strategyResults as $strategy => $results) {
    echo "$strategy: " . count($results) . " tasks\n";
}
```

### Pattern 3: Multi-Task Learning

Train on diverse task types:

```php
$taskTypes = [
    'math' => [
        'tasks' => ['Calculate 20 * 15', 'Find 30% of 250'],
        'ideal_strategy' => 'analytical',
    ],
    'creative' => [
        'tasks' => ['Name a coffee shop', 'Write a product tagline'],
        'ideal_strategy' => 'creative',
    ],
    'planning' => [
        'tasks' => ['Steps to deploy an app', 'Create a project timeline'],
        'ideal_strategy' => 'systematic',
    ],
];

foreach ($taskTypes as $type => $config) {
    echo "\n=== " . ucfirst($type) . " Tasks ===\n";
    
    foreach ($config['tasks'] as $task) {
        $result = $agent->run($task);
        $strategyUsed = $result->getMetadata()['strategy_used'];
        
        // Reward based on strategy match
        $reward = ($strategyUsed === $config['ideal_strategy']) ? 0.95 : 0.65;
        
        echo "Task: $task\n";
        echo "Strategy: $strategyUsed (ideal: {$config['ideal_strategy']})\n";
        echo "Reward: " . number_format($reward, 2) . "\n\n";
        
        $expId = $result->getMetadata()['experience_id'];
        $agent->provideFeedback($expId, $reward, $reward > 0.7);
    }
}
```

### Pattern 4: Adaptive Difficulty

Adjust difficulty based on performance:

```php
$currentDifficulty = 1;
$successCount = 0;
$attemptCount = 0;

for ($round = 1; $round <= 20; $round++) {
    // Generate task based on difficulty
    $task = generateTask($currentDifficulty);
    
    $result = $agent->run($task);
    $expId = $result->getMetadata()['experience_id'];
    
    // Evaluate result
    $isCorrect = evaluateAnswer($result->getAnswer(), $task);
    $attemptCount++;
    
    if ($isCorrect) {
        $successCount++;
        $reward = 0.9;
        $agent->provideFeedback($expId, $reward, true);
    } else {
        $reward = 0.3;
        $agent->provideFeedback($expId, $reward, false);
    }
    
    // Adjust difficulty every 5 rounds
    if ($attemptCount % 5 === 0) {
        $successRate = $successCount / $attemptCount;
        
        if ($successRate > 0.8 && $currentDifficulty < 5) {
            $currentDifficulty++;
            echo "Difficulty increased to $currentDifficulty\n";
        } elseif ($successRate < 0.5 && $currentDifficulty > 1) {
            $currentDifficulty--;
            echo "Difficulty decreased to $currentDifficulty\n";
        }
    }
}

function generateTask($difficulty) {
    $tasks = [
        1 => 'Simple addition',
        2 => 'Percentage calculation',
        3 => 'Algebraic equation',
        4 => 'Quadratic formula',
        5 => 'Calculus problem',
    ];
    return $tasks[$difficulty] ?? $tasks[1];
}

function evaluateAnswer($answer, $task) {
    // Simplified evaluation
    return strlen($answer) > 10; // Just check for substantial answer
}
```

---

## Chapter 7: Production Deployment

### Setup for Production

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Configure logging
$logger = new Logger('learning_agent');
$logger->pushHandler(
    new RotatingFileHandler('/var/log/agent/learning.log', 30, Logger::INFO)
);
$logger->pushHandler(
    new StreamHandler('php://stderr', Logger::ERROR)
);

// Create production agent
$agent = new LearningAgent($client, [
    'name' => 'production_learner',
    'learning_rate' => 0.08,  // Lower for stability
    'replay_buffer_size' => 5000,  // Larger for long-term learning
    'initial_strategies' => [
        'analytical',
        'creative',
        'systematic',
        'collaborative',
        'adaptive',
    ],
    'logger' => $logger,
]);
```

### Error Handling

```php
try {
    $result = $agent->run($task);
    
    if ($result->isSuccess()) {
        $expId = $result->getMetadata()['experience_id'];
        
        // Provide feedback with error handling
        try {
            $reward = calculateReward($result->getAnswer());
            $agent->provideFeedback($expId, $reward, true);
        } catch (\Exception $e) {
            $logger->error('Feedback failed', [
                'experience_id' => $expId,
                'error' => $e->getMessage(),
            ]);
            // Provide neutral feedback as fallback
            $agent->provideFeedback($expId, 0.5, true);
        }
    } else {
        $logger->error('Task failed', [
            'task' => $task,
            'error' => $result->getError(),
        ]);
    }
} catch (\Throwable $e) {
    $logger->critical('Agent crashed', [
        'task' => $task,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
```

### Performance Monitoring

```php
// Periodic performance checks
function monitorAgentHealth($agent, $logger) {
    $performance = $agent->getPerformance();
    
    $totalAttempts = array_sum(array_column($performance, 'attempts'));
    $totalSuccesses = array_sum(array_column($performance, 'successes'));
    $successRate = ($totalSuccesses / max(1, $totalAttempts)) * 100;
    
    // Alert if performance drops
    if ($successRate < 50) {
        $logger->warning('Low success rate detected', [
            'success_rate' => $successRate,
            'total_attempts' => $totalAttempts,
        ]);
    }
    
    // Check for strategy imbalance
    foreach ($performance as $strategy => $metrics) {
        if ($metrics['attempts'] >= 20 && $metrics['avg_reward'] < 0.3) {
            $logger->warning('Strategy underperforming', [
                'strategy' => $strategy,
                'avg_reward' => $metrics['avg_reward'],
                'attempts' => $metrics['attempts'],
            ]);
        }
    }
    
    // Log overall health
    $logger->info('Agent health check', [
        'success_rate' => $successRate,
        'total_attempts' => $totalAttempts,
        'strategies' => count($performance),
        'experience_buffer' => count($agent->getExperiences()),
    ]);
}

// Run health check every N tasks
$taskCounter = 0;
$healthCheckInterval = 50;

// In your task loop
if (++$taskCounter % $healthCheckInterval === 0) {
    monitorAgentHealth($agent, $logger);
}
```

### Graceful Degradation

```php
class RobustLearningAgent
{
    private LearningAgent $agent;
    private Logger $logger;
    private int $consecutiveFailures = 0;
    private const MAX_FAILURES = 5;
    
    public function runSafe(string $task): ?AgentResult
    {
        try {
            $result = $this->agent->run($task);
            
            if ($result->isSuccess()) {
                $this->consecutiveFailures = 0;
                return $result;
            } else {
                $this->handleFailure($result->getError());
                return null;
            }
        } catch (\Throwable $e) {
            $this->handleFailure($e->getMessage());
            return null;
        }
    }
    
    private function handleFailure(string $error): void
    {
        $this->consecutiveFailures++;
        
        $this->logger->error('Task failed', [
            'error' => $error,
            'consecutive_failures' => $this->consecutiveFailures,
        ]);
        
        if ($this->consecutiveFailures >= self::MAX_FAILURES) {
            $this->logger->critical('Agent degraded - too many failures');
            // Could trigger alerts, switch to backup agent, etc.
        }
    }
}
```

---

## Chapter 8: Optimization Tips

### Tip 1: Balance Exploration and Exploitation

```php
// Early training: more exploration
$trainingAgent = new LearningAgent($client, [
    'learning_rate' => 0.15,  // Higher learning rate
]);

// Production: less exploration (agent adjusts automatically)
$prodAgent = new LearningAgent($client, [
    'learning_rate' => 0.08,  // Lower learning rate
]);
```

### Tip 2: Reward Shaping

```php
function shapeReward($baseReward, $context) {
    // Time bonus
    if ($context['response_time'] < 1.0) {
        $baseReward += 0.05;
    }
    
    // Quality bonus
    if ($context['answer_length'] > 100 && $context['answer_length'] < 500) {
        $baseReward += 0.05;
    }
    
    // Consistency bonus (if previous similar tasks succeeded)
    if ($context['similar_task_success_rate'] > 0.8) {
        $baseReward += 0.1;
    }
    
    return min(1.0, $baseReward);
}
```

### Tip 3: Batch Feedback

```php
$pendingFeedback = [];

// Collect experiences
for ($i = 0; $i < 100; $i++) {
    $result = $agent->run("Task $i");
    $pendingFeedback[] = [
        'id' => $result->getMetadata()['experience_id'],
        'result' => $result,
    ];
}

// Provide feedback in batch
foreach ($pendingFeedback as $pending) {
    $reward = evaluateResult($pending['result']);
    $agent->provideFeedback($pending['id'], $reward, $reward > 0.5);
}
```

### Tip 4: Periodic Performance Reviews

```php
class PerformanceReviewer
{
    public function review(LearningAgent $agent): array
    {
        $performance = $agent->getPerformance();
        $recommendations = [];
        
        foreach ($performance as $strategy => $metrics) {
            if ($metrics['attempts'] < 5) {
                $recommendations[] = "Need more data for '$strategy' strategy";
            } elseif ($metrics['avg_reward'] < 0.4) {
                $recommendations[] = "Consider removing '$strategy' strategy";
            } elseif ($metrics['avg_reward'] > 0.8) {
                $recommendations[] = "'$strategy' is performing excellently";
            }
        }
        
        return $recommendations;
    }
}

$reviewer = new PerformanceReviewer();
$recommendations = $reviewer->review($agent);

foreach ($recommendations as $recommendation) {
    echo "• $recommendation\n";
}
```

---

## Chapter 9: Troubleshooting

### Problem: Agent Not Learning

**Symptoms**: Performance not improving over time

**Solutions**:

```php
// 1. Check feedback coverage
$experiences = $agent->getExperiences();
$withFeedback = array_filter($experiences, fn($e) => $e['reward'] !== null);
$feedbackRate = (count($withFeedback) / count($experiences)) * 100;

echo "Feedback coverage: " . number_format($feedbackRate, 1) . "%\n";

if ($feedbackRate < 80) {
    echo "⚠️  Provide more feedback! Target: >90%\n";
}

// 2. Check reward variance
$rewards = array_column($withFeedback, 'reward');
$variance = stats_variance($rewards);

echo "Reward variance: " . number_format($variance, 3) . "\n";

if ($variance < 0.1) {
    echo "⚠️  Rewards are too similar. Provide more varied feedback.\n";
}

// 3. Increase learning opportunities
$agent->addStrategy('experimental');
```

### Problem: Strategy Imbalance

**Symptoms**: Agent always uses the same strategy

**Solutions**:

```php
$experiences = $agent->getExperiences();
$strategyCount = [];

foreach ($experiences as $exp) {
    $strategy = $exp['strategy'];
    $strategyCount[$strategy] = ($strategyCount[$strategy] ?? 0) + 1;
}

arsort($strategyCount);
$mostUsed = array_keys($strategyCount)[0];
$leastUsed = array_keys($strategyCount)[count($strategyCount) - 1];

$imbalance = $strategyCount[$mostUsed] / max(1, $strategyCount[$leastUsed]);

if ($imbalance > 5) {
    echo "⚠️  Strategy imbalance detected\n";
    echo "Most used: $mostUsed ({$strategyCount[$mostUsed]} times)\n";
    echo "Least used: $leastUsed ({$strategyCount[$leastUsed]} times)\n";
    
    // Solution: Provide better feedback to underused strategies
}
```

### Problem: Memory Issues

**Symptoms**: High memory usage

**Solutions**:

```php
// 1. Reduce buffer size
$agent = new LearningAgent($client, [
    'replay_buffer_size' => 100,  // Smaller buffer
]);

// 2. Monitor memory
$experienceCount = count($agent->getExperiences());
$memoryUsage = memory_get_usage(true) / 1024 / 1024;

echo "Experiences: $experienceCount\n";
echo "Memory: " . number_format($memoryUsage, 2) . " MB\n";

if ($memoryUsage > 100) {
    echo "⚠️  High memory usage detected\n";
}
```

---

## Conclusion

Congratulations! You've completed the LearningAgent tutorial. You now know how to:

✅ Create adaptive learning agents  
✅ Provide effective feedback for learning  
✅ Manage and optimize strategies  
✅ Track performance metrics  
✅ Implement advanced learning patterns  
✅ Deploy learning agents to production  
✅ Troubleshoot common issues  

### Next Steps

1. **Experiment**: Try the examples in `/examples/learning_agent.php`
2. **Build**: Create your own learning agent for a specific use case
3. **Optimize**: Fine-tune rewards and strategies for your domain
4. **Monitor**: Set up performance tracking in production
5. **Share**: Contribute your learnings back to the community

### Additional Resources

- [LearningAgent Documentation](../LearningAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [API Reference](../LearningAgent.md#api-reference)
- [Examples Directory](../../examples/)

### Community

Questions? Ideas? Share them with the community!

---

**Happy Learning! 🚀**

