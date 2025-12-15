# MakerAgent Documentation

## Overview

The `MakerAgent` implements the MAKER framework (Massively Decomposed Agentic Processes with first-to-ahead-by-K Error correction and Red-flagging) from the groundbreaking paper ["Solving a Million-Step LLM Task with Zero Errors"](https://arxiv.org/html/2511.09030v1).

This agent can solve complex tasks requiring millions of steps with near-zero error rates through extreme decomposition, multi-agent voting, and uncertainty detection.

## Features

- 🔬 **Maximal Decomposition**: Breaks complex tasks into minimal, atomic subtasks
- 🗳️ **First-to-Ahead-by-K Voting**: Multi-agent consensus for each subtask to eliminate errors
- 🚩 **Red-Flagging**: Detects and retries unreliable or uncertain responses
- 📊 **Execution Statistics**: Detailed metrics on decomposition depth, votes, and error rates
- 🎯 **High Reliability**: Achieves near-zero error rates even on million-step tasks
- ⚙️ **Configurable Parameters**: Tune voting thresholds, decomposition depth, and more

## Key Concepts

### Massively Decomposed Agentic Processes (MDAP)

Instead of having a single LLM solve a complex task in one go, MAKER decomposes tasks into the smallest possible subtasks. Each subtask is so simple that even a non-reasoning model can execute it reliably.

### First-to-Ahead-by-K Voting

For each subtask, multiple micro-agents vote on the answer. The first answer to achieve a lead of K votes wins. This decorrelates errors and ensures consensus before proceeding.

### Red-Flagging

The agent detects linguistic markers of uncertainty (e.g., "wait, maybe", "let me reconsider") and circular reasoning patterns. When detected, the response is discarded and a new attempt is made.

## Installation

The MakerAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\MakerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');

$maker = new MakerAgent($client, [
    'voting_k' => 2,              // First to lead by 2 votes wins
    'enable_red_flagging' => true, // Enable uncertainty detection
    'max_decomposition_depth' => 10,
]);

$result = $maker->run('Calculate 15! (15 factorial) step by step');

if ($result->isSuccess()) {
    echo "Answer: {$result->getAnswer()}\n";

    // View execution statistics
    $stats = $result->getMetadata()['execution_stats'];
    echo "Total Steps: {$stats['total_steps']}\n";
    echo "Votes Cast: {$stats['votes_cast']}\n";
    echo "Error Rate: {$result->getMetadata()['error_rate']}\n";
}
```

## Configuration

The MakerAgent accepts configuration options in its constructor:

```php
$maker = new MakerAgent($client, [
    'name' => 'my_maker_agent',
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 2048,
    'voting_k' => 3,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 10,
    'logger' => $logger,
]);
```

### Configuration Options

| Option                    | Type            | Default               | Description                                                 |
| ------------------------- | --------------- | --------------------- | ----------------------------------------------------------- |
| `name`                    | string          | `'maker_agent'`       | Unique name for the agent                                   |
| `model`                   | string          | `'claude-sonnet-4-5'` | Claude model to use                                         |
| `max_tokens`              | int             | `2048`                | Maximum tokens per API call                                 |
| `voting_k`                | int             | `3`                   | Vote margin required for consensus (higher = more reliable) |
| `enable_red_flagging`     | bool            | `true`                | Enable uncertainty detection and retry                      |
| `max_decomposition_depth` | int             | `10`                  | Maximum recursion depth for decomposition                   |
| `logger`                  | LoggerInterface | `NullLogger`          | PSR-3 compatible logger                                     |

### Voting K Parameter

The `voting_k` parameter controls the consensus threshold:

- **K=2**: Moderate reliability, faster execution (5 votes max)
- **K=3**: High reliability, balanced performance (recommended)
- **K=4**: Very high reliability, slower execution

The paper shows that K=2 or K=3 is optimal for most tasks.

## How It Works

### 1. Task Decomposition

The agent analyzes each task and decides whether to decompose it further:

```php
// Heuristics for decomposition:
- Task length > 100 characters
- Contains sequential keywords: "then", "next", "after", "finally"
- Contains enumeration: "first", "second", "1.", "2."
- Current depth < max_decomposition_depth
```

If decomposition is needed, the agent uses voting to ensure correct breakdown:

```php
$subtasks = $this->decomposeWithVoting($task);
// Returns: ["Step 1", "Step 2", "Step 3", ...]
```

### 2. Atomic Execution with Voting

For atomic tasks, multiple micro-agents execute independently and vote:

```php
// Generate N = 2k-1 candidate answers
// First answer to lead by K votes wins
// If red flag detected, retry
```

### 3. Result Composition

Subtask results are composed back into the final answer, also using voting for reliability.

## Execution Statistics

The agent tracks detailed execution metrics:

```php
$result = $maker->run($task);
$stats = $result->getMetadata()['execution_stats'];

// Available statistics:
$stats['total_steps']        // Total execution steps
$stats['atomic_executions']  // Number of atomic tasks executed
$stats['decompositions']     // Number of decompositions performed
$stats['subtasks_created']   // Total subtasks created
$stats['votes_cast']         // Total votes across all decisions
$stats['red_flags_detected'] // Number of uncertain responses retried

// Also available:
$metadata['error_rate']      // Estimated error rate (0.0 - 1.0)
$metadata['duration_seconds'] // Total execution time
```

## Red-Flagging

Red-flagging detects unreliable responses using linguistic markers:

```php
// Red flag indicators (from the paper):
'wait, maybe'          => 1.0    // Strong red flag
'let me reconsider'    => 1.0
'on second thought'    => 1.0
'not as we think'      => 1.0
'actually'             => 0.5    // Moderate red flag
'wait'                 => 0.3    // Weak red flag
'hmm'                  => 0.3

// Circular reasoning detection:
// If 70%+ sentences are duplicates -> red flag
```

When red flags accumulate to a score ≥ 1.0, the response is discarded and retried.

## Advanced Usage

### Dynamic Configuration

Adjust parameters during execution:

```php
$maker = new MakerAgent($client);

// For critical tasks, increase reliability
$maker->setVotingK(4);
$maker->setRedFlagging(true);

$result = $maker->run($criticalTask);

// For simple tasks, optimize for speed
$maker->setVotingK(2);
$result = $maker->run($simpleTask);
```

### Custom Micro-Agents

While the MakerAgent creates micro-agents internally, you can understand their roles:

- **decomposer**: Breaks tasks into subtasks
- **executor**: Executes atomic tasks
- **composer**: Combines subtask results
- **validator**: Validates results (extensible)
- **discriminator**: Chooses between alternatives (extensible)

### Handling Long-Running Tasks

For tasks that might take a long time:

```php
use Psr\Log\LoggerInterface;

$maker = new MakerAgent($client, [
    'logger' => $logger,  // Monitor progress
    'voting_k' => 2,      // Optimize for speed
    'max_decomposition_depth' => 15,  // Allow deeper decomposition
]);

// The agent will log:
// - Decomposition decisions
// - Voting progress
// - Red flag detections
// - Completion metrics
```

## Best Practices

### 1. Choose the Right K

```php
// Simple tasks (arithmetic, lookup):
'voting_k' => 2

// Complex reasoning (logic, planning):
'voting_k' => 3

// Critical tasks (medical, financial):
'voting_k' => 4
```

### 2. Enable Red-Flagging

Always enable red-flagging unless you have a specific reason not to:

```php
'enable_red_flagging' => true  // Recommended
```

### 3. Set Appropriate Depth

```php
// Short tasks (< 10 steps):
'max_decomposition_depth' => 5

// Medium tasks (10-100 steps):
'max_decomposition_depth' => 10  // Default

// Long tasks (100+ steps):
'max_decomposition_depth' => 15
```

### 4. Monitor Performance

```php
$result = $maker->run($task);

if ($result->isSuccess()) {
    $stats = $result->getMetadata()['execution_stats'];

    // High vote count might indicate ambiguous task
    if ($stats['votes_cast'] > $stats['atomic_executions'] * 10) {
        echo "Warning: High vote count, task may be ambiguous\n";
    }

    // Many red flags might indicate confusion
    if ($stats['red_flags_detected'] > $stats['total_steps'] * 0.1) {
        echo "Warning: Many red flags detected\n";
    }
}
```

## Paper Results vs Implementation

### Paper (arXiv:2511.09030v1)

- **Task**: 20-disk Towers of Hanoi (1,048,575 moves)
- **Result**: ZERO ERRORS
- **Comparison**: Standard LLMs fail after ~100-300 steps

### This Implementation

- Demonstrates the same principles on practical PHP tasks
- Optimized for typical use cases (10-1000 steps)
- Achieves near-zero error rates on decomposable tasks
- Suitable for production use with appropriate task sizing

## When to Use MakerAgent

### ✅ Good Use Cases

- Multi-step mathematical calculations
- Sequential procedures with validation
- Complex reasoning with verifiable steps
- Tasks requiring high reliability
- Problems that can be decomposed

### ❌ Not Suitable For

- Simple single-step tasks (use regular agents)
- Creative/open-ended generation
- Tasks requiring domain knowledge databases
- Real-time latency-critical applications
- Non-decomposable holistic tasks

## Performance Considerations

### Token Usage

MakerAgent uses multiple API calls per subtask:

```
Tokens ≈ subtasks × (2k-1) × avg_tokens_per_call
```

For a task with 10 subtasks, K=2:

- Votes per subtask: 3-5 (first-to-ahead-by-2)
- Total calls: ~30-50
- Estimate tokens accordingly

### Execution Time

```
Time ≈ subtasks × votes_per_subtask × avg_api_latency
```

For faster execution:

- Reduce `voting_k`
- Reduce `max_decomposition_depth`
- Use faster models (e.g., Claude Haiku)

### Cost Optimization

```php
// For cost-sensitive applications:
$maker = new MakerAgent($client, [
    'model' => 'claude-haiku-4',  // Cheaper model
    'voting_k' => 2,               // Minimum reliable voting
    'max_tokens' => 1024,          // Reduce if possible
]);
```

## Error Handling

```php
$result = $maker->run($task);

if (!$result->isSuccess()) {
    $error = $result->getError();
    $stats = $result->getMetadata()['execution_stats'] ?? [];

    echo "Task failed: {$error}\n";
    echo "Attempted steps: {$stats['total_steps']}\n";

    // Retry with different parameters?
    $maker->setVotingK($maker->getVotingK() + 1);
}
```

## Comparison with Other Agents

| Feature                | MakerAgent | ChainOfThoughtAgent | AutonomousAgent |
| ---------------------- | ---------- | ------------------- | --------------- |
| Multi-step reliability | ⭐⭐⭐⭐⭐ | ⭐⭐⭐              | ⭐⭐⭐          |
| Complex reasoning      | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐            | ⭐⭐⭐          |
| Speed                  | ⭐⭐       | ⭐⭐⭐⭐            | ⭐⭐⭐          |
| Token efficiency       | ⭐⭐       | ⭐⭐⭐⭐            | ⭐⭐⭐          |
| Error correction       | ⭐⭐⭐⭐⭐ | ⭐⭐                | ⭐⭐            |
| Task decomposition     | ⭐⭐⭐⭐⭐ | ⭐⭐                | ⭐⭐⭐⭐        |

## Examples

See the `examples/` directory for complete examples:

- `maker_example.php`: Basic usage with multiple task types
- `maker_towers_hanoi.php`: Benchmark on the paper's Towers of Hanoi problem

## API Reference

### MakerAgent Methods

```php
// Core method
public function run(string $task): AgentResult

// Configuration methods
public function setVotingK(int $k): self
public function setRedFlagging(bool $enabled): self

// Statistics
public function getExecutionStats(): array

// Agent interface
public function getName(): string
```

### AgentResult Methods

```php
public function isSuccess(): bool
public function getAnswer(): string
public function getError(): string
public function getMetadata(): array
public function getIterations(): int
```

## Further Reading

- **Original Paper**: [Solving a Million-Step LLM Task with Zero Errors](https://arxiv.org/html/2511.09030v1)
- **Tutorial**: See `docs/tutorials/MakerAgent_Tutorial.md`
- **Examples**: See `examples/maker_*.php`
