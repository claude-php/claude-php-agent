# MicroAgent Documentation

## Overview

The `MicroAgent` is a lightweight, specialized agent designed for single-purpose tasks within the MAKER framework. It serves as the atomic unit of complex multi-agent systems, providing focused, consistent, and efficient execution of specific subtasks.

MicroAgents are the building blocks that enable the Massively Decomposed Agentic Processes (MDAP) approach, which can solve tasks requiring millions of steps with near-zero error rates through extreme decomposition and parallel voting.

## Features

- 🎯 **Single Responsibility**: Each agent has one specific role and does it well
- 🔄 **Specialized Roles**: Five distinct roles for different task types
- 📊 **High Consistency**: Low temperature (0.1) for deterministic outputs
- 🔁 **Retry Logic**: Built-in exponential backoff for reliability
- ⚡ **Lightweight**: Minimal overhead for fast execution
- 🔧 **Customizable**: Custom system prompts and configuration options
- 📝 **PSR-3 Logging**: Full observability and debugging support

## Installation

The MicroAgent is included in the `claude-php-agent` package. Ensure you have the package installed:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\MicroAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$microAgent = new MicroAgent($client, [
    'role' => 'executor',
]);

// Execute a simple task
$result = $microAgent->execute('Calculate 15% tip on $67.43');
echo $result; // "$10.11"
```

## Agent Roles

MicroAgents support five specialized roles, each optimized for specific task types:

### 1. Executor (Default)

The executor role is designed for direct task execution with precise, concise responses.

```php
$executor = new MicroAgent($client, [
    'role' => 'executor',
]);

$result = $executor->execute('Calculate the area of a circle with radius 5');
```

**Best For:**
- Direct calculations
- Simple transformations
- Atomic operations
- Quick lookups

**System Prompt:**
> "You are a focused executor. Execute tasks precisely and concisely."

### 2. Decomposer

The decomposer role breaks complex tasks into minimal, clear subtasks.

```php
$decomposer = new MicroAgent($client, [
    'role' => 'decomposer',
]);

$subtasks = $decomposer->execute(
    'Break down the task of deploying a web application into subtasks'
);
```

**Best For:**
- Task planning
- Breaking down complex processes
- Creating step-by-step procedures
- Identifying dependencies

**System Prompt:**
> "You are a precise task decomposer. Break tasks into minimal, clear subtasks."

### 3. Composer

The composer role synthesizes multiple subtask results into coherent final answers.

```php
$composer = new MicroAgent($client, [
    'role' => 'composer',
]);

$result = $composer->execute(
    "Combine these results: \n1. Server started\n2. Database connected\n3. Tests passed"
);
```

**Best For:**
- Result aggregation
- Summary generation
- Report composition
- Multi-source synthesis

**System Prompt:**
> "You are a result composer. Synthesize subtask results coherently."

### 4. Validator

The validator role verifies that results meet requirements and are correct.

```php
$validator = new MicroAgent($client, [
    'role' => 'validator',
]);

$isValid = $validator->execute(
    'Validate this calculation: 15% of $67.43 = $10.11'
);
```

**Best For:**
- Result verification
- Correctness checking
- Requirement validation
- Quality assurance

**System Prompt:**
> "You are a validator. Verify that results meet requirements."

### 5. Discriminator

The discriminator role chooses the best solution from multiple alternatives.

```php
$discriminator = new MicroAgent($client, [
    'role' => 'discriminator',
]);

