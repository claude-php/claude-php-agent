# ReflexAgent Tutorial: Building Rule-Based Intelligent Systems

## Introduction

This tutorial will guide you through building production-ready rule-based agents using the ReflexAgent. We'll start with basic concepts and progress to advanced patterns used in real-world applications.

By the end of this tutorial, you'll be able to:

- Create and manage condition-action rules
- Use different condition types (strings, regex, callables)
- Implement dynamic actions with custom logic
- Build priority-based rule systems
- Handle edge cases with LLM fallback
- Create stateful, context-aware agents
- Design production-ready reflex agents

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and regular expressions

## Table of Contents

1. [Getting Started](#getting-started)
2. [Understanding Reflex Agents](#understanding-reflex-agents)
3. [Your First Reflex Agent](#your-first-reflex-agent)
4. [Condition Types](#condition-types)
5. [Action Types](#action-types)
6. [Rule Priority System](#rule-priority-system)
7. [Dynamic Rule Management](#dynamic-rule-management)
8. [LLM Fallback Strategy](#llm-fallback-strategy)
9. [Building Real-World Applications](#building-real-world-applications)
10. [Production Best Practices](#production-best-practices)

## Getting Started

### Installation

First, ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to test the ReflexAgent:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ReflexAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the reflex agent
$agent = new ReflexAgent($client, [
    'name' => 'tutorial_agent',
]);

echo "Reflex agent ready!\n";
```

## Understanding Reflex Agents

### What is a Reflex Agent?

A reflex agent is the simplest type of AI agent. It operates on a straightforward principle:

```
IF condition THEN action
```

Think of it like a sophisticated if-else statement with optional AI fallback. It's fast, predictable, and perfect for well-defined scenarios.

### When to Use Reflex Agents

✅ **Good Use Cases:**
- FAQ bots and customer service automation
- Input validation and routing
- Simple command processing
- Rule-based classification
- Menu-driven interfaces

❌ **When NOT to Use:**
- Complex reasoning tasks
- Multi-step problem solving
- Tasks requiring context understanding
- Dynamic decision making

### Reflex Agent Architecture

```
Input → Rule Matching → Action → Output
         ↓ (no match)
         LLM Fallback (optional)
```

## Your First Reflex Agent

Let's build a simple greeting bot:

```php
use ClaudeAgents\Agents\ReflexAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$agent = new ReflexAgent($client);

// Add greeting rules
$agent->addRule(
    name: 'hello',
    condition: 'hello',
    action: 'Hello! How can I help you today?'
);

$agent->addRule(
    name: 'goodbye',
    condition: 'bye',
    action: 'Goodbye! Have a great day!'
);

// Test the agent
$result = $agent->run('hello there');

if ($result->isSuccess()) {
    echo $result->getAnswer(); // "Hello! How can I help you today?"
}
```

### Understanding the Result

```php
$result = $agent->run('hello');

// Check if successful
if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    // Get metadata
    $metadata = $result->getMetadata();
    echo "Rule matched: {$metadata['rule_matched']}\n";
    echo "Priority: {$metadata['priority']}\n";
} else {
    echo "Error: {$result->getError()}\n";
}
```

## Condition Types

ReflexAgent supports three types of conditions:

### 1. String Conditions (Substring Match)

Simple case-insensitive substring matching:

```php
$agent->addRule('help', 'help', 'How can I help you?');

// These all match:
$agent->run('help');           // Exact match
$agent->run('I need help');    // Contains "help"
$agent->run('HELP ME');        // Case insensitive
```

**Best for:** Simple keyword detection

### 2. Regex Conditions

Pattern matching using regular expressions:

```php
// Match email addresses
$agent->addRule(
    'email',
    '/\b[\w\.-]+@[\w\.-]+\.\w{2,}\b/',
    'I found an email address!'
);

$agent->run('Contact me at john@example.com'); // Matches

// Match phone numbers
$agent->addRule(
    'phone',
    '/\b\d{3}-\d{3}-\d{4}\b/',
    'Phone number detected!'
);

$agent->run('Call me at 555-123-4567'); // Matches

// Match commands
$agent->addRule(
    'deploy_command',
    '/^deploy\s+(prod|staging|dev)$/i',
    'Deploying to environment...'
);
```

**Best for:** Pattern matching, structured data extraction

### 3. Callable Conditions

Custom logic using functions:

```php
// Length-based condition
$agent->addRule(
    'long_message',
    fn(string $input) => strlen($input) > 100,
    'That\'s a long message!'
);

// Complex validation
$agent->addRule(
    'valid_json',
    function(string $input) {
        json_decode($input);
        return json_last_error() === JSON_ERROR_NONE;
    },
    'Valid JSON received!'
);

// Time-based condition
$agent->addRule(
    'business_hours',
    function(string $input) {
        $hour = (int)date('H');
        return $hour >= 9 && $hour < 17;
    },
    'We\'re currently open!'
);
```

**Best for:** Complex logic, external checks, dynamic conditions

## Action Types

Actions determine what happens when a rule matches:

### 1. String Actions

Return a fixed response:

```php
$agent->addRule('hello', 'hello', 'Hello! Welcome!');
```

### 2. Callable Actions

Generate dynamic responses:

```php
// Extract and use information
$agent->addRule(
    'greet_by_name',
    '/my name is (\w+)/i',
    function(string $input) {
        preg_match('/my name is (\w+)/i', $input, $matches);
        $name = $matches[1];
        return "Nice to meet you, {$name}!";
    }
);

// Perform calculations
$agent->addRule(
    'calculate',
    '/(\d+)\s*\+\s*(\d+)/',
    function(string $input) {
        preg_match('/(\d+)\s*\+\s*(\d+)/', $input, $matches);
        $result = (int)$matches[1] + (int)$matches[2];
        return "The answer is: {$result}";
    }
);

// Call external services
$agent->addRule(
    'check_stock',
    '/stock (\w+)/i',
    function(string $input) {
        preg_match('/stock (\w+)/i', $input, $matches);
        $symbol = $matches[1];
        $price = getStockPrice($symbol); // External API
        return "{$symbol} is currently at ${$price}";
    }
);
```

### 3. Stateful Actions

Use closures to maintain state:

```php
$visitCount = 0;

$agent->addRule(
    'track_visits',
    'visit',
    function($input) use (&$visitCount) {
        $visitCount++;
        return "This is visit #{$visitCount}";
    }
);

$agent->run('visit'); // "This is visit #1"
$agent->run('visit'); // "This is visit #2"
```

## Rule Priority System

Rules are evaluated in priority order (highest first):

### Basic Priority

```php
// High priority rule
$agent->addRule('urgent', 'help', 'URGENT HELP!', priority: 10);

// Low priority rule
$agent->addRule('normal', 'help', 'Normal help', priority: 5);

$agent->run('help'); // Returns "URGENT HELP!"
```

### Overlapping Rules

```php
// Specific match (high priority)
$agent->addRule(
    'urgent_help',
    '/urgent.*help/i',
    'URGENT: Connecting to emergency support...',
    priority: 10
);

// General match (medium priority)
$agent->addRule(
    'help',
    'help',
    'How can I help you?',
    priority: 5
);

// Catch-all (low priority)
$agent->addRule(
    'default',
    fn() => true,
    'I didn\'t understand that.',
    priority: 1
);

$agent->run('urgent help needed');  // Matches urgent_help (priority 10)
$agent->run('help please');         // Matches help (priority 5)
$agent->run('random text');         // Matches default (priority 1)
```

### Priority Strategy

```php
// Priority ranges for different rule types
const PRIORITY_CRITICAL   = 100; // Emergency/critical patterns
const PRIORITY_HIGH       = 50;  // Specific patterns
const PRIORITY_NORMAL     = 25;  // General patterns
const PRIORITY_LOW        = 10;  // Broad patterns
const PRIORITY_FALLBACK   = 1;   // Catch-all rules

$agent->addRule('emergency', '/911|emergency/i', '🚨 Emergency!', PRIORITY_CRITICAL);
$agent->addRule('specific', '/order.*status/i', 'Checking order...', PRIORITY_HIGH);
$agent->addRule('general', 'order', 'Order inquiry', PRIORITY_NORMAL);
$agent->addRule('broad', 'help', 'Help menu', PRIORITY_LOW);
$agent->addRule('catchall', fn() => true, 'Default', PRIORITY_FALLBACK);
```

## Dynamic Rule Management

Manage rules at runtime:

### Adding Rules

```php
// Add single rule
$agent->addRule('new_rule', 'test', 'Response');

// Add multiple rules
$agent->addRules([
    ['name' => 'rule1', 'condition' => 'test1', 'action' => 'Response 1', 'priority' => 10],
    ['name' => 'rule2', 'condition' => 'test2', 'action' => 'Response 2', 'priority' => 5],
]);
```

### Removing Rules

```php
// Remove specific rule
$removed = $agent->removeRule('old_rule');

if ($removed) {
    echo "Rule removed successfully\n";
}
```

### Clearing Rules

```php
// Remove all rules
$agent->clearRules();

// Add new set of rules
$agent->addRules($newRules);
```

### Inspecting Rules

```php
// Get all rules
$rules = $agent->getRules();

foreach ($rules as $rule) {
    echo "Name: {$rule['name']}\n";
    echo "Priority: {$rule['priority']}\n";
}

// Count rules
echo "Total rules: " . count($agent->getRules()) . "\n";
```

### Dynamic Rule Loading

```php
// Load rules from configuration
function loadRulesFromConfig(ReflexAgent $agent, string $configFile): void
{
    $config = json_decode(file_get_contents($configFile), true);
    
    foreach ($config['rules'] as $rule) {
        $agent->addRule(
            $rule['name'],
            $rule['condition'],
            $rule['action'],
            $rule['priority'] ?? 0
        );
    }
}

loadRulesFromConfig($agent, 'rules.json');
```

## LLM Fallback Strategy

Handle unmatched inputs with AI:

### Enabling Fallback

```php
// With fallback (default)
$agent = new ReflexAgent($client, [
    'use_llm_fallback' => true
]);

$agent->addRule('hello', 'hello', 'Hi!');

// No rule matches, uses Claude
$result = $agent->run('What is the weather?');
echo $result->getAnswer(); // AI-generated response

$metadata = $result->getMetadata();
if ($metadata['used_llm_fallback']) {
    echo "Used AI fallback\n";
}
```

### Disabling Fallback

```php
// Strict rule-based only
$agent = new ReflexAgent($client, [
    'use_llm_fallback' => false
]);

$agent->addRule('hello', 'hello', 'Hi!');

// No rule matches, returns error
$result = $agent->run('random text');
echo $result->getError(); // "No matching rule found for input"
```

### Fallback Strategy

```php
// Use fallback for customer service
$customerBot = new ReflexAgent($client, ['use_llm_fallback' => true]);

// Disable for strict validation
$validator = new ReflexAgent($client, ['use_llm_fallback' => false]);

// Conditional fallback
function createAgent(bool $allowFlexibility): ReflexAgent
{
    global $client;
    return new ReflexAgent($client, [
        'use_llm_fallback' => $allowFlexibility
    ]);
}
```

## Building Real-World Applications

### Application 1: Customer Service Bot

```php
$customerBot = new ReflexAgent($client, [
    'name' => 'customer_service',
    'use_llm_fallback' => true,
]);

// Greetings (high priority)
$customerBot->addRule(
    'greeting',
    '/^(hi|hello|hey)/i',
    'Hello! Welcome to our support. How can I help you?',
    priority: 10
);

// Order tracking
$customerBot->addRule(
    'track_order',
    '/order\s+([A-Z0-9-]+)/i',
    function($input) {
        preg_match('/order\s+([A-Z0-9-]+)/i', $input, $m);
        $orderId = $m[1];
        $status = getOrderStatus($orderId);
        return "Order {$orderId} status: {$status}";
    },
    priority: 15
);

// Returns
$customerBot->addRule(
    'returns',
    '/return|refund/i',
    'To start a return, visit our returns portal at returns.example.com',
    priority: 12
);

// Business hours
$customerBot->addRule(
    'hours',
    '/hours|open|close/i',
    'We\'re open Monday-Friday 9am-5pm EST',
    priority: 10
);

// Billing
$customerBot->addRule(
    'billing',
    '/billing|payment|charge/i',
    'For billing inquiries, email billing@example.com',
    priority: 10
);

// Farewell
$customerBot->addRule(
    'goodbye',
    '/bye|goodbye|thanks/i',
    'You\'re welcome! Have a great day!',
    priority: 8
);
```

### Application 2: Command Router

```php
$commandRouter = new ReflexAgent($client, [
    'name' => 'command_router',
    'use_llm_fallback' => false, // Strict command processing
]);

// Deploy command
$commandRouter->addRule(
    'deploy',
    '/^deploy\s+(prod|staging|dev)$/i',
    function($input) {
        preg_match('/^deploy\s+(\w+)$/i', $input, $m);
        $env = $m[1];
        // Trigger deployment
        return "Deploying to {$env}...";
    },
    priority: 10
);

// Status command
$commandRouter->addRule(
    'status',
    '/^status$/i',
    fn() => getSystemStatus(),
    priority: 10
);

// Restart command
$commandRouter->addRule(
    'restart',
    '/^restart\s+(\w+)$/i',
    function($input) {
        preg_match('/^restart\s+(\w+)$/i', $input, $m);
        $service = $m[1];
        restartService($service);
        return "Restarting {$service}...";
    },
    priority: 10
);

// Help command
$commandRouter->addRule(
    'help',
    '/^help$/i',
    "Available commands:\n" .
    "  deploy [prod|staging|dev]\n" .
    "  status\n" .
    "  restart [service]\n",
    priority: 5
);
```

### Application 3: Input Validator

```php
$validator = new ReflexAgent($client, [
    'name' => 'validator',
    'use_llm_fallback' => false,
]);

// Valid email
$validator->addRule(
    'valid_email',
    function($input) {
        return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
    },
    fn($input) => "✅ Valid email: {$input}",
    priority: 10
);

// Valid phone
$validator->addRule(
    'valid_phone',
    '/^\d{3}-\d{3}-\d{4}$/',
    '✅ Valid phone number',
    priority: 10
);

// Valid ZIP code
$validator->addRule(
    'valid_zip',
    '/^\d{5}(-\d{4})?$/',
    '✅ Valid ZIP code',
    priority: 10
);

// Invalid input (catch-all)
$validator->addRule(
    'invalid',
    fn() => true,
    '❌ Invalid input format',
    priority: 1
);
```

### Application 4: Smart FAQ Bot

```php
$faqBot = new ReflexAgent($client, [
    'name' => 'faq_bot',
    'use_llm_fallback' => true,
]);

// Product questions
$faqBot->addRules([
    [
        'name' => 'shipping',
        'condition' => '/shipping|deliver/i',
        'action' => 'We offer free shipping on orders over $50. Standard delivery is 3-5 business days.',
        'priority' => 10
    ],
    [
        'name' => 'returns',
        'condition' => '/return|refund/i',
        'action' => '30-day return policy. Items must be unused with tags attached.',
        'priority' => 10
    ],
    [
        'name' => 'warranty',
        'condition' => '/warranty|guarantee/i',
        'action' => 'All products come with a 1-year manufacturer warranty.',
        'priority' => 10
    ],
    [
        'name' => 'sizing',
        'condition' => '/size|sizing|fit/i',
        'action' => 'Check our size guide at example.com/size-guide. Unsure? Size up!',
        'priority' => 10
    ],
]);
```

### Application 5: Context-Aware Agent

```php
$state = [
    'stage' => 'init',
    'user_name' => null,
    'user_email' => null,
];

$contextAgent = new ReflexAgent($client, [
    'name' => 'context_agent',
    'use_llm_fallback' => false,
]);

// Initial greeting
$contextAgent->addRule(
    'start',
    fn($input) => $state['stage'] === 'init',
    function($input) use (&$state) {
        $state['stage'] = 'ask_name';
        return 'Welcome! What is your name?';
    },
    priority: 10
);

// Collect name
$contextAgent->addRule(
    'collect_name',
    fn($input) => $state['stage'] === 'ask_name',
    function($input) use (&$state) {
        $state['user_name'] = $input;
        $state['stage'] = 'ask_email';
        return "Thanks, {$input}! What's your email?";
    },
    priority: 10
);

// Collect email
$contextAgent->addRule(
    'collect_email',
    fn($input) => $state['stage'] === 'ask_email' && 
                   filter_var($input, FILTER_VALIDATE_EMAIL),
    function($input) use (&$state) {
        $state['user_email'] = $input;
        $state['stage'] = 'complete';
        return "Perfect! I have your info:\n" .
               "Name: {$state['user_name']}\n" .
               "Email: {$state['user_email']}";
    },
    priority: 10
);
```

## Production Best Practices

### 1. Error Handling

```php
try {
    $result = $agent->run($userInput);
    
    if ($result->isSuccess()) {
        return $result->getAnswer();
    } else {
        // Log error
        error_log("Agent error: " . $result->getError());
        return "Sorry, I couldn't process that request.";
    }
} catch (\Throwable $e) {
    error_log("Agent exception: " . $e->getMessage());
    return "An error occurred. Please try again.";
}
```

### 2. Input Sanitization

```php
function sanitizeInput(string $input): string
{
    // Trim whitespace
    $input = trim($input);
    
    // Limit length
    if (strlen($input) > 1000) {
        $input = substr($input, 0, 1000);
    }
    
    // Remove control characters
    $input = preg_replace('/[\x00-\x1F\x7F]/u', '', $input);
    
    return $input;
}

$cleanInput = sanitizeInput($userInput);
$result = $agent->run($cleanInput);
```

### 3. Logging

```php
use Psr\Log\LoggerInterface;

$agent = new ReflexAgent($client, [
    'name' => 'production_agent',
    'logger' => $logger, // PSR-3 logger
]);

// The agent will automatically log:
// - Rule matches
// - LLM fallback usage
// - Errors
```

### 4. Rate Limiting

```php
class RateLimitedReflexAgent
{
    private ReflexAgent $agent;
    private array $requestCounts = [];
    private int $maxRequests = 100;
    private int $timeWindow = 60; // seconds
    
    public function run(string $input, string $userId): AgentResult
    {
        if ($this->isRateLimited($userId)) {
            return AgentResult::failure(
                error: 'Rate limit exceeded. Please try again later.'
            );
        }
        
        $this->recordRequest($userId);
        return $this->agent->run($input);
    }
    
    private function isRateLimited(string $userId): bool
    {
        $now = time();
        $requests = $this->requestCounts[$userId] ?? [];
        
        // Remove old requests
        $requests = array_filter(
            $requests,
            fn($time) => $now - $time < $this->timeWindow
        );
        
        return count($requests) >= $this->maxRequests;
    }
    
    private function recordRequest(string $userId): void
    {
        $this->requestCounts[$userId][] = time();
    }
}
```

### 5. Monitoring

```php
class MonitoredReflexAgent
{
    private ReflexAgent $agent;
    private array $metrics = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'llm_fallback_count' => 0,
        'rule_match_counts' => [],
    ];
    
    public function run(string $input): AgentResult
    {
        $this->metrics['total_requests']++;
        
        $result = $this->agent->run($input);
        
        if ($result->isSuccess()) {
            $this->metrics['successful_requests']++;
            
            $metadata = $result->getMetadata();
            if (isset($metadata['rule_matched'])) {
                $rule = $metadata['rule_matched'];
                $this->metrics['rule_match_counts'][$rule] =
                    ($this->metrics['rule_match_counts'][$rule] ?? 0) + 1;
            }
            
            if ($metadata['used_llm_fallback'] ?? false) {
                $this->metrics['llm_fallback_count']++;
            }
        } else {
            $this->metrics['failed_requests']++;
        }
        
        return $result;
    }
    
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
```

### 6. Testing

```php
// Unit tests
class ReflexAgentTest extends TestCase
{
    public function testGreetingRule(): void
    {
        $client = $this->createMock(ClaudePhp::class);
        $agent = new ReflexAgent($client, ['use_llm_fallback' => false]);
        
        $agent->addRule('hello', 'hello', 'Hi there!');
        
        $result = $agent->run('hello world');
        
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hi there!', $result->getAnswer());
    }
}

// Integration tests
function testAgentWithRealAPI(): void
{
    $agent = new ReflexAgent($client);
    $agent->addRule('test', 'test', 'Test response');
    
    $result = $agent->run('test input');
    assert($result->isSuccess());
}
```

### 7. Performance Optimization

```php
// Cache regex compilations
class CachedReflexAgent
{
    private array $regexCache = [];
    
    private function isRegex(string $pattern): bool
    {
        if (!isset($this->regexCache[$pattern])) {
            $this->regexCache[$pattern] = 
                @preg_match($pattern, '') !== false;
        }
        return $this->regexCache[$pattern];
    }
}

// Limit rule count
const MAX_RULES = 100;

function addRuleWithLimit(ReflexAgent $agent, ...): void
{
    if (count($agent->getRules()) >= MAX_RULES) {
        throw new RuntimeException('Max rules exceeded');
    }
    $agent->addRule(...);
}
```

## Summary

You've learned how to:

✅ Create and configure ReflexAgents
✅ Use different condition types (string, regex, callable)
✅ Implement dynamic actions
✅ Manage rule priorities
✅ Add and remove rules dynamically
✅ Use LLM fallback strategically
✅ Build real-world applications
✅ Apply production best practices

## Next Steps

1. Build your own reflex agent for your use case
2. Experiment with different rule priorities
3. Combine with other agent types for complex workflows
4. Monitor and optimize rule performance
5. Share your use cases with the community

## Additional Resources

- [ReflexAgent Documentation](../ReflexAgent.md)
- [Basic Example](../../examples/reflex_agent.php)
- [Advanced Example](../../examples/advanced_reflex_agent.php)
- [Agent Selection Guide](../agent-selection-guide.md)

Happy building! 🚀

