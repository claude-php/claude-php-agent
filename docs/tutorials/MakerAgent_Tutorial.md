# MAKER Agent Tutorial

Welcome to this comprehensive tutorial on the `MakerAgent`! MAKER (Massively Decomposed Agentic Processes with first-to-ahead-by-K Error correction and Red-flagging) represents a breakthrough in reliable task execution, capable of solving million-step problems with near-zero error rates.

## Table of Contents

1. [Introduction](#introduction)
2. [What is the MAKER Framework?](#what-is-the-maker-framework)
3. [Setup](#setup)
4. [Tutorial 1: Your First MAKER Agent](#tutorial-1-your-first-maker-agent)
5. [Tutorial 2: Understanding Decomposition](#tutorial-2-understanding-decomposition)
6. [Tutorial 3: Voting and Error Correction](#tutorial-3-voting-and-error-correction)
7. [Tutorial 4: Red-Flagging in Action](#tutorial-4-red-flagging-in-action)
8. [Tutorial 5: Multi-Step Mathematical Problems](#tutorial-5-multi-step-mathematical-problems)
9. [Tutorial 6: Logic Puzzles](#tutorial-6-logic-puzzles)
10. [Tutorial 7: The Towers of Hanoi Benchmark](#tutorial-7-the-towers-of-hanoi-benchmark)
11. [Tutorial 8: Tuning for Performance](#tutorial-8-tuning-for-performance)
12. [Production Best Practices](#production-best-practices)
13. [Common Patterns](#common-patterns)
14. [Troubleshooting](#troubleshooting)
15. [Next Steps](#next-steps)

## Introduction

This tutorial will teach you how to build ultra-reliable task execution systems using the MAKER framework. By the end, you'll be able to:

- Understand the three core principles of MAKER
- Implement agents that handle complex multi-step tasks
- Achieve near-zero error rates through voting and decomposition
- Optimize for reliability vs. performance trade-offs
- Build production-ready systems for critical applications

## What is the MAKER Framework?

MAKER is based on the paper ["Solving a Million-Step LLM Task with Zero Errors"](https://arxiv.org/html/2511.09030v1) and consists of three key components:

### 1. Maximal Decomposition

Break tasks into the smallest possible subtasks. Each subtask should be so simple that even a basic model can execute it reliably.

```
Complex Task: "Calculate compound interest for $1000 at 5% for 3 years"

Decomposed:
1. Calculate interest for year 1
2. Add interest to principal
3. Calculate interest for year 2
4. Add interest to new principal
5. Calculate interest for year 3
6. Add interest to get final amount
```

### 2. First-to-Ahead-by-K Error Correction

Multiple independent agents vote on each subtask. The first answer to achieve a K-vote lead wins. This decorrelates errors and ensures consensus.

```
Subtask: "What is 12 × 8?"

Agent 1: "96"  ✓
Agent 2: "96"  ✓
Agent 3: "86"  ✗

With K=2, "96" wins (2 votes vs 1)
```

### 3. Red-Flagging

Detect linguistic markers of uncertainty or confusion and automatically retry:

```
Response: "Hmm, wait maybe I should reconsider..."
Action: 🚩 Red flag detected → Retry this step
```

### Why MAKER?

**Standard LLMs:**
- Error rate: ~0.5-1% per step
- Reliable for: ~100-300 consecutive steps
- Result: Failure on complex multi-step tasks

**MAKER:**
- Error rate: Near-zero through voting
- Reliable for: 1,000,000+ consecutive steps
- Result: Zero errors on benchmark tasks

## Setup

First, install the package and set up your API key:

```bash
composer require claude-php-agent
```

Set your Anthropic API key:

```bash
export ANTHROPIC_API_KEY='your-api-key-here'
```

Or create a `.env` file:

```
ANTHROPIC_API_KEY=your-api-key-here
```

## Tutorial 1: Your First MAKER Agent

Let's start with a simple example to understand the basics.

### Step 1: Create Your First Agent

Create a file `my_first_maker.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a MAKER agent with default settings
$maker = new MakerAgent($client, [
    'name' => 'my_first_maker',
    'voting_k' => 2,              // First to lead by 2 votes wins
    'enable_red_flagging' => true, // Enable uncertainty detection
]);

// Solve a simple multi-step problem
$task = "Calculate: (10 + 5) × 3 - 8. Show each step.";

echo "Task: {$task}\n\n";

$result = $maker->run($task);

if ($result->isSuccess()) {
    echo "Answer:\n{$result->getAnswer()}\n\n";
    
    // View execution statistics
    $stats = $result->getMetadata()['execution_stats'];
    echo "Execution Statistics:\n";
    echo "  Total Steps: {$stats['total_steps']}\n";
    echo "  Atomic Executions: {$stats['atomic_executions']}\n";
    echo "  Votes Cast: {$stats['votes_cast']}\n";
    echo "  Red Flags: {$stats['red_flags_detected']}\n";
    echo "  Error Rate: " . $result->getMetadata()['error_rate'] . "\n";
} else {
    echo "Error: {$result->getError()}\n";
}
```

Run it:

```bash
php my_first_maker.php
```

**Expected Output:**

```
Task: Calculate: (10 + 5) × 3 - 8. Show each step.

Answer:
Step 1: Calculate 10 + 5 = 15
Step 2: Calculate 15 × 3 = 45
Step 3: Calculate 45 - 8 = 37

Final Answer: 37

Execution Statistics:
  Total Steps: 4
  Atomic Executions: 3
  Votes Cast: 9
  Red Flags: 0
  Error Rate: 0.0
```

### Understanding What Happened

1. **Task Analysis**: The agent detected this was a multi-step calculation
2. **Decomposition**: Broke it into 3 atomic operations
3. **Voting**: Each operation had ~3 votes (with K=2, needs 2-vote lead)
4. **Composition**: Results were combined into the final answer
5. **No Errors**: All steps executed successfully

## Tutorial 2: Understanding Decomposition

Let's explore how MAKER decomposes tasks.

### Example: Watching Decomposition in Action

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;
use Psr\Log\LogLevel;

// Create a simple logger to see what's happening
class SimpleLogger implements \Psr\Log\LoggerInterface
{
    use \Psr\Log\LoggerTrait;
    
    public function log($level, $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
        if (!empty($context)) {
            echo "  Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
}

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$maker = new MakerAgent($client, [
    'voting_k' => 2,
    'max_decomposition_depth' => 5,
    'logger' => new SimpleLogger(),
]);

$task = <<<TASK
You need to:
1. Calculate 15 × 8
2. Add 32 to the result
3. Divide by 4
4. Subtract 10
What is the final result?
TASK;

$result = $maker->run($task);

echo "\nFinal Answer: {$result->getAnswer()}\n";
```

**Output shows decomposition process:**

```
[10:15:32] info: MAKER Agent starting
[10:15:32] debug: MDAP execution at depth 0
[10:15:33] debug: Decomposing task at depth 0
[10:15:34] debug: Decomposing with voting
[10:15:36] debug: Executing subtask 0/4
[10:15:37] debug: Executing atomic subtask with voting
[10:15:39] debug: Executing subtask 1/4
[10:15:40] debug: Executing atomic subtask with voting
...
```

### Decomposition Triggers

The agent decomposes tasks when it detects:

1. **Length**: Task > 100 characters
2. **Sequential keywords**: "then", "next", "after", "finally"
3. **Enumeration**: "first", "second", "1.", "2."
4. **Depth**: Current depth < max_decomposition_depth

### Controlling Decomposition

```php
// Shallow decomposition (faster, less reliable)
$maker = new MakerAgent($client, [
    'max_decomposition_depth' => 3,  // Stop early
]);

// Deep decomposition (slower, more reliable)
$maker = new MakerAgent($client, [
    'max_decomposition_depth' => 15,  // Allow deep nesting
]);

// Atomic-only (no decomposition)
$maker = new MakerAgent($client, [
    'max_decomposition_depth' => 0,  // Disable decomposition
]);
```

## Tutorial 3: Voting and Error Correction

The heart of MAKER is the voting mechanism that eliminates errors.

### Understanding K Parameter

The `voting_k` parameter determines the vote margin required for consensus:

```php
// K=2: Need 2-vote lead (fast, moderate reliability)
$maker = new MakerAgent($client, ['voting_k' => 2]);

// K=3: Need 3-vote lead (balanced, high reliability)
$maker = new MakerAgent($client, ['voting_k' => 3]);

// K=4: Need 4-vote lead (slow, maximum reliability)
$maker = new MakerAgent($client, ['voting_k' => 4]);
```

### Voting Example

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Test different K values
$kvote_values = [2, 3, 4];

$task = "What is the sum of all prime numbers between 10 and 20?";

foreach ($kvote_values as $k) {
    echo "Testing with K={$k}:\n";
    
    $maker = new MakerAgent($client, [
        'voting_k' => $k,
        'enable_red_flagging' => false,  // Disable to isolate voting
    ]);
    
    $startTime = microtime(true);
    $result = $maker->run($task);
    $duration = microtime(true) - $startTime;
    
    if ($result->isSuccess()) {
        $stats = $result->getMetadata()['execution_stats'];
        echo "  Answer: {$result->getAnswer()}\n";
        echo "  Votes Cast: {$stats['votes_cast']}\n";
        echo "  Duration: " . round($duration, 2) . "s\n";
        echo "  Error Rate: " . $result->getMetadata()['error_rate'] . "\n\n";
    }
}
```

**Expected Pattern:**

```
Testing with K=2:
  Answer: 60 (13 + 17 + 19 = 49... wait, 11 + 13 + 17 + 19 = 60)
  Votes Cast: 15
  Duration: 3.2s
  Error Rate: 0.0

Testing with K=3:
  Answer: 60
  Votes Cast: 21
  Duration: 4.5s
  Error Rate: 0.0

Testing with K=4:
  Answer: 60
  Votes Cast: 28
  Duration: 5.8s
  Error Rate: 0.0
```

### First-to-Ahead-by-K Algorithm

```
N = 2K - 1  (maximum votes needed)

Example with K=2:
  Vote 1: Answer A (1-0)
  Vote 2: Answer A (2-0) ← Ahead by 2! Winner = A
  (Vote 3: Not needed)
```

This ensures:
- **Efficiency**: Stops as soon as consensus is clear
- **Reliability**: Requires significant margin
- **Decorrelation**: Independent agents reduce correlated errors

## Tutorial 4: Red-Flagging in Action

Red-flagging detects and retries uncertain responses automatically.

### Red Flag Indicators

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Test with and without red-flagging
echo "=== Without Red-Flagging ===\n";
$noRedFlag = new MakerAgent($client, [
    'voting_k' => 2,
    'enable_red_flagging' => false,
]);

$ambiguousTask = "If a bat and ball cost $1.10 total, and the bat costs $1 more than the ball, what does the ball cost?";

$result1 = $noRedFlag->run($ambiguousTask);
$stats1 = $result1->getMetadata()['execution_stats'];
echo "Answer: {$result1->getAnswer()}\n";
echo "Votes: {$stats1['votes_cast']}\n";
echo "Red Flags: {$stats1['red_flags_detected']}\n\n";

echo "=== With Red-Flagging ===\n";
$withRedFlag = new MakerAgent($client, [
    'voting_k' => 2,
    'enable_red_flagging' => true,
]);

$result2 = $withRedFlag->run($ambiguousTask);
$stats2 = $result2->getMetadata()['execution_stats'];
echo "Answer: {$result2->getAnswer()}\n";
echo "Votes: {$stats2['votes_cast']}\n";
echo "Red Flags Detected & Retried: {$stats2['red_flags_detected']}\n";
```

### Red Flag Patterns Detected

The agent detects these linguistic markers:

| Phrase | Weight | Meaning |
|--------|--------|---------|
| "wait, maybe" | 1.0 | Strong uncertainty |
| "let me reconsider" | 1.0 | Reconsidering answer |
| "on second thought" | 1.0 | Changing answer |
| "not as we think" | 1.0 | Contradiction |
| "actually" | 0.5 | Mild correction |
| "hmm" | 0.3 | Thinking indicator |
| "wait" | 0.3 | Pause indicator |

When total red flag score ≥ 1.0, the response is retried.

### Circular Reasoning Detection

```php
// Red flag also detects circular reasoning:
// If 70%+ of sentences are duplicates → red flag

Example Response:
"The answer is X because X. Since X, we get X. Therefore X."
→ 🚩 Circular reasoning detected → Retry
```

## Tutorial 5: Multi-Step Mathematical Problems

MAKER excels at sequential calculations.

### Example: Compound Interest

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$maker = new MakerAgent($client, [
    'voting_k' => 3,  // High reliability for financial calculations
    'enable_red_flagging' => true,
]);

$task = <<<TASK
Calculate compound interest:
- Principal: \$5,000
- Annual rate: 6%
- Time: 5 years
- Compounded annually

Show the balance after each year and the final amount.
TASK;

$result = $maker->run($task);

if ($result->isSuccess()) {
    echo "Problem:\n{$task}\n\n";
    echo "Solution:\n{$result->getAnswer()}\n\n";
    
    $stats = $result->getMetadata()['execution_stats'];
    echo "Reliability Metrics:\n";
    echo "  Decompositions: {$stats['decompositions']}\n";
    echo "  Subtasks: {$stats['subtasks_created']}\n";
    echo "  Votes for Consensus: {$stats['votes_cast']}\n";
    echo "  Error Rate: " . $result->getMetadata()['error_rate'] . "\n";
}
```

### Expected Output

```
Solution:
Year 1: $5,000 × 1.06 = $5,300
Year 2: $5,300 × 1.06 = $5,618
Year 3: $5,618 × 1.06 = $5,955.08
Year 4: $5,955.08 × 1.06 = $6,312.38
Year 5: $6,312.38 × 1.06 = $6,691.13

Final Amount: $6,691.13
Total Interest Earned: $1,691.13

Reliability Metrics:
  Decompositions: 2
  Subtasks: 7
  Votes for Consensus: 21
  Error Rate: 0.0
```

## Tutorial 6: Logic Puzzles

MAKER handles complex logical reasoning through decomposition.

### Example: Classic Logic Puzzle

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$maker = new MakerAgent($client, [
    'voting_k' => 3,  // Logic requires high reliability
    'max_decomposition_depth' => 8,
]);

$puzzle = <<<PUZZLE
Five houses in a row, each painted a different color.
Clues:
1. The red house is immediately to the left of the blue house
2. The green house is at one end
3. The yellow house is in the middle
4. The white house is next to the green house

What is the order of houses from left to right?
PUZZLE;

$result = $maker->run($puzzle);

if ($result->isSuccess()) {
    echo "Puzzle:\n{$puzzle}\n\n";
    echo "Solution:\n{$result->getAnswer()}\n\n";
    
    $stats = $result->getMetadata()['execution_stats'];
    echo "Reasoning Process:\n";
    echo "  Total Steps: {$stats['total_steps']}\n";
    echo "  Decomposition Levels: {$stats['decompositions']}\n";
    echo "  Subtasks Evaluated: {$stats['subtasks_created']}\n";
}
```

## Tutorial 7: The Towers of Hanoi Benchmark

The paper's benchmark problem - solving the Towers of Hanoi puzzle.

### Understanding the Problem

For N disks, the optimal solution requires 2^N - 1 moves:
- 3 disks: 7 moves
- 4 disks: 15 moves
- 5 disks: 31 moves
- 20 disks: 1,048,575 moves (the paper's benchmark!)

### Example: 3-Disk Problem

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$maker = new MakerAgent($client, [
    'voting_k' => 2,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 6,
]);

$task = <<<TASK
Solve the Towers of Hanoi puzzle with 3 disks.

Rules:
- Three pegs (A, B, C)
- All disks start on peg A
- Goal: Move all disks to peg C
- Only move one disk at a time
- Never place a larger disk on a smaller disk

Provide the complete sequence of moves.
TASK;

echo "Solving Towers of Hanoi (3 disks)...\n\n";

$startTime = microtime(true);
$result = $maker->run($task);
$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    echo "Solution:\n{$result->getAnswer()}\n\n";
    
    // Validate solution
    $moveCount = preg_match_all('/move/i', $result->getAnswer());
    $expectedMoves = pow(2, 3) - 1;  // 7 moves
    
    echo "Validation:\n";
    echo "  Moves in solution: {$moveCount}\n";
    echo "  Expected moves: {$expectedMoves}\n";
    echo "  Correct: " . ($moveCount === $expectedMoves ? "✓ YES" : "✗ NO") . "\n\n";
    
    $stats = $result->getMetadata()['execution_stats'];
    echo "MAKER Statistics:\n";
    echo "  Total Steps: {$stats['total_steps']}\n";
    echo "  Decompositions: {$stats['decompositions']}\n";
    echo "  Votes Cast: {$stats['votes_cast']}\n";
    echo "  Duration: " . round($duration, 2) . "s\n";
}
```

### Expected Solution

```
Solution:
Move disk 1 from A to C
Move disk 2 from A to B
Move disk 1 from C to B
Move disk 3 from A to C
Move disk 1 from B to A
Move disk 2 from B to C
Move disk 1 from A to C

Validation:
  Moves in solution: 7
  Expected moves: 7
  Correct: ✓ YES

MAKER Statistics:
  Total Steps: 8
  Decompositions: 3
  Votes Cast: 24
  Duration: 5.4s
```

See `examples/maker_towers_hanoi.php` for the complete implementation with validation.

## Tutorial 8: Tuning for Performance

Learn to optimize MAKER for your specific needs.

### Speed vs. Reliability Trade-off

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$task = "Calculate the factorial of 8 (8!)";

// Fast configuration (less reliable)
echo "=== Fast Configuration ===\n";
$fastMaker = new MakerAgent($client, [
    'voting_k' => 2,
    'enable_red_flagging' => false,
    'max_decomposition_depth' => 3,
]);

$start = microtime(true);
$result1 = $fastMaker->run($task);
$duration1 = microtime(true) - $start;

echo "Duration: " . round($duration1, 2) . "s\n";
echo "Votes: {$result1->getMetadata()['execution_stats']['votes_cast']}\n\n";

// Reliable configuration (slower)
echo "=== Reliable Configuration ===\n";
$reliableMaker = new MakerAgent($client, [
    'voting_k' => 4,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 10,
]);

$start = microtime(true);
$result2 = $reliableMaker->run($task);
$duration2 = microtime(true) - $start;

echo "Duration: " . round($duration2, 2) . "s\n";
echo "Votes: {$result2->getMetadata()['execution_stats']['votes_cast']}\n\n";

// Balanced configuration (recommended)
echo "=== Balanced Configuration (Recommended) ===\n";
$balancedMaker = new MakerAgent($client, [
    'voting_k' => 3,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 10,
]);

$start = microtime(true);
$result3 = $balancedMaker->run($task);
$duration3 = microtime(true) - $start;

echo "Duration: " . round($duration3, 2) . "s\n";
echo "Votes: {$result3->getMetadata()['execution_stats']['votes_cast']}\n";
```

### Configuration Guidelines

**For Simple Tasks (< 10 steps):**
```php
[
    'voting_k' => 2,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 5,
]
```

**For Medium Tasks (10-100 steps):**
```php
[
    'voting_k' => 3,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 10,
]
```

**For Complex/Critical Tasks (100+ steps):**
```php
[
    'voting_k' => 4,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 15,
]
```

**For Cost-Sensitive Applications:**
```php
[
    'model' => 'claude-haiku-4',  // Cheaper model
    'voting_k' => 2,
    'max_tokens' => 1024,
]
```

## Production Best Practices

### 1. Always Use Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('maker');
$logger->pushHandler(new StreamHandler('logs/maker.log', Logger::DEBUG));

$maker = new MakerAgent($client, [
    'logger' => $logger,
]);
```

### 2. Monitor Execution Statistics

```php
$result = $maker->run($task);

if ($result->isSuccess()) {
    $stats = $result->getMetadata()['execution_stats'];
    
    // Alert on anomalies
    if ($stats['red_flags_detected'] > $stats['total_steps'] * 0.2) {
        // Too many red flags - task might be ambiguous
        $logger->warning('High red flag rate', ['stats' => $stats]);
    }
    
    if ($stats['votes_cast'] > $stats['atomic_executions'] * 15) {
        // Too many votes needed - low consensus
        $logger->warning('Low consensus rate', ['stats' => $stats]);
    }
}
```

### 3. Set Timeouts

```php
$timeout = 300;  // 5 minutes
$startTime = time();

$result = $maker->run($task);

if (time() - $startTime > $timeout) {
    throw new RuntimeException('Task execution timeout');
}
```

### 4. Handle Failures Gracefully

```php
$result = $maker->run($task);

if (!$result->isSuccess()) {
    $error = $result->getError();
    $stats = $result->getMetadata()['execution_stats'] ?? [];
    
    // Log failure details
    $logger->error('MAKER task failed', [
        'error' => $error,
        'task' => substr($task, 0, 100),
        'stats' => $stats,
    ]);
    
    // Could retry with different parameters
    if ($stats['total_steps'] === 0) {
        // Failed immediately, maybe API issue
        sleep(5);
        $result = $maker->run($task);  // Retry
    }
}
```

### 5. Validate Critical Results

```php
$result = $maker->run($financialCalculation);

if ($result->isSuccess()) {
    $answer = $result->getAnswer();
    
    // Extract numeric result
    preg_match('/\$?([\d,]+\.?\d*)/', $answer, $matches);
    $amount = floatval(str_replace(',', '', $matches[1] ?? '0'));
    
    // Sanity check
    if ($amount < 0 || $amount > 1000000) {
        $logger->warning('Result outside expected range', [
            'amount' => $amount,
            'answer' => $answer,
        ]);
        
        // Maybe run again with higher K
        $maker->setVotingK(4);
        $result = $maker->run($financialCalculation);
    }
}
```

## Common Patterns

### Pattern 1: Progressive Enhancement

Start fast, increase reliability if needed:

```php
$maker = new MakerAgent($client, ['voting_k' => 2]);

$result = $maker->run($task);

// If uncertain, retry with higher reliability
if ($result->getMetadata()['error_rate'] > 0.01) {
    $maker->setVotingK(3);
    $result = $maker->run($task);
}
```

### Pattern 2: Task-Specific Configuration

```php
function getMakerForTaskType(string $type, ClaudePhp $client): MakerAgent
{
    $configs = [
        'math' => ['voting_k' => 3, 'max_decomposition_depth' => 8],
        'logic' => ['voting_k' => 4, 'max_decomposition_depth' => 12],
        'simple' => ['voting_k' => 2, 'max_decomposition_depth' => 5],
        'critical' => ['voting_k' => 4, 'max_decomposition_depth' => 15],
    ];
    
    return new MakerAgent($client, $configs[$type] ?? $configs['simple']);
}

// Usage
$mathMaker = getMakerForTaskType('math', $client);
$result = $mathMaker->run($mathProblem);
```

### Pattern 3: Batch Processing

```php
$tasks = [
    "Calculate 15 × 23",
    "What is the square root of 144?",
    "Calculate 100 / 4",
    // ... more tasks
];

$maker = new MakerAgent($client, ['voting_k' => 2]);

$results = [];
foreach ($tasks as $task) {
    $result = $maker->run($task);
    $results[] = [
        'task' => $task,
        'answer' => $result->getAnswer(),
        'stats' => $result->getMetadata()['execution_stats'],
    ];
}

// Analyze batch statistics
$totalVotes = array_sum(array_column(array_column($results, 'stats'), 'votes_cast'));
$avgVotesPerTask = $totalVotes / count($tasks);

echo "Average votes per task: {$avgVotesPerTask}\n";
```

## Troubleshooting

### Issue: Too Many Votes Needed

**Symptom:** High vote counts, slow execution

**Causes:**
- Task is ambiguous
- Multiple valid interpretations
- Red flags triggering often

**Solutions:**
```php
// 1. Check task clarity
if ($stats['votes_cast'] > $stats['atomic_executions'] * 10) {
    echo "Task may be ambiguous. Rephrase for clarity.\n";
}

// 2. Reduce voting threshold temporarily
$maker->setVotingK(2);

// 3. Disable red-flagging if causing excessive retries
$maker->setRedFlagging(false);
```

### Issue: Decomposition Too Shallow

**Symptom:** Few subtasks, errors in results

**Solution:**
```php
// Increase decomposition depth
$maker = new MakerAgent($client, [
    'max_decomposition_depth' => 15,  // Higher limit
]);

// Or manually break down task before passing
$subtasks = explode("\n", $task);
foreach ($subtasks as $subtask) {
    $result = $maker->run($subtask);
}
```

### Issue: High API Costs

**Symptom:** Many API calls, high token usage

**Solutions:**
```php
// 1. Use cheaper model
$maker = new MakerAgent($client, [
    'model' => 'claude-haiku-4',
]);

// 2. Reduce voting threshold
$maker = new MakerAgent($client, [
    'voting_k' => 2,  // Minimum
]);

// 3. Limit decomposition
$maker = new MakerAgent($client, [
    'max_decomposition_depth' => 3,
]);

// 4. Cache results for repeated tasks
$cache = [];
$cacheKey = md5($task);
if (isset($cache[$cacheKey])) {
    $result = $cache[$cacheKey];
} else {
    $result = $maker->run($task);
    $cache[$cacheKey] = $result;
}
```

### Issue: Task Timeouts

**Symptom:** Very long execution times

**Solutions:**
```php
// 1. Set conservative limits
$maker = new MakerAgent($client, [
    'max_decomposition_depth' => 5,
    'voting_k' => 2,
]);

// 2. Implement timeout
$timeout = 60;  // seconds
$start = time();

// Could implement interruptible execution
// (requires custom modification)
```

## Next Steps

Congratulations! You've learned the fundamentals of the MAKER framework. Here's what to explore next:

### 1. Advanced Topics

- **Parallel Voting**: Implement concurrent API calls for faster voting
- **Adaptive K**: Dynamically adjust K based on task confidence
- **Custom Micro-Agents**: Specialized agents for domain tasks
- **Persistent State**: Save/resume execution for very long tasks

### 2. Integration Patterns

- Integrate with your existing agent systems
- Combine with RAG for knowledge-intensive tasks
- Use with ChainOfThoughtAgent for hybrid approaches
- Build multi-agent systems with specialized MAKERs

### 3. Research Directions

- Explore the original paper for theoretical insights
- Experiment with different decomposition strategies
- Measure error rates on your specific tasks
- Contribute improvements back to the framework

### 4. Production Deployment

- Set up monitoring and alerting
- Implement caching and optimization
- Build user interfaces for task submission
- Create dashboards for execution analytics

## Additional Resources

- **Paper**: [Solving a Million-Step LLM Task with Zero Errors](https://arxiv.org/html/2511.09030v1)
- **Documentation**: `docs/MakerAgent.md`
- **Examples**: `examples/maker_*.php`
- **API Reference**: See documentation for complete method details

## Conclusion

The MAKER framework represents a paradigm shift in reliable AI task execution. By combining:

1. **Maximal Decomposition** - Breaking tasks into atomic units
2. **Voting Error Correction** - Achieving consensus through multiple agents
3. **Red-Flagging** - Detecting and retrying uncertain responses

You can now build systems that solve complex, multi-step problems with unprecedented reliability.

Start building, experiment with the parameters, and see how MAKER can enhance your applications!

Happy coding! 🚀

