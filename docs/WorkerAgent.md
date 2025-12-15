# WorkerAgent Documentation

## Overview

The `WorkerAgent` is a specialized agent designed for specific domains or tasks as part of larger hierarchical agent systems. Each worker has a clear specialty and can be used either standalone for focused tasks or as a component in a master-worker (hierarchical) architecture.

## Features

- 🎯 **Specialized Expertise**: Each worker focuses on a specific domain or task type
- 🔧 **Configurable**: Customize system prompts, models, and token limits
- 📊 **Token Tracking**: Monitor API usage per worker
- 🔄 **Reusable**: Same worker can handle multiple tasks sequentially
- 🏗️ **Building Block**: Designed to work seamlessly with HierarchicalAgent
- 📝 **Metadata Rich**: Tracks worker name, specialty, and execution details

## Installation

The WorkerAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\WorkerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');

// Create a specialized worker
$mathWorker = new WorkerAgent($client, [
    'name' => 'math_expert',
    'specialty' => 'mathematical calculations and analysis',
    'system' => 'You are a mathematics expert. Provide precise calculations.',
]);

// Use the worker
$result = $mathWorker->run('Calculate the average of 15, 23, 27, 19, and 32');

if ($result->isSuccess()) {
    echo $result->getAnswer();
}
```

## Configuration

The WorkerAgent accepts the following configuration options:

```php
$worker = new WorkerAgent($client, [
    'name' => 'specialist_agent',       // Worker identifier
    'specialty' => 'specific domain',   // Description of expertise
    'system' => 'Custom system prompt', // Worker's system prompt
    'model' => 'claude-sonnet-4-5',     // Model to use
    'max_tokens' => 2048,               // Max tokens per response
]);
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'worker'` | Unique identifier for the worker |
| `specialty` | string | `'general tasks'` | Description of worker's expertise |
| `system` | string | Auto-generated | System prompt for the worker |
| `model` | string | `'claude-sonnet-4-5'` | Claude model to use |
| `max_tokens` | int | `2048` | Maximum tokens per response |

## Worker Specialties

### Creating Effective Specialists

The key to a good worker is a clear, focused specialty:

```php
// Good: Specific and focused
$worker = new WorkerAgent($client, [
    'specialty' => 'Python code security analysis and vulnerability detection',
]);

// Less effective: Too broad
$worker = new WorkerAgent($client, [
    'specialty' => 'general programming',
]);
```

### Example Specialties

#### Technical Domains

```php
// Code Review Specialist
$codeReviewer = new WorkerAgent($client, [
    'name' => 'code_reviewer',
    'specialty' => 'code review, security analysis, and best practices',
    'system' => 'You are a senior software engineer. Review code for bugs, security issues, performance problems, and best practices. Provide specific, actionable feedback.',
]);

// Database Optimization Specialist
$dbExpert = new WorkerAgent($client, [
    'name' => 'db_optimizer',
    'specialty' => 'SQL optimization and database performance',
    'system' => 'You are a database expert. Optimize queries, suggest indexes, and improve database performance.',
]);

// API Design Specialist
$apiDesigner = new WorkerAgent($client, [
    'name' => 'api_designer',
    'specialty' => 'RESTful API design and best practices',
    'system' => 'You are an API design expert. Design clean, RESTful APIs with proper documentation.',
]);
```

#### Content Creation

```php
// Technical Writer
$techWriter = new WorkerAgent($client, [
    'name' => 'tech_writer',
    'specialty' => 'technical documentation and explanation',
    'system' => 'You are a technical writer. Create clear, accurate documentation with proper terminology.',
]);

// Marketing Copywriter
$copywriter = new WorkerAgent($client, [
    'name' => 'copywriter',
    'specialty' => 'persuasive writing and marketing copy',
    'system' => 'You are a marketing copywriter. Write compelling, benefit-focused content that drives action.',
]);

// Content Editor
$editor = new WorkerAgent($client, [
    'name' => 'editor',
    'specialty' => 'editing, proofreading, and style',
    'system' => 'You are a professional editor. Edit for clarity, grammar, flow, and style.',
]);
```

#### Analysis and Research

```php
// Data Analyst
$dataAnalyst = new WorkerAgent($client, [
    'name' => 'data_analyst',
    'specialty' => 'data analysis, statistics, and visualization',
    'system' => 'You are a data analyst. Analyze datasets, calculate statistics, identify trends, and provide insights.',
]);

