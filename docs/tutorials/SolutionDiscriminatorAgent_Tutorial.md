# SolutionDiscriminatorAgent Tutorial: Building an Intelligent Solution Evaluator

## Introduction

This tutorial will guide you through using the SolutionDiscriminatorAgent to objectively evaluate and compare multiple solution candidates. We'll start with basic comparisons and progress to sophisticated multi-criteria evaluations used in real-world decision-making.

By the end of this tutorial, you'll be able to:

- Evaluate multiple solutions using LLM-based scoring
- Define custom evaluation criteria for your specific needs
- Provide context to guide evaluations
- Integrate solution discrimination into your development workflow
- Make data-driven decisions when choosing between alternatives

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and decision-making processes

## Table of Contents

1. [Getting Started](#getting-started)
2. [Your First Evaluation](#your-first-evaluation)
3. [Understanding Criteria](#understanding-criteria)
4. [Working with Context](#working-with-context)
5. [Comparing Algorithms](#comparing-algorithms)
6. [Evaluating Design Decisions](#evaluating-design-decisions)
7. [Code Review Use Cases](#code-review-use-cases)
8. [Integration Patterns](#integration-patterns)
9. [Best Practices](#best-practices)

## Getting Started

### Installation

Ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to start evaluating solutions:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\SolutionDiscriminatorAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the discriminator agent
$discriminator = new SolutionDiscriminatorAgent($client, [
    'name' => 'tutorial_discriminator',
]);

echo "Solution Discriminator ready!\n";
```

## Your First Evaluation

Let's start with a simple example: choosing between two approaches for caching.

### Step 1: Define Your Solutions

```php
$cachingSolutions = [
    [
        'id' => 'redis',
        'name' => 'Redis',
        'type' => 'In-memory data store',
        'description' => 'Fast, supports complex data structures',
        'pros' => 'Very fast, rich features, clustering support',
        'cons' => 'Requires separate server, memory-intensive',
    ],
    [
        'id' => 'memcached',
        'name' => 'Memcached',
        'type' => 'In-memory cache',
        'description' => 'Simple, fast key-value cache',
        'pros' => 'Simple, very fast, minimal overhead',
        'cons' => 'Limited data types, no persistence',
    ],
];
```

### Step 2: Evaluate the Solutions

```php
$evaluations = $discriminator->evaluateSolutions($cachingSolutions);

// Display results
foreach ($evaluations as $eval) {
    echo "\n{$eval['solution_id']}:\n";
    echo "  Total Score: " . number_format($eval['total_score'], 3) . "\n";
    echo "  Criteria:\n";
    foreach ($eval['scores'] as $criterion => $score) {
        echo "    - {$criterion}: " . number_format($score, 3) . "\n";
    }
}
```

**Output:**
```
redis:
  Total Score: 0.833
  Criteria:
    - correctness: 0.850
    - completeness: 0.900
    - quality: 0.750

memcached:
  Total Score: 0.767
  Criteria:
    - correctness: 0.800
    - completeness: 0.700
    - quality: 0.800
```

### Step 3: Identify the Best Solution

```php
// Sort by score
usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

$best = $evaluations[0];
echo "\nBest Solution: {$best['solution_id']}\n";
echo "Score: " . number_format($best['total_score'], 3) . "\n";
```

## Understanding Criteria

Criteria are the dimensions along which solutions are evaluated. Choosing the right criteria is crucial for getting meaningful results.

### Default Criteria

By default, the agent uses:
- **correctness**: How accurate and correct the solution is
- **completeness**: Whether it fully addresses the problem
- **quality**: Overall implementation quality

### Defining Custom Criteria

Let's evaluate database query optimization strategies with domain-specific criteria:

```php
$queryOptimizations = [
    [
        'id' => 'add_index',
        'strategy' => 'Add Database Index',
        'effort' => 'Low',
        'impact' => 'High',
        'risk' => 'Very Low',
    ],
    [
        'id' => 'denormalize',
        'strategy' => 'Denormalize Tables',
        'effort' => 'High',
        'impact' => 'Very High',
        'risk' => 'Medium',
    ],
    [
        'id' => 'query_rewrite',
        'strategy' => 'Rewrite Query',
        'effort' => 'Medium',
        'impact' => 'Medium',
        'risk' => 'Low',
    ],
];

// Custom criteria for optimization decisions
$optimizationEvaluator = new SolutionDiscriminatorAgent($client, [
    'criteria' => [
        'implementation_effort',
        'performance_impact',
        'risk_level',
        'maintainability',
    ],
]);

$evaluations = $optimizationEvaluator->evaluateSolutions($queryOptimizations);
```

### Choosing Good Criteria

**Good criteria are:**
- Specific and measurable
- Relevant to your decision
- Independent of each other
- Clear in meaning

**Examples:**

```php
// Good - specific, relevant
$criteria = ['time_complexity', 'space_complexity', 'code_readability'];

// Good - for architecture decisions
$criteria = ['scalability', 'deployment_complexity', 'team_familiarity'];

// Less good - too vague
$criteria = ['good', 'better', 'best'];

// Less good - overlapping
$criteria = ['speed', 'performance', 'fast'];  // Too similar
```

## Working with Context

Context helps guide the evaluation by providing situational information.

### Without Context

```php
$solutions = [
    ['id' => 'microservices', 'description' => 'Independent services'],
    ['id' => 'monolith', 'description' => 'Single application'],
];

// Generic evaluation
$evaluations = $discriminator->evaluateSolutions($solutions);
```

### With Context

```php
// Startup context - different priorities
$startupContext = "Early-stage startup with 3 developers, " .
                  "need to move fast and iterate quickly, " .
                  "limited operations expertise";

$startupEval = $discriminator->evaluateSolutions($solutions, $startupContext);

// Enterprise context - different priorities
$enterpriseContext = "Large enterprise with 50+ developers, " .
                     "high traffic requiring horizontal scaling, " .
                     "dedicated DevOps team";

$enterpriseEval = $discriminator->evaluateSolutions($solutions, $enterpriseContext);
```

The same solutions will likely score differently with different contexts!

### Crafting Effective Context

Include relevant details:

```php
$context = implode(', ', [
    'PHP 8.2 application',
    'MySQL 8.0 database',
    'Team of 5 developers',
    'E-commerce platform',
    '100k monthly active users',
    'High read volume (90%)',
    'Peak traffic during sales events',
    'Budget constraints',
    '6-month timeline',
]);

$evaluations = $discriminator->evaluateSolutions($solutions, $context);
```

## Comparing Algorithms

One of the most common uses is comparing algorithm implementations.

### Example: Sorting Algorithms

```php
$sortingAlgorithms = [
    [
        'id' => 'bubble_sort',
        'name' => 'Bubble Sort',
        'time_complexity' => 'O(n²)',
        'space_complexity' => 'O(1)',
        'stable' => true,
        'description' => 'Simple comparison-based sort',
        'code_lines' => 15,
        'best_case' => 'O(n)',
        'use_case' => 'Small datasets, educational purposes',
    ],
    [
        'id' => 'quick_sort',
        'name' => 'Quick Sort',
        'time_complexity' => 'O(n log n) average',
        'space_complexity' => 'O(log n)',
        'stable' => false,
        'description' => 'Efficient divide-and-conquer sort',
        'code_lines' => 20,
        'best_case' => 'O(n log n)',
        'use_case' => 'General purpose, large datasets',
    ],
    [
        'id' => 'merge_sort',
        'name' => 'Merge Sort',
        'time_complexity' => 'O(n log n)',
        'space_complexity' => 'O(n)',
        'stable' => true,
        'description' => 'Predictable divide-and-conquer sort',
        'code_lines' => 25,
        'best_case' => 'O(n log n)',
        'use_case' => 'When stability is required',
    ],
];

$algorithmEvaluator = new SolutionDiscriminatorAgent($client, [
    'criteria' => [
        'time_efficiency',
        'space_efficiency',
        'implementation_simplicity',
        'practical_utility',
    ],
]);

$context = "Need to sort user-generated data (1000-10000 items), " .
           "stability is important, memory is limited";

$evaluations = $algorithmEvaluator->evaluateSolutions($sortingAlgorithms, $context);

// Rank them
usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

echo "Rankings for your use case:\n";
foreach ($evaluations as $i => $eval) {
    $rank = $i + 1;
    echo "{$rank}. {$eval['solution_id']}: " . 
         number_format($eval['total_score'], 3) . "\n";
}
```

### Example: Search Algorithms

```php
$searchAlgorithms = [
    [
        'id' => 'linear_search',
        'name' => 'Linear Search',
        'complexity' => 'O(n)',
        'requirements' => 'None',
        'description' => 'Check each element sequentially',
    ],
    [
        'id' => 'binary_search',
        'name' => 'Binary Search',
        'complexity' => 'O(log n)',
        'requirements' => 'Sorted array',
        'description' => 'Divide and conquer on sorted data',
    ],
    [
        'id' => 'hash_lookup',
        'name' => 'Hash Table Lookup',
        'complexity' => 'O(1) average',
        'requirements' => 'Extra memory for hash table',
        'description' => 'Direct access via hash function',
    ],
];

$context = "Frequent searches in dataset of 10,000 items, " .
           "data rarely changes, memory available";

$evaluations = $discriminator->evaluateSolutions($searchAlgorithms, $context);
```

## Evaluating Design Decisions

Design decisions are perfect candidates for objective evaluation.

### Example: API Design

```php
$apiDesigns = [
    [
        'id' => 'rest',
        'name' => 'REST API',
        'approach' => 'Resource-oriented with HTTP methods',
        'pros' => [
            'Widely understood',
            'Great tooling',
            'HTTP caching',
            'Stateless',
        ],
        'cons' => [
            'Over-fetching/under-fetching',
            'Multiple round trips',
            'Versioning challenges',
        ],
        'learning_curve' => 'Low',
        'community' => 'Very large',
    ],
    [
        'id' => 'graphql',
        'name' => 'GraphQL',
        'approach' => 'Query language for APIs',
        'pros' => [
            'Flexible queries',
            'Single endpoint',
            'Strong typing',
            'No over-fetching',
        ],
        'cons' => [
            'Complexity',
            'Caching challenges',
            'File uploads',
        ],
        'learning_curve' => 'Medium',
        'community' => 'Large',
    ],
    [
        'id' => 'grpc',
        'name' => 'gRPC',
        'approach' => 'Binary protocol with Protocol Buffers',
        'pros' => [
            'Very fast',
            'Streaming support',
            'Strong contracts',
            'Cross-language',
        ],
        'cons' => [
            'Not browser-friendly',
            'Steeper learning curve',
            'Debugging harder',
        ],
        'learning_curve' => 'High',
        'community' => 'Growing',
    ],
];

$apiEvaluator = new SolutionDiscriminatorAgent($client, [
    'criteria' => [
        'developer_experience',
        'performance',
        'client_compatibility',
        'ecosystem_maturity',
    ],
]);

$context = "Mobile app backend, need real-time updates, " .
           "team familiar with REST, supporting iOS and Android";

$evaluations = $apiEvaluator->evaluateSolutions($apiDesigns, $context);

// Display with visual bars
foreach ($evaluations as $eval) {
    echo "\n{$eval['solution_id']}:\n";
    foreach ($eval['scores'] as $criterion => $score) {
        $bar = str_repeat('█', (int)($score * 20));
        $percentage = number_format($score * 100, 1);
        echo "  {$criterion}: {$bar} {$percentage}%\n";
    }
    echo "  TOTAL: " . number_format($eval['total_score'], 3) . "\n";
}
```

### Example: Database Schema Design

```php
$schemaDesigns = [
    [
        'id' => 'normalized_3nf',
        'name' => '3rd Normal Form',
        'structure' => 'Fully normalized with separate tables',
        'tables' => 8,
        'joins_typical_query' => 4,
        'data_redundancy' => 'Minimal',
        'write_performance' => 'Excellent',
        'read_performance' => 'Moderate',
        'maintenance' => 'Easy',
    ],
    [
        'id' => 'denormalized',
        'name' => 'Denormalized',
        'structure' => 'Combined tables with redundancy',
        'tables' => 3,
        'joins_typical_query' => 1,
        'data_redundancy' => 'High',
        'write_performance' => 'Poor',
        'read_performance' => 'Excellent',
        'maintenance' => 'Difficult',
    ],
    [
        'id' => 'hybrid',
        'name' => 'Hybrid with Views',
        'structure' => 'Normalized base + materialized views',
        'tables' => 8,
        'joins_typical_query' => 0,
        'data_redundancy' => 'Controlled',
        'write_performance' => 'Good',
        'read_performance' => 'Excellent',
        'maintenance' => 'Moderate',
    ],
];

$schemaEvaluator = new SolutionDiscriminatorAgent($client, [
    'criteria' => [
        'read_performance',
        'write_performance',
        'maintainability',
        'data_integrity',
    ],
]);

$context = "E-commerce platform, 90% reads, 10% writes, " .
           "complex product catalog, frequent reporting queries";

$evaluations = $schemaEvaluator->evaluateSolutions($schemaDesigns, $context);
```

## Code Review Use Cases

Use the discriminator to make code review decisions.

### Example: Refactoring Strategies

```php
class RefactoringDecision
{
    private SolutionDiscriminatorAgent $discriminator;
    
    public function __construct(SolutionDiscriminatorAgent $discriminator)
    {
        $this->discriminator = $discriminator;
    }
    
    public function evaluateRefactoringOptions(
        string $currentCode,
        array $options,
        array $constraints
    ): array {
        $context = $this->buildContext($currentCode, $constraints);
        
        $evaluations = $this->discriminator->evaluateSolutions($options, $context);
        
        // Sort by score
        usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        
        return $evaluations;
    }
    
    private function buildContext(string $currentCode, array $constraints): string
    {
        return implode(', ', [
            "Current code: {$currentCode} lines",
            "Test coverage: {$constraints['test_coverage']}%",
            "Team size: {$constraints['team_size']}",
            "Timeline: {$constraints['timeline']}",
            "Risk tolerance: {$constraints['risk_tolerance']}",
        ]);
    }
}

// Usage
$refactoringOptions = [
    [
        'id' => 'extract_class',
        'technique' => 'Extract Class',
        'description' => 'Split large class into focused classes',
        'estimated_effort' => '3 days',
        'risk' => 'Medium',
        'benefit' => 'High maintainability',
        'breaking_changes' => false,
    ],
    [
        'id' => 'introduce_interface',
        'technique' => 'Extract Interface',
        'description' => 'Create interface for dependency injection',
        'estimated_effort' => '1 day',
        'risk' => 'Low',
        'benefit' => 'Better testability',
        'breaking_changes' => false,
    ],
    [
        'id' => 'complete_rewrite',
        'technique' => 'Complete Rewrite',
        'description' => 'Rewrite from scratch with modern patterns',
        'estimated_effort' => '2 weeks',
        'risk' => 'High',
        'benefit' => 'Clean architecture',
        'breaking_changes' => true,
    ],
];

$constraints = [
    'test_coverage' => 45,
    'team_size' => 3,
    'timeline' => '1 week',
    'risk_tolerance' => 'low',
];

$evaluator = new RefactoringDecision($discriminator);
$evaluations = $evaluator->evaluateRefactoringOptions(
    '500',
    $refactoringOptions,
    $constraints
);

echo "Recommended refactoring: {$evaluations[0]['solution_id']}\n";
```

### Example: Library Selection

```php
$loggingLibraries = [
    [
        'id' => 'monolog',
        'name' => 'Monolog',
        'stars' => 20500,
        'downloads' => '500M+',
        'last_update' => '2 weeks ago',
        'php_version' => '>=7.2',
        'features' => ['handlers', 'formatters', 'processors'],
        'license' => 'MIT',
    ],
    [
        'id' => 'log4php',
        'name' => 'Apache Log4php',
        'stars' => 450,
        'downloads' => '5M+',
        'last_update' => '3 years ago',
        'php_version' => '>=5.2.7',
        'features' => ['appenders', 'layouts', 'filters'],
        'license' => 'Apache 2.0',
    ],
    [
        'id' => 'klogger',
        'name' => 'KLogger',
        'stars' => 1000,
        'downloads' => '10M+',
        'last_update' => '1 year ago',
        'php_version' => '>=5.3',
        'features' => ['simple', 'PSR-3'],
        'license' => 'MIT',
    ],
];

$libraryEvaluator = new SolutionDiscriminatorAgent($client, [
    'criteria' => [
        'community_support',
        'maintenance_activity',
        'feature_richness',
        'ease_of_use',
    ],
]);

$context = "Modern PHP 8.2 application, need structured logging, " .
           "planning to use for years, team prefers PSR standards";

$evaluations = $libraryEvaluator->evaluateSolutions($loggingLibraries, $context);
```

## Integration Patterns

### Pattern 1: Decision Pipeline

Chain multiple evaluations together:

```php
class DesignDecisionPipeline
{
    private array $evaluators = [];
    
    public function addStage(string $name, SolutionDiscriminatorAgent $evaluator): self
    {
        $this->evaluators[$name] = $evaluator;
        return $this;
    }
    
    public function evaluate(array $solutions): array
    {
        $results = [];
        
        foreach ($this->evaluators as $stage => $evaluator) {
            $evaluations = $evaluator->evaluateSolutions($solutions);
            $results[$stage] = $evaluations;
            
            // Filter to top 3 for next stage
            usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
            $solutions = array_slice($evaluations, 0, 3);
        }
        
        return $results;
    }
}

// Usage
$pipeline = new DesignDecisionPipeline();

$pipeline
    ->addStage('technical', new SolutionDiscriminatorAgent($client, [
        'criteria' => ['performance', 'scalability', 'security'],
    ]))
    ->addStage('business', new SolutionDiscriminatorAgent($client, [
        'criteria' => ['cost', 'time_to_market', 'team_expertise'],
    ]))
    ->addStage('risk', new SolutionDiscriminatorAgent($client, [
        'criteria' => ['implementation_risk', 'maintenance_risk', 'vendor_lock_in'],
    ]));

$results = $pipeline->evaluate($solutions);
```

### Pattern 2: Weighted Criteria

Implement custom weighting:

```php
class WeightedEvaluator
{
    private SolutionDiscriminatorAgent $discriminator;
    private array $weights;
    
    public function __construct(
        SolutionDiscriminatorAgent $discriminator,
        array $weights
    ) {
        $this->discriminator = $discriminator;
        $this->weights = $weights;
    }
    
    public function evaluate(array $solutions): array
    {
        $evaluations = $this->discriminator->evaluateSolutions($solutions);
        
        // Apply weights
        foreach ($evaluations as &$eval) {
            $weightedScore = 0;
            $totalWeight = array_sum($this->weights);
            
            foreach ($eval['scores'] as $criterion => $score) {
                $weight = $this->weights[$criterion] ?? 1;
                $weightedScore += $score * $weight;
            }
            
            $eval['weighted_score'] = $weightedScore / $totalWeight;
        }
        
        return $evaluations;
    }
}

// Usage
$weights = [
    'performance' => 3,      // 3x importance
    'maintainability' => 2,  // 2x importance
    'simplicity' => 1,       // 1x importance (baseline)
];

$weightedEvaluator = new WeightedEvaluator($discriminator, $weights);
$evaluations = $weightedEvaluator->evaluate($solutions);
```

### Pattern 3: Evaluation Cache

Cache evaluations to avoid redundant API calls:

```php
class CachedEvaluator
{
    private SolutionDiscriminatorAgent $discriminator;
    private array $cache = [];
    
    public function evaluateSolutions(array $solutions, ?string $context = null): array
    {
        $cacheKey = $this->generateCacheKey($solutions, $context);
        
        if (isset($this->cache[$cacheKey])) {
            echo "Using cached evaluation\n";
            return $this->cache[$cacheKey];
        }
        
        $evaluations = $this->discriminator->evaluateSolutions($solutions, $context);
        $this->cache[$cacheKey] = $evaluations;
        
        return $evaluations;
    }
    
    private function generateCacheKey(array $solutions, ?string $context): string
    {
        return md5(json_encode($solutions) . $context);
    }
}
```

## Best Practices

### 1. Start Broad, Then Narrow

```php
// Phase 1: Broad evaluation
$broadEvaluator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['overall_fit', 'feasibility'],
]);

$broadEval = $broadEvaluator->evaluateSolutions($allSolutions);

// Take top 3
usort($broadEval, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
$topSolutions = array_slice($broadEval, 0, 3);

// Phase 2: Detailed evaluation
$detailedEvaluator = new SolutionDiscriminatorAgent($client, [
    'criteria' => ['performance', 'maintainability', 'security', 'scalability'],
]);

$detailedEval = $detailedEvaluator->evaluateSolutions($topSolutions);
```

### 2. Document Your Decisions

```php
class DocumentedEvaluation
{
    public function evaluate(
        array $solutions,
        string $context,
        SolutionDiscriminatorAgent $discriminator
    ): array {
        $evaluations = $discriminator->evaluateSolutions($solutions, $context);
        
        // Create decision log
        $log = [
            'timestamp' => date('c'),
            'context' => $context,
            'criteria' => $discriminator->getCriteria(),
            'solutions_evaluated' => count($solutions),
            'evaluations' => $evaluations,
            'decision' => $this->selectBest($evaluations),
        ];
        
        file_put_contents(
            'decisions.jsonl',
            json_encode($log) . "\n",
            FILE_APPEND
        );
        
        return $evaluations;
    }
    
    private function selectBest(array $evaluations): array
    {
        usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        return $evaluations[0];
    }
}
```

### 3. Validate Results

```php
function validateEvaluation(array $evaluations): bool
{
    // Check for suspicious patterns
    
    // 1. All scores too similar
    $scores = array_column($evaluations, 'total_score');
    $variance = variance($scores);
    
    if ($variance < 0.01) {
        echo "Warning: Very low variance in scores\n";
        echo "Consider: More specific criteria or richer solution details\n";
        return false;
    }
    
    // 2. Extreme scores
    foreach ($evaluations as $eval) {
        if ($eval['total_score'] < 0.2 || $eval['total_score'] > 0.95) {
            echo "Warning: Extreme score detected\n";
            return false;
        }
    }
    
    return true;
}

function variance(array $values): float
{
    $mean = array_sum($values) / count($values);
    $squaredDiffs = array_map(fn($v) => ($v - $mean) ** 2, $values);
    return array_sum($squaredDiffs) / count($values);
}
```

### 4. Combine with Human Review

```php
function hybridEvaluation(
    array $solutions,
    SolutionDiscriminatorAgent $discriminator
): array {
    // AI evaluation
    $aiEvaluations = $discriminator->evaluateSolutions($solutions);
    
    // Get top 3
    usort($aiEvaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
    $finalists = array_slice($aiEvaluations, 0, 3);
    
    // Present to humans for final decision
    echo "AI has narrowed down to 3 finalists:\n\n";
    foreach ($finalists as $i => $eval) {
        echo ($i + 1) . ". {$eval['solution_id']} - ";
        echo "Score: " . number_format($eval['total_score'], 3) . "\n";
        
        echo "   Top strength: " . array_key_first($eval['scores']) . "\n";
    }
    
    echo "\nPlease select final winner (1-3): ";
    $choice = (int)trim(fgets(STDIN));
    
    return $finalists[$choice - 1];
}
```

### 5. Monitor and Improve

```php
class EvaluationMetrics
{
    private array $history = [];
    
    public function recordEvaluation(array $evaluations, ?string $selectedId = null): void
    {
        $this->history[] = [
            'timestamp' => time(),
            'evaluations' => $evaluations,
            'selected' => $selectedId,
        ];
    }
    
    public function getInsights(): array
    {
        $totalEvaluations = count($this->history);
        $avgScores = [];
        
        foreach ($this->history as $record) {
            foreach ($record['evaluations'] as $eval) {
                $id = $eval['solution_id'];
                if (!isset($avgScores[$id])) {
                    $avgScores[$id] = ['sum' => 0, 'count' => 0];
                }
                $avgScores[$id]['sum'] += $eval['total_score'];
                $avgScores[$id]['count']++;
            }
        }
        
        $insights = [];
        foreach ($avgScores as $id => $data) {
            $insights[$id] = $data['sum'] / $data['count'];
        }
        
        arsort($insights);
        
        return [
            'total_evaluations' => $totalEvaluations,
            'average_scores' => $insights,
        ];
    }
}
```

## Conclusion

You now have a comprehensive understanding of the SolutionDiscriminatorAgent! You've learned:

✅ How to evaluate multiple solutions objectively  
✅ Defining and using custom evaluation criteria  
✅ Providing context to guide evaluations  
✅ Comparing algorithms and design decisions  
✅ Integrating evaluation into code review processes  
✅ Advanced patterns and best practices

## Next Steps

- Review the [SolutionDiscriminatorAgent API Documentation](../SolutionDiscriminatorAgent.md)
- Check out the [working example](../../examples/solution_discriminator_example.php)
- Try evaluating solutions in your own projects
- Experiment with different criteria for your specific use cases
- Integrate with your existing decision-making workflows

## Additional Resources

- [SolutionDiscriminatorAgent.md](../SolutionDiscriminatorAgent.md) - Complete API reference
- [solution_discriminator_example.php](../../examples/solution_discriminator_example.php) - Working examples
- [MakerAgent.md](../MakerAgent.md) - Integration with MAKER framework
- [Claude API Documentation](https://docs.anthropic.com/)

Happy evaluating! 🎯

