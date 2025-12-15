# Reflection Agent Tutorial

Welcome to this comprehensive tutorial on using the `ReflectionAgent`! The Reflection pattern enables AI to generate output, critically evaluate its quality, and iteratively refine it until excellence is achieved.

## Table of Contents

1. [Introduction](#introduction)
2. [What is the Reflection Pattern?](#what-is-the-reflection-pattern)
3. [Setup](#setup)
4. [Tutorial 1: Your First Reflection Agent](#tutorial-1-your-first-reflection-agent)
5. [Tutorial 2: Understanding the Reflection Loop](#tutorial-2-understanding-the-reflection-loop)
6. [Tutorial 3: Code Generation with Reflection](#tutorial-3-code-generation-with-reflection)
7. [Tutorial 4: Content Writing Improvement](#tutorial-4-content-writing-improvement)
8. [Tutorial 5: Custom Evaluation Criteria](#tutorial-5-custom-evaluation-criteria)
9. [Tutorial 6: Production Best Practices](#tutorial-6-production-best-practices)
10. [Common Patterns](#common-patterns)
11. [Troubleshooting](#troubleshooting)
12. [Next Steps](#next-steps)

## Introduction

This tutorial will teach you how to leverage the Generate-Reflect-Refine pattern to produce high-quality output. By the end, you'll be able to:

- Understand when and why to use reflection
- Configure quality thresholds and refinement limits
- Create domain-specific evaluation criteria
- Build production-ready systems with iterative improvement
- Monitor and optimize the reflection process

## What is the Reflection Pattern?

The Reflection pattern is a meta-cognitive approach where an AI system:

1. **Generates** an initial response
2. **Reflects** on the quality and identifies issues
3. **Refines** the output based on feedback
4. **Repeats** until quality standards are met

**Without Reflection:**

```
Q: Write a function to validate emails
A: [Returns basic function with potential issues]
```

**With Reflection:**

```
Q: Write a function to validate emails

Initial Generation:
[Basic function]

Reflection (Score: 6/10):
- Missing input validation
- No handling of edge cases
- Lacks documentation

Refined Version (Score: 9/10):
[Improved function with validation, edge cases, docs]
```

The Reflection approach provides:

- **Higher Quality**: Systematic improvement through self-evaluation
- **Self-Correction**: Identifies and fixes its own mistakes
- **Transparency**: See the improvement process
- **Flexibility**: Works across different domains

## Setup

First, install the package and set up your API key:

```bash
composer require claude-php-agent
```

Set your Anthropic API key:

```bash
export ANTHROPIC_API_KEY='your-api-key-here'
```

## Tutorial 1: Your First Reflection Agent

Let's create a simple reflection agent to improve code quality.

### Step 1: Basic Reflection

Create a file `my_first_reflection.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a reflection agent
$agent = new ReflectionAgent($client, [
    'name' => 'my_first_reflection',
    'max_refinements' => 3,
    'quality_threshold' => 8,
]);

// Ask it to generate something
$task = "Write a PHP function to check if a number is prime.";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "Task: {$task}\n\n";
    echo "Final Output:\n{$result->getAnswer()}\n\n";
    
    // Show improvement history
    $metadata = $result->getMetadata();
    echo "Reflection History:\n";
    foreach ($metadata['reflections'] as $reflection) {
        echo "  Iteration {$reflection['iteration']}: ";
        echo "Score {$reflection['score']}/10\n";
    }
    
    echo "\nFinal Score: {$metadata['final_score']}/10\n";
    echo "Total Iterations: {$result->getIterations()}\n";
} else {
    echo "Error: {$result->getError()}\n";
}
```

Run it:

```bash
php my_first_reflection.php
```

**Expected Output:**

```
Task: Write a PHP function to check if a number is prime.

Final Output:
<?php
/**
 * Check if a number is prime
 * 
 * @param int $number The number to check
 * @return bool True if prime, false otherwise
 * @throws InvalidArgumentException If number is less than 2
 */
function isPrime(int $number): bool
{
    if ($number < 2) {
        throw new InvalidArgumentException('Number must be >= 2');
    }
    
    if ($number === 2) {
        return true;
    }
    
    if ($number % 2 === 0) {
        return false;
    }
    
    $sqrt = (int) sqrt($number);
    for ($i = 3; $i <= $sqrt; $i += 2) {
        if ($number % $i === 0) {
            return false;
        }
    }
    
    return true;
}

Reflection History:
  Iteration 1: Score 6/10
  Iteration 2: Score 8/10

Final Score: 8/10
Total Iterations: 4
```

### Understanding What Happened

1. **Initial Generation**: Agent created a basic prime checker
2. **First Reflection**: Identified missing error handling and docs (Score: 6)
3. **First Refinement**: Added validation and documentation
4. **Second Reflection**: Confirmed quality threshold met (Score: 8)

## Tutorial 2: Understanding the Reflection Loop

Let's visualize how the reflection loop works with detailed logging.

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Set up logging to see the process
$logger = new Logger('reflection');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,
    'logger' => $logger,
]);

$task = "Write a function to calculate factorial with memoization.";

$result = $agent->run($task);

if ($result->isSuccess()) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "FINAL OUTPUT\n";
    echo str_repeat("=", 80) . "\n";
    echo $result->getAnswer() . "\n";
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "IMPROVEMENT JOURNEY\n";
    echo str_repeat("=", 80) . "\n";
    
    $metadata = $result->getMetadata();
    foreach ($metadata['reflections'] as $i => $reflection) {
        echo "\nReflection #{$reflection['iteration']}:\n";
        echo "Score: {$reflection['score']}/10\n";
        echo "Feedback: {$reflection['feedback']}\n";
    }
    
    echo "\nFinal Quality: {$metadata['final_score']}/10\n";
    echo "Token Usage: {$metadata['token_usage']['total']} tokens\n";
}
```

### The Reflection Cycle

```
┌──────────────────────────────────────────────────┐
│                    START                         │
└──────────────┬───────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────┐
│  STEP 1: GENERATE                                │
│  Create initial response to the task             │
└──────────────┬───────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────┐
│  STEP 2: REFLECT                                 │
│  • Evaluate quality (1-10 score)                 │
│  • Identify what works                           │
│  • Identify what needs improvement               │
│  • Provide specific suggestions                  │
└──────────────┬───────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────┐
│  DECISION: Quality Threshold Met?                │
│  Score >= 8? (configurable)                      │
└──────┬──────────────────────────────┬────────────┘
       │ YES                          │ NO
       │                              │
       ▼                              ▼
┌──────────────┐      ┌──────────────────────────────┐
│   DONE       │      │  STEP 3: REFINE              │
│  Return      │      │  Improve based on feedback    │
│  result      │      └───────────┬──────────────────┘
└──────────────┘                  │
                                  │
                                  ▼
                        ┌──────────────────────┐
                        │  Max iterations?     │
                        └──────┬──────────┬────┘
                               │ NO       │ YES
                               │          │
                               └──────────┴─────► DONE
                                  (loop back
                                   to REFLECT)
```

## Tutorial 3: Code Generation with Reflection

Let's build a code generator that produces high-quality, production-ready code.

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a code-focused reflection agent
$codeAgent = new ReflectionAgent($client, [
    'name' => 'code_reflection_agent',
    'max_refinements' => 3,
    'quality_threshold' => 9, // Higher standard for code
    'criteria' => 'code correctness, type safety, error handling, documentation, edge cases, and PSR-12 compliance',
]);

$tasks = [
    "Write a class for managing user sessions with Redis backend.",
    "Create a function to safely parse JSON with error handling.",
    "Build a rate limiter class using token bucket algorithm.",
];

foreach ($tasks as $i => $task) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "Task " . ($i + 1) . ": {$task}\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $result = $codeAgent->run($task);
    
    if ($result->isSuccess()) {
        echo $result->getAnswer() . "\n\n";
        
        $metadata = $result->getMetadata();
        echo "Quality Score: {$metadata['final_score']}/10\n";
        echo "Refinement Iterations: " . count($metadata['reflections']) . "\n";
        
        if ($metadata['final_score'] >= 9) {
            echo "✓ Production-ready quality achieved!\n";
        } else {
            echo "⚠ Quality threshold not met (wanted >= 9)\n";
        }
    } else {
        echo "Error: {$result->getError()}\n";
    }
}
```

### Code Quality Criteria

For code generation, use specific criteria:

```php
$codeAgent = new ReflectionAgent($client, [
    'criteria' => implode(', ', [
        'correctness and bug-free logic',
        'proper type hints and return types',
        'comprehensive error handling',
        'PHPDoc documentation',
        'edge case handling',
        'PSR-12 code style compliance',
        'security best practices',
    ]),
    'quality_threshold' => 9,
]);
```

## Tutorial 4: Content Writing Improvement

Use reflection to improve written content iteratively.

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a writing-focused reflection agent
$writingAgent = new ReflectionAgent($client, [
    'name' => 'writing_reflection_agent',
    'max_refinements' => 2,
    'quality_threshold' => 8,
    'criteria' => 'clarity, engagement, grammar, structure, persuasiveness, and call-to-action effectiveness',
]);

$writingTasks = [
    "introduction" => "Write an engaging introduction for a blog post about sustainable living.",
    "product" => "Write a compelling product description for noise-cancelling headphones.",
    "email" => "Write a professional email requesting a meeting with a potential client.",
];

foreach ($writingTasks as $type => $task) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo strtoupper($type) . "\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $result = $writingAgent->run($task);
    
    if ($result->isSuccess()) {
        echo "Final Version:\n";
        echo str_repeat("-", 80) . "\n";
        echo $result->getAnswer() . "\n";
        echo str_repeat("-", 80) . "\n\n";
        
        $metadata = $result->getMetadata();
        
        echo "Improvement History:\n";
        foreach ($metadata['reflections'] as $reflection) {
            echo "  • Iteration {$reflection['iteration']}: {$reflection['score']}/10\n";
        }
        echo "  • Final: {$metadata['final_score']}/10\n\n";
        
        // Show improvement
        $firstScore = $metadata['reflections'][0]['score'] ?? 0;
        $improvement = $metadata['final_score'] - $firstScore;
        if ($improvement > 0) {
            echo "Quality improved by +{$improvement} points!\n";
        }
    }
}
```

### Writing Criteria Examples

Different writing tasks need different criteria:

```php
// Blog writing
$blogCriteria = 'clarity, engagement, SEO optimization, readability, and storytelling';

// Technical documentation
$docCriteria = 'accuracy, clarity, completeness, examples, and organization';

// Marketing copy
$marketingCriteria = 'persuasiveness, emotional appeal, clear benefits, urgency, and strong CTA';

// Email communication
$emailCriteria = 'professionalism, clarity, conciseness, appropriate tone, and clear action items';
```

## Tutorial 5: Custom Evaluation Criteria

Learn to create domain-specific evaluation criteria for specialized tasks.

### Example 1: API Documentation

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$apiDocAgent = new ReflectionAgent($client, [
    'name' => 'api_doc_agent',
    'criteria' => implode(', ', [
        'accurate endpoint specifications',
        'complete parameter documentation',
        'practical code examples',
        'clear error responses',
        'authentication details',
        'rate limiting information',
    ]),
    'quality_threshold' => 8,
    'max_refinements' => 3,
]);

$task = "Document a REST API endpoint for user authentication (POST /auth/login)";

$result = $apiDocAgent->run($task);

if ($result->isSuccess()) {
    echo $result->getAnswer() . "\n";
}
```

### Example 2: Database Schema Design

```php
$schemaAgent = new ReflectionAgent($client, [
    'name' => 'schema_agent',
    'criteria' => implode(', ', [
        'proper normalization',
        'appropriate indexes',
        'foreign key relationships',
        'data type selection',
        'scalability considerations',
        'migration safety',
    ]),
    'quality_threshold' => 9,
]);

$task = "Design a database schema for an e-commerce order system.";
$result = $schemaAgent->run($task);
```

### Example 3: Test Case Generation

```php
$testAgent = new ReflectionAgent($client, [
    'name' => 'test_agent',
    'criteria' => implode(', ', [
        'comprehensive test coverage',
        'edge case handling',
        'clear test descriptions',
        'proper assertions',
        'setup and teardown',
        'test independence',
    ]),
    'quality_threshold' => 8,
]);

$task = "Write PHPUnit tests for a password validation function.";
$result = $testAgent->run($task);
```

## Tutorial 6: Production Best Practices

### Monitoring and Metrics

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ReflectionAgent;
use ClaudePhp\ClaudePhp;

class ReflectionMetrics
{
    private array $metrics = [];
    
    public function record(string $taskType, $result): void
    {
        if (!$result->isSuccess()) {
            return;
        }
        
        $metadata = $result->getMetadata();
        
        $this->metrics[] = [
            'task_type' => $taskType,
            'timestamp' => time(),
            'final_score' => $metadata['final_score'],
            'iterations' => $result->getIterations(),
            'refinements' => count($metadata['reflections']),
            'tokens_used' => $metadata['token_usage']['total'],
            'initial_score' => $metadata['reflections'][0]['score'] ?? 0,
            'improvement' => $metadata['final_score'] - ($metadata['reflections'][0]['score'] ?? 0),
        ];
    }
    
    public function getAverageScore(string $taskType = null): float
    {
        $filtered = $taskType 
            ? array_filter($this->metrics, fn($m) => $m['task_type'] === $taskType)
            : $this->metrics;
        
        if (empty($filtered)) {
            return 0.0;
        }
        
        $total = array_sum(array_column($filtered, 'final_score'));
        return $total / count($filtered);
    }
    
    public function getAverageImprovement(): float
    {
        if (empty($this->metrics)) {
            return 0.0;
        }
        
        $total = array_sum(array_column($this->metrics, 'improvement'));
        return $total / count($this->metrics);
    }
    
    public function getTotalTokens(): int
    {
        return array_sum(array_column($this->metrics, 'tokens_used'));
    }
    
    public function report(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "REFLECTION METRICS REPORT\n";
        echo str_repeat("=", 80) . "\n\n";
        
        echo "Total Tasks: " . count($this->metrics) . "\n";
        echo "Average Final Score: " . round($this->getAverageScore(), 2) . "/10\n";
        echo "Average Improvement: +" . round($this->getAverageImprovement(), 2) . " points\n";
        echo "Total Tokens Used: " . number_format($this->getTotalTokens()) . "\n";
        
        $avgRefinements = array_sum(array_column($this->metrics, 'refinements')) / count($this->metrics);
        echo "Average Refinements: " . round($avgRefinements, 1) . "\n";
    }
}

// Usage
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$agent = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,
]);

$metrics = new ReflectionMetrics();

$tasks = [
    'code' => [
        "Write a function to merge sorted arrays",
        "Create a class for job queue management",
    ],
    'writing' => [
        "Write a product description for a smart watch",
        "Write a blog introduction about AI",
    ],
];

foreach ($tasks as $type => $taskList) {
    foreach ($taskList as $task) {
        $result = $agent->run($task);
        $metrics->record($type, $result);
    }
}

$metrics->report();

echo "\nBy Task Type:\n";
echo "  Code: " . round($metrics->getAverageScore('code'), 2) . "/10\n";
echo "  Writing: " . round($metrics->getAverageScore('writing'), 2) . "/10\n";
```

### Cost Management

```php
class CostAwareReflectionAgent
{
    private ReflectionAgent $agent;
    private int $maxTokensPerTask;
    private float $costPerToken;
    
    public function __construct(
        ReflectionAgent $agent,
        int $maxTokensPerTask = 10000,
        float $costPerToken = 0.000003
    ) {
        $this->agent = $agent;
        $this->maxTokensPerTask = $maxTokensPerTask;
        $this->costPerToken = $costPerToken;
    }
    
    public function runWithBudget(string $task): array
    {
        $result = $this->agent->run($task);
        
        $metadata = $result->getMetadata();
        $tokensUsed = $metadata['token_usage']['total'];
        $estimatedCost = $tokensUsed * $this->costPerToken;
        
        return [
            'result' => $result,
            'tokens_used' => $tokensUsed,
            'estimated_cost' => $estimatedCost,
            'within_budget' => $tokensUsed <= $this->maxTokensPerTask,
        ];
    }
}

// Usage
$agent = new ReflectionAgent($client, [
    'max_refinements' => 3,
    'quality_threshold' => 8,
]);

$costAwareAgent = new CostAwareReflectionAgent($agent, 5000);

$output = $costAwareAgent->runWithBudget("Write a complex API handler");

if ($output['within_budget']) {
    echo "Task completed within budget\n";
    echo "Cost: $" . number_format($output['estimated_cost'], 4) . "\n";
} else {
    echo "Warning: Task exceeded token budget\n";
}
```

### Quality Assurance

```php
class QualityGate
{
    private int $minimumScore;
    private int $maxRetries;
    
    public function __construct(int $minimumScore = 8, int $maxRetries = 2)
    {
        $this->minimumScore = $minimumScore;
        $this->maxRetries = $maxRetries;
    }
    
    public function ensureQuality(ReflectionAgent $agent, string $task): ?string
    {
        $attempts = 0;
        
        while ($attempts < $this->maxRetries) {
            $result = $agent->run($task);
            
            if (!$result->isSuccess()) {
                $attempts++;
                continue;
            }
            
            $metadata = $result->getMetadata();
            if ($metadata['final_score'] >= $this->minimumScore) {
                return $result->getAnswer();
            }
            
            // Didn't meet quality gate, try again with emphasis
            $task = "IMPORTANT: High quality required (score >= {$this->minimumScore}/10)\n\n{$task}";
            $attempts++;
        }
        
        // Failed to meet quality after retries
        error_log("Quality gate failed after {$attempts} attempts");
        return null;
    }
}

// Usage
$qualityGate = new QualityGate(minimumScore: 8, maxRetries: 3);

$output = $qualityGate->ensureQuality($agent, "Write a secure authentication function");

if ($output !== null) {
    echo "Quality gate passed!\n";
    echo $output;
} else {
    echo "Failed to meet quality standards\n";
}
```

## Common Patterns

### Pattern 1: Progressive Refinement

```php
// Start with lower threshold, increase for important tasks
function createAdaptiveAgent(ClaudePhp $client, string $importance): ReflectionAgent
{
    $config = match($importance) {
        'draft' => [
            'quality_threshold' => 6,
            'max_refinements' => 1,
        ],
        'standard' => [
            'quality_threshold' => 8,
            'max_refinements' => 2,
        ],
        'critical' => [
            'quality_threshold' => 9,
            'max_refinements' => 4,
        ],
        default => [
            'quality_threshold' => 7,
            'max_refinements' => 2,
        ],
    };
    
    return new ReflectionAgent($client, $config);
}

// Usage
$draftAgent = createAdaptiveAgent($client, 'draft');
$criticalAgent = createAdaptiveAgent($client, 'critical');
```

### Pattern 2: Multi-Stage Refinement

```php
// Use reflection at multiple stages
$initialAgent = new ReflectionAgent($client, [
    'criteria' => 'basic correctness and completeness',
    'quality_threshold' => 7,
    'max_refinements' => 1,
]);

$polishAgent = new ReflectionAgent($client, [
    'criteria' => 'professional quality, documentation, and best practices',
    'quality_threshold' => 9,
    'max_refinements' => 2,
]);

// Stage 1: Get it working
$stage1 = $initialAgent->run("Create a file upload handler");

if ($stage1->isSuccess()) {
    // Stage 2: Make it excellent
    $stage2 = $polishAgent->run(
        "Review and polish this code:\n\n" . $stage1->getAnswer()
    );
    
    if ($stage2->isSuccess()) {
        echo $stage2->getAnswer();
    }
}
```

### Pattern 3: Specialized Reflection

```php
// Create a factory for different reflection types
class ReflectionAgentFactory
{
    public static function forCode(ClaudePhp $client): ReflectionAgent
    {
        return new ReflectionAgent($client, [
            'name' => 'code_reflection',
            'criteria' => 'correctness, type safety, error handling, documentation, and best practices',
            'quality_threshold' => 9,
            'max_refinements' => 3,
        ]);
    }
    
    public static function forWriting(ClaudePhp $client): ReflectionAgent
    {
        return new ReflectionAgent($client, [
            'name' => 'writing_reflection',
            'criteria' => 'clarity, engagement, grammar, and persuasiveness',
            'quality_threshold' => 8,
            'max_refinements' => 2,
        ]);
    }
    
    public static function forDocumentation(ClaudePhp $client): ReflectionAgent
    {
        return new ReflectionAgent($client, [
            'name' => 'doc_reflection',
            'criteria' => 'accuracy, clarity, completeness, and examples',
            'quality_threshold' => 8,
            'max_refinements' => 2,
        ]);
    }
}

// Usage
$codeAgent = ReflectionAgentFactory::forCode($client);
$writingAgent = ReflectionAgentFactory::forWriting($client);
```

## Troubleshooting

### Issue: Quality Threshold Never Met

**Problem:** Agent always hits max refinements without reaching threshold

**Solutions:**

```php
// Solution 1: Lower the threshold
$agent = new ReflectionAgent($client, [
    'quality_threshold' => 7, // Lower from 8
]);

// Solution 2: Increase max refinements
$agent = new ReflectionAgent($client, [
    'max_refinements' => 5, // Increase from 3
]);

// Solution 3: Make criteria more specific
$agent = new ReflectionAgent($client, [
    'criteria' => 'specific measurable criterion 1, specific criterion 2',
]);
```

### Issue: No Improvement Between Iterations

**Problem:** Refinements don't actually improve the output

**Solutions:**

```php
// Solution 1: More specific criteria
$agent = new ReflectionAgent($client, [
    'criteria' => 'concrete issue 1 with examples, concrete issue 2 with examples',
]);

// Solution 2: Check reflection feedback
$result = $agent->run($task);
$metadata = $result->getMetadata();

foreach ($metadata['reflections'] as $reflection) {
    echo "Feedback: {$reflection['feedback']}\n";
    // If feedback is vague, adjust criteria
}
```

### Issue: High Token Usage

**Problem:** Too many tokens consumed per task

**Solutions:**

```php
// Solution 1: Reduce max refinements
$agent = new ReflectionAgent($client, [
    'max_refinements' => 2, // Reduce from 3
]);

// Solution 2: Reduce max tokens per call
$agent = new ReflectionAgent($client, [
    'max_tokens' => 1024, // Reduce from 2048
]);

// Solution 3: Use higher initial quality
$agent = new ReflectionAgent($client, [
    'quality_threshold' => 7, // Accept lower quality
]);
```

### Issue: Inconsistent Scores

**Problem:** Score extraction doesn't work reliably

**Solution:**

```php
// The agent looks for patterns like "Score: 7/10" or "Quality: 8"
// Ensure your criteria encourages clear scoring:

$agent = new ReflectionAgent($client, [
    'criteria' => 'criterion 1, criterion 2. Provide a clear quality score at the end.',
]);
```

## Next Steps

Now that you've mastered the Reflection pattern, explore:

1. **[Chain-of-Thought Agent](ChainOfThoughtAgent_Tutorial.md)** - For step-by-step reasoning
2. **[Hierarchical Agents](HierarchicalAgent_Tutorial.md)** - For breaking down complex tasks
3. **[Learning Agent](LearningAgent_Tutorial.md)** - For continuous improvement

### Additional Resources

- [Reflection Agent Documentation](../ReflectionAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [Example Code](../../examples/reflection_agent.php)
- [Research: Self-Reflection in Language Models](https://arxiv.org/abs/2303.11366)

## Summary

You've learned:

- ✅ What the Reflection pattern is and why it produces better results
- ✅ How to configure quality thresholds and refinement limits
- ✅ How to create domain-specific evaluation criteria
- ✅ Production best practices for monitoring and cost management
- ✅ Common patterns for different use cases
- ✅ Troubleshooting techniques for common issues

The Reflection pattern is powerful for any task where quality matters more than speed. Experiment with different criteria and thresholds to find the sweet spot for your use case!

---

**Happy reflecting! 🪞**