// Research Specialist
$researcher = new WorkerAgent($client, [
    'name' => 'researcher',
    'specialty' => 'research, fact-checking, and information synthesis',
    'system' => 'You are a research specialist. Find relevant information, verify facts, and synthesize findings.',
]);

// Competitive Analyst
$competitiveAnalyst = new WorkerAgent($client, [
    'name' => 'competitive_analyst',
    'specialty' => 'competitive intelligence and market analysis',
    'system' => 'You are a competitive analyst. Analyze competitors, identify trends, and recommend strategies.',
]);
```

## Usage Patterns

### Standalone Usage

Use workers independently for focused tasks:

```php
$mathWorker = new WorkerAgent($client, [
    'name' => 'calculator',
    'specialty' => 'mathematical calculations',
]);

// Single task
$result = $mathWorker->run('Calculate compound interest: $1000 at 5% for 10 years');

if ($result->isSuccess()) {
    echo "Answer: {$result->getAnswer()}\n";
    
    $metadata = $result->getMetadata();
    echo "Worker: {$metadata['worker']}\n";
    echo "Specialty: {$metadata['specialty']}\n";
}
```

### Sequential Tasks

Same worker can handle multiple related tasks:

```php
$codeReviewer = new WorkerAgent($client, [
    'specialty' => 'code review and security',
]);

$tasks = [
    'Review this authentication function',
    'Check this database query for SQL injection',
    'Analyze this API endpoint for security issues',
];

foreach ($tasks as $task) {
    $result = $codeReviewer->run($task);
    
    if ($result->isSuccess()) {
        echo "Task: {$task}\n";
        echo "Review: {$result->getAnswer()}\n\n";
    }
}
```

### Hierarchical Systems

Workers shine when coordinated by a HierarchicalAgent:

```php
use ClaudeAgents\Agents\HierarchicalAgent;

// Create specialized workers
$mathWorker = new WorkerAgent($client, [
    'name' => 'math_expert',
    'specialty' => 'mathematical calculations',
]);

$writingWorker = new WorkerAgent($client, [
    'name' => 'writer',
    'specialty' => 'content creation',
]);

// Create master coordinator
$master = new HierarchicalAgent($client);
$master->registerWorker('math_expert', $mathWorker);
$master->registerWorker('writer', $writingWorker);

// Complex task requiring multiple specialists
$result = $master->run(
    'Calculate the average of 45, 67, 89, and 123, then write a brief ' .
    'explanation of what an average represents and why it\'s useful.'
);
```

## Result Handling

### Success Results

```php
$result = $worker->run($task);

if ($result->isSuccess()) {
    // Get the answer
    $answer = $result->getAnswer();
    
    // Get execution metadata
    $metadata = $result->getMetadata();
    $workerName = $metadata['worker'];
    $specialty = $metadata['specialty'];
    
    // Get token usage
    $usage = $result->getTokenUsage();
    echo "Input tokens: {$usage['input']}\n";
    echo "Output tokens: {$usage['output']}\n";
    echo "Total tokens: {$usage['total']}\n";
    
    // Get iterations (always 1 for WorkerAgent)
    $iterations = $result->getIterations();
    
    // Get messages
    $messages = $result->getMessages();
}
```

### Error Handling

```php
$result = $worker->run($task);

if (!$result->isSuccess()) {
    $error = $result->getError();
    echo "Worker failed: {$error}\n";
    
    // Check metadata even on failure
    $metadata = $result->getMetadata();
    $workerName = $metadata['worker'] ?? 'unknown';
    echo "Failed worker: {$workerName}\n";
}
```

## Advanced Usage

### Custom Model Selection

Different workers can use different models:

```php
// Complex analysis uses Sonnet
$complexWorker = new WorkerAgent($client, [
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 4096,
    'specialty' => 'complex problem solving',
]);

// Simple tasks use Haiku (faster, cheaper)
$simpleWorker = new WorkerAgent($client, [
    'model' => 'claude-haiku-3-5',
    'max_tokens' => 1024,
    'specialty' => 'simple formatting tasks',
]);
```

### Token Optimization

Control token usage per worker:

```php
$efficientWorker = new WorkerAgent($client, [
    'max_tokens' => 512,  // Limit response length
    'system' => 'Provide concise, focused answers. No unnecessary details.',
]);

$result = $efficientWorker->run($task);
$usage = $result->getTokenUsage();