$bestOption = $discriminator->execute(
    "Choose between: A) Fast but expensive, B) Slow but cheap, C) Balanced"
);
```

**Best For:**
- Option selection
- Trade-off evaluation
- Voting systems
- Decision making

**System Prompt:**
> "You are a discriminator. Choose the best solution from alternatives."

## Configuration

The MicroAgent accepts configuration options in its constructor:

```php
$microAgent = new MicroAgent($client, [
    'role' => 'executor',           // Agent role
    'model' => 'claude-sonnet-4-5', // Claude model
    'max_tokens' => 2048,            // Max response tokens
    'temperature' => 0.1,            // Sampling temperature
    'logger' => $logger,             // PSR-3 logger
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `role` | string | `'executor'` | Agent role (decomposer, executor, composer, validator, discriminator) |
| `model` | string | `'claude-sonnet-4-5'` | Claude model to use |
| `max_tokens` | int | `2048` | Maximum tokens per response |
| `temperature` | float | `0.1` | Sampling temperature (0.0-1.0) |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Advanced Features

### Custom System Prompts

You can override the default system prompt for specialized behavior:

```php
$microAgent = new MicroAgent($client, [
    'role' => 'executor',
]);

$microAgent->setSystemPrompt(
    'You are a code reviewer. Analyze code for bugs and suggest improvements.'
);

$review = $microAgent->execute($codeToReview);
```

### Retry Logic with Exponential Backoff

For critical tasks, use the built-in retry mechanism:

```php
$microAgent = new MicroAgent($client);

try {
    // Retries up to 3 times with exponential backoff: 0.1s, 0.2s, 0.4s
    $result = $microAgent->executeWithRetry(
        prompt: 'Critical calculation task',
        maxRetries: 3
    );
} catch (\Throwable $e) {
    echo "All retry attempts failed: {$e->getMessage()}";
}
```

**Backoff Schedule:**
- Attempt 1: Immediate
- Attempt 2: 100ms delay
- Attempt 3: 200ms delay
- Attempt 4: 400ms delay

### Getting Agent Information

```php
$microAgent = new MicroAgent($client, [
    'role' => 'validator',
]);

$role = $microAgent->getRole(); // "validator"
```

## Use Cases

### 1. Task Decomposition in Multi-Step Processes

```php
$decomposer = new MicroAgent($client, ['role' => 'decomposer']);
$subtasks = $decomposer->execute('Plan a database migration');

// Process each subtask with executor agents
foreach (parseSubtasks($subtasks) as $task) {
    $executor = new MicroAgent($client, ['role' => 'executor']);
    $result = $executor->execute($task);
}
```

### 2. Parallel Processing with Voting

```php
// Generate multiple candidates in parallel
$candidates = [];
for ($i = 0; $i < 5; $i++) {
    $agent = new MicroAgent($client, ['role' => 'executor']);
    $candidates[] = $agent->execute('Solve this problem: ...');
}

// Vote on best answer
$discriminator = new MicroAgent($client, ['role' => 'discriminator']);
$bestAnswer = $discriminator->execute(
    'Choose the best solution: ' . implode("\n", $candidates)
);
```

### 3. Result Validation Pipeline

```php
$executor = new MicroAgent($client, ['role' => 'executor']);
$result = $executor->execute('Calculate compound interest...');

$validator = new MicroAgent($client, ['role' => 'validator']);
$isValid = $validator->execute("Verify this calculation: {$result}");

if (strpos($isValid, 'VALID') !== false) {
    echo "Result validated: {$result}";
}
```

### 4. Multi-Agent Result Synthesis

```php
$subtaskResults = [
    $agent1->execute('Analyze performance'),
    $agent2->execute('Check security'),
    $agent3->execute('Review code quality'),
];

$composer = new MicroAgent($client, ['role' => 'composer']);
$finalReport = $composer->execute(
    'Synthesize these analysis results: ' . implode("\n", $subtaskResults)
);
```

## Integration with MAKER Framework

MicroAgents are the foundation of the MAKER (Massively Decomposed Agentic Processes) framework. The `MakerAgent` orchestrates multiple MicroAgents to solve complex tasks with near-zero error rates.

```php
use ClaudeAgents\Agents\MakerAgent;

$makerAgent = new MakerAgent($client, [
    'voting_k' => 3,              // First-to-ahead-by-3 voting
    'enable_red_flagging' => true, // Detect uncertain responses
]);

// Internally, MakerAgent creates and coordinates multiple MicroAgents
$result = $makerAgent->run('Complex multi-step task...');
```

**Key MAKER Components:**
- **Decomposer MicroAgents**: Break tasks into subtasks with voting
- **Executor MicroAgents**: Execute atomic subtasks in parallel
- **Validator MicroAgents**: Verify results at each step
- **Composer MicroAgents**: Synthesize subtask results
- **Discriminator MicroAgents**: Choose winning answers from votes

## Performance Considerations

### Temperature Settings

MicroAgents default to **temperature 0.1** for maximum consistency:

```php
// High consistency (default)
$agent = new MicroAgent($client, ['temperature' => 0.1]);

// For creative tasks, increase temperature
$agent = new MicroAgent($client, ['temperature' => 0.7]);
```

**Recommended Temperatures:**
- **0.0-0.2**: Calculations, validation, deterministic tasks
- **0.3-0.5**: Balanced creativity and consistency
- **0.6-1.0**: Creative writing, brainstorming

### Token Limits

Adjust `max_tokens` based on expected response length:

```php
// Short responses (calculations, yes/no)
$agent = new MicroAgent($client, ['max_tokens' => 512]);

// Medium responses (explanations)
$agent = new MicroAgent($client, ['max_tokens' => 2048]);

// Long responses (detailed analysis)
$agent = new MicroAgent($client, ['max_tokens' => 4096]);
```

### Parallel Execution

For maximum performance, execute multiple MicroAgents in parallel using async libraries:

```php
// Sequential (slow)
$results = [];
foreach ($tasks as $task) {
    $agent = new MicroAgent($client);
    $results[] = $agent->execute($task);
}

// Parallel (fast) - pseudocode with async library
$promises = [];
foreach ($tasks as $task) {
    $agent = new MicroAgent($client);
    $promises[] = async($agent->execute($task));
}
$results = await_all($promises);
```

## Logging and Debugging

Enable logging to monitor MicroAgent behavior:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('micro_agent');
$logger->pushHandler(new StreamHandler('path/to/micro_agent.log', Logger::DEBUG));

$microAgent = new MicroAgent($client, [
    'role' => 'executor',
    'logger' => $logger,
]);

$result = $microAgent->execute('Task');
```

**Log Events:**
- Agent creation with configuration
- Task execution start
- Response received with token usage
- Errors and retry attempts
- Execution time metrics

## Error Handling

```php
$microAgent = new MicroAgent($client);

try {
    $result = $microAgent->execute('Task');
} catch (\ClaudePhp\Exceptions\ApiException $e) {
    // API errors (rate limits, invalid keys, etc.)
    echo "API Error: {$e->getMessage()}";
} catch (\ClaudePhp\Exceptions\NetworkException $e) {
    // Network connectivity issues
    echo "Network Error: {$e->getMessage()}";
} catch (\Throwable $e) {
    // Other errors
    echo "Error: {$e->getMessage()}";
}
```

## Best Practices

### 1. Choose the Right Role

Match the role to your task type:

```php
// ✅ Good: Right role for the task
$decomposer = new MicroAgent($client, ['role' => 'decomposer']);
$subtasks = $decomposer->execute('Break down deployment process');

// ❌ Bad: Wrong role
$executor = new MicroAgent($client, ['role' => 'executor']);
$subtasks = $executor->execute('Break down deployment process');
```

### 2. Keep Tasks Atomic

MicroAgents work best with focused, single-purpose tasks:

```php
// ✅ Good: Atomic task
$result = $microAgent->execute('Calculate 15% tip on $67.43');

// ❌ Bad: Too complex for a single MicroAgent
$result = $microAgent->execute(
    'Plan a party, calculate costs, send invitations, and order supplies'
);
```

### 3. Use Retry for Critical Operations

```php
// ✅ Good: Retry critical calculations
$result = $microAgent->executeWithRetry('Critical calculation', 3);

// ⚠️ Caution: Don't retry idempotent operations that could cause duplicates
$result = $microAgent->execute('Send email notification');
```

### 4. Leverage Low Temperature

```php
// ✅ Good: Low temperature for consistency
$calculator = new MicroAgent($client, [
    'role' => 'executor',
    'temperature' => 0.1,
]);

// The same input will produce nearly identical output
for ($i = 0; $i < 10; $i++) {
    $result = $calculator->execute('15% of $67.43');
    // All results will be very similar
}
```

### 5. Combine with Other Agents

```php
// ✅ Good: Use specialized agents together
$decomposer = new MicroAgent($client, ['role' => 'decomposer']);
$subtasks = $decomposer->execute($complexTask);

foreach (parseSubtasks($subtasks) as $subtask) {
    $executor = new MicroAgent($client, ['role' => 'executor']);
    $results[] = $executor->execute($subtask);
}

$composer = new MicroAgent($client, ['role' => 'composer']);
$finalResult = $composer->execute('Combine: ' . implode("\n", $results));
```

## Examples

### Example 1: Simple Calculation

```php
$executor = new MicroAgent($client, ['role' => 'executor']);
$tip = $executor->execute('Calculate 18% tip on $123.45');
echo $tip; // "$22.22"
```

### Example 2: Task Decomposition

```php
$decomposer = new MicroAgent($client, ['role' => 'decomposer']);
$plan = $decomposer->execute(
    'Break down the process of deploying a Laravel application to AWS'
);
echo $plan;
// 1. Set up AWS account and configure IAM
// 2. Create RDS database instance
// 3. Set up EC2 instance or use Elastic Beanstalk
// ...
```

### Example 3: Result Validation

```php
$validator = new MicroAgent($client, ['role' => 'validator']);
$isValid = $validator->execute(
    'Validate: The square root of 144 is 12'
);
echo $isValid; // "VALID - The calculation is correct"
```

### Example 4: Option Selection

```php
$discriminator = new MicroAgent($client, ['role' => 'discriminator']);
$choice = $discriminator->execute(
    "Choose the best approach:\n" .
    "A) Microservices - Complex but scalable\n" .
    "B) Monolith - Simple but harder to scale\n" .
    "C) Modular monolith - Balanced approach"
);
echo $choice; // "Option C is best because..."
```

### Example 5: Custom System Prompt

```php
$expert = new MicroAgent($client, ['role' => 'executor']);
$expert->setSystemPrompt(
    'You are a PHP expert. Provide concise, production-ready code examples.'
);

$code = $expert->execute('Show me how to validate an email in PHP');
echo $code;
// filter_var($email, FILTER_VALIDATE_EMAIL) !== false
```

## Related Components

- **[MakerAgent](../src/Agents/MakerAgent.php)**: Orchestrates multiple MicroAgents with voting
- **[AgentInterface](../src/Contracts/AgentInterface.php)**: Base interface for all agents
- **[AgentResult](../src/AgentResult.php)**: Standardized result container

## Further Reading

- [MicroAgent Tutorial](./tutorials/MicroAgent_Tutorial.md)
- [MAKER Framework Paper](https://arxiv.org/html/2511.09030v1)
- [Complete Example](../examples/micro_agent_example.php)
- [MakerAgent Documentation](./MakerAgent.md)

## API Reference

### Constructor

```php
public function __construct(ClaudePhp $client, array $options = [])
```

**Parameters:**
- `$client` (ClaudePhp): The Claude API client
- `$options` (array): Configuration options

**Options:**
- `role` (string): Agent role - 'decomposer', 'executor', 'composer', 'validator', 'discriminator'
- `model` (string): Claude model name
- `max_tokens` (int): Maximum tokens per response
- `temperature` (float): Sampling temperature (0.0-1.0)
- `logger` (LoggerInterface): PSR-3 logger

### Methods

#### execute(string $prompt): string

Execute the micro-agent's task.

**Parameters:**
- `$prompt` (string): The task to execute

**Returns:**
- `string`: The agent's response

**Throws:**
- `\Throwable`: On execution failure

#### executeWithRetry(string $prompt, int $maxRetries = 3): string

Execute with retry logic and exponential backoff.

**Parameters:**
- `$prompt` (string): The task to execute
- `$maxRetries` (int): Maximum retry attempts

**Returns:**
- `string`: The agent's response

**Throws:**
- `\Throwable`: If all retry attempts fail

#### getRole(): string

Get the micro-agent's role.

**Returns:**
- `string`: The agent's role

#### setSystemPrompt(string $prompt): self

Set a custom system prompt.

**Parameters:**
- `$prompt` (string): The custom system prompt

**Returns:**
- `self`: The agent instance for method chaining

## Troubleshooting

### Issue: Inconsistent Results

**Problem:** Getting different answers for the same task

**Solution:** Lower the temperature
```php
$agent = new MicroAgent($client, ['temperature' => 0.0]);
```

### Issue: Responses Too Short

**Problem:** Agent responses are cut off

**Solution:** Increase max_tokens
```php
$agent = new MicroAgent($client, ['max_tokens' => 4096]);
```

### Issue: Rate Limiting

**Problem:** Hitting API rate limits

**Solution:** Add retry logic and delays
```php
$result = $agent->executeWithRetry($task, 5);
```

### Issue: Wrong Role Behavior

**Problem:** Agent not performing task correctly

**Solution:** Verify you're using the appropriate role
```php
// For breaking down tasks
$agent = new MicroAgent($client, ['role' => 'decomposer']);

// For executing tasks
$agent = new MicroAgent($client, ['role' => 'executor']);
```

## License

This component is part of the claude-php-agent package and follows the same license.

## Support

For issues, questions, or contributions, please refer to the main project repository.

