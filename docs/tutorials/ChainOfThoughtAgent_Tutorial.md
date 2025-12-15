# Chain-of-Thought Agent Tutorial

Welcome to this comprehensive tutorial on using the `ChainOfThoughtAgent`! Chain-of-Thought (CoT) reasoning is a powerful technique that enables AI to solve complex problems by breaking them down into intermediate steps.

## Table of Contents

1. [Introduction](#introduction)
2. [What is Chain-of-Thought Reasoning?](#what-is-chain-of-thought-reasoning)
3. [Setup](#setup)
4. [Tutorial 1: Your First CoT Agent](#tutorial-1-your-first-cot-agent)
5. [Tutorial 2: Zero-shot vs Few-shot](#tutorial-2-zero-shot-vs-few-shot)
6. [Tutorial 3: Mathematical Reasoning](#tutorial-3-mathematical-reasoning)
7. [Tutorial 4: Logic Puzzles](#tutorial-4-logic-puzzles)
8. [Tutorial 5: Decision Making](#tutorial-5-decision-making)
9. [Tutorial 6: Custom Examples](#tutorial-6-custom-examples)
10. [Tutorial 7: Production Best Practices](#tutorial-7-production-best-practices)
11. [Common Patterns](#common-patterns)
12. [Troubleshooting](#troubleshooting)
13. [Next Steps](#next-steps)

## Introduction

This tutorial will teach you how to leverage Chain-of-Thought reasoning to solve complex problems systematically. By the end, you'll be able to:

- Understand when and why to use CoT reasoning
- Implement zero-shot and few-shot CoT patterns
- Optimize CoT agents for different problem types
- Build production-ready reasoning systems

## What is Chain-of-Thought Reasoning?

Chain-of-Thought reasoning is a prompting technique where the AI model breaks down complex problems into intermediate reasoning steps. Instead of jumping directly to an answer, it shows its work.

**Without CoT:**

```
Q: What is 15% of $80?
A: $12
```

**With CoT:**

```
Q: What is 15% of $80?
A: Let me solve this step by step:
   Step 1: Convert percentage to decimal: 15% = 0.15
   Step 2: Multiply by the amount: $80 × 0.15 = $12
   Final Answer: $12
```

The CoT approach provides:

- **Transparency**: See how the answer was reached
- **Accuracy**: Fewer errors through systematic reasoning
- **Debuggability**: Identify where reasoning went wrong
- **Trust**: Verify the logic independently

## Setup

First, install the package and set up your API key:

```bash
composer require claude-php-agent
```

Set your Anthropic API key:

```bash
export ANTHROPIC_API_KEY='your-api-key-here'
```

## Tutorial 1: Your First CoT Agent

Let's create a simple CoT agent to solve a math problem.

### Step 1: Basic Zero-shot CoT

Create a file `my_first_cot.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a zero-shot CoT agent
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'name' => 'my_first_cot',
]);

// Ask it to solve a problem
$problem = "If I buy 3 items at $7.50 each and have a $5 coupon, what's my total?";

$result = $agent->run($problem);

if ($result->isSuccess()) {
    echo "Problem: {$problem}\n\n";
    echo "Solution:\n{$result->getAnswer()}\n\n";

    // Check token usage
    $tokens = $result->getMetadata()['tokens'];
    echo "Tokens used: {$tokens['input']} in, {$tokens['output']} out\n";
} else {
    echo "Error: {$result->getError()}\n";
}
```

Run it:

```bash
php my_first_cot.php
```

**Expected Output:**

```
Problem: If I buy 3 items at $7.50 each and have a $5 coupon, what's my total?

Solution:
Let me think step by step.

Step 1: Calculate the cost of 3 items
3 items × $7.50 = $22.50

Step 2: Apply the $5 coupon
$22.50 - $5.00 = $17.50

Final Answer: $17.50

Tokens used: 45 in, 68 out
```

### Understanding What Happened

1. **Zero-shot mode**: We didn't provide examples; the trigger phrase "Let's think step by step" activated CoT reasoning
2. **Automatic breakdown**: The AI naturally broke the problem into steps
3. **Clear conclusion**: The answer is explicitly stated at the end

## Tutorial 2: Zero-shot vs Few-shot

Let's compare both approaches to understand when to use each.

### Zero-shot CoT

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Zero-shot: No examples, just a trigger
$zeroShot = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => CoTPrompts::zeroShotTrigger(),
]);

$problem = "A train leaves at 2:30 PM and arrives at 5:15 PM. How long was the journey?";

$result = $zeroShot->run($problem);
echo "Zero-shot Result:\n{$result->getAnswer()}\n\n";
```

### Few-shot CoT

```php
// Few-shot: Learn from examples
$fewShot = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::mathExamples(),
]);

$result = $fewShot->run($problem);
echo "Few-shot Result:\n{$result->getAnswer()}\n\n";
```

### When to Use Each

**Use Zero-shot when:**

- Problem types vary widely
- You need quick prototyping
- The domain is general
- You want simplicity

**Use Few-shot when:**

- You have specific formatting requirements
- The domain is specialized
- Consistency is critical
- You can create good examples

## Tutorial 3: Mathematical Reasoning

Let's build a math problem solver using CoT.

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Use few-shot with math examples for consistency
$mathAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::mathExamples(),
    'name' => 'math_solver',
]);

// Test different problem types
$problems = [
    // Basic arithmetic
    "What is 127 + 384?",

    // Percentages
    "A shirt costs $45. With 30% off, what's the sale price?",

    // Word problems
    "Sarah has 3 times as many books as Tom. Tom has 15 books. How many do they have together?",

    // Multi-step
    "A rectangle is 12cm long and 8cm wide. What is its area and perimeter?",
];

foreach ($problems as $i => $problem) {
    echo "Problem " . ($i + 1) . ": {$problem}\n";

    $result = $mathAgent->run($problem);

    if ($result->isSuccess()) {
        echo "Solution:\n{$result->getAnswer()}\n";
    } else {
        echo "Error: {$result->getError()}\n";
    }

    echo str_repeat("-", 60) . "\n\n";
}
```

### Tips for Math Problems

1. **Use few-shot mode** for consistent formatting
2. **Provide examples** that match your problem complexity
3. **Validate answers** programmatically when possible

```php
// Extract and validate numeric answers
$result = $mathAgent->run("What is 25% of 80?");

if ($result->isSuccess()) {
    $answer = $result->getAnswer();

    // Extract the final number
    if (preg_match('/Final Answer:\s*\$?(\d+\.?\d*)/', $answer, $matches)) {
        $numericAnswer = (float)$matches[1];

        // Verify
        $expected = 80 * 0.25;
        if (abs($numericAnswer - $expected) < 0.01) {
            echo "✓ Answer verified: {$numericAnswer}\n";
        } else {
            echo "✗ Answer incorrect: {$numericAnswer} (expected {$expected})\n";
        }
    }
}
```

## Tutorial 4: Logic Puzzles

CoT is excellent for logic problems that require systematic analysis.

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Use logic examples to teach constraint reasoning
$logicAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::logicExamples(),
    'name' => 'logic_solver',
]);

$puzzle = <<<EOT
Four people (Alice, Bob, Carol, David) are sitting in a row.
- Alice sits next to Bob
- Bob does not sit next to Carol
- David sits at one end
- Carol is not at either end

Who sits where?
EOT;

$result = $logicAgent->run($puzzle);

if ($result->isSuccess()) {
    echo "Puzzle:\n{$puzzle}\n\n";
    echo "Solution:\n{$result->getAnswer()}\n";
}
```

### Custom Logic Examples

For specialized logic problems, create domain-specific examples:

```php
$customLogicExamples = [
    [
        'question' => 'If A > B and B > C, what is the relationship between A and C?',
        'answer' => "Let me reason through this:\n" .
                   "Constraint 1: A > B (A is greater than B)\n" .
                   "Constraint 2: B > C (B is greater than C)\n\n" .
                   "Analysis:\n" .
                   "- If A is greater than B, and B is greater than C\n" .
                   "- Then A must be greater than C (transitive property)\n\n" .
                   "Final Answer: A > C",
    ],
];

$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => $customLogicExamples,
]);
```

## Tutorial 5: Decision Making

Use CoT to analyze decisions systematically.

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Use decision examples for structured analysis
$decisionAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::decisionExamples(),
    'name' => 'decision_maker',
]);

$decision = <<<EOT
A software team must choose between:
Option A: Rewrite legacy system ($500k, 12 months, modern stack)
Option B: Incremental updates ($200k, 6 months, keep current stack)

Current system has: technical debt, slow performance, 5 known bugs
Team experience: Strong with current stack, learning curve for new stack

Which option should they choose?
EOT;

$result = $decisionAgent->run($decision);

if ($result->isSuccess()) {
    echo "Decision Scenario:\n{$decision}\n\n";
    echo "Analysis:\n{$result->getAnswer()}\n";
}
```

### Decision-Making Pattern

Create examples that follow a consistent structure:

```php
$decisionExamples = [
    [
        'question' => 'Should we hire a specialist or train existing staff?',
        'answer' => "Let me analyze this decision:\n\n" .
                   "Key Factors:\n" .
                   "1. Cost - Training: Lower upfront, Specialist: Higher salary\n" .
                   "2. Time - Training: 3-6 months, Specialist: Immediate\n" .
                   "3. Retention - Training: Higher loyalty, Specialist: Uncertain\n" .
                   "4. Quality - Training: Learning curve, Specialist: Proven expertise\n\n" .
                   "Analysis:\n" .
                   "- If timeline is critical: Hire specialist\n" .
                   "- If budget is tight and time flexible: Train staff\n" .
                   "- If team morale is important: Train staff\n\n" .
                   "Recommendation: For most stable teams with reasonable timelines, " .
                   "training existing staff provides better long-term value.",
    ],
];
```

## Tutorial 6: Custom Examples

Create examples tailored to your specific domain.

### Example: E-commerce Pricing

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Custom examples for e-commerce pricing calculations
$pricingExamples = [
    [
        'question' => 'Item: $50, Member discount: 10%, Promo code: 15% off. Final price?',
        'answer' => "Let me calculate the final price:\n" .
                   "Step 1: Apply member discount\n" .
                   "  $50 × 10% = $5 discount\n" .
                   "  Price after member discount: $50 - $5 = $45\n\n" .
                   "Step 2: Apply promo code to discounted price\n" .
                   "  $45 × 15% = $6.75 discount\n" .
                   "  Price after promo: $45 - $6.75 = $38.25\n\n" .
                   "Final Answer: $38.25",
    ],
    [
        'question' => 'Cart: 3 items at $20, 2 at $15. Shipping: $10 (free over $75). Tax: 8%. Total?',
        'answer' => "Let me calculate the order total:\n" .
                   "Step 1: Calculate subtotal\n" .
                   "  3 × $20 = $60\n" .
                   "  2 × $15 = $30\n" .
                   "  Subtotal: $60 + $30 = $90\n\n" .
                   "Step 2: Determine shipping\n" .
                   "  Subtotal $90 > $75, so shipping is FREE\n" .
                   "  Shipping: $0\n\n" .
                   "Step 3: Calculate tax on subtotal\n" .
                   "  $90 × 8% = $7.20\n\n" .
                   "Step 4: Calculate total\n" .
                   "  $90 + $0 + $7.20 = $97.20\n\n" .
                   "Final Answer: $97.20",
    ],
];

$pricingAgent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => $pricingExamples,
    'name' => 'pricing_calculator',
]);

// Test with new scenarios
$scenarios = [
    "Item: $80, Black Friday: 40% off, Member bonus: $5 off. Final price?",
    "Cart: 5 items at $12. Shipping: $8 (free over $50). Tax: 7%. Total?",
];

foreach ($scenarios as $scenario) {
    echo "Scenario: {$scenario}\n";
    $result = $pricingAgent->run($scenario);
    echo "Result:\n{$result->getAnswer()}\n\n";
    echo str_repeat("=", 60) . "\n\n";
}
```

### Guidelines for Custom Examples

1. **Match your domain**: Examples should reflect your actual use cases
2. **Show all steps**: Don't skip intermediate calculations
3. **Be consistent**: Use the same format across examples
4. **Include edge cases**: Cover unusual scenarios in your examples
5. **Test thoroughly**: Verify examples work before deploying

## Tutorial 7: Production Best Practices

### Logging and Monitoring

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Set up comprehensive logging
$logger = new Logger('cot-agent');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
$logger->pushHandler(new RotatingFileHandler('logs/cot.log', 30, Logger::DEBUG));

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => $myExamples,
    'logger' => $logger,
    'name' => 'production-cot',
]);

// Track metrics
$metrics = [
    'total_requests' => 0,
    'successful' => 0,
    'failed' => 0,
    'total_tokens' => 0,
];

$result = $agent->run($task);

$metrics['total_requests']++;

if ($result->isSuccess()) {
    $metrics['successful']++;
    $tokens = $result->getMetadata()['tokens'];
    $metrics['total_tokens'] += $tokens['input'] + $tokens['output'];
} else {
    $metrics['failed']++;
    $logger->error('CoT agent failed', [
        'task' => $task,
        'error' => $result->getError(),
    ]);
}

// Log metrics periodically
$logger->info('CoT Metrics', $metrics);
```

### Error Handling

```php
function runCoTSafely(ChainOfThoughtAgent $agent, string $task, int $maxRetries = 3): ?AgentResult
{
    $attempt = 0;
    $lastError = null;

    while ($attempt < $maxRetries) {
        try {
            $result = $agent->run($task);

            if ($result->isSuccess()) {
                return $result;
            }

            $lastError = $result->getError();
            $attempt++;

            // Exponential backoff
            if ($attempt < $maxRetries) {
                sleep(pow(2, $attempt));
            }

        } catch (\Exception $e) {
            $lastError = $e->getMessage();
            $attempt++;

            if ($attempt < $maxRetries) {
                sleep(pow(2, $attempt));
            }
        }
    }

    error_log("CoT agent failed after {$maxRetries} attempts: {$lastError}");
    return null;
}

// Usage
$result = runCoTSafely($agent, $task);

if ($result !== null) {
    // Process successful result
    echo $result->getAnswer();
} else {
    // Handle failure
    echo "Unable to process task after multiple attempts.\n";
}
```

### Caching for Performance

```php
class CoTCache
{
    private array $cache = [];

    public function get(string $task): ?AgentResult
    {
        $key = md5($task);
        return $this->cache[$key] ?? null;
    }

    public function set(string $task, AgentResult $result): void
    {
        $key = md5($task);
        $this->cache[$key] = $result;
    }

    public function has(string $task): bool
    {
        $key = md5($task);
        return isset($this->cache[$key]);
    }
}

// Usage
$cache = new CoTCache();

if ($cache->has($task)) {
    $result = $cache->get($task);
    echo "From cache: {$result->getAnswer()}\n";
} else {
    $result = $agent->run($task);
    if ($result->isSuccess()) {
        $cache->set($task, $result);
        echo "Fresh result: {$result->getAnswer()}\n";
    }
}
```

### Rate Limiting

```php
class RateLimiter
{
    private int $maxRequestsPerMinute;
    private array $requests = [];

    public function __construct(int $maxRequestsPerMinute = 50)
    {
        $this->maxRequestsPerMinute = $maxRequestsPerMinute;
    }

    public function allowRequest(): bool
    {
        $now = time();
        $oneMinuteAgo = $now - 60;

        // Remove old requests
        $this->requests = array_filter(
            $this->requests,
            fn($timestamp) => $timestamp > $oneMinuteAgo
        );

        if (count($this->requests) < $this->maxRequestsPerMinute) {
            $this->requests[] = $now;
            return true;
        }

        return false;
    }

    public function waitUntilAllowed(): void
    {
        while (!$this->allowRequest()) {
            sleep(1);
        }
    }
}

// Usage
$rateLimiter = new RateLimiter(50);

foreach ($tasks as $task) {
    $rateLimiter->waitUntilAllowed();
    $result = $agent->run($task);
    // Process result...
}
```

## Common Patterns

### Pattern 1: Multi-Question Analysis

```php
$agent = new ChainOfThoughtAgent($client, ['mode' => 'zero_shot']);

$complexTask = <<<EOT
Analyze this scenario and answer three questions:

Scenario: A company has 100 employees. 60% are developers, 25% are in sales, 15% are in support.
Developers average $90k salary, Sales averages $70k, Support averages $50k.

Questions:
1. How many people in each department?
2. What is the total annual salary cost?
3. What is the average salary across all employees?
EOT;

$result = $agent->run($complexTask);
```

### Pattern 2: Verification Loop

```php
function verifyAnswer(ChainOfThoughtAgent $agent, string $task, callable $validator): AgentResult
{
    $result = $agent->run($task);

    if ($result->isSuccess()) {
        $answer = $result->getAnswer();

        if (!$validator($answer)) {
            // Ask agent to reconsider
            $followUp = $task . "\n\nPlease double-check your answer.";
            return $agent->run($followUp);
        }
    }

    return $result;
}

// Usage
$result = verifyAnswer($agent, "What is 15% of $200?", function($answer) {
    return str_contains($answer, '$30');
});
```

### Pattern 3: Progressive Complexity

```php
// Start simple, increase complexity
$problems = [
    'easy' => "What is 10% of 100?",
    'medium' => "What is 17.5% of $240 with $10 off?",
    'hard' => "Calculate total: 3 items at 15% off $50, plus 8% tax, minus $20 coupon.",
];

foreach ($problems as $level => $problem) {
    echo "Level: {$level}\n";
    $result = $agent->run($problem);
    echo $result->getAnswer() . "\n\n";
}
```

## Troubleshooting

### Issue: Incomplete Reasoning

**Problem:** Agent skips steps or jumps to conclusion

**Solution:**

```php
// Use few-shot with detailed examples
$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::mathExamples(), // Shows detailed steps
]);
```

### Issue: Inconsistent Format

**Problem:** Output format varies between runs

**Solution:**

```php
// Provide examples with consistent structure
$examples = [
    [
        'question' => '...',
        'answer' => "Step 1: ...\nStep 2: ...\nFinal Answer: ..."
    ],
];
```

### Issue: Wrong Answers

**Problem:** Agent makes calculation errors

**Solution:**

```php
// 1. Add verification examples
// 2. Use more specific trigger phrases
// 3. Validate programmatically

$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => "Let's solve this carefully, step by step, checking each calculation.",
]);
```

### Issue: High Token Usage

**Problem:** Too many tokens consumed

**Solution:**

```php
// 1. Use zero-shot instead of few-shot
// 2. Simplify trigger phrases
// 3. Break complex tasks into smaller ones

$agent = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => "Let's think step by step.", // Shorter trigger
]);
```

## Next Steps

Now that you've mastered Chain-of-Thought reasoning, explore:

1. **[Tree-of-Thoughts Agent](../../Claude-PHP-SDK/tutorials/08-tree-of-thoughts/)** - For exploring multiple reasoning paths
2. **[Reflection Agent](reflection_agent.md)** - For self-correction and improvement
3. **[Hierarchical Agents](hierarchical_agent.md)** - For breaking down complex tasks

### Additional Resources

- [CoT Documentation](../ChainOfThoughtAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [Example Code](../../examples/cot_example.php)
- [Research Paper: Chain-of-Thought Prompting](https://arxiv.org/abs/2201.11903)

## Summary

You've learned:

- ✅ What Chain-of-Thought reasoning is and why it's powerful
- ✅ How to implement zero-shot and few-shot CoT
- ✅ When to use each approach
- ✅ How to create custom examples for your domain
- ✅ Production best practices for reliability and monitoring
- ✅ Common patterns and troubleshooting techniques

Keep experimenting with different trigger phrases and examples to find what works best for your use case!

---

**Happy reasoning! 🧠**