// Calculate cost (example for Sonnet)
$cost = ($usage['input'] * 0.003 / 1000) + ($usage['output'] * 0.015 / 1000);
echo "Cost: $" . number_format($cost, 4) . "\n";
```

### Worker Factory Pattern

Create workers programmatically:

```php
class WorkerFactory
{
    private ClaudePhp $client;
    
    public function __construct(ClaudePhp $client)
    {
        $this->client = $client;
    }
    
    public function createCodeReviewer(): WorkerAgent
    {
        return new WorkerAgent($this->client, [
            'name' => 'code_reviewer',
            'specialty' => 'code review and security',
            'system' => 'You are a senior engineer. Review code for issues.',
            'model' => 'claude-sonnet-4-5',
        ]);
    }
    
    public function createWriter(string $style = 'professional'): WorkerAgent
    {
        $prompts = [
            'professional' => 'Write in a professional, formal tone.',
            'casual' => 'Write in a casual, friendly tone.',
            'technical' => 'Write in a technical, precise tone.',
        ];
        
        return new WorkerAgent($this->client, [
            'name' => "writer_{$style}",
            'specialty' => 'content writing',
            'system' => "You are a writer. {$prompts[$style]}",
        ]);
    }
}

// Usage
$factory = new WorkerFactory($client);
$reviewer = $factory->createCodeReviewer();
$writer = $factory->createWriter('technical');
```

### Multiple Workers for A/B Testing

Compare different approaches:

```php
$approaches = [
    'conservative' => new WorkerAgent($client, [
        'system' => 'Be conservative. Focus on safety and reliability.',
    ]),
    'innovative' => new WorkerAgent($client, [
        'system' => 'Be innovative. Suggest cutting-edge solutions.',
    ]),
    'balanced' => new WorkerAgent($client, [
        'system' => 'Balance innovation with practicality.',
    ]),
];

$task = 'How should we architect our new microservices system?';

foreach ($approaches as $approach => $worker) {
    $result = $worker->run($task);
    echo "\n{$approach} approach:\n";
    echo $result->getAnswer() . "\n";
}
```

## Best Practices

### 1. Clear Specialties

Make specialties specific and descriptive:

```php
// ✅ Good
'specialty' => 'React and Vue.js frontend development with TypeScript'

// ❌ Too vague
'specialty' => 'programming'
```

### 2. Focused System Prompts

Provide clear instructions:

```php
// ✅ Good
'system' => 'You are a financial analyst specializing in risk assessment. ' .
            'Analyze data for risks, calculate risk scores, and provide ' .
            'actionable mitigation strategies.'

// ❌ Too generic
'system' => 'You help with finance stuff.'
```

### 3. Appropriate Token Limits

Match token limits to task complexity:

```php
// Simple tasks
$simpleWorker = new WorkerAgent($client, ['max_tokens' => 512]);

// Complex analysis
$complexWorker = new WorkerAgent($client, ['max_tokens' => 4096]);
```

### 4. Descriptive Names

Use names that reflect the worker's role:

```php
// ✅ Good names
'name' => 'security_auditor'
'name' => 'sql_optimizer'
'name' => 'content_editor'

// ❌ Poor names
'name' => 'worker1'
'name' => 'agent'
```

### 5. Consistent Model Selection

Choose models based on task requirements:

```php
// Complex reasoning: Sonnet
$strategist = new WorkerAgent($client, [
    'model' => 'claude-sonnet-4-5',
]);

// Simple tasks: Haiku
$formatter = new WorkerAgent($client, [
    'model' => 'claude-haiku-3-5',
]);
```

## Use Cases

### Software Development

```php
// Code Review Team
$securityReviewer = new WorkerAgent($client, [
    'specialty' => 'security vulnerabilities and secure coding',
]);

$performanceReviewer = new WorkerAgent($client, [
    'specialty' => 'performance optimization and algorithms',
]);

$qualityReviewer = new WorkerAgent($client, [
    'specialty' => 'code quality and maintainability',
]);
```

### Content Creation

```php
// Editorial Team
$researcher = new WorkerAgent($client, [
    'specialty' => 'research and fact-checking',
]);

$writer = new WorkerAgent($client, [
    'specialty' => 'creative writing and storytelling',
]);

$seoExpert = new WorkerAgent($client, [
    'specialty' => 'SEO optimization and keywords',
]);

$editor = new WorkerAgent($client, [
    'specialty' => 'editing and proofreading',
]);
```

### Business Analysis

```php
// Analysis Team
$marketAnalyst = new WorkerAgent($client, [
    'specialty' => 'market trends and consumer behavior',
]);

