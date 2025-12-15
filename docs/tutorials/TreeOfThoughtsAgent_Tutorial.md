# Tree-of-Thoughts Agent Tutorial

Welcome to this comprehensive tutorial on using the `TreeOfThoughtsAgent`! Tree-of-Thoughts (ToT) reasoning is an advanced technique that explores multiple solution paths simultaneously through systematic tree search, enabling superior problem-solving capabilities.

## Table of Contents

1. [Introduction](#introduction)
2. [What is Tree-of-Thoughts Reasoning?](#what-is-tree-of-thoughts-reasoning)
3. [Setup](#setup)
4. [Tutorial 1: Your First ToT Agent](#tutorial-1-your-first-tot-agent)
5. [Tutorial 2: Understanding Search Strategies](#tutorial-2-understanding-search-strategies)
6. [Tutorial 3: Mathematical Problem Solving](#tutorial-3-mathematical-problem-solving)
7. [Tutorial 4: Strategic Planning](#tutorial-4-strategic-planning)
8. [Tutorial 5: Design Decisions](#tutorial-5-design-decisions)
9. [Tutorial 6: Tuning Performance](#tutorial-6-tuning-performance)
10. [Tutorial 7: Production Best Practices](#tutorial-7-production-best-practices)
11. [Common Patterns](#common-patterns)
12. [Troubleshooting](#troubleshooting)
13. [Next Steps](#next-steps)

## Introduction

This tutorial will teach you how to leverage Tree-of-Thoughts reasoning to explore complex solution spaces systematically. By the end, you'll be able to:

- Understand when and why to use ToT reasoning
- Implement different search strategies (best-first, breadth-first, depth-first)
- Optimize ToT agents for different problem types
- Build production-ready exploration systems
- Balance exploration vs. token efficiency

## What is Tree-of-Thoughts Reasoning?

Tree-of-Thoughts reasoning explores problem-solving as a tree structure where each node represents a potential approach or step. Unlike linear reasoning (Chain-of-Thought) that follows one path, ToT:

1. **Generates multiple thoughts** at each step (branches)
2. **Evaluates each thought** for quality/feasibility
3. **Strategically explores** the most promising paths
4. **Backtracks** from unpromising approaches
5. **Selects the best path** through the solution space

### Visual Comparison

**Chain-of-Thought (Linear):**
```
Problem → Step 1 → Step 2 → Step 3 → Solution
```

**Tree-of-Thoughts (Branching):**
```
                Problem
               /   |   \
            Idea1 Idea2 Idea3
           /  \    |     / \
        Step1 Step2 Step3 Step4 Step5
                    |
                Solution
```

### When to Use ToT vs CoT

**Use Tree-of-Thoughts when:**
- Multiple valid approaches exist
- You need to explore alternatives
- Quality matters more than speed
- Problem benefits from backtracking
- Solution space is complex

**Use Chain-of-Thought when:**
- Problem has clear sequential steps
- Speed/efficiency is critical
- One approach is clearly best
- Simple to moderate complexity

## Setup

First, install the package and set up your API key:

```bash
composer require claude-php-agent
```

Set your Anthropic API key:

```bash
export ANTHROPIC_API_KEY='your-api-key-here'
```

## Tutorial 1: Your First ToT Agent

Let's create a simple ToT agent to solve a classic problem.

### Step 1: Basic Best-First Search

Create a file `my_first_tot.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a ToT agent with best-first search
$agent = new TreeOfThoughtsAgent($client, [
    'name' => 'my_first_tot',
    'branch_count' => 3,        // Generate 3 ideas per node
    'max_depth' => 3,           // Explore up to 3 levels deep
    'search_strategy' => 'best_first',
]);

// Solve the classic "24 game"
$problem = "Use the numbers 3, 5, 7, 11 with basic operations (+, -, *, /) to make 24";

echo "Problem: {$problem}\n\n";

$result = $agent->run($problem);

if ($result->isSuccess()) {
    echo "Solution Path:\n";
    echo $result->getAnswer() . "\n\n";
    
    // Examine the metadata
    $metadata = $result->getMetadata();
    echo "Strategy: {$metadata['strategy']}\n";
    echo "Nodes explored: {$metadata['total_nodes']}\n";
    echo "Tree depth: {$metadata['max_depth']}\n";
    echo "Solution path length: {$metadata['path_length']}\n";
    echo "Best score: " . round($metadata['best_score'], 2) . "/10\n";
    echo "Tokens used: " . ($metadata['tokens']['input'] + $metadata['tokens']['output']) . "\n";
} else {
    echo "Error: " . $result->getError() . "\n";
}
```

Run it:

```bash
php my_first_tot.php
```

### Step 2: Understanding the Output

The agent will output something like:

```
Solution Path:

Step 1 (Score: 8.5): Try combining larger numbers first: 11 * 7 = 77
Step 2 (Score: 9.0): Then subtract to reduce: 77 - 5 = 72
Step 3 (Score: 9.5): Divide to reach target: 72 / 3 = 24

Strategy: best_first
Nodes explored: 13
Tree depth: 3
Solution path length: 4
Best score: 9.5/10
Tokens used: 1847
```

### What Happened?

1. **Root Node**: Started with the problem
2. **First Level**: Generated 3 different approaches
3. **Evaluation**: Scored each approach (1-10)
4. **Exploration**: Expanded the best-scored approaches
5. **Continued**: Repeated until max depth reached
6. **Selection**: Returned the highest-scored complete path

## Tutorial 2: Understanding Search Strategies

ToT supports three search strategies. Let's compare them.

### Best-First Search (Greedy)

Explores the most promising branches first.

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

$result = $agent->run("How can I reduce my monthly expenses by $500?");
```

**Characteristics:**
- Most token-efficient
- Focuses on quality over coverage
- May miss alternative solutions
- Best for optimization problems

### Breadth-First Search (Systematic)

Explores all nodes at each level before going deeper.

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 3,
    'search_strategy' => 'breadth_first',
]);

$result = $agent->run("What are the pros and cons of remote work?");
```

**Characteristics:**
- Comprehensive exploration
- Sees all options at each level
- Higher token usage
- Best for exploratory problems

### Depth-First Search (Sequential)

Follows one branch completely before exploring others.

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 4,
    'search_strategy' => 'depth_first',
]);

$result = $agent->run("Debug this: users can't login after password reset");
```

**Characteristics:**
- Follows logical sequences
- Lower memory usage
- May miss better alternatives
- Best for sequential problems

### Comparing All Three

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$problem = "Plan a healthy dinner for 4 people under $30";

$strategies = ['best_first', 'breadth_first', 'depth_first'];

foreach ($strategies as $strategy) {
    echo "\n=== {$strategy} ===\n";
    
    $agent = new TreeOfThoughtsAgent($client, [
        'branch_count' => 3,
        'max_depth' => 2,
        'search_strategy' => $strategy,
    ]);
    
    $result = $agent->run($problem);
    
    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        echo "Nodes explored: {$metadata['total_nodes']}\n";
        echo "Tokens used: " . ($metadata['tokens']['input'] + $metadata['tokens']['output']) . "\n";
    }
}
```

## Tutorial 3: Mathematical Problem Solving

ToT excels at mathematical problems with multiple solution paths.

### Example: Number Puzzles

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,        // More branches for more approaches
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

// Classic math puzzle
$problem = "I'm thinking of a number. If I multiply it by 3, add 7, " .
           "then divide by 2, I get 13. What's my number?";

echo "Problem: {$problem}\n\n";

$result = $agent->run($problem);

if ($result->isSuccess()) {
    echo $result->getAnswer() . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "The agent explored {$metadata['total_nodes']} different approaches\n";
    echo "to find the best solution (score: " . round($metadata['best_score'], 1) . "/10)\n";
}
```

### Example: Optimization Problems

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 4,
    'search_strategy' => 'best_first',
]);

$problem = "A farmer has 100 feet of fence. What dimensions of a rectangular " .
           "pen will maximize the area? Consider different approaches.";

$result = $agent->run($problem);
```

## Tutorial 4: Strategic Planning

ToT is excellent for planning problems with multiple valid approaches.

### Example: Product Launch

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Use breadth-first to explore different strategies
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 3,
    'search_strategy' => 'breadth_first',
]);

$problem = "Plan a go-to-market strategy for a new AI-powered task manager. " .
           "Consider target audience, pricing, channels, and timeline.";

echo "Strategic Planning Problem:\n{$problem}\n\n";

$result = $agent->run($problem);

if ($result->isSuccess()) {
    echo "Recommended Strategy:\n";
    echo $result->getAnswer() . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "Explored {$metadata['total_nodes']} strategic options\n";
    echo "Evaluation depth: {$metadata['max_depth']} levels\n";
}
```

### Example: Budget Allocation

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

$problem = "I have $10,000 to split between marketing, development, " .
           "and operations for my startup. How should I allocate it?";

$result = $agent->run($problem);
```

## Tutorial 5: Design Decisions

ToT helps explore design alternatives systematically.

### Example: Database Schema Design

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

$problem = "Design a database schema for a social media platform with users, posts, " .
           "comments, and likes. Consider scalability, query performance, and data integrity.";

echo "Design Problem:\n{$problem}\n\n";

$result = $agent->run($problem);

if ($result->isSuccess()) {
    echo "Recommended Design:\n";
    echo $result->getAnswer() . "\n\n";
    
    // The agent explored different normalization approaches,
    // indexing strategies, and relationship models
}
```

### Example: API Design

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 2,
    'search_strategy' => 'breadth_first',
]);

$problem = "Design a RESTful API for an e-commerce platform. " .
           "What endpoints, resources, and authentication approach should we use?";

$result = $agent->run($problem);
```

## Tutorial 6: Tuning Performance

Learn to balance exploration quality with token efficiency.

### Understanding the Trade-offs

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$problem = "Optimize a website's loading time. What are the best approaches?";

// Configuration 1: Quick exploration (low tokens)
$quick = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 2,
    'search_strategy' => 'best_first',
]);

// Configuration 2: Balanced
$balanced = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

// Configuration 3: Thorough exploration (high tokens)
$thorough = new TreeOfThoughtsAgent($client, [
    'branch_count' => 5,
    'max_depth' => 4,
    'search_strategy' => 'breadth_first',
]);

// Compare results
foreach (['quick' => $quick, 'balanced' => $balanced, 'thorough' => $thorough] as $name => $agent) {
    echo "\n=== {$name} configuration ===\n";
    
    $result = $agent->run($problem);
    
    if ($result->isSuccess()) {
        $metadata = $result->getMetadata();
        echo "Nodes: {$metadata['total_nodes']}, ";
        echo "Tokens: " . ($metadata['tokens']['input'] + $metadata['tokens']['output']) . ", ";
        echo "Score: " . round($metadata['best_score'], 1) . "/10\n";
    }
}
```

### Recommended Configurations

```php
// For prototyping (fast iteration)
$prototype = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,
    'max_depth' => 2,
]);

// For production (quality solutions)
$production = new TreeOfThoughtsAgent($client, [
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
]);

// For critical decisions (exhaustive search)
$critical = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 4,
    'search_strategy' => 'best_first',
]);

// For exploratory analysis (see all options)
$exploratory = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 3,
    'search_strategy' => 'breadth_first',
]);
```

## Tutorial 7: Production Best Practices

Build robust, production-ready ToT systems.

### Complete Production Example

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Setup logging
$logger = new Logger('tot-production');
$logger->pushHandler(new StreamHandler('logs/tot.log', Logger::INFO));

// Initialize client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create production-ready agent
$agent = new TreeOfThoughtsAgent($client, [
    'name' => 'production_tot',
    'branch_count' => 3,
    'max_depth' => 3,
    'search_strategy' => 'best_first',
    'logger' => $logger,
]);

// Business problem
$problem = "Our SaaS churn rate is 8%. What are the most effective " .
           "strategies to reduce it to under 5% in 6 months?";

try {
    $logger->info("Starting ToT analysis", ['problem' => $problem]);
    
    $result = $agent->run($problem);
    
    if ($result->isSuccess()) {
        // Extract solution
        $solution = $result->getAnswer();
        $metadata = $result->getMetadata();
        
        // Log success
        $logger->info("ToT analysis complete", [
            'nodes_explored' => $metadata['total_nodes'],
            'best_score' => $metadata['best_score'],
            'tokens_used' => $metadata['tokens']['input'] + $metadata['tokens']['output'],
        ]);
        
        // Store for analysis
        $analysisData = [
            'problem' => $problem,
            'solution' => $solution,
            'metadata' => $metadata,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        file_put_contents(
            'analysis_results.json',
            json_encode($analysisData, JSON_PRETTY_PRINT)
        );
        
        // Display results
        echo "=== Strategic Analysis Complete ===\n\n";
        echo $solution . "\n\n";
        echo "Confidence Score: " . round($metadata['best_score'], 1) . "/10\n";
        echo "Analysis Depth: {$metadata['path_length']} steps\n";
        
    } else {
        // Handle failure
        $error = $result->getError();
        $logger->error("ToT analysis failed", ['error' => $error]);
        echo "Analysis failed: {$error}\n";
    }
    
} catch (\Exception $e) {
    $logger->error("Unexpected error", ['exception' => $e->getMessage()]);
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
```

### Error Handling

```php
class ToTAnalyzer
{
    private TreeOfThoughtsAgent $agent;
    private LoggerInterface $logger;
    private int $maxRetries = 3;
    
    public function analyze(string $problem): array
    {
        $attempts = 0;
        
        while ($attempts < $this->maxRetries) {
            try {
                $result = $this->agent->run($problem);
                
                if ($result->isSuccess()) {
                    return [
                        'success' => true,
                        'solution' => $result->getAnswer(),
                        'metadata' => $result->getMetadata(),
                    ];
                }
                
                // Log failure and retry
                $this->logger->warning("Analysis failed, retrying", [
                    'attempt' => $attempts + 1,
                    'error' => $result->getError(),
                ]);
                
                $attempts++;
                sleep(2 ** $attempts); // Exponential backoff
                
            } catch (\Exception $e) {
                $this->logger->error("Exception during analysis", [
                    'exception' => $e->getMessage(),
                ]);
                $attempts++;
            }
        }
        
        return [
            'success' => false,
            'error' => 'Analysis failed after ' . $this->maxRetries . ' attempts',
        ];
    }
}
```

### Monitoring and Metrics

```php
class ToTMetrics
{
    private array $metrics = [];
    
    public function recordAnalysis(AgentResult $result): void
    {
        if (!$result->isSuccess()) {
            return;
        }
        
        $metadata = $result->getMetadata();
        
        $this->metrics[] = [
            'timestamp' => time(),
            'nodes_explored' => $metadata['total_nodes'],
            'max_depth' => $metadata['max_depth'],
            'best_score' => $metadata['best_score'],
            'tokens_used' => $metadata['tokens']['input'] + $metadata['tokens']['output'],
            'strategy' => $metadata['strategy'],
        ];
    }
    
    public function getAverageTokens(): float
    {
        $total = array_sum(array_column($this->metrics, 'tokens_used'));
        return $total / count($this->metrics);
    }
    
    public function getAverageScore(): float
    {
        $total = array_sum(array_column($this->metrics, 'best_score'));
        return $total / count($this->metrics);
    }
    
    public function report(): array
    {
        return [
            'total_analyses' => count($this->metrics),
            'avg_tokens' => round($this->getAverageTokens(), 0),
            'avg_score' => round($this->getAverageScore(), 2),
            'avg_nodes' => round(array_sum(array_column($this->metrics, 'nodes_explored')) / count($this->metrics), 0),
        ];
    }
}
```

## Common Patterns

### Pattern 1: Compare Multiple Solutions

```php
function compareApproaches(TreeOfThoughtsAgent $agent, string $problem, array $approaches): array
{
    $results = [];
    
    foreach ($approaches as $approach) {
        $fullProblem = "{$problem}\n\nSpecific approach to explore: {$approach}";
        $result = $agent->run($fullProblem);
        
        if ($result->isSuccess()) {
            $results[$approach] = [
                'solution' => $result->getAnswer(),
                'score' => $result->getMetadata()['best_score'],
            ];
        }
    }
    
    // Sort by score
    uasort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    
    return $results;
}

// Usage
$approaches = [
    'Cost-focused approach',
    'Speed-focused approach',
    'Quality-focused approach',
];

$results = compareApproaches($agent, $problem, $approaches);
```

### Pattern 2: Iterative Refinement

```php
function iterativeRefinement(TreeOfThoughtsAgent $agent, string $problem, int $iterations = 3): string
{
    $bestSolution = '';
    $bestScore = 0;
    
    for ($i = 0; $i < $iterations; $i++) {
        $prompt = $i === 0 
            ? $problem 
            : "{$problem}\n\nPrevious attempt: {$bestSolution}\n\nFind a better approach:";
        
        $result = $agent->run($prompt);
        
        if ($result->isSuccess()) {
            $score = $result->getMetadata()['best_score'];
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSolution = $result->getAnswer();
            }
        }
    }
    
    return $bestSolution;
}
```

### Pattern 3: Parallel Exploration

```php
function parallelExplore(ClaudePhp $client, string $problem, array $strategies): array
{
    $results = [];
    
    foreach ($strategies as $strategy) {
        $agent = new TreeOfThoughtsAgent($client, [
            'branch_count' => 3,
            'max_depth' => 3,
            'search_strategy' => $strategy,
        ]);
        
        $result = $agent->run($problem);
        
        if ($result->isSuccess()) {
            $results[$strategy] = [
                'solution' => $result->getAnswer(),
                'metadata' => $result->getMetadata(),
            ];
        }
    }
    
    return $results;
}

// Usage
$results = parallelExplore($client, $problem, ['best_first', 'breadth_first', 'depth_first']);
```

## Troubleshooting

### Problem: Results are not optimal

**Symptoms:** Solution quality is lower than expected

**Solutions:**
1. Increase branch count for more alternatives
2. Use best-first search strategy
3. Increase max depth for deeper exploration

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 5,        // More alternatives
    'max_depth' => 4,           // Deeper exploration
    'search_strategy' => 'best_first',  // Focus on quality
]);
```

### Problem: Too slow or expensive

**Symptoms:** High token usage, long wait times

**Solutions:**
1. Reduce branch count
2. Reduce max depth
3. Use best-first search

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 2,        // Fewer alternatives
    'max_depth' => 2,           // Shallower exploration
    'search_strategy' => 'best_first',
]);
```

