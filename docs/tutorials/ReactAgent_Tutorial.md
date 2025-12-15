# ReactAgent Tutorial: Building Intelligent Problem-Solving Agents

## Introduction

This tutorial will guide you through building powerful AI agents using the ReactAgent class. The ReactAgent implements the **ReAct (Reason-Act-Observe)** pattern, which enables agents to think through problems step-by-step, use tools to gather information, and make informed decisions.

By the end of this tutorial, you'll be able to:

- Understand the ReAct pattern and how it works
- Create agents with various tools
- Build multi-step problem-solving workflows
- Monitor and debug agent execution
- Implement real-world use cases

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and closures

## Table of Contents

1. [Understanding the ReAct Pattern](#understanding-the-react-pattern)
2. [Your First ReactAgent](#your-first-reactagent)
3. [Working with Tools](#working-with-tools)
4. [Multi-Step Reasoning](#multi-step-reasoning)
5. [Monitoring and Debugging](#monitoring-and-debugging)
6. [Real-World Examples](#real-world-examples)
7. [Best Practices](#best-practices)

## Understanding the ReAct Pattern

The ReAct pattern is a powerful approach to AI problem-solving that alternates between three phases:

### 1. Reason (Think)
The agent analyzes the task and plans what to do next.

### 2. Act (Do)
The agent uses available tools to gather information or perform actions.

### 3. Observe (Learn)
The agent reviews the results and decides whether to continue or provide a final answer.

### Example Flow

```
Task: "What's the weather in Tokyo and convert 25°C to Fahrenheit?"

┌─────────────────────────────────────────┐
│ Iteration 1: Reason                     │
│ "I need weather data and temperature    │
│  conversion"                             │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ Iteration 1: Act                        │
│ - get_weather("Tokyo")                  │
│ - convert_temp(25, "C", "F")            │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ Iteration 1: Observe                    │
│ - Weather: Sunny, 25°C                  │
│ - Conversion: 77°F                      │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ Iteration 2: Reason                     │
│ "I have all needed information"         │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ Iteration 2: Act                        │
│ [No tools needed]                       │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│ Iteration 2: Observe & Answer           │
│ "Tokyo is sunny at 25°C (77°F)"         │
└─────────────────────────────────────────┘
```

## Your First ReactAgent

Let's start with a simple example to understand the basics.

### Step 1: Setup

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
```

### Step 2: Create a Simple Tool

```php
// Create a calculator tool
$calculator = Tool::create('calculate')
    ->description('Perform mathematical calculations')
    ->stringParam('expression', 'The math expression to evaluate')
    ->handler(function (array $input): string {
        // Validate input
        $expr = $input['expression'];
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid expression";
        }
        
        // Evaluate safely
        try {
            $result = eval("return {$expr};");
            return (string) $result;
        } catch (Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    });
```

### Step 3: Create and Run the Agent

```php
// Create ReactAgent
$agent = new ReactAgent($client, [
    'name' => 'math_assistant',
    'tools' => [$calculator],
    'system' => 'You are a helpful math assistant.',
]);

// Run a task
$result = $agent->run('What is 15 multiplied by 23?');

if ($result->isSuccess()) {
    echo "Answer: {$result->getAnswer()}\n";
    echo "Iterations: {$result->getIterations()}\n";
}
```

**Output:**
```
Answer: The result is 345.
Iterations: 2
```

### What Just Happened?

1. **Iteration 1**: Agent reasoned it needed to calculate, used the calculator tool
2. **Iteration 2**: Agent observed the result (345) and provided the final answer

## Working with Tools

Tools are the building blocks that give your agent capabilities. Let's explore different types of tools.

### Data Retrieval Tool

```php
$weatherTool = Tool::create('get_weather')
    ->description('Get current weather for a location')
    ->stringParam('location', 'City name')
    ->handler(function (array $input): string {
        // In production, call a real weather API
        $location = $input['location'];
        $apiKey = getenv('WEATHER_API_KEY');
        
        $response = file_get_contents(
            "https://api.weatherapi.com/v1/current.json?key={$apiKey}&q={$location}"
        );
        
        return $response;
    });
```

### Database Tool

```php
$databaseTool = Tool::create('query_users')
    ->description('Query user database')
    ->stringParam('query', 'Search criteria')
    ->handler(function (array $input): string {
        // Query your database
        $users = DB::table('users')
            ->where('name', 'like', "%{$input['query']}%")
            ->get();
        
        return json_encode($users);
    });
```

### File System Tool

```php
$fileReaderTool = Tool::create('read_file')
    ->description('Read contents of a file')
    ->stringParam('path', 'File path')
    ->handler(function (array $input): string {
        $path = $input['path'];
        
        if (!file_exists($path)) {
            return "Error: File not found";
        }
        
        return file_get_contents($path);
    });
```

### API Integration Tool

```php
$apiTool = Tool::create('search_products')
    ->description('Search product catalog')
    ->stringParam('query', 'Search query')
    ->numberParam('limit', 'Max results', required: false)
    ->handler(function (array $input): string {
        $query = $input['query'];
        $limit = $input['limit'] ?? 10;
        
        $ch = curl_init("https://api.example.com/products?q={$query}&limit={$limit}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    });
```

### Combining Multiple Tools

```php
$agent = new ReactAgent($client, [
    'name' => 'versatile_assistant',
    'tools' => [
        $calculator,
        $weatherTool,
        $databaseTool,
        $fileReaderTool,
        $apiTool,
    ],
    'system' => 'You are a versatile assistant with access to various tools.',
]);
```

## Multi-Step Reasoning

The power of ReactAgent shines when dealing with complex, multi-step tasks.

### Example 1: Research and Analysis

```php
$searchTool = Tool::create('web_search')
    ->description('Search the web for information')
    ->stringParam('query', 'Search query')
    ->handler(function (array $input): string {
        // Implement web search (e.g., using Google Custom Search API)
        return searchWeb($input['query']);
    });

$summarizeTool = Tool::create('extract_key_points')
    ->description('Extract key points from text')
    ->stringParam('text', 'Text to analyze')
    ->handler(function (array $input): string {
        // Implement text analysis
        return extractKeyPoints($input['text']);
    });

$agent = new ReactAgent($client, [
    'name' => 'researcher',
    'tools' => [$searchTool, $summarizeTool],
    'max_iterations' => 15,
]);

$result = $agent->run(
    'Research the latest developments in quantum computing and ' .
    'provide a summary of the top 3 breakthroughs'
);
```

### Example 2: E-commerce Assistant

```php
$searchProducts = Tool::create('search_products')
    ->stringParam('query', 'Product search query')
    ->handler(fn($i) => searchProductCatalog($i['query']));

$checkInventory = Tool::create('check_stock')
    ->stringParam('product_id', 'Product ID')
    ->handler(fn($i) => checkProductStock($i['product_id']));

$getPrice = Tool::create('get_price')
    ->stringParam('product_id', 'Product ID')
    ->handler(fn($i) => getProductPrice($i['product_id']));

$calculateShipping = Tool::create('calculate_shipping')
    ->stringParam('product_id', 'Product ID')
    ->stringParam('zip_code', 'Delivery ZIP code')
    ->handler(fn($i) => calculateShippingCost($i['product_id'], $i['zip_code']));

$agent = new ReactAgent($client, [
    'name' => 'shopping_assistant',
    'tools' => [$searchProducts, $checkInventory, $getPrice, $calculateShipping],
    'system' => 'You are a helpful shopping assistant. Help customers ' .
                'find products, check availability, and calculate total costs.',
]);

$result = $agent->run(
    'I need a wireless mouse under $50. Check if it\'s in stock ' .
    'and tell me the total cost with shipping to 94102.'
);
```

### Example 3: Data Analysis Pipeline

```php
$queryDb = Tool::create('query_database')
    ->stringParam('sql', 'SQL query')
    ->handler(fn($i) => DB::select($i['sql']));

$calculateStats = Tool::create('calculate_statistics')
    ->stringParam('data', 'JSON data array')
    ->handler(function ($input): string {
        $data = json_decode($input['data'], true);
        return json_encode([
            'mean' => array_sum($data) / count($data),
            'min' => min($data),
            'max' => max($data),
            'count' => count($data),
        ]);
    });

$generateReport = Tool::create('format_report')
    ->stringParam('data', 'Data to format')
    ->stringParam('format', 'Report format: markdown, html, json')
    ->handler(fn($i) => formatReport($i['data'], $i['format']));

$agent = new ReactAgent($client, [
    'name' => 'data_analyst',
    'tools' => [$queryDb, $calculateStats, $generateReport],
    'system' => 'You are a data analyst. Query databases, perform ' .
                'statistical analysis, and generate clear reports.',
]);

$result = $agent->run(
    'Query sales data for Q4 2023, calculate key statistics, ' .
    'and generate a markdown report'
);
```

## Monitoring and Debugging

Understanding what your agent is doing is crucial for debugging and optimization.

### Iteration Tracking

```php
$iterationLog = [];

$agent->onIteration(function ($iteration, $response, $context) use (&$iterationLog) {
    $iterationLog[] = [
        'iteration' => $iteration,
        'timestamp' => microtime(true),
        'message' => $context->getAnswer(),
    ];
    
    echo "\n=== Iteration {$iteration} ===\n";
    echo "Current reasoning: {$context->getAnswer()}\n";
});

$result = $agent->run('Your task...');

// Review the log
print_r($iterationLog);
```

### Tool Execution Monitoring

```php
$toolUsage = [];

$agent->onToolExecution(function ($tool, $input, $result) use (&$toolUsage) {
    $toolUsage[] = [
        'tool' => $tool,
        'input' => $input,
        'result' => $result,
        'timestamp' => microtime(true),
    ];
    
    echo "🔧 Tool: {$tool}\n";
    echo "   Input: " . json_encode($input) . "\n";
    echo "   Result: " . substr((string)$result, 0, 100) . "...\n\n";
});

$result = $agent->run('Your task...');

// Analyze tool usage
echo "Total tools used: " . count($toolUsage) . "\n";
foreach (array_count_values(array_column($toolUsage, 'tool')) as $tool => $count) {
    echo "  {$tool}: {$count} times\n";
}
```

### Performance Monitoring

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('agent');
$logger->pushHandler(new StreamHandler('agent.log', Logger::DEBUG));

$agent = new ReactAgent($client, [
    'logger' => $logger,
]);

// Log will contain detailed execution information
$result = $agent->run('Your task...');
```

### Analyzing Results

```php
$result = $agent->run('Complex task...');

echo "Success: " . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Iterations: {$result->getIterations()}\n";
echo "Tools used: " . count($result->getToolCalls()) . "\n";

// Token usage
$usage = $result->getTokenUsage();
echo "Tokens: {$usage['total']} (in: {$usage['input']}, out: {$usage['output']})\n";

// Tool breakdown
foreach ($result->getToolCalls() as $call) {
    echo "  - {$call['tool']}\n";
}

// Message history
$messages = $result->getMessages();
echo "Total messages exchanged: " . count($messages) . "\n";
```

## Real-World Examples

### Example 1: Customer Support Bot

```php
// Define tools
$getTicket = Tool::create('get_ticket')
    ->description('Retrieve support ticket details')
    ->stringParam('ticket_id', 'Ticket ID')
    ->handler(function ($input): string {
        return json_encode(SupportTicket::find($input['ticket_id']));
    });

$searchKB = Tool::create('search_knowledge_base')
    ->description('Search knowledge base for solutions')
    ->stringParam('query', 'Search query')
    ->handler(function ($input): string {
        return json_encode(KB::search($input['query']));
    });

$updateTicket = Tool::create('update_ticket')
    ->description('Update ticket with response')
    ->stringParam('ticket_id', 'Ticket ID')
    ->stringParam('response', 'Response message')
    ->handler(function ($input): string {
        SupportTicket::update($input['ticket_id'], $input['response']);
        return "Ticket updated successfully";
    });

$escalate = Tool::create('escalate_to_human')
    ->description('Escalate ticket to human agent')
    ->stringParam('ticket_id', 'Ticket ID')
    ->stringParam('reason', 'Escalation reason')
    ->handler(function ($input): string {
        SupportTicket::escalate($input['ticket_id'], $input['reason']);
        return "Ticket escalated";
    });

// Create agent
$supportAgent = new ReactAgent($client, [
    'name' => 'support_bot',
    'tools' => [$getTicket, $searchKB, $updateTicket, $escalate],
    'system' => 
        'You are a customer support agent. Be empathetic and helpful. ' .
        'Follow these steps:\n' .
        '1. Get ticket details\n' .
        '2. Search knowledge base for solutions\n' .
        '3. If you can solve it, update ticket with solution\n' .
        '4. If unsure or complex, escalate to human\n' .
        'Always be polite and professional.',
    'max_iterations' => 10,
]);

// Handle ticket
$result = $supportAgent->run('Handle ticket #12345');

if ($result->isSuccess()) {
    echo "Ticket handled: {$result->getAnswer()}\n";
}
```

### Example 2: Financial Analysis Agent

```php
$getStockPrice = Tool::create('get_stock_price')
    ->stringParam('symbol', 'Stock symbol')
    ->handler(fn($i) => getStockData($i['symbol']));

$calculateMetrics = Tool::create('calculate_metrics')
    ->stringParam('data', 'Financial data JSON')
    ->handler(function ($input): string {
        $data = json_decode($input['data'], true);
        return json_encode([
            'pe_ratio' => calculatePE($data),
            'growth_rate' => calculateGrowth($data),
            'volatility' => calculateVolatility($data),
        ]);
    });

$compareStocks = Tool::create('compare_stocks')
    ->stringParam('symbols', 'Comma-separated stock symbols')
    ->handler(fn($i) => compareStockMetrics($i['symbols']));

$financialAgent = new ReactAgent($client, [
    'name' => 'financial_analyst',
    'tools' => [$getStockPrice, $calculateMetrics, $compareStocks],
    'system' => 
        'You are a financial analyst. Provide data-driven insights. ' .
        'Always include supporting metrics and explain your reasoning. ' .
        'Mention risks and limitations.',
]);

$result = $financialAgent->run(
    'Compare AAPL and MSFT. Which is a better investment for long-term growth? ' .
    'Provide analysis with specific metrics.'
);
```

### Example 3: Content Management System

```php
$searchContent = Tool::create('search_content')
    ->stringParam('query', 'Search query')
    ->handler(fn($i) => Content::search($i['query']));

$createPost = Tool::create('create_post')
    ->stringParam('title', 'Post title')
    ->stringParam('content', 'Post content')
    ->stringParam('tags', 'Comma-separated tags')
    ->handler(function ($input): string {
        $post = Content::create([
            'title' => $input['title'],
            'content' => $input['content'],
            'tags' => explode(',', $input['tags']),
        ]);
        return "Post created with ID: {$post->id}";
    });

$publishPost = Tool::create('publish_post')
    ->stringParam('post_id', 'Post ID')
    ->handler(function ($input): string {
        Content::publish($input['post_id']);
        return "Post published";
    });

$cmsAgent = new ReactAgent($client, [
    'name' => 'content_manager',
    'tools' => [$searchContent, $createPost, $publishPost],
    'system' => 
        'You are a content management assistant. Help with content creation, ' .
        'editing, and publishing. Ensure content is well-formatted and properly tagged.',
]);

$result = $cmsAgent->run(
    'Create a blog post about "10 PHP Best Practices" with appropriate tags, ' .
    'then publish it.'
);
```

## Best Practices

### 1. Write Clear System Prompts

```php
// Good: Specific and actionable
$agent = new ReactAgent($client, [
    'system' => 
        'You are a code review assistant. When reviewing code:\n' .
        '1. Check for syntax errors\n' .
        '2. Identify security vulnerabilities\n' .
        '3. Suggest performance improvements\n' .
        '4. Ensure coding standards compliance\n' .
        'Be constructive and provide specific examples.',
]);

// Less effective: Too vague
$agent = new ReactAgent($client, [
    'system' => 'You help with code.',
]);
```

### 2. Set Appropriate Limits

```php
// Simple tasks: Lower iterations
$quickAgent = new ReactAgent($client, ['max_iterations' => 5]);

// Complex reasoning: Higher iterations
$researchAgent = new ReactAgent($client, ['max_iterations' => 20]);

// Token limits
$economicalAgent = new ReactAgent($client, ['max_tokens' => 2048]);
```

### 3. Design Tools for Single Responsibilities

```php
// Good: Each tool has one clear purpose
$getTool = Tool::create('get_user')->handler(...);
$updateTool = Tool::create('update_user')->handler(...);
$deleteTool = Tool::create('delete_user')->handler(...);

// Less ideal: One tool doing everything
$userTool = Tool::create('user_operations')
    ->stringParam('action', 'Action: get, update, delete')
    ->handler(...);
```

### 4. Validate and Sanitize Tool Inputs

```php
$tool = Tool::create('send_email')
    ->stringParam('to', 'Email address')
    ->handler(function ($input): string {
        $email = filter_var($input['to'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new \InvalidArgumentException('Invalid email address');
        }
        
        // Additional checks
        if (isBlockedEmail($email)) {
            throw new \Exception('Email address is blocked');
        }
        
        return sendEmail($email);
    });
```

### 5. Handle Errors Gracefully

```php
$tool = Tool::create('api_call')
    ->handler(function ($input): string {
        try {
            $result = callExternalApi($input['endpoint']);
            return $result;
        } catch (\Exception $e) {
            // Return error message that the agent can understand
            return "Error: " . $e->getMessage() . ". Try an alternative approach.";
        }
    });
```

### 6. Use Caching for Expensive Operations

```php
class CachedTool
{
    private array $cache = [];
    
    public function getTool(): ToolInterface
    {
        return Tool::create('expensive_search')
            ->handler(function ($input): string {
                $key = json_encode($input);
                
                if (isset($this->cache[$key])) {
                    return $this->cache[$key];
                }
                
                $result = expensiveSearch($input['query']);
                $this->cache[$key] = $result;
                
                return $result;
            });
    }
}
```

### 7. Monitor and Log Production Agents

```php
$agent = new ReactAgent($client, [
    'logger' => $productionLogger,
]);

$agent->onIteration(function ($iteration) {
    if ($iteration > 15) {
        throw new \RuntimeException('Too many iterations - possible infinite loop');
    }
});

$agent->onToolExecution(function ($tool, $input, $result) {
    // Log for analytics
    Analytics::record('tool_usage', [
        'tool' => $tool,
        'success' => !str_contains($result, 'Error'),
    ]);
});
```

## Conclusion

You now have a comprehensive understanding of the ReactAgent and how to build intelligent problem-solving agents! You've learned:

✅ The ReAct pattern and how it enables step-by-step reasoning  
✅ Creating agents with various tools and configurations  
✅ Building multi-step workflows for complex tasks  
✅ Monitoring and debugging agent execution  
✅ Real-world implementation patterns  
✅ Best practices for production deployments

## Next Steps

- Explore the [ReactAgent API Documentation](../ReactAgent.md)
- Check out the [examples directory](../../examples/) for more code samples
- Learn about [Multi-Agent Systems](../MultiAgent.md)
- Study [Advanced Agent Patterns](../agent-selection-guide.md)

## Additional Resources

- [ReactAgent.md](../ReactAgent.md) - Complete API reference
- [react_agent.php](../../examples/react_agent.php) - Working example
- [ReAct Paper](https://arxiv.org/abs/2210.03629) - Original research
- [Claude API Documentation](https://docs.anthropic.com/)

Happy building! 🚀

