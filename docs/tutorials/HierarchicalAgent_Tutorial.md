# HierarchicalAgent Tutorial: Building Master-Worker AI Systems

## Introduction

This tutorial will guide you through building sophisticated multi-agent systems using the HierarchicalAgent pattern. You'll learn how to coordinate specialized AI agents to solve complex, multi-domain problems that require different types of expertise.

By the end of this tutorial, you'll be able to:
- Understand the master-worker pattern for AI agents
- Create and configure specialized worker agents
- Coordinate multiple agents to solve complex tasks
- Build real-world multi-agent systems
- Optimize performance and manage costs
- Handle errors and edge cases

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and object-oriented programming
- Familiarity with AI agent concepts (helpful but not required)

## Table of Contents

1. [Understanding the Pattern](#understanding-the-pattern)
2. [Your First Hierarchical System](#your-first-hierarchical-system)
3. [Creating Specialized Workers](#creating-specialized-workers)
4. [Advanced Task Coordination](#advanced-task-coordination)
5. [Building Real-World Systems](#building-real-world-systems)
6. [Performance Optimization](#performance-optimization)
7. [Error Handling and Recovery](#error-handling-and-recovery)
8. [Production Best Practices](#production-best-practices)

## Understanding the Pattern

### What is the Master-Worker Pattern?

The master-worker pattern (also called hierarchical agent pattern) coordinates multiple specialized "worker" agents under a single "master" agent:

```
┌─────────────────────────────────┐
│      Master Agent               │
│                                 │
│  • Understands the big picture  │
│  • Breaks down complex tasks    │
│  • Delegates to specialists     │
│  • Combines results             │
└───────────┬─────────────────────┘
            │
            ├──────┬──────┬──────┐
            ▼      ▼      ▼      ▼
         ┌────┐ ┌────┐ ┌────┐ ┌────┐
         │ W1 │ │ W2 │ │ W3 │ │ W4 │
         └────┘ └────┘ └────┘ └────┘
       Math    Write  Code   Data
       Expert  Expert Expert Expert
```

### When to Use This Pattern

Use hierarchical agents when:

✅ **Multiple domains of expertise** - Task requires different specializations
✅ **Complex problems** - Single agent would struggle with the full scope
✅ **Quality matters** - Each subtask needs specialist attention
✅ **Parallelizable work** - Subtasks can be worked on independently

Don't use when:

❌ **Simple tasks** - Overhead isn't worth it for basic problems
❌ **Sequential reasoning** - Use Chain of Thought instead
❌ **Limited budget** - Multiple API calls increase costs
❌ **Real-time requirements** - Multiple agents take more time

### The Three Phases

Every hierarchical agent execution goes through three phases:

1. **Decomposition**: Master breaks task into subtasks
2. **Execution**: Workers complete their assigned subtasks
3. **Synthesis**: Master combines results into final answer

## Your First Hierarchical System

Let's build a simple two-agent system to understand the basics.

### Step 1: Installation and Setup

```bash
composer require your-org/claude-php-agent
```

Create a new file `tutorial_hierarchical.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

echo "Hierarchical Agent Tutorial\n";
echo str_repeat("=", 50) . "\n\n";
```

### Step 2: Create Your First Worker

A worker is a specialized agent with a specific area of expertise:

```php
// Create a math specialist
$mathWorker = new WorkerAgent($client, [
    'name' => 'math_expert',
    'specialty' => 'mathematical calculations and analysis',
    'system' => 'You are a mathematics expert. Provide precise calculations with clear explanations.'
]);

echo "Created math worker: {$mathWorker->getName()}\n";
echo "Specialty: {$mathWorker->getSpecialty()}\n\n";
```

### Step 3: Create a Second Worker

Let's add a writing specialist:

```php
// Create a writing specialist
$writerWorker = new WorkerAgent($client, [
    'name' => 'writer_expert',
    'specialty' => 'clear and engaging writing',
    'system' => 'You are a professional writer. Create clear, engaging content that explains complex topics simply.'
]);

echo "Created writer worker: {$writerWorker->getName()}\n";
echo "Specialty: {$writerWorker->getSpecialty()}\n\n";
```

### Step 4: Create the Master Agent

The master coordinates the workers:

```php
// Create the master coordinator
$master = new HierarchicalAgent($client, [
    'name' => 'master_coordinator',
]);

echo "Created master agent: {$master->getName()}\n\n";
```

### Step 5: Register Workers

Tell the master about available workers:

```php
$master->registerWorker('math_expert', $mathWorker);
$master->registerWorker('writer_expert', $writerWorker);

echo "Registered workers:\n";
foreach ($master->getWorkerNames() as $name) {
    echo "  • {$name}\n";
}
echo "\n";
```

### Step 6: Run Your First Task

Give the master a task that requires both specialists:

```php
$task = "Calculate the sum of 123 and 456, then explain in simple terms what addition means and why it's useful.";

echo "Task: {$task}\n\n";
echo "Processing...\n\n";

$result = $master->run($task);

if ($result->isSuccess()) {
    echo "✅ SUCCESS!\n\n";
    echo "Final Answer:\n";
    echo str_repeat("-", 50) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 50) . "\n\n";
    
    // Show execution details
    $metadata = $result->getMetadata();
    echo "Execution Details:\n";
    echo "  • Iterations: {$result->getIterations()}\n";
    echo "  • Subtasks: {$metadata['subtasks']}\n";
    echo "  • Workers used: " . implode(', ', $metadata['workers_used']) . "\n";
    echo "  • Duration: {$metadata['duration_seconds']} seconds\n";
    echo "  • Total tokens: {$metadata['token_usage']['total']}\n";
} else {
    echo "❌ ERROR: {$result->getError()}\n";
}
```

### Expected Output

```
Hierarchical Agent Tutorial
==================================================

Created math worker: math_expert
Specialty: mathematical calculations and analysis

Created writer worker: writer_expert
Specialty: clear and engaging writing

Created master agent: master_coordinator

Registered workers:
  • math_expert
  • writer_expert

Task: Calculate the sum of 123 and 456, then explain in simple terms what addition means and why it's useful.

Processing...

✅ SUCCESS!

Final Answer:
--------------------------------------------------
The sum of 123 and 456 is 579.

Addition is one of the fundamental operations in mathematics. At its core, addition means combining or putting together quantities to find their total. When we add 123 and 456, we're essentially asking "if I have 123 items and gain 456 more items, how many do I have in total?"

Addition is incredibly useful in everyday life. We use it when managing money (calculating total expenses), cooking (combining ingredients), planning trips (estimating total travel time), and countless other situations...
--------------------------------------------------

Execution Details:
  • Iterations: 4
  • Subtasks: 2
  • Workers used: math_expert, writer_expert
  • Duration: 8.45 seconds
  • Total tokens: 892
```

### What Just Happened?

Let's break down the execution:

1. **Decomposition** (Iteration 1): Master analyzed the task and created two subtasks:
   - Math expert: Calculate 123 + 456
   - Writer expert: Explain what addition is and why it's useful

2. **Execution** (Iterations 2-3): Each worker completed their subtask:
   - Math expert provided: "579"
   - Writer expert provided: explanation paragraph

3. **Synthesis** (Iteration 4): Master combined both results into a coherent answer

## Creating Specialized Workers

Now let's build more sophisticated workers for different domains.

### Research Specialist

```php
$researchWorker = new WorkerAgent($client, [
    'name' => 'researcher',
    'specialty' => 'research, fact-finding, and information synthesis',
    'system' => 'You are a research specialist. Find relevant information, verify facts, and provide well-sourced answers. Always explain your reasoning.',
    'max_tokens' => 3000, // More tokens for detailed research
]);
```

### Code Analysis Specialist

```php
$codeWorker = new WorkerAgent($client, [
    'name' => 'code_analyst',
    'specialty' => 'code review, debugging, and best practices',
    'system' => 'You are a senior software engineer. Review code for bugs, security issues, performance problems, and adherence to best practices. Provide specific, actionable feedback.',
]);
```

### Data Analysis Specialist

```php
$dataWorker = new WorkerAgent($client, [
    'name' => 'data_analyst',
    'specialty' => 'data analysis, statistics, and insights',
    'system' => 'You are a data analyst. Analyze datasets, calculate statistics, identify trends, and provide data-driven insights. Always show your calculations.',
]);
```

### Creative Specialist

```php
$creativeWorker = new WorkerAgent($client, [
    'name' => 'creative_writer',
    'specialty' => 'creative writing, storytelling, and engagement',
    'system' => 'You are a creative writer. Craft engaging narratives, create compelling copy, and tell stories that resonate. Focus on emotion and connection.',
]);
```

### Building a Complete Team

```php
// Create a diverse team
$master = new HierarchicalAgent($client);

$master->registerWorker('researcher', $researchWorker);
$master->registerWorker('code_analyst', $codeWorker);
$master->registerWorker('data_analyst', $dataWorker);
$master->registerWorker('creative_writer', $creativeWorker);

// This team can now handle complex, multi-faceted tasks
$result = $master->run(
    'Analyze the performance data from our API, identify bottlenecks in the code, ' .
    'research industry best practices, and write a compelling executive summary.'
);
```

## Advanced Task Coordination

### Understanding Task Decomposition

The master agent automatically decomposes tasks. Let's examine how:

```php
// Complex task
$task = "Create a blog post about quantum computing: research the topic, " .
        "explain the science, write engaging content, and add a catchy title.";

// Master will decompose into something like:
// 1. researcher: Research quantum computing fundamentals and recent developments
// 2. code_analyst: Explain technical concepts and algorithms
// 3. creative_writer: Write engaging introduction and catchy title
// 4. researcher: Fact-check and verify scientific accuracy
```

### Monitoring Decomposition

Add logging to see the decomposition:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('hierarchical');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$master = new HierarchicalAgent($client, [
    'logger' => $logger,
]);

// Now you'll see debug output showing the decomposition process
```

### Worker Selection Strategy

The master uses worker specialty descriptions to make intelligent assignments:

```php
// Good: Clear, specific specialties
$sqlWorker = new WorkerAgent($client, [
    'specialty' => 'SQL query optimization and database performance tuning',
]);

$frontendWorker = new WorkerAgent($client, [
    'specialty' => 'React, Vue, and modern frontend development',
]);

// Less effective: Vague specialties
$generalWorker = new WorkerAgent($client, [
    'specialty' => 'general programming',  // Too broad!
]);
```

### Fallback Behavior

When a requested worker doesn't exist:

```php
$master = new HierarchicalAgent($client);
$master->registerWorker('only_worker', $someWorker);

// Task requests a non-existent worker
// Master will use 'only_worker' as fallback
$result = $master->run('Task requiring specialized_worker');

// If NO workers are registered, subtask notes no worker available
```

## Building Real-World Systems

### Example 1: Code Review System

```php
<?php
// code_review_system.php

require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\HierarchicalAgent;
use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Security specialist
$securityWorker = new WorkerAgent($client, [
    'name' => 'security_expert',
    'specialty' => 'security vulnerabilities, injection attacks, and secure coding',
    'system' => 'You are a security expert. Review code for vulnerabilities like SQL injection, XSS, CSRF, authentication issues, and data exposure. Provide specific fixes.',
]);

// Performance specialist
$performanceWorker = new WorkerAgent($client, [
    'name' => 'performance_expert',
    'specialty' => 'performance optimization, algorithms, and scalability',
    'system' => 'You are a performance expert. Identify bottlenecks, inefficient algorithms, memory issues, and scalability problems. Suggest optimizations.',
]);

// Best practices specialist
$practicesWorker = new WorkerAgent($client, [
    'name' => 'practices_expert',
    'specialty' => 'coding standards, design patterns, and maintainability',
    'system' => 'You are a code quality expert. Review for clean code principles, SOLID principles, design patterns, naming conventions, and maintainability.',
]);

// Test coverage specialist
$testWorker = new WorkerAgent($client, [
    'name' => 'test_expert',
    'specialty' => 'unit testing, integration testing, and test coverage',
    'system' => 'You are a testing expert. Suggest test cases, identify untested code paths, and recommend testing strategies.',
]);

// Create master
$codeReviewer = new HierarchicalAgent($client, [
    'name' => 'code_review_master',
]);

$codeReviewer->registerWorker('security_expert', $securityWorker);
$codeReviewer->registerWorker('performance_expert', $performanceWorker);
$codeReviewer->registerWorker('practices_expert', $practicesWorker);
$codeReviewer->registerWorker('test_expert', $testWorker);

// Review code
$code = <<<'PHP'
function getUserData($userId) {
    $query = "SELECT * FROM users WHERE id = " . $userId;
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}
PHP;

$result = $codeReviewer->run(
    "Review this PHP function for security issues, performance problems, " .
    "best practice violations, and suggest tests:\n\n{$code}"
);

if ($result->isSuccess()) {
    echo "CODE REVIEW REPORT\n";
    echo str_repeat("=", 80) . "\n\n";
    echo $result->getAnswer() . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "Review completed by: " . implode(', ', $metadata['workers_used']) . "\n";
}
```

### Example 2: Content Creation Pipeline

```php
<?php
// content_pipeline.php

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Research specialist
$researcher = new WorkerAgent($client, [
    'name' => 'researcher',
    'specialty' => 'topic research, fact-checking, and source verification',
    'system' => 'Research topics thoroughly. Find key facts, statistics, expert opinions, and recent developments. Cite sources.',
]);

// SEO specialist
$seoExpert = new WorkerAgent($client, [
    'name' => 'seo_expert',
    'specialty' => 'SEO optimization, keywords, and search rankings',
    'system' => 'Optimize content for search engines. Identify keywords, suggest meta descriptions, and recommend content structure for better rankings.',
]);

// Content writer
$writer = new WorkerAgent($client, [
    'name' => 'content_writer',
    'specialty' => 'engaging writing, storytelling, and audience connection',
    'system' => 'Write compelling, engaging content. Use storytelling, clear structure, and emotional connection. Write for your target audience.',
]);

// Editor
$editor = new WorkerAgent($client, [
    'name' => 'editor',
    'specialty' => 'editing, proofreading, grammar, and style',
    'system' => 'Edit for clarity, grammar, flow, and style. Ensure consistent tone, fix errors, and improve readability.',
]);

$contentMaster = new HierarchicalAgent($client, [
    'name' => 'content_pipeline',
]);

$contentMaster->registerWorker('researcher', $researcher);
$contentMaster->registerWorker('seo_expert', $seoExpert);
$contentMaster->registerWorker('content_writer', $writer);
$contentMaster->registerWorker('editor', $editor);

// Create blog post
$result = $contentMaster->run(
    'Create a comprehensive blog post about "Best Practices for Remote Team Management" ' .
    'targeted at startup founders. Include research, SEO optimization, and engaging writing.'
);

if ($result->isSuccess()) {
    echo $result->getAnswer() . "\n\n";
    
    // Save to file
    file_put_contents('blog_post.md', $result->getAnswer());
    echo "Blog post saved to blog_post.md\n";
}
```

### Example 3: Business Analysis System

```php
<?php
// business_analysis.php

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Market analyst
$marketAnalyst = new WorkerAgent($client, [
    'name' => 'market_analyst',
    'specialty' => 'market analysis, trends, and consumer behavior',
    'system' => 'Analyze market trends, consumer behavior, and industry dynamics. Provide data-driven insights.',
]);

// Financial analyst
$financialAnalyst = new WorkerAgent($client, [
    'name' => 'financial_analyst',
    'specialty' => 'financial analysis, projections, and risk assessment',
    'system' => 'Analyze financial data, create projections, assess risks, and calculate ROI. Show all calculations.',
]);

// Competitive analyst
$competitiveAnalyst = new WorkerAgent($client, [
    'name' => 'competitive_analyst',
    'specialty' => 'competitive intelligence and positioning',
    'system' => 'Analyze competitors, identify differentiators, and recommend positioning strategies.',
]);

// Strategy consultant
$strategist = new WorkerAgent($client, [
    'name' => 'strategist',
    'specialty' => 'business strategy and recommendations',
    'system' => 'Synthesize analysis into actionable strategy. Provide clear recommendations with rationale.',
]);

$businessMaster = new HierarchicalAgent($client, [
    'name' => 'business_strategist',
]);

$businessMaster->registerWorker('market_analyst', $marketAnalyst);
$businessMaster->registerWorker('financial_analyst', $financialAnalyst);
$businessMaster->registerWorker('competitive_analyst', $competitiveAnalyst);
$businessMaster->registerWorker('strategist', $strategist);

// Analyze business opportunity
$result = $businessMaster->run(
    'Should we expand our SaaS product into the healthcare market? ' .
    'Current ARR: $2M, 150 customers in finance sector. ' .
    'Healthcare competitors: Epic ($3B), Cerner ($1.5B). ' .
    'Expansion cost estimate: $500K. Provide comprehensive analysis and recommendation.'
);
```

## Performance Optimization

### Token Management

Track and optimize token usage:

```php
$result = $master->run($task);

$usage = $result->getTokenUsage();
$estimatedCost = (
    ($usage['input'] * 0.003 / 1000) +   // Sonnet input cost
    ($usage['output'] * 0.015 / 1000)     // Sonnet output cost
);

echo "Token Usage:\n";
echo "  Input: {$usage['input']} tokens\n";
echo "  Output: {$usage['output']} tokens\n";
echo "  Total: {$usage['total']} tokens\n";
echo "  Estimated cost: $" . number_format($estimatedCost, 4) . "\n";
```

### Using Different Models

Optimize costs by using different models for different roles:

```php
// Master uses Sonnet for smart decomposition
$master = new HierarchicalAgent($client, [
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 2048,
]);

// Simple workers use Haiku (faster, cheaper)
$simpleWorker = new WorkerAgent($client, [
    'model' => 'claude-haiku-3-5',
    'max_tokens' => 1024,
]);

// Complex workers use Sonnet
$complexWorker = new WorkerAgent($client, [
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 3000,
]);
```

### Limiting Scope

Keep worker responses focused:

```php
$worker = new WorkerAgent($client, [
    'max_tokens' => 1024,  // Shorter responses
    'system' => 'Provide concise, focused answers. No unnecessary details.',
]);
```

### Execution Time

Typical execution time breakdown:

```
1 master (decompose):     2-3 seconds
N workers (execute):      3-5 seconds each
1 master (synthesize):    2-4 seconds

Total for 3 workers:      ~15-20 seconds
Total for 5 workers:      ~25-30 seconds
```

## Error Handling and Recovery

### Handling Failures Gracefully

```php
$result = $master->run($complexTask);

if (!$result->isSuccess()) {
    $error = $result->getError();
    
    // Log the error
    error_log("Hierarchical agent failed: {$error}");
    
    // Determine failure type
    if (strpos($error, 'decompose') !== false) {
        echo "Failed to break down the task. Try simplifying it.\n";
    } elseif (strpos($error, 'API') !== false) {
        echo "API error. Check your connection and API key.\n";
    } else {
        echo "Unknown error: {$error}\n";
    }
    
    // Fallback to simpler approach
    $fallbackResult = $simpleAgent->run($complexTask);
}
```

### Retry Logic

Implement retries for transient failures:

```php
function runWithRetry($master, $task, $maxRetries = 3) {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $result = $master->run($task);
            
            if ($result->isSuccess()) {
                return $result;
            }
            
            echo "Attempt {$attempt} failed: {$result->getError()}\n";
            
            if ($attempt < $maxRetries) {
                sleep(2 ** $attempt); // Exponential backoff
            }
        } catch (\Exception $e) {
            echo "Attempt {$attempt} threw exception: {$e->getMessage()}\n";
            
            if ($attempt < $maxRetries) {
                sleep(2 ** $attempt);
            } else {
                throw $e;
            }
        }
    }
    
    throw new \RuntimeException("Failed after {$maxRetries} attempts");
}

// Usage
$result = runWithRetry($master, $task);
```

### Validation

Validate results before using:

```php
$result = $master->run($task);

if ($result->isSuccess()) {
    $answer = $result->getAnswer();
    
    // Validate answer quality
    if (strlen($answer) < 50) {
        echo "Warning: Response seems too short\n";
    }
    
    $metadata = $result->getMetadata();
    
    // Ensure expected workers were used
    $expectedWorkers = ['researcher', 'writer'];
    $usedWorkers = $metadata['workers_used'] ?? [];
    
    $missingWorkers = array_diff($expectedWorkers, $usedWorkers);
    if (!empty($missingWorkers)) {
        echo "Warning: Expected workers not used: " . implode(', ', $missingWorkers) . "\n";
    }
}
```

## Production Best Practices

### 1. Configuration Management

```php
class HierarchicalAgentFactory
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function createContentPipeline(ClaudePhp $client): HierarchicalAgent
    {
        $master = new HierarchicalAgent($client, [
            'name' => $this->config['name'] ?? 'content_pipeline',
            'model' => $this->config['model'] ?? 'claude-sonnet-4-5',
            'max_tokens' => $this->config['max_tokens'] ?? 2048,
        ]);
        
        foreach ($this->config['workers'] as $workerConfig) {
            $worker = new WorkerAgent($client, $workerConfig);
            $master->registerWorker($workerConfig['name'], $worker);
        }
        
        return $master;
    }
}

// Usage with config file
$config = require 'agent_config.php';
$factory = new HierarchicalAgentFactory($config);
$master = $factory->createContentPipeline($client);
```

### 2. Logging and Monitoring

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('hierarchical');
$logger->pushHandler(new RotatingFileHandler(
    'logs/hierarchical.log',
    30, // Keep 30 days
    Logger::INFO
));
$logger->pushHandler(new StreamHandler('php://stderr', Logger::ERROR));

$master = new HierarchicalAgent($client, [
    'logger' => $logger,
]);

// Logs will include:
// - Task decomposition
// - Worker assignments
// - Execution progress
// - Errors and warnings
```

### 3. Caching Results

```php
class CachedHierarchicalAgent
{
    private HierarchicalAgent $agent;
    private array $cache = [];
    
    public function __construct(HierarchicalAgent $agent)
    {
        $this->agent = $agent;
    }
    
    public function run(string $task): AgentResult
    {
        $cacheKey = md5($task);
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $result = $this->agent->run($task);
        
        if ($result->isSuccess()) {
            $this->cache[$cacheKey] = $result;
        }
        
        return $result;
    }
}
```

### 4. Rate Limiting

```php
class RateLimitedAgent
{
    private HierarchicalAgent $agent;
    private int $maxRequestsPerMinute;
    private array $requestTimes = [];
    
    public function run(string $task): AgentResult
    {
        $this->waitForRateLimit();
        $this->requestTimes[] = time();
        
        return $this->agent->run($task);
    }
    
    private function waitForRateLimit(): void
    {
        // Remove requests older than 1 minute
        $cutoff = time() - 60;
        $this->requestTimes = array_filter(
            $this->requestTimes,
            fn($time) => $time > $cutoff
        );
        
        // Wait if at limit
        if (count($this->requestTimes) >= $this->maxRequestsPerMinute) {
            $oldestRequest = min($this->requestTimes);
            $waitTime = 60 - (time() - $oldestRequest);
            if ($waitTime > 0) {
                sleep($waitTime);
            }
        }
    }
}
```

### 5. Testing

```php
// test_hierarchical_system.php

use PHPUnit\Framework\TestCase;

class HierarchicalSystemTest extends TestCase
{
    private HierarchicalAgent $agent;
    
    protected function setUp(): void
    {
        $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
        $this->agent = $this->createTestAgent($client);
    }
    
    public function testSimpleTask(): void
    {
        $result = $this->agent->run('Simple test task');
        
        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getAnswer());
    }
    
    public function testWorkerAssignment(): void
    {
        $result = $this->agent->run('Calculate 2+2 and explain the result');
        
        $metadata = $result->getMetadata();
        $this->assertContains('math_expert', $metadata['workers_used']);
        $this->assertContains('writer_expert', $metadata['workers_used']);
    }
    
    public function testTokenUsage(): void
    {
        $result = $this->agent->run('Brief task');
        
        $usage = $result->getTokenUsage();
        $this->assertGreaterThan(0, $usage['input']);
        $this->assertGreaterThan(0, $usage['output']);
        $this->assertEquals(
            $usage['input'] + $usage['output'],
            $usage['total']
        );
    }
}
```

## Conclusion

You've learned how to build sophisticated multi-agent systems using the HierarchicalAgent pattern. Key takeaways:

1. **Master-Worker Pattern**: Coordinate specialists for complex tasks
2. **Worker Specialization**: Create focused experts for specific domains
3. **Task Decomposition**: Let the master intelligently break down problems
4. **Real-World Applications**: Code review, content creation, business analysis
5. **Production Ready**: Logging, caching, rate limiting, and testing

### Next Steps

- Build your own specialized agent team
- Experiment with different worker combinations
- Optimize for your specific use case
- Monitor and measure performance
- Scale to production workloads

### Additional Resources

- [HierarchicalAgent API Documentation](../HierarchicalAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [Example Code](../../examples/hierarchical_agent.php)

## Troubleshooting

### Issue: Workers not being used

**Solution**: Check specialty descriptions are clear and specific:

```php
// Bad
'specialty' => 'programming'

// Good
'specialty' => 'Python backend development, API design, and database optimization'
```

### Issue: High token usage

**Solutions**:
- Use Haiku for simple workers
- Reduce max_tokens
- Make tasks more focused
- Cache common results

### Issue: Slow execution

**Solutions**:
- Limit number of workers
- Use faster models (Haiku)
- Reduce max_tokens
- Simplify worker system prompts

### Issue: Inconsistent results

**Solutions**:
- Add validation logic
- Use more specific system prompts
- Increase max_tokens for complex tasks
- Add result verification step

## Support

For questions or issues:
- GitHub Issues: [Report a bug](https://github.com/your-org/claude-php-agent/issues)
- Documentation: [Read the docs](../HierarchicalAgent.md)
- Examples: [View examples](../../examples/)

Happy building! 🚀