$financialAnalyst = new WorkerAgent($client, [
    'specialty' => 'financial analysis and projections',
]);

$strategist = new WorkerAgent($client, [
    'specialty' => 'business strategy and recommendations',
]);
```

## Performance Considerations

### Token Usage

Monitor and optimize token consumption:

```php
$result = $worker->run($task);
$usage = $result->getTokenUsage();

// Log usage
error_log("Worker {$worker->getName()} used {$usage['total']} tokens");

// Track costs
$inputCost = $usage['input'] * 0.003 / 1000;   // Sonnet rates
$outputCost = $usage['output'] * 0.015 / 1000;
$totalCost = $inputCost + $outputCost;
```

### Response Time

Typical response times:

```
Simple tasks (Haiku):    1-2 seconds
Complex tasks (Sonnet):  3-5 seconds
Large outputs:           5-10 seconds
```

### Cost Optimization

```php
// Use Haiku for simple workers
$cheapWorker = new WorkerAgent($client, [
    'model' => 'claude-haiku-3-5',
    'max_tokens' => 1024,
]);

// Reserve Sonnet for complex reasoning
$expensiveWorker = new WorkerAgent($client, [
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 4096,
]);
```

## API Reference

### WorkerAgent Class

```php
class WorkerAgent implements AgentInterface
{
    /**
     * Create a new worker agent.
     */
    public function __construct(ClaudePhp $client, array $options = []);
    
    /**
     * Run a task through this worker.
     */
    public function run(string $task): AgentResult;
    
    /**
     * Get the worker's name.
     */
    public function getName(): string;
    
    /**
     * Get the worker's specialty description.
     */
    public function getSpecialty(): string;
}
```

### Constructor Options

```php
[
    'name' => string,           // Worker identifier
    'specialty' => string,      // Domain expertise description
    'system' => string,         // System prompt
    'model' => string,          // Claude model name
    'max_tokens' => int,        // Maximum response tokens
]
```

### AgentResult Methods

```php
$result = $worker->run($task);

// Check success
$result->isSuccess(): bool

// Get results
$result->getAnswer(): string
$result->getMessages(): array
$result->getIterations(): int
$result->getMetadata(): array
$result->getTokenUsage(): array

// Get error (if failed)
$result->getError(): string
```

## Examples

See the [examples/worker_agent.php](../examples/worker_agent.php) file for comprehensive working examples including:

1. Math Specialist Worker
2. Writing Specialist Worker
3. Code Analysis Specialist
4. Research Specialist
5. Multiple Workers Comparison

## Integration with Other Agents

### With HierarchicalAgent

Workers are designed to work seamlessly with HierarchicalAgent:

```php
$master = new HierarchicalAgent($client);

// Register multiple workers
$master->registerWorker('analyst', $dataAnalyst);
$master->registerWorker('writer', $contentWriter);
$master->registerWorker('reviewer', $codeReviewer);

// Master coordinates workers automatically
$result = $master->run('Complex multi-domain task');
```

See [HierarchicalAgent Documentation](HierarchicalAgent.md) for details.

## Troubleshooting

### Issue: Generic or Poor Results

**Solution**: Improve the system prompt and specialty description:

```php
// Before
$worker = new WorkerAgent($client, [
    'specialty' => 'writing',
    'system' => 'You write stuff.',
]);

// After
$worker = new WorkerAgent($client, [
    'specialty' => 'technical documentation for API endpoints',
    'system' => 'You are a technical writer specializing in API documentation. ' .
                'Write clear, accurate documentation with examples. Include request/' .
                'response formats, error codes, and usage examples.',
]);
```

### Issue: High Token Usage

**Solutions**:
- Reduce `max_tokens`
- Use more concise system prompts
- Switch to Haiku for simpler tasks
- Add "be concise" to system prompt

### Issue: Slow Response Times

**Solutions**:
- Use Haiku instead of Sonnet
- Reduce `max_tokens`
- Simplify the task
- Consider caching common requests

## Further Reading

- [Tutorial: Building with WorkerAgent](tutorials/WorkerAgent_Tutorial.md)
- [HierarchicalAgent Documentation](HierarchicalAgent.md)
- [Agent Selection Guide](agent-selection-guide.md)
- [Example Code](../examples/worker_agent.php)

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/your-org/claude-php-agent).

