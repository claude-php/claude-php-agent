# WorkerAgent Tutorial: Building Specialized AI Workers

## Introduction

This tutorial will guide you through creating and using specialized AI worker agents. You'll learn how to build focused, expert agents that excel at specific tasks, and how to use them both independently and as part of larger hierarchical systems.

By the end of this tutorial, you'll be able to:
- Create specialized worker agents for different domains
- Configure workers with custom prompts and settings
- Use workers for standalone tasks
- Combine workers in hierarchical agent systems
- Optimize worker performance and costs
- Build production-ready specialist teams

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and object-oriented programming
- Familiarity with AI agent concepts (helpful but not required)

## Table of Contents

1. [Understanding Worker Agents](#understanding-worker-agents)
2. [Your First Worker](#your-first-worker)
3. [Creating Specialized Workers](#creating-specialized-workers)
4. [Using Workers Standalone](#using-workers-standalone)
5. [Workers in Hierarchical Systems](#workers-in-hierarchical-systems)
6. [Building Production Teams](#building-production-teams)
7. [Performance Optimization](#performance-optimization)
8. [Best Practices](#best-practices)

## Understanding Worker Agents

### What is a Worker Agent?

A WorkerAgent is a specialized AI agent designed to excel at a specific type of task or domain:

```
┌─────────────────────────────────┐
│      Worker Agent               │
│                                 │
│  Name: "code_reviewer"          │
│  Specialty: "security analysis" │
│  System: "Expert in secure..."  │
│                                 │
│  • Focused expertise            │
│  • Consistent behavior          │
│  • Reusable across tasks        │
└─────────────────────────────────┘
```

### When to Use Worker Agents

Use workers when you need:

✅ **Specialized expertise** - Task requires domain-specific knowledge
✅ **Consistent quality** - Same type of work across multiple tasks
✅ **Reusable components** - Same specialist needed repeatedly
✅ **Team coordination** - Multiple specialists working together

Don't use when:

❌ **General tasks** - No specialized knowledge required
❌ **One-time use** - Won't reuse the specialist
❌ **Tool-based work** - Need ReAct pattern with tools instead

### Key Concepts

**Specialty**: What the worker is expert at
```php
'specialty' => 'Python security analysis and vulnerability detection'
```

**System Prompt**: How the worker behaves
```php
'system' => 'You are a security expert. Find vulnerabilities and suggest fixes.'
```

**Name**: Worker identifier
```php
'name' => 'security_auditor'
```

## Your First Worker

Let's create your first worker agent step by step.

### Step 1: Installation

```bash
composer require your-org/claude-php-agent
```

### Step 2: Basic Setup

Create a file `tutorial_worker.php`:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

echo "Worker Agent Tutorial\n";
echo str_repeat("=", 50) . "\n\n";
```

### Step 3: Create Your First Worker

Let's create a simple math specialist:

```php
// Create a math expert worker
$mathWorker = new WorkerAgent($client, [
    'name' => 'math_expert',
    'specialty' => 'mathematical calculations and analysis',
    'system' => 'You are a mathematics expert. Provide precise calculations with clear explanations. Always show your work step by step.',
]);

echo "Created worker: {$mathWorker->getName()}\n";
echo "Specialty: {$mathWorker->getSpecialty()}\n\n";
```

### Step 4: Use Your Worker

Give your worker a task:

```php
$task = "Calculate the compound interest on $5,000 invested at 6% annual interest for 10 years, compounded annually.";

echo "Task: {$task}\n\n";
echo "Processing...\n\n";

$result = $mathWorker->run($task);

if ($result->isSuccess()) {
    echo "✅ Answer:\n";
    echo str_repeat("-", 50) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 50) . "\n\n";
    
    // Show execution details
    $metadata = $result->getMetadata();
    echo "Worker: {$metadata['worker']}\n";
    echo "Specialty: {$metadata['specialty']}\n";
    
    $usage = $result->getTokenUsage();
    echo "Tokens: {$usage['total']} total ";
    echo "({$usage['input']} in, {$usage['output']} out)\n";
} else {
    echo "❌ Error: {$result->getError()}\n";
}
```

### Expected Output

```
Worker Agent Tutorial
==================================================

Created worker: math_expert
Specialty: mathematical calculations and analysis

Task: Calculate the compound interest on $5,000 invested at 6% annual interest for 10 years, compounded annually.

Processing...

✅ Answer:
--------------------------------------------------
I'll calculate the compound interest step by step using the formula:
A = P(1 + r)^t

Where:
- P = Principal ($5,000)
- r = Annual interest rate (6% = 0.06)
- t = Time in years (10)

Calculation:
A = $5,000 × (1 + 0.06)^10
A = $5,000 × (1.06)^10
A = $5,000 × 1.7908477
A = $8,954.24

The compound interest earned is:
Interest = Final Amount - Principal
Interest = $8,954.24 - $5,000.00
Interest = $3,954.24

Your investment will grow to $8,954.24 over 10 years, earning $3,954.24 in compound interest.
--------------------------------------------------

Worker: math_expert
Specialty: mathematical calculations and analysis
Tokens: 287 total (89 in, 198 out)
```

### What Just Happened?

1. **Created a specialist**: Defined a math expert with specific capabilities
2. **Gave it a task**: Asked for a compound interest calculation
3. **Got detailed results**: Received step-by-step calculation
4. **Tracked usage**: Monitored token consumption

## Creating Specialized Workers

Now let's create workers for different domains.

### Code Review Specialist

```php
$codeReviewer = new WorkerAgent($client, [
    'name' => 'code_reviewer',
    'specialty' => 'code review, security analysis, and best practices',
    'system' => 'You are a senior software engineer with 15+ years experience. ' .
                'Review code for bugs, security vulnerabilities, performance issues, ' .
                'and violations of best practices. Provide specific, actionable feedback ' .
                'with code examples where helpful.',
    'max_tokens' => 3000,  // More tokens for detailed reviews
]);

// Use the reviewer
$code = <<<'PHP'
function login($username, $password) {
    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = mysql_query($query);
    return mysql_fetch_array($result);
}
PHP;

$result = $codeReviewer->run("Review this PHP login function:\n\n{$code}");

if ($result->isSuccess()) {
    echo "Code Review:\n";
    echo $result->getAnswer() . "\n";
}
```

### Writing Specialist

```php
$contentWriter = new WorkerAgent($client, [
    'name' => 'content_writer',
    'specialty' => 'engaging writing, storytelling, and content creation',
    'system' => 'You are a professional content writer with expertise in creating ' .
                'engaging, well-structured content. Use storytelling techniques, ' .
                'vivid language, and clear structure to connect with readers.',
]);

$result = $contentWriter->run(
    'Write a compelling opening paragraph for a blog post about ' .
    'the benefits of morning routines for productivity.'
);

if ($result->isSuccess()) {
    echo "Opening Paragraph:\n";
    echo $result->getAnswer() . "\n";
}
```

### Data Analysis Specialist

```php
$dataAnalyst = new WorkerAgent($client, [
    'name' => 'data_analyst',
    'specialty' => 'data analysis, statistics, and insights extraction',
    'system' => 'You are a data analyst. Analyze datasets, calculate statistics, ' .
                'identify trends and patterns, and provide data-driven insights. ' .
                'Always show your calculations and reasoning.',
]);

$data = "Sales: Jan=$45K, Feb=$52K, Mar=$48K, Apr=$61K, May=$58K, Jun=$67K";

$result = $dataAnalyst->run(
    "Analyze this sales data and provide insights:\n{$data}"
);

if ($result->isSuccess()) {
    echo "Analysis:\n";
    echo $result->getAnswer() . "\n";
}
```

### Research Specialist

```php
$researcher = new WorkerAgent($client, [
    'name' => 'researcher',
    'specialty' => 'research, fact-finding, and information synthesis',
    'system' => 'You are a research specialist. Synthesize information from your ' .
                'knowledge base, provide well-structured findings, and explain complex ' .
                'topics clearly. Always cite the basis of your knowledge when relevant.',
]);

$result = $researcher->run(
    'Explain the differences between microservices and monolithic architecture, ' .
    'including when to use each approach.'
);

if ($result->isSuccess()) {
    echo "Research Findings:\n";
    echo $result->getAnswer() . "\n";
}
```

## Using Workers Standalone

### Single Task Execution

```php
// Create a specialist
$sqlOptimizer = new WorkerAgent($client, [
    'name' => 'sql_optimizer',
    'specialty' => 'SQL query optimization and database performance',
    'system' => 'You are a database expert. Optimize SQL queries, suggest indexes, ' .
                'and improve query performance. Explain your recommendations.',
]);

// Execute a task
$query = "SELECT * FROM orders o, customers c WHERE o.customer_id = c.id AND o.date > '2024-01-01'";

$result = $sqlOptimizer->run("Optimize this SQL query:\n{$query}");

if ($result->isSuccess()) {
    echo $result->getAnswer() . "\n";
}
```

### Sequential Tasks

Use the same worker for multiple related tasks:

```php
$testWriter = new WorkerAgent($client, [
    'name' => 'test_writer',
    'specialty' => 'unit testing and test case design',
    'system' => 'You are a testing expert. Write comprehensive unit tests using PHPUnit.',
]);

$functions = [
    'function add($a, $b) { return $a + $b; }',
    'function subtract($a, $b) { return $a - $b; }',
    'function multiply($a, $b) { return $a * $b; }',
];

foreach ($functions as $function) {
    $result = $testWriter->run("Write PHPUnit tests for:\n{$function}");
    
    if ($result->isSuccess()) {
        echo "Tests:\n{$result->getAnswer()}\n\n";
    }
}
```

### Monitoring Performance

Track worker performance across tasks:

```php
$worker = new WorkerAgent($client, [
    'name' => 'analyzer',
    'specialty' => 'data analysis',
]);

$tasks = ['Task 1', 'Task 2', 'Task 3'];
$totalTokens = 0;
$totalTime = 0;

foreach ($tasks as $task) {
    $startTime = microtime(true);
    
    $result = $worker->run($task);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    if ($result->isSuccess()) {
        $usage = $result->getTokenUsage();
        $totalTokens += $usage['total'];
        $totalTime += $duration;
        
        echo "Task: {$task}\n";
        echo "  Tokens: {$usage['total']}\n";
        echo "  Time: " . number_format($duration, 2) . "s\n\n";
    }
}

echo "Total tokens: {$totalTokens}\n";
echo "Total time: " . number_format($totalTime, 2) . "s\n";
echo "Average tokens/task: " . round($totalTokens / count($tasks)) . "\n";
```

## Workers in Hierarchical Systems

Workers truly shine when coordinated by a HierarchicalAgent.

### Building a Multi-Specialist System

```php
use ClaudeAgents\Agents\HierarchicalAgent;

// Create specialized workers
$researcher = new WorkerAgent($client, [
    'name' => 'researcher',
    'specialty' => 'research and fact-finding',
    'system' => 'You are a research specialist. Find and synthesize information.',
]);

$analyst = new WorkerAgent($client, [
    'name' => 'analyst',
    'specialty' => 'data analysis and insights',
    'system' => 'You are a data analyst. Analyze data and provide insights.',
]);

$writer = new WorkerAgent($client, [
    'name' => 'writer',
    'specialty' => 'content creation and writing',
    'system' => 'You are a writer. Create clear, engaging content.',
]);

// Create master coordinator
$master = new HierarchicalAgent($client, [
    'name' => 'master_coordinator',
]);

// Register workers
$master->registerWorker('researcher', $researcher);
$master->registerWorker('analyst', $analyst);
$master->registerWorker('writer', $writer);

// Complex task requiring multiple specialists
$result = $master->run(
    'Research current trends in AI development, analyze their impact on ' .
    'the software industry, and write a comprehensive blog post about it.'
);

if ($result->isSuccess()) {
    echo $result->getAnswer() . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "Workers used: " . implode(', ', $metadata['workers_used']) . "\n";
    echo "Subtasks: {$metadata['subtasks']}\n";
}
```

### Code Review System

```php
// Create a team of code reviewers
$securityReviewer = new WorkerAgent($client, [
    'name' => 'security_expert',
    'specialty' => 'security vulnerabilities and secure coding practices',
    'system' => 'You are a security expert. Find security issues like SQL injection, ' .
                'XSS, CSRF, authentication problems, and data exposure.',
]);

$performanceReviewer = new WorkerAgent($client, [
    'name' => 'performance_expert',
    'specialty' => 'performance optimization and efficient algorithms',
    'system' => 'You are a performance expert. Identify bottlenecks, inefficient ' .
                'algorithms, memory issues, and scalability problems.',
]);

$qualityReviewer = new WorkerAgent($client, [
    'name' => 'quality_expert',
    'specialty' => 'code quality, maintainability, and best practices',
    'system' => 'You are a code quality expert. Review for clean code principles, ' .
                'SOLID principles, design patterns, and maintainability.',
]);

// Create review coordinator
$codeReviewMaster = new HierarchicalAgent($client, [
    'name' => 'code_review_coordinator',
]);

$codeReviewMaster->registerWorker('security_expert', $securityReviewer);
$codeReviewMaster->registerWorker('performance_expert', $performanceReviewer);
$codeReviewMaster->registerWorker('quality_expert', $qualityReviewer);

// Review code with all specialists
$code = file_get_contents('path/to/code.php');

$result = $codeReviewMaster->run(
    "Comprehensively review this code for security, performance, and quality:\n\n{$code}"
);

if ($result->isSuccess()) {
    echo "COMPREHENSIVE CODE REVIEW\n";
    echo str_repeat("=", 80) . "\n\n";
    echo $result->getAnswer() . "\n\n";
    
    $metadata = $result->getMetadata();
    echo "Reviewers: " . implode(', ', $metadata['workers_used']) . "\n";
}
```

### Content Creation Pipeline

```php
// Build a content creation team
$researchWorker = new WorkerAgent($client, [
    'name' => 'researcher',
    'specialty' => 'topic research and fact-checking',
    'system' => 'Research topics thoroughly. Find key facts, statistics, and sources.',
]);

$seoWorker = new WorkerAgent($client, [
    'name' => 'seo_specialist',
    'specialty' => 'SEO optimization and keyword strategy',
    'system' => 'Optimize content for search engines. Identify keywords and structure.',
]);

$writerWorker = new WorkerAgent($client, [
    'name' => 'content_writer',
    'specialty' => 'engaging writing and storytelling',
    'system' => 'Write compelling content using storytelling and clear structure.',
]);

$editorWorker = new WorkerAgent($client, [
    'name' => 'editor',
    'specialty' => 'editing, proofreading, and style',
    'system' => 'Edit for clarity, grammar, flow, and consistent style.',
]);

// Create content pipeline master
$contentMaster = new HierarchicalAgent($client, [
    'name' => 'content_pipeline',
]);

$contentMaster->registerWorker('researcher', $researchWorker);
$contentMaster->registerWorker('seo_specialist', $seoWorker);
$contentMaster->registerWorker('content_writer', $writerWorker);
$contentMaster->registerWorker('editor', $editorWorker);

// Create a blog post
$result = $contentMaster->run(
    'Create a blog post about "Best Practices for API Design" ' .
    'including research, SEO optimization, engaging writing, and final editing.'
);

if ($result->isSuccess()) {
    // Save the blog post
    file_put_contents('blog_post.md', $result->getAnswer());
    echo "Blog post created and saved!\n";
}
```

## Building Production Teams

### Worker Factory Pattern

Create a reusable factory for common workers:

```php
class WorkerFactory
{
    private ClaudePhp $client;
    private array $config;
    
    public function __construct(ClaudePhp $client, array $config = [])
    {
        $this->client = $client;
        $this->config = $config;
    }
    
    public function createSecurityReviewer(): WorkerAgent
    {
        return new WorkerAgent($this->client, [
            'name' => 'security_reviewer',
            'specialty' => 'security analysis and vulnerability detection',
            'system' => 'You are a security expert. Find vulnerabilities and ' .
                        'suggest specific fixes with code examples.',
            'model' => $this->config['security_model'] ?? 'claude-sonnet-4-5',
            'max_tokens' => $this->config['security_tokens'] ?? 3000,
        ]);
    }
    
    public function createPerformanceOptimizer(): WorkerAgent
    {
        return new WorkerAgent($this->client, [
            'name' => 'performance_optimizer',
            'specialty' => 'performance optimization and scalability',
            'system' => 'You are a performance expert. Identify bottlenecks and ' .
                        'suggest specific optimizations.',
            'model' => $this->config['performance_model'] ?? 'claude-sonnet-4-5',
            'max_tokens' => $this->config['performance_tokens'] ?? 2000,
        ]);
    }
    
    public function createContentWriter(string $tone = 'professional'): WorkerAgent
    {
        $tones = [
            'professional' => 'Write in a professional, formal tone suitable for business.',
            'casual' => 'Write in a casual, friendly tone that feels conversational.',
            'technical' => 'Write in a technical, precise tone for expert audiences.',
            'educational' => 'Write in an educational tone that teaches and explains clearly.',
        ];
        
        return new WorkerAgent($this->client, [
            'name' => "writer_{$tone}",
            'specialty' => 'content writing and communication',
            'system' => "You are a professional writer. {$tones[$tone]}",
            'model' => $this->config['writer_model'] ?? 'claude-sonnet-4-5',
            'max_tokens' => $this->config['writer_tokens'] ?? 2048,
        ]);
    }
    
    public function createDataAnalyst(): WorkerAgent
    {
        return new WorkerAgent($this->client, [
            'name' => 'data_analyst',
            'specialty' => 'data analysis, statistics, and visualization',
            'system' => 'You are a data analyst. Analyze data, calculate statistics, ' .
                        'identify trends, and provide actionable insights.',
            'model' => $this->config['analyst_model'] ?? 'claude-sonnet-4-5',
            'max_tokens' => $this->config['analyst_tokens'] ?? 2048,
        ]);
    }
}

// Usage
$config = [
    'security_model' => 'claude-sonnet-4-5',
    'security_tokens' => 3000,
    'writer_model' => 'claude-haiku-3-5',  // Cheaper for simple writing
    'writer_tokens' => 1024,
];

$factory = new WorkerFactory($client, $config);

$securityReviewer = $factory->createSecurityReviewer();
$technicalWriter = $factory->createContentWriter('technical');
$analyst = $factory->createDataAnalyst();
```

### Configuration Management

Use configuration files for worker setup:

```php
// config/workers.php
return [
    'code_review' => [
        'security' => [
            'name' => 'security_expert',
            'specialty' => 'security vulnerabilities and secure coding',
            'system' => 'You are a security expert...',
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 3000,
        ],
        'performance' => [
            'name' => 'performance_expert',
            'specialty' => 'performance optimization',
            'system' => 'You are a performance expert...',
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 2000,
        ],
    ],
    'content' => [
        'writer' => [
            'name' => 'content_writer',
            'specialty' => 'content creation',
            'system' => 'You are a writer...',
            'model' => 'claude-haiku-3-5',
            'max_tokens' => 1024,
        ],
    ],
];

// Load and create workers
$workerConfigs = require 'config/workers.php';

function createWorkerFromConfig(ClaudePhp $client, array $config): WorkerAgent
{
    return new WorkerAgent($client, $config);
}

$securityExpert = createWorkerFromConfig($client, $workerConfigs['code_review']['security']);
$contentWriter = createWorkerFromConfig($client, $workerConfigs['content']['writer']);
```

### Worker Registry

Manage a collection of workers:

```php
class WorkerRegistry
{
    private array $workers = [];
    
    public function register(string $key, WorkerAgent $worker): void
    {
        $this->workers[$key] = $worker;
    }
    
    public function get(string $key): ?WorkerAgent
    {
        return $this->workers[$key] ?? null;
    }
    
    public function has(string $key): bool
    {
        return isset($this->workers[$key]);
    }
    
    public function all(): array
    {
        return $this->workers;
    }
    
    public function listSpecialties(): array
    {
        $specialties = [];
        
        foreach ($this->workers as $key => $worker) {
            $specialties[$key] = $worker->getSpecialty();
        }
        
        return $specialties;
    }
}

// Usage
$registry = new WorkerRegistry();
$registry->register('security', $securityWorker);
$registry->register('writer', $contentWriter);
$registry->register('analyst', $dataAnalyst);

// List available specialists
foreach ($registry->listSpecialties() as $key => $specialty) {
    echo "{$key}: {$specialty}\n";
}

// Get and use a worker
$worker = $registry->get('security');
if ($worker) {
    $result = $worker->run('Review this code...');
}
```

## Performance Optimization

### Token Management

Monitor and optimize token usage:

```php
class TokenTracker
{
    private array $usage = [];
    
    public function track(string $workerName, array $tokenUsage): void
    {
        if (!isset($this->usage[$workerName])) {
            $this->usage[$workerName] = [
                'calls' => 0,
                'total_tokens' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
            ];
        }
        
        $this->usage[$workerName]['calls']++;
        $this->usage[$workerName]['total_tokens'] += $tokenUsage['total'];
        $this->usage[$workerName]['input_tokens'] += $tokenUsage['input'];
        $this->usage[$workerName]['output_tokens'] += $tokenUsage['output'];
    }
    
    public function getUsage(string $workerName): ?array
    {
        return $this->usage[$workerName] ?? null;
    }
    
    public function getTotalCost(string $model = 'claude-sonnet-4-5'): float
    {
        $rates = [
            'claude-sonnet-4-5' => ['input' => 0.003, 'output' => 0.015],
            'claude-haiku-3-5' => ['input' => 0.0008, 'output' => 0.004],
        ];
        
        $rate = $rates[$model] ?? $rates['claude-sonnet-4-5'];
        $totalCost = 0;
        
        foreach ($this->usage as $usage) {
            $totalCost += ($usage['input_tokens'] * $rate['input'] / 1000);
            $totalCost += ($usage['output_tokens'] * $rate['output'] / 1000);
        }
        
        return $totalCost;
    }
    
    public function report(): void
    {
        echo "Token Usage Report\n";
        echo str_repeat("=", 60) . "\n\n";
        
        foreach ($this->usage as $workerName => $usage) {
            echo "{$workerName}:\n";
            echo "  Calls: {$usage['calls']}\n";
            echo "  Total tokens: {$usage['total_tokens']}\n";
            echo "  Avg tokens/call: " . round($usage['total_tokens'] / $usage['calls']) . "\n";
            echo "  Input: {$usage['input_tokens']}, Output: {$usage['output_tokens']}\n\n";
        }
        
        echo "Estimated cost: $" . number_format($this->getTotalCost(), 4) . "\n";
    }
}

// Usage
$tracker = new TokenTracker();

$worker = new WorkerAgent($client, ['name' => 'analyst']);

$tasks = ['Task 1', 'Task 2', 'Task 3'];

foreach ($tasks as $task) {
    $result = $worker->run($task);
    
    if ($result->isSuccess()) {
        $tracker->track($worker->getName(), $result->getTokenUsage());
    }
}

$tracker->report();
```

### Model Selection Strategy

Use different models for different complexity levels:

```php
function createOptimizedWorker(
    ClaudePhp $client,
    string $name,
    string $specialty,
    string $complexity = 'medium'
): WorkerAgent {
    $configs = [
        'simple' => [
            'model' => 'claude-haiku-3-5',
            'max_tokens' => 512,
        ],
        'medium' => [
            'model' => 'claude-haiku-3-5',
            'max_tokens' => 1024,
        ],
        'complex' => [
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 2048,
        ],
        'advanced' => [
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 4096,
        ],
    ];
    
    $config = $configs[$complexity] ?? $configs['medium'];
    
    return new WorkerAgent($client, [
        'name' => $name,
        'specialty' => $specialty,
        'model' => $config['model'],
        'max_tokens' => $config['max_tokens'],
    ]);
}

// Create workers with appropriate complexity
$simpleFormatter = createOptimizedWorker(
    $client,
    'formatter',
    'text formatting',
    'simple'
);

$complexAnalyst = createOptimizedWorker(
    $client,
    'analyst',
    'complex data analysis',
    'advanced'
);
```

### Caching Results

Cache worker results for repeated tasks:

```php
class CachedWorker
{
    private WorkerAgent $worker;
    private array $cache = [];
    private int $ttl;
    
    public function __construct(WorkerAgent $worker, int $ttl = 3600)
    {
        $this->worker = $worker;
        $this->ttl = $ttl;
    }
    
    public function run(string $task): AgentResult
    {
        $cacheKey = md5($task);
        
        // Check cache
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            
            if (time() - $cached['timestamp'] < $this->ttl) {
                return $cached['result'];
            }
            
            // Expired, remove
            unset($this->cache[$cacheKey]);
        }
        
        // Execute and cache
        $result = $this->worker->run($task);
        
        if ($result->isSuccess()) {
            $this->cache[$cacheKey] = [
                'result' => $result,
                'timestamp' => time(),
            ];
        }
        
        return $result;
    }
    
    public function clearCache(): void
    {
        $this->cache = [];
    }
}

// Usage
$worker = new WorkerAgent($client, ['name' => 'analyst']);
$cachedWorker = new CachedWorker($worker, 3600); // 1 hour TTL

// First call hits API
$result1 = $cachedWorker->run('Analyze this data...');

// Second call uses cache
$result2 = $cachedWorker->run('Analyze this data...');
```

## Best Practices

### 1. Write Clear, Specific System Prompts

```php
// ❌ Bad: Too vague
$worker = new WorkerAgent($client, [
    'system' => 'You help with code.',
]);

// ✅ Good: Clear and specific
$worker = new WorkerAgent($client, [
    'system' => 'You are a senior PHP developer specializing in Laravel. ' .
                'Review code for bugs, security issues, and Laravel best practices. ' .
                'Provide specific fixes with code examples. Focus on PSR-12 standards ' .
                'and modern PHP 8+ features.',
]);
```

### 2. Use Descriptive Specialties

```php
// ❌ Bad: Too general
'specialty' => 'programming'

// ✅ Good: Specific and focused
'specialty' => 'React and TypeScript frontend development with focus on performance optimization and accessibility'
```

### 3. Match Token Limits to Task Complexity

```php
// Simple formatting tasks
$formatter = new WorkerAgent($client, [
    'max_tokens' => 256,
    'specialty' => 'JSON formatting and validation',
]);

// Complex analysis
$analyst = new WorkerAgent($client, [
    'max_tokens' => 4096,
    'specialty' => 'comprehensive system architecture review',
]);
```

### 4. Use Consistent Naming Conventions

```php
// ✅ Good naming pattern: role_domain
$workers = [
    'reviewer_security' => $securityReviewer,
    'reviewer_performance' => $performanceReviewer,
    'writer_technical' => $technicalWriter,
    'writer_marketing' => $marketingWriter,
    'analyst_data' => $dataAnalyst,
    'analyst_business' => $businessAnalyst,
];
```

### 5. Handle Errors Gracefully

```php
function runWithRetry(WorkerAgent $worker, string $task, int $maxRetries = 3): ?AgentResult
{
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $result = $worker->run($task);
        
        if ($result->isSuccess()) {
            return $result;
        }
        
        error_log("Attempt {$attempt} failed: {$result->getError()}");
        
        if ($attempt < $maxRetries) {
            sleep(2 ** $attempt); // Exponential backoff
        }
    }
    
    error_log("All {$maxRetries} attempts failed for worker: {$worker->getName()}");
    return null;
}

// Usage
$result = runWithRetry($worker, $task);

if ($result && $result->isSuccess()) {
    echo $result->getAnswer();
} else {
    echo "Worker failed after retries\n";
}
```

### 6. Validate Results

```php
function validateWorkerResult(AgentResult $result, array $expectations = []): bool
{
    if (!$result->isSuccess()) {
        return false;
    }
    
    $answer = $result->getAnswer();
    
    // Check minimum length
    if (isset($expectations['min_length'])) {
        if (strlen($answer) < $expectations['min_length']) {
            error_log("Answer too short: " . strlen($answer) . " chars");
            return false;
        }
    }
    
    // Check for required keywords
    if (isset($expectations['required_keywords'])) {
        foreach ($expectations['required_keywords'] as $keyword) {
            if (stripos($answer, $keyword) === false) {
                error_log("Missing required keyword: {$keyword}");
                return false;
            }
        }
    }
    
    return true;
}

// Usage
$result = $worker->run($task);

$valid = validateWorkerResult($result, [
    'min_length' => 100,
    'required_keywords' => ['analysis', 'recommendation'],
]);

if (!$valid) {
    // Handle invalid result
    echo "Result validation failed\n";
}
```

## Conclusion

You've learned how to build and use specialized WorkerAgent instances effectively. Key takeaways:

1. **Specialization**: Focus each worker on a specific domain or task type
2. **Configuration**: Customize prompts, models, and tokens for optimal results
3. **Standalone Use**: Workers can handle tasks independently
4. **Team Coordination**: Workers excel in hierarchical agent systems
5. **Production Ready**: Use factories, registries, and caching for scale
6. **Optimization**: Track tokens, choose models wisely, cache results

### Next Steps

- Create your own specialized workers for your domain
- Build a team of complementary specialists
- Combine workers in hierarchical systems
- Optimize for performance and cost
- Deploy to production with proper monitoring

### Additional Resources

- [WorkerAgent API Documentation](../WorkerAgent.md)
- [HierarchicalAgent Tutorial](HierarchicalAgent_Tutorial.md)
- [Example Code](../../examples/worker_agent.php)
- [Agent Selection Guide](../agent-selection-guide.md)

## Troubleshooting

### Issue: Poor Quality Results

**Solution**: Improve system prompt specificity:
```php
// Add more context and examples
'system' => 'You are an expert in X. When analyzing Y, consider Z. ' .
            'Format your response as: 1) Overview, 2) Details, 3) Recommendations.'
```

### Issue: Inconsistent Behavior

**Solution**: Make the specialty and system prompt more focused:
```php
// Too broad
'specialty' => 'software development'

// Better
'specialty' => 'Python backend API development using FastAPI and PostgreSQL'
```

### Issue: High Costs

**Solutions**:
- Use Haiku for simpler tasks
- Reduce max_tokens
- Implement caching
- Add "be concise" to system prompts

## Support

For questions or issues:
- GitHub Issues: [Report a bug](https://github.com/your-org/claude-php-agent/issues)
- Documentation: [Read the docs](../WorkerAgent.md)
- Examples: [View examples](../../examples/)

Happy building! 🚀

