# TreeOfThoughtsAgent

The `TreeOfThoughtsAgent` implements the Tree-of-Thoughts (ToT) reasoning pattern, a powerful approach for exploring multiple solution paths through systematic tree search. This agent is particularly effective for complex problem-solving tasks where considering multiple approaches simultaneously improves solution quality.

## Table of Contents

- [Overview](#overview)
- [Key Concepts](#key-concepts)
- [Features](#features)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Configuration Options](#configuration-options)
- [Search Strategies](#search-strategies)
- [Example Patterns](#example-patterns)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)

## Overview

Tree-of-Thoughts reasoning explores problem-solving as a tree structure where each node represents a thought or approach. The agent systematically generates, evaluates, and explores multiple branches to find the best solution path. Unlike linear reasoning, ToT enables:

1. **Parallel exploration** of multiple solution approaches
2. **Backtracking** from unpromising paths
3. **Strategic search** using different algorithms
4. **Evaluation** of intermediate steps

## Key Concepts

### Tree Structure

- **Root Node**: The original problem statement
- **Child Nodes**: Different approaches or next steps
- **Branches**: Exploration paths through the solution space
- **Leaves**: Terminal nodes representing complete solution paths

### Search Strategies

- **Best-First Search**: Prioritizes highest-scored nodes (most efficient)
- **Breadth-First Search**: Explores all nodes at each depth level
- **Depth-First Search**: Follows one path deeply before exploring others

### Evaluation

Each thought/approach is scored on a scale of 1-10 based on:
- Feasibility
- Likelihood of success
- Efficiency
- Custom criteria

## Features

- ✅ Multiple search strategies (best-first, breadth-first, depth-first)
- ✅ Configurable branching factor
- ✅ Adjustable tree depth
- ✅ Automatic thought evaluation
- ✅ Best path extraction
- ✅ Token usage tracking
- ✅ PSR-3 logging support
- ✅ Comprehensive metadata
- ✅ Type-safe implementation

## Installation

The `TreeOfThoughtsAgent` is included in the `claude-php-agent` package:

```bash
composer require claude-php-agent
```

## Basic Usage

### Simple Best-First Search

```php
use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

$result = $agent->run(
    'Use the numbers 3, 5, 7, 11 with basic operations to make 24'
);

if ($result->isSuccess()) {
    echo $result->getAnswer();
    // Output shows the best solution path with scores
}
```

### With Custom Configuration

```php
$agent = new TreeOfThoughtsAgent($client, [
    'name' => 'problem_solver',
    'branch_count' => 4,
    'max_depth' => 4,
    'search_strategy' => 'best_first',
    'logger' => $logger,
]);

$result = $agent->run('Design an efficient database schema for an e-commerce site');
```

## Configuration Options

### Constructor Parameters

```php
new TreeOfThoughtsAgent(ClaudePhp $client, array $options = [])
```

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | `string` | `'tot_agent'` | Agent identifier |
| `branch_count` | `int` | `3` | Number of thoughts to generate per node |
| `max_depth` | `int` | `4` | Maximum tree depth to explore |
| `search_strategy` | `string` | `'best_first'` | Search algorithm: `'best_first'`, `'breadth_first'`, or `'depth_first'` |
| `logger` | `LoggerInterface` | `NullLogger` | PSR-3 logger instance |

## Search Strategies

### Best-First Search

Explores the most promising nodes first based on evaluation scores.

```php
$agent = new TreeOfThoughtsAgent($client, [
    'search_strategy' => 'best_first',
    'branch_count' => 3,
    'max_depth' => 4,
]);
```

**When to Use:**
- Finding optimal solutions efficiently
- Limited token budget
- Quality over exploration coverage

**Characteristics:**
- Most token-efficient
- Focuses on high-quality paths
- May miss alternative solutions

### Breadth-First Search

Explores all nodes at each depth level before proceeding deeper.

```php
$agent = new TreeOfThoughtsAgent($client, [
    'search_strategy' => 'breadth_first',
    'branch_count' => 2,
    'max_depth' => 3,
]);
```

**When to Use:**
- Need comprehensive exploration
- Want to see all options at each level
- Problem has multiple valid approaches

**Characteristics:**
- Systematic exploration
- Good coverage of solution space
- Higher token usage

### Depth-First Search

Follows one branch completely before exploring others.

```php
$agent = new TreeOfThoughtsAgent($client, [
    'search_strategy' => 'depth_first',
    'branch_count' => 2,
    'max_depth' => 5,
]);
```

**When to Use:**
- Following logical sequences
- Deep reasoning required
- Memory-constrained environments

**Characteristics:**
- Focuses on completion
- Lower memory usage
- May miss better alternatives

## Example Patterns

### Mathematical Problem Solving

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

$result = $agent->run(
    'Solve: (x + 3) * 2 = 14. Find x using multiple approaches.'
);

foreach ($result->getMetadata()['path'] ?? [] as $step) {
    echo "Step: {$step['thought']} (Score: {$step['score']})\n";
}
```

### Strategic Planning

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 4,
    'search_strategy' => 'best_first',
]);

$result = $agent->run(
    'Create a 6-month product launch strategy for a new SaaS application'
);
```

### Design Decisions

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'breadth_first',
]);

$result = $agent->run(
    'Design a database schema for a social media application. ' .
    'Consider scalability, performance, and data relationships.'
);
```

### Algorithm Optimization

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

$result = $agent->run(
    'Optimize a sorting algorithm for a dataset with mostly sorted data. ' .
    'What modifications would improve performance?'
);
```

## Best Practices

### 1. Choose the Right Strategy

**Best-first for:**
- Optimization problems
- Resource-constrained scenarios
- Finding optimal solutions

**Breadth-first for:**
- Exploring alternatives
- Comprehensive analysis
- Multiple valid solutions

**Depth-first for:**
- Sequential reasoning
- Following single threads
- Deep analysis

### 2. Balance Branch Count and Depth

```php
// Narrow and deep - focused exploration
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 5,
]);

// Wide and shallow - broad coverage
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 5,
    'max_depth' => 2,
]);

// Balanced - general purpose
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
]);
```

### 3. Monitor Token Usage

```php
$result = $agent->run($task);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    $totalTokens = $metadata['tokens']['input'] + $metadata['tokens']['output'];
    
    echo "Tokens used: {$totalTokens}\n";
    echo "Nodes explored: {$metadata['total_nodes']}\n";
    echo "Efficiency: " . ($totalTokens / $metadata['total_nodes']) . " tokens/node\n";
}
```

### 4. Handle Results Properly

```php
$result = $agent->run($task);

if ($result->isSuccess()) {
    // Extract the solution
    $answer = $result->getAnswer();
    
    // Get metadata for analysis
    $metadata = $result->getMetadata();
    
    echo "Best path length: {$metadata['path_length']}\n";
    echo "Best score: {$metadata['best_score']}/10\n";
    echo "Total nodes explored: {$metadata['total_nodes']}\n";
    echo "Max depth reached: {$metadata['max_depth']}\n";
} else {
    // Log and handle errors
    error_log("ToT Agent failed: " . $result->getError());
}
```

### 5. Use Logging for Debugging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('tot-agent');
$logger->pushHandler(new StreamHandler('logs/tot.log', Logger::DEBUG));

$agent = new TreeOfThoughtsAgent($client, [
    'logger' => $logger,
    'search_strategy' => 'best_first',
]);
```

### 6. Optimize for Your Use Case

```php
// For quick prototyping
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 2,
]);

// For production quality
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 4,
    'logger' => $productionLogger,
]);

