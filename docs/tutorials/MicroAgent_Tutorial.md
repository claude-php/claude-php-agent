# MicroAgent Tutorial: Building Efficient Atomic Agents

## Introduction

This tutorial will guide you through building production-ready systems using MicroAgents. We'll start with basic concepts and progress to advanced patterns used in real-world multi-agent systems.

By the end of this tutorial, you'll be able to:

- Create and configure MicroAgents for different roles
- Execute atomic tasks with high consistency
- Implement retry logic for reliability
- Build multi-agent voting systems
- Create custom specialized agents
- Integrate MicroAgents into complex workflows

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and async patterns
- Familiarity with agent concepts (helpful but not required)

## Table of Contents

1. [Getting Started](#getting-started)
2. [Understanding Agent Roles](#understanding-agent-roles)
3. [Basic Task Execution](#basic-task-execution)
4. [Working with Different Roles](#working-with-different-roles)
5. [Custom System Prompts](#custom-system-prompts)
6. [Retry Logic and Reliability](#retry-logic-and-reliability)
7. [Building Multi-Agent Systems](#building-multi-agent-systems)
8. [Voting and Error Correction](#voting-and-error-correction)
9. [Integration with MAKER Framework](#integration-with-maker-framework)
10. [Production Best Practices](#production-best-practices)

## Getting Started

### Installation

First, ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to test the MicroAgent:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MicroAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create a micro-agent
$microAgent = new MicroAgent($client, [
    'role' => 'executor',
]);

echo "MicroAgent ready!\n";
```

**Verify it works:**

```bash
php your_script.php
# Output: MicroAgent ready!
```

## Understanding Agent Roles

MicroAgents are specialized for specific types of tasks. Understanding which role to use is crucial for optimal performance.

### The Five Roles

```php
// 1. EXECUTOR - Direct task execution (default)
$executor = new MicroAgent($client, ['role' => 'executor']);

// 2. DECOMPOSER - Breaking down complex tasks
$decomposer = new MicroAgent($client, ['role' => 'decomposer']);

// 3. COMPOSER - Synthesizing multiple results
$composer = new MicroAgent($client, ['role' => 'composer']);

// 4. VALIDATOR - Verifying correctness
$validator = new MicroAgent($client, ['role' => 'validator']);

// 5. DISCRIMINATOR - Choosing best option
$discriminator = new MicroAgent($client, ['role' => 'discriminator']);
```

### Role Selection Guide

| Task Type | Best Role | Example |
|-----------|-----------|---------|
| Simple calculation | Executor | "Calculate 15% tip on $67.43" |
| Breaking down process | Decomposer | "Plan a deployment process" |
| Combining results | Composer | "Synthesize these test results" |
| Checking correctness | Validator | "Verify this calculation" |
| Picking best option | Discriminator | "Choose the best approach" |

## Basic Task Execution

### Example 1: Simple Calculation

```php
<?php
use ClaudeAgents\Agents\MicroAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$executor = new MicroAgent($client, [
    'role' => 'executor',
    'temperature' => 0.1,  // Low for consistency
]);

$result = $executor->execute('Calculate 15% tip on $67.43');
echo "Tip amount: {$result}\n";
```

**Output:**
```
Tip amount: $10.11
```

### Example 2: Understanding Temperature

Temperature controls output randomness:

```php
// Low temperature = Consistent, deterministic
$consistent = new MicroAgent($client, ['temperature' => 0.1]);

// Test consistency
for ($i = 0; $i < 3; $i++) {
    echo $consistent->execute('What is 5 * 7?') . "\n";
}
// Output: 35, 35, 35 (same every time)

// High temperature = Creative, varied
$creative = new MicroAgent($client, ['temperature' => 0.9]);

for ($i = 0; $i < 3; $i++) {
    echo $creative->execute('Give me a random color') . "\n";
}
// Output: Blue, Crimson, Teal (different each time)
```

**Best Practice:** Use temperature 0.0-0.2 for calculations, validation, and deterministic tasks.

## Working with Different Roles

### Executor: Direct Execution

The executor is your go-to role for straightforward tasks.

```php
$executor = new MicroAgent($client, ['role' => 'executor']);

// Mathematical operations
$area = $executor->execute('Calculate the area of a circle with radius 10');
echo "Area: {$area}\n";

// Data transformations
$json = $executor->execute('Convert this CSV to JSON: name,age\nAlice,30\nBob,25');
echo "JSON: {$json}\n";

// Quick lookups
$answer = $executor->execute('What is the capital of France?');
echo "Capital: {$answer}\n";
```

### Decomposer: Task Breakdown

The decomposer excels at planning and breaking down complex processes.

```php
$decomposer = new MicroAgent($client, ['role' => 'decomposer']);

$complexTask = "Deploy a Laravel application to production on AWS";

$subtasks = $decomposer->execute(
    "Break this task into clear, minimal subtasks:\n{$complexTask}"
);

echo "Deployment Steps:\n";
echo $subtasks;
```

**Output:**
```
Deployment Steps:
1. Set up AWS account and configure IAM roles
2. Create and configure RDS database instance
3. Set up EC2 instance or Elastic Beanstalk environment
4. Configure security groups and networking
5. Install PHP, Composer, and required extensions
6. Clone repository and install dependencies
7. Configure environment variables (.env)
8. Run database migrations
9. Set up web server (Nginx/Apache)
10. Configure SSL certificate
11. Set up monitoring and logging
12. Test deployment
```

### Composer: Result Synthesis

The composer combines multiple results into coherent outputs.

```php
$composer = new MicroAgent($client, ['role' => 'composer']);

// Imagine these came from different agents/processes
$subtaskResults = [
    "Server health check: OK (CPU: 23%, Memory: 45%, Disk: 67%)",
    "Database status: Connected (10ms latency, 234 active connections)",
    "API response times: Average 145ms, P95: 320ms, P99: 890ms",
    "Error rate: 0.03% (12 errors in last 1000 requests)",
];

$report = $composer->execute(
    "Synthesize these monitoring results into a coherent system health report:\n" .
    implode("\n", $subtaskResults)
);

echo "System Health Report:\n";
echo $report;
```

**Output:**
```
System Health Report:

OVERALL STATUS: HEALTHY ✓

Infrastructure:
- Server resources are within normal operating parameters
- CPU and memory usage are comfortable
- Disk usage at 67% - monitor but not critical

Database:
- Connection stable with excellent latency (10ms)
- High activity with 234 concurrent connections

Performance:
- API response times are good (avg 145ms)
- 95th percentile acceptable at 320ms
- 99th percentile at 890ms suggests occasional slow queries

Reliability:
- Error rate of 0.03% is excellent
- Only 12 errors per 1000 requests

RECOMMENDATION: System is operating normally. Consider investigating P99 latency outliers.
```

### Validator: Result Verification

The validator checks correctness and verifies requirements.

```php
$validator = new MicroAgent($client, ['role' => 'validator']);

// Validate calculations
$calculation = "15% of $67.43 = $10.11";
$isValid = $validator->execute(
    "Validate this calculation. Respond with 'VALID' or 'INVALID' and explain:\n{$calculation}"
);
echo "Validation: {$isValid}\n\n";

// Validate code
$code = "filter_var(\$email, FILTER_VALIDATE_EMAIL)";
$codeCheck = $validator->execute(
    "Is this correct PHP email validation? {$code}"
);
echo "Code validation: {$codeCheck}\n\n";

// Validate logic
$logic = "If it's raining AND I have an umbrella, then I stay dry";
$logicCheck = $validator->execute(
    "Is this logical statement valid? {$logic}"
);
echo "Logic validation: {$logicCheck}\n";
```

### Discriminator: Option Selection

The discriminator evaluates alternatives and chooses the best one.

```php
$discriminator = new MicroAgent($client, ['role' => 'discriminator']);

$options = [
    "Option A: MySQL - Widely used, great documentation, proven at scale",
    "Option B: PostgreSQL - Advanced features, better JSON support, stricter standards",
    "Option C: MongoDB - NoSQL flexibility, horizontal scaling, document model",
];

$choice = $discriminator->execute(
    "We're building an e-commerce platform. Choose the best database:\n" .
    implode("\n", $options)
);

echo "Database Choice:\n{$choice}\n";
```

## Custom System Prompts

You can override the default system prompt for specialized behavior.

### Example 1: Domain Expert

```php
$sqlExpert = new MicroAgent($client, ['role' => 'executor']);

$sqlExpert->setSystemPrompt(
    'You are a SQL expert. Provide optimized, production-ready SQL queries with explanations.'
);

$query = $sqlExpert->execute(
    'Write a query to find the top 10 customers by total purchase amount in the last 30 days'
);

echo "SQL Query:\n{$query}\n";
```

### Example 2: Code Reviewer

```php
$reviewer = new MicroAgent($client, ['role' => 'validator']);

$reviewer->setSystemPrompt(
    'You are a senior code reviewer. Check for bugs, security issues, and best practices. Be concise.'
);

$codeToReview = <<<'PHP'
function authenticate($username, $password) {
    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}
PHP;

$review = $reviewer->execute("Review this PHP authentication function:\n{$codeToReview}");
echo "Code Review:\n{$review}\n";
```

**Expected Output:**
```
Code Review:

🚨 CRITICAL SECURITY ISSUES:

1. SQL Injection: User input is directly interpolated into query
   - FIX: Use prepared statements
   
2. Plain text password: Password appears to be stored/compared in plain text
   - FIX: Use password_hash() and password_verify()

3. Undefined variable: $conn is not in scope
   - FIX: Pass as parameter or use dependency injection

SECURE VERSION:
function authenticate($conn, $username, $password) {
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return password_verify($password, $row['password_hash']);
    }
    return false;
}
```

### Example 3: Specialized Format

```php
$jsonGenerator = new MicroAgent($client, ['role' => 'executor']);

$jsonGenerator->setSystemPrompt(
    'You are a JSON generator. Always respond with valid JSON only, no explanations.'
);

$json = $jsonGenerator->execute(
    'Generate a user profile with name, email, age, and interests (array)'
);

echo "Generated JSON:\n{$json}\n";

// Verify it's valid JSON
$decoded = json_decode($json, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ Valid JSON\n";
    print_r($decoded);
}
```

## Retry Logic and Reliability

MicroAgents include built-in retry logic with exponential backoff for production reliability.

### Basic Retry Usage

```php
$microAgent = new MicroAgent($client);

try {
    // Automatically retries up to 3 times with exponential backoff
    $result = $microAgent->executeWithRetry(
        prompt: 'Critical calculation that must succeed',
        maxRetries: 3
    );
    
    echo "Success: {$result}\n";
    
} catch (\Throwable $e) {
    // All attempts failed
    echo "Failed after all retries: {$e->getMessage()}\n";
}
```

### Understanding Backoff

The retry mechanism uses exponential backoff:

```php
// Retry schedule:
// Attempt 1: Immediate
// Attempt 2: Wait 100ms (0.1s)
// Attempt 3: Wait 200ms (0.2s)
// Attempt 4: Wait 400ms (0.4s)

$startTime = microtime(true);

$result = $microAgent->executeWithRetry('Task', maxRetries: 3);

$duration = microtime(true) - $startTime;
echo "Completed in: " . round($duration, 2) . "s\n";
```

### When to Use Retry

```php
// ✅ GOOD: Retry for critical calculations
$result = $microAgent->executeWithRetry('Calculate financial projection', 3);

// ✅ GOOD: Retry for validation checks
$isValid = $validator->executeWithRetry('Verify data integrity', 3);

// ⚠️ CAUTION: Don't retry operations with side effects
// ❌ BAD: Could send multiple emails
$result = $microAgent->execute('Send confirmation email');

// ✅ GOOD: Retry idempotent operations
$result = $microAgent->executeWithRetry('Generate report (idempotent)', 3);
```

### Logging Retries

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('micro_agent');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$microAgent = new MicroAgent($client, [
    'logger' => $logger,
]);

// You'll see retry attempts in logs
$result = $microAgent->executeWithRetry('Task', 3);
```

## Building Multi-Agent Systems

MicroAgents shine when combined into multi-agent workflows.

### Pattern 1: Pipeline

Process data through a series of specialized agents:

```php
function processPipeline($data, $client) {
    // Step 1: Parse input
    $parser = new MicroAgent($client, ['role' => 'executor']);
    $parser->setSystemPrompt('Extract structured data from text');
    $parsed = $parser->execute("Parse: {$data}");
    
    // Step 2: Validate
    $validator = new MicroAgent($client, ['role' => 'validator']);
    $validation = $validator->execute("Validate: {$parsed}");
    
    if (strpos($validation, 'INVALID') !== false) {
        throw new \RuntimeException("Validation failed: {$validation}");
    }
    
    // Step 3: Transform
    $transformer = new MicroAgent($client, ['role' => 'executor']);
    $transformer->setSystemPrompt('Transform data to target format');
    $transformed = $transformer->execute("Transform to JSON: {$parsed}");
    
    return $transformed;
}

// Usage
$result = processPipeline("User: John Doe, Age: 30, Email: john@example.com", $client);
echo "Pipeline result: {$result}\n";
```

### Pattern 2: Parallel Processing

Execute multiple independent tasks simultaneously:

```php
function parallelAnalysis($data, $client) {
    $analyses = [];
    
    // Create specialized analyzers
    $analyzers = [
        'security' => 'Check for security vulnerabilities',
        'performance' => 'Identify performance issues',
        'quality' => 'Assess code quality and maintainability',
    ];
    
    // In production, you'd use async/parallel execution
    // Here we show the pattern sequentially
    foreach ($analyzers as $type => $instruction) {
        $agent = new MicroAgent($client, ['role' => 'executor']);
        $agent->setSystemPrompt("You are a {$type} expert. {$instruction}");
        $analyses[$type] = $agent->execute("Analyze:\n{$data}");
    }
    
    // Compose results
    $composer = new MicroAgent($client, ['role' => 'composer']);
    $report = $composer->execute(
        "Synthesize these analyses:\n" . print_r($analyses, true)
    );
    
    return $report;
}
```

### Pattern 3: Hierarchical Decomposition

Break down complex tasks recursively:

```php
function hierarchicalDecompose($task, $client, $depth = 0, $maxDepth = 3) {
    if ($depth >= $maxDepth) {
        // Execute as atomic task
        $executor = new MicroAgent($client, ['role' => 'executor']);
        return $executor->execute($task);
    }
    
    // Decompose
    $decomposer = new MicroAgent($client, ['role' => 'decomposer']);
    $subtasksText = $decomposer->execute("Break into subtasks: {$task}");
    
    // Check if already atomic
    if (strpos($subtasksText, 'ATOMIC_TASK') !== false) {
        $executor = new MicroAgent($client, ['role' => 'executor']);
        return $executor->execute($task);
    }
    
    // Parse subtasks (simplified)
    $subtasks = parseSubtasks($subtasksText);
    
    // Process each subtask recursively
    $results = [];
    foreach ($subtasks as $subtask) {
        $results[] = hierarchicalDecompose($subtask, $client, $depth + 1, $maxDepth);
    }
    
    // Compose results
    $composer = new MicroAgent($client, ['role' => 'composer']);
    return $composer->execute(
        "Original task: {$task}\nSubtask results:\n" . implode("\n", $results)
    );
}

function parseSubtasks($text) {
    $lines = explode("\n", $text);
    $subtasks = [];
    foreach ($lines as $line) {
        if (preg_match('/^\d+\.\s*(.+)$/', trim($line), $matches)) {
            $subtasks[] = $matches[1];
        }
    }
    return $subtasks;
}

// Usage
$result = hierarchicalDecompose(
    "Plan and execute a complete website redesign",
    $client
);
```

## Voting and Error Correction

Use multiple MicroAgents for error correction through voting.

### Basic Voting Pattern

```php
function voteOnAnswer($question, $client, $numVotes = 5) {
    $candidates = [];
    
    // Generate multiple answers
    for ($i = 0; $i < $numVotes; $i++) {
        $agent = new MicroAgent($client, [
            'role' => 'executor',
            'temperature' => 0.1,  // Low but not zero for slight variation
        ]);
        
        $answer = $agent->execute($question);
        $normalized = normalizeAnswer($answer);
        
        if (!isset($candidates[$normalized])) {
            $candidates[$normalized] = [
                'answer' => $answer,
                'votes' => 0,
            ];
        }
        
        $candidates[$normalized]['votes']++;
    }
    
    // Find winner
    uasort($candidates, fn($a, $b) => $b['votes'] <=> $a['votes']);
    $winner = reset($candidates);
    
    return [
        'answer' => $winner['answer'],
        'votes' => $winner['votes'],
        'total_candidates' => count($candidates),
    ];
}

function normalizeAnswer($answer) {
    // Normalize for comparison
    return md5(strtolower(trim(preg_replace('/\s+/', ' ', $answer))));
}

// Usage
$result = voteOnAnswer('What is 15% of $67.43?', $client, 5);
echo "Answer: {$result['answer']}\n";
echo "Votes: {$result['votes']}/5\n";
echo "Candidates: {$result['total_candidates']}\n";
```

### First-to-Ahead-by-K Voting

Implement the voting strategy from the MAKER framework:

```php
function firstToAheadByK($task, $client, $k = 3) {
    $candidates = [];
    $maxAttempts = 15;  // Safety limit
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        // Generate candidate
        $agent = new MicroAgent($client, ['role' => 'executor']);
        $answer = $agent->execute($task);
        $key = normalizeAnswer($answer);
        
        if (!isset($candidates[$key])) {
            $candidates[$key] = ['answer' => $answer, 'votes' => 0];
        }
        
        $candidates[$key]['votes']++;
        
        // Check for winner (ahead by K)
        if (count($candidates) >= 2) {
            $votes = array_column($candidates, 'votes');
            rsort($votes);
            
            if (($votes[0] - $votes[1]) >= $k) {
                // We have a winner!
                uasort($candidates, fn($a, $b) => $b['votes'] <=> $a['votes']);
                $winner = reset($candidates);
                
                return [
                    'answer' => $winner['answer'],
                    'votes' => $winner['votes'],
                    'attempts' => $attempt + 1,
                    'k' => $k,
                ];
            }
        }
        
        $attempt++;
    }
    
    // No clear winner, return most voted
    uasort($candidates, fn($a, $b) => $b['votes'] <=> $a['votes']);
    $winner = reset($candidates);
    
    return [
        'answer' => $winner['answer'],
        'votes' => $winner['votes'],
        'attempts' => $attempt,
        'k' => $k,
        'timeout' => true,
    ];
}

// Usage
$result = firstToAheadByK('Calculate compound interest: $1000 at 5% for 3 years', $client, 3);
echo "Answer: {$result['answer']}\n";
echo "Confidence: {$result['votes']} votes\n";
echo "Attempts: {$result['attempts']}\n";
```

## Integration with MAKER Framework

MicroAgents are designed to work seamlessly with the MakerAgent.

### Understanding MAKER

MAKER = **M**assively **A**gentic decomposition + **K**-voting **E**rror correction + **R**ed-flagging

```php
use ClaudeAgents\Agents\MakerAgent;

// MakerAgent uses MicroAgents internally
$maker = new MakerAgent($client, [
    'voting_k' => 3,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 5,
]);

$result = $maker->run('Complex multi-step task requiring high reliability');
```

### How MAKER Uses MicroAgents

```php
// Internally, MakerAgent does:

// 1. Decompose with voting
$decomposer = new MicroAgent($client, ['role' => 'decomposer']);
// Vote on decomposition (2k-1 candidates)

// 2. Execute each subtask with voting
foreach ($subtasks as $subtask) {
    $executor = new MicroAgent($client, ['role' => 'executor']);
    // Vote on execution (2k-1 candidates)
}

// 3. Compose with voting
$composer = new MicroAgent($client, ['role' => 'composer']);
// Vote on composition (2k-1 candidates)
```

### When to Use MicroAgent vs MakerAgent

```php
// Use MicroAgent for:
// - Single, atomic tasks
// - Parts of a larger system you're building
// - Custom workflows
$microAgent = new MicroAgent($client);
$result = $microAgent->execute('Single focused task');

// Use MakerAgent for:
// - Complex multi-step tasks
// - Tasks requiring high reliability
// - Tasks where errors are costly
$makerAgent = new MakerAgent($client);
$result = $makerAgent->run('Complex task requiring millions of steps');
```

## Production Best Practices

### 1. Configure Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('micro_agent');
$logger->pushHandler(new RotatingFileHandler(
    'logs/micro_agent.log',
    7,  // Keep 7 days
    Logger::INFO
));

$microAgent = new MicroAgent($client, [
    'logger' => $logger,
]);
```

### 2. Use Appropriate Models

```php
// Fast tasks: Use Haiku
$fast = new MicroAgent($client, [
    'model' => 'claude-haiku-4',
    'role' => 'executor',
]);

// Complex tasks: Use Sonnet
$complex = new MicroAgent($client, [
    'model' => 'claude-sonnet-4-5',
    'role' => 'decomposer',
]);

// Critical tasks: Use Opus (when available)
$critical = new MicroAgent($client, [
    'model' => 'claude-opus-4',  // Future model
    'role' => 'validator',
]);
```

### 3. Optimize Token Usage

```php
// Short responses
$calculator = new MicroAgent($client, [
    'max_tokens' => 256,  // Saves costs
]);

// Long responses
$analyzer = new MicroAgent($client, [
    'max_tokens' => 4096,
]);
```

### 4. Implement Monitoring

```php
class MicroAgentMonitor {
    private $metrics = [];
    
    public function executeWithMetrics($agent, $task) {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            $result = $agent->execute($task);
            
            $this->metrics[] = [
                'duration' => microtime(true) - $startTime,
                'memory' => memory_get_usage() - $startMemory,
                'role' => $agent->getRole(),
                'success' => true,
            ];
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->metrics[] = [
                'duration' => microtime(true) - $startTime,
                'memory' => memory_get_usage() - $startMemory,
                'role' => $agent->getRole(),
                'success' => false,
                'error' => $e->getMessage(),
            ];
            
            throw $e;
        }
    }
    
    public function getAverageMetrics() {
        $count = count($this->metrics);
        if ($count === 0) return [];
        
        return [
            'avg_duration' => array_sum(array_column($this->metrics, 'duration')) / $count,
            'avg_memory' => array_sum(array_column($this->metrics, 'memory')) / $count,
            'success_rate' => count(array_filter($this->metrics, fn($m) => $m['success'])) / $count,
        ];
    }
}

// Usage
$monitor = new MicroAgentMonitor();
$result = $monitor->executeWithMetrics($microAgent, 'Task');
print_r($monitor->getAverageMetrics());
```

### 5. Error Handling Strategy

```php
class ResilientMicroAgent {
    private $client;
    private $logger;
    
    public function __construct($client, $logger) {
        $this->client = $client;
        $this->logger = $logger;
    }
    
    public function execute($task, $role = 'executor') {
        try {
            $agent = new MicroAgent($this->client, [
                'role' => $role,
                'logger' => $this->logger,
            ]);
            
            return $agent->executeWithRetry($task, 3);
            
        } catch (\ClaudePhp\Exceptions\RateLimitException $e) {
            $this->logger->warning('Rate limit hit, backing off');
            sleep(5);
            return $this->execute($task, $role);  // Retry after backoff
            
        } catch (\ClaudePhp\Exceptions\ApiException $e) {
            $this->logger->error('API error: ' . $e->getMessage());
            throw $e;
            
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error: ' . $e->getMessage());
            throw $e;
        }
    }
}
```

### 6. Caching Pattern

```php
class CachedMicroAgent {
    private $agent;
    private $cache = [];
    
    public function __construct($agent) {
        $this->agent = $agent;
    }
    
    public function execute($task) {
        $key = md5($task);
        
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        $result = $this->agent->execute($task);
        $this->cache[$key] = $result;
        
        return $result;
    }
}

// Usage: Cache deterministic computations
$agent = new MicroAgent($client, ['temperature' => 0.0]);
$cached = new CachedMicroAgent($agent);

// First call: Actual API call
$result1 = $cached->execute('What is 15% of $67.43?');

// Second call: Returns cached result
$result2 = $cached->execute('What is 15% of $67.43?');
```

## Complete Example: Production System

Here's a complete example combining everything we've learned:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MicroAgent;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ProductionAgentSystem {
    private $client;
    private $logger;
    
    public function __construct($apiKey) {
        $this->client = new ClaudePhp(apiKey: $apiKey);
        
        $this->logger = new Logger('agent_system');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    }
    
    public function processComplexTask($task) {
        $this->logger->info('Starting complex task', ['task' => substr($task, 0, 50)]);
        
        // Step 1: Decompose
        $subtasks = $this->decompose($task);
        
        // Step 2: Execute with voting
        $results = [];
        foreach ($subtasks as $i => $subtask) {
            $this->logger->info("Executing subtask {$i}");
            $results[] = $this->executeWithVoting($subtask);
        }
        
        // Step 3: Compose
        $final = $this->compose($task, $results);
        
        // Step 4: Validate
        $validation = $this->validate($final);
        
        if (strpos($validation, 'VALID') === false) {
            $this->logger->error('Validation failed', ['validation' => $validation]);
            throw new \RuntimeException('Result validation failed');
        }
        
        $this->logger->info('Task completed successfully');
        
        return $final;
    }
    
    private function decompose($task) {
        $decomposer = new MicroAgent($this->client, [
            'role' => 'decomposer',
            'logger' => $this->logger,
        ]);
        
        $result = $decomposer->executeWithRetry(
            "Break into 3-5 clear subtasks:\n{$task}",
            3
        );
        
        return $this->parseSubtasks($result);
    }
    
    private function executeWithVoting($task, $k = 2) {
        $candidates = [];
        $attempts = 0;
        $maxAttempts = 7;  // 2k + 3
        
        while ($attempts < $maxAttempts) {
            $agent = new MicroAgent($this->client, [
                'role' => 'executor',
                'logger' => $this->logger,
                'temperature' => 0.1,
            ]);
            
            $answer = $agent->execute($task);
            $key = md5(strtolower(trim($answer)));
            
            if (!isset($candidates[$key])) {
                $candidates[$key] = ['answer' => $answer, 'votes' => 0];
            }
            
            $candidates[$key]['votes']++;
            
            // Check for winner
            if (count($candidates) >= 2) {
                $votes = array_column($candidates, 'votes');
                rsort($votes);
                
                if (($votes[0] - $votes[1]) >= $k) {
                    uasort($candidates, fn($a, $b) => $b['votes'] <=> $a['votes']);
                    $winner = reset($candidates);
                    $this->logger->info('Voting complete', [
                        'attempts' => $attempts + 1,
                        'votes' => $winner['votes'],
                    ]);
                    return $winner['answer'];
                }
            }
            
            $attempts++;
        }
        
        // Return most voted
        uasort($candidates, fn($a, $b) => $b['votes'] <=> $a['votes']);
        $winner = reset($candidates);
        return $winner['answer'];
    }
    
    private function compose($originalTask, $results) {
        $composer = new MicroAgent($this->client, [
            'role' => 'composer',
            'logger' => $this->logger,
        ]);
        
        $prompt = "Original task: {$originalTask}\n\n" .
                  "Subtask results:\n" . implode("\n", $results);
        
        return $composer->executeWithRetry($prompt, 3);
    }
    
    private function validate($result) {
        $validator = new MicroAgent($this->client, [
            'role' => 'validator',
            'logger' => $this->logger,
        ]);
        
        return $validator->execute(
            "Validate this result is coherent and complete: {$result}"
        );
    }
    
    private function parseSubtasks($text) {
        $lines = explode("\n", $text);
        $subtasks = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^\d+\.\s*(.+)$/', trim($line), $matches)) {
                $subtasks[] = $matches[1];
            }
        }
        
        return $subtasks ?: [$text];
    }
}

// Usage
$apiKey = getenv('ANTHROPIC_API_KEY');
$system = new ProductionAgentSystem($apiKey);

try {
    $result = $system->processComplexTask(
        'Analyze the security implications of implementing OAuth2 in a PHP application'
    );
    
    echo "Final Result:\n";
    echo $result;
    
} catch (\Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
}
```

## Conclusion

You've learned how to:

- ✅ Create and configure MicroAgents for different roles
- ✅ Execute atomic tasks with high consistency
- ✅ Implement retry logic for reliability
- ✅ Build multi-agent voting systems
- ✅ Create custom specialized agents
- ✅ Integrate MicroAgents into complex workflows
- ✅ Apply production best practices

### Key Takeaways

1. **Choose the right role** for each task type
2. **Use low temperature** (0.1) for consistency
3. **Implement retry logic** for critical operations
4. **Combine agents** for complex workflows
5. **Use voting** for error correction
6. **Monitor and log** everything in production

### Next Steps

- Explore [MakerAgent](./MakerAgent_Tutorial.md) for complex multi-step tasks
- Read the [MAKER paper](https://arxiv.org/html/2511.09030v1)
- Check out [complete examples](../../examples/micro_agent_example.php)
- Build your own multi-agent system!

### Resources

- [MicroAgent Documentation](../MicroAgent.md)
- [MakerAgent Documentation](../MakerAgent.md)
- [Example Code](../../examples/micro_agent_example.php)
- [Test Suite](../../tests/Unit/MicroAgentTest.php)

Happy building! 🚀