### Problem: Missing alternative solutions

**Symptoms:** Agent finds one solution but misses others

**Solutions:**
1. Use breadth-first search
2. Increase branch count
3. Reduce max depth to explore widely but not deeply

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'max_depth' => 2,
    'search_strategy' => 'breadth_first',
]);
```

### Problem: Inconsistent results

**Symptoms:** Different runs produce very different results

**Solutions:**
1. Increase branch count for more stability
2. Use best-first for more consistent quality
3. Add logging to understand variations

```php
$agent = new TreeOfThoughtsAgent($client, [
    'branch_count' => 4,
    'search_strategy' => 'best_first',
    'logger' => $logger,
]);
```

## Next Steps

### Explore Advanced Topics

1. **Combine with Other Agents:**
   - Use ToT for exploration, then refine with ChainOfThought
   - Use IntentClassifier to route to ToT when needed

2. **Custom Evaluation:**
   - Extend the Evaluator class for domain-specific scoring
   - Implement custom pruning strategies

3. **Integrate with Workflows:**
   - Build multi-step workflows with ToT at decision points
   - Cache and reuse exploration results

### Further Reading

- [TreeOfThoughtsAgent Documentation](../TreeOfThoughtsAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [Research Paper: Tree of Thoughts](https://arxiv.org/abs/2305.10601)

### Practice Problems

Try these problems with ToT:

1. **Easy:** "Plan a week of healthy meals under $100"
2. **Medium:** "Design a caching strategy for a high-traffic API"
3. **Hard:** "Optimize a recommendation algorithm for personalization and performance"

### Get Help

- Check the [examples/tot_example.php](../../examples/tot_example.php) file
- Read the [API documentation](../TreeOfThoughtsAgent.md)
- Review the test files for usage patterns

## Conclusion

You now have a comprehensive understanding of Tree-of-Thoughts reasoning! Key takeaways:

- **ToT explores multiple paths** for better solutions
- **Choose your strategy** based on your needs (best-first, breadth-first, depth-first)
- **Balance branch count and depth** for optimal performance
- **Use logging and monitoring** in production
- **Iterate and refine** your approach based on results

Happy exploring! 🌳