// For exhaustive search
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 5,
    'max_depth' => 3,
    'search_strategy' => 'breadth_first',
]);
```

## API Reference

### TreeOfThoughtsAgent Class

```php
class TreeOfThoughtsAgent implements AgentInterface
```

#### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
)
```

#### Methods

##### run(string $task): AgentResult

Execute the agent with a problem-solving task.

**Parameters:**
- `$task` - The problem to solve using tree-of-thoughts exploration

**Returns:**
- `AgentResult` object containing:
  - `answer` - Best solution path with step-by-step reasoning
  - `iterations` - Number of iterations (always 1 for ToT)
  - `metadata` - Contains:
    - `strategy` - Search strategy used
    - `total_nodes` - Total nodes in tree
    - `max_depth` - Maximum depth reached
    - `path_length` - Length of best path
    - `best_score` - Score of best solution
    - `tokens` - Token usage information
  - `success` - Whether the task completed successfully

**Example:**

```php
$result = $agent->run('Solve this complex problem');

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    $metadata = $result->getMetadata();
    echo "Strategy: {$metadata['strategy']}\n";
    echo "Nodes explored: {$metadata['total_nodes']}\n";
}
```

##### getName(): string

Get the agent's name.

**Returns:**
- The agent's configured name

### Supporting Classes

#### ThoughtTree

Manages the tree structure of thoughts.

```php
$tree = new ThoughtTree('Root problem');
$root = $tree->getRoot();
$child = $tree->addThought($root, 'Child thought');
$bestPath = $tree->getBestPath();
```

#### ThoughtNode

Represents a single thought in the tree.

```php
$node = new ThoughtNode('id', 'Thought text', 0);
$node->setScore(8.5);
$children = $node->getChildren();
$path = $node->getPath();
```

#### SearchStrategy

Implements search algorithms.

```php
$next = SearchStrategy::bestFirst($frontier, 3);
$next = SearchStrategy::breadthFirst($frontier, 2);
$next = SearchStrategy::depthFirst($frontier, 5);
```

#### Evaluator

Evaluates thought quality.

```php
$evaluator = new Evaluator($client, 'Problem', 'efficiency');
$score = $evaluator->evaluate('Proposed approach');
$scores = $evaluator->evaluateMultiple($thoughts);
```

## Advanced Usage

### Custom Evaluation Criteria

The agent evaluates thoughts based on feasibility and likelihood of success. For domain-specific evaluation, you can extend the Evaluator class.

### Pruning Strategies

Different search strategies implement implicit pruning:
- **Best-first**: Prunes low-scoring branches
- **Breadth-first**: No pruning (explores all)
- **Depth-first**: Prunes by following one path

### Performance Tuning

```php
// Token-efficient configuration
$efficient = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 2,
    'search_strategy' => 'best_first',
]);

// Quality-focused configuration
$quality = new TreeOfThoughtsAgent($client, [
    'branch_count' => 5,
    'max_depth' => 4,
    'search_strategy' => 'breadth_first',
]);
```

### Integration with Other Agents

```php
// Use ToT for exploration, then refine with CoT
$totAgent = new TreeOfThoughtsAgent($client);
$cotAgent = new ChainOfThoughtAgent($client);

$exploration = $totAgent->run($problem);
if ($exploration->isSuccess()) {
    // Refine the best approach
    $refinement = $cotAgent->run(
        "Refine this approach: " . $exploration->getAnswer()
    );
}
```

## Troubleshooting

### Common Issues

**Problem:** Tree exploration is too slow

**Solution:** Reduce branch count or max depth:

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 2,
]);
```

**Problem:** Not finding optimal solutions

**Solution:** Use best-first search with higher branch count:

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'search_strategy' => 'best_first',
]);
```

**Problem:** High token usage

**Solution:** Use best-first with lower branch count:

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);
```

## Examples

See the `examples/tot_example.php` file for comprehensive working examples including:
- Best-first search for optimization
- Breadth-first for exploration
- Depth-first for sequential reasoning
- Complex problem solving
- Different configurations

Run the example:

```bash
export ANTHROPIC_API_KEY='your-api-key'
php examples/tot_example.php
```

## Further Reading

- [Tutorial: Tree-of-Thoughts Reasoning](tutorials/TreeOfThoughtsAgent_Tutorial.md)
- [Agent Selection Guide](agent-selection-guide.md)
- [Research Paper: Tree of Thoughts](https://arxiv.org/abs/2305.10601)

## License

This component is part of the claude-php-agent package and is licensed under the MIT License.

