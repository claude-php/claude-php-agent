# Tutorial 3: Multi-Tool Agent

**Time: 45 minutes** | **Difficulty: Intermediate**

You've learned how to build agents with the ReAct loop. Now let's give your agent multiple tools and teach it to choose the right tool for each situation. This is where agents become truly powerful!

## 🎯 Learning Objectives

By the end of this tutorial, you'll be able to:

- Define multiple diverse tools
- Understand how Claude selects the right tool
- Handle tool selection reasoning
- Design focused, single-purpose tools
- Validate tool inputs properly
- Debug tool selection issues
- Build production-ready multi-tool agents

## 🏗️ What We're Building

We'll create a **Multi-Tool Assistant** with four different tools:

1. **Calculator** - Perform mathematical calculations
2. **Weather** - Get current weather information
3. **String Tools** - Manipulate text (uppercase, lowercase, reverse)
4. **Current Time** - Get current date/time

The agent will intelligently choose which tool to use based on the user's request.

## 📋 Prerequisites

Make sure you have:

- Completed [Tutorial 2: ReAct Loop Basics](./02-ReAct-Basics.md)
- Understanding of the ReAct pattern
- Claude PHP Agent Framework installed
- API key configured

## 🛠️ Step 1: Define Multiple Tools

Let's create four distinct tools, each with a clear, focused purpose:

```php
<?php

use ClaudeAgents\Tools\Tool;

// Tool 1: Calculator
$calculator = Tool::create('calculate')
    ->description(
        'Perform precise mathematical calculations. ' .
        'Supports +, -, *, /, parentheses. ' .
        'Use this for any math operations.'
    )
    ->stringParam('expression', 'Math expression (e.g., "25 * 17 + 100")')
    ->handler(function (array $input): string {
        $expr = $input['expression'];
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expr)) {
            return "Error: Invalid expression";
        }
        try {
            return (string)eval("return {$expr};");
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    });

// Tool 2: Weather (simulated)
$weather = Tool::create('get_weather')
    ->description(
        'Get current weather information for a location. ' .
        'Returns temperature, conditions, and forecast. ' .
        'Use this when asked about weather or temperature.'
    )
    ->stringParam('location', 'City name or location (e.g., "San Francisco")')
    ->handler(function (array $input): string {
        $location = $input['location'];
        
        // Simulate weather API response
        $conditions = ['Sunny', 'Cloudy', 'Rainy', 'Partly Cloudy'];
        $temp = rand(50, 85);
        $condition = $conditions[array_rand($conditions)];
        
        return json_encode([
            'location' => $location,
            'temperature' => $temp . '°F',
            'condition' => $condition,
            'forecast' => 'Pleasant conditions expected',
        ]);
    });

// Tool 3: String Operations
$stringOps = Tool::create('string_operation')
    ->description(
        'Perform string operations on text. ' .
        'Operations: uppercase, lowercase, reverse, length. ' .
        'Use this for text manipulation tasks.'
    )
    ->stringParam('text', 'The text to manipulate')
    ->stringParam('operation', 'Operation: "uppercase", "lowercase", "reverse", or "length"')
    ->handler(function (array $input): string {
        $text = $input['text'];
        $operation = strtolower($input['operation']);
        
        return match($operation) {
            'uppercase' => strtoupper($text),
            'lowercase' => strtolower($text),
            'reverse' => strrev($text),
            'length' => (string)strlen($text),
            default => "Error: Unknown operation '{$operation}'"
        };
    });

// Tool 4: Current Time
$currentTime = Tool::create('get_current_time')
    ->description(
        'Get the current date and time. ' .
        'Returns timestamp, date, time, and timezone. ' .
        'Use this when asked about current time or date.'
    )
    ->stringParam('timezone', 'Timezone (e.g., "America/New_York", default: UTC)', required: false)
    ->handler(function (array $input): string {
        $timezone = $input['timezone'] ?? 'UTC';
        
        try {
            $tz = new DateTimeZone($timezone);
            $now = new DateTime('now', $tz);
            
            return json_encode([
                'timestamp' => $now->getTimestamp(),
                'date' => $now->format('Y-m-d'),
                'time' => $now->format('H:i:s'),
                'timezone' => $timezone,
                'formatted' => $now->format('l, F j, Y g:i A T'),
            ]);
        } catch (Exception $e) {
            return "Error: Invalid timezone '{$timezone}'";
        }
    });
```

## 🔍 How Tool Selection Works

### Claude's Decision Process

When Claude receives a user message, it:

1. **Reads all tool descriptions**
2. **Analyzes the user's request**
3. **Matches request to tool capabilities**
4. **Selects the most appropriate tool**
5. **Extracts parameters from the request**
6. **Calls the tool with extracted parameters**

### Good Tool Descriptions

The quality of your tool descriptions directly impacts selection accuracy:

**❌ Bad:**
```php
->description('Does stuff with numbers')
```

**✅ Good:**
```php
->description(
    'Perform precise mathematical calculations. ' .
    'Supports +, -, *, /, parentheses. ' .
    'Use this for any math operations.'
)
```

### Tool Selection Examples

**Request**: "What's 25 times 17?"
- Claude selects: `calculate` tool
- Reasoning: Math operation

**Request**: "What's the weather in Boston?"
- Claude selects: `get_weather` tool
- Reasoning: Weather inquiry

**Request**: "Convert 'Hello World' to uppercase"
- Claude selects: `string_operation` tool
- Reasoning: Text manipulation

**Request**: "What time is it in Tokyo?"
- Claude selects: `get_current_time` tool
- Reasoning: Time inquiry

## 🚀 Step 2: Implement Multi-Tool Agent

Using AgentHelpers with multiple tools:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Helpers\AgentHelpers;
use ClaudePhp\ClaudePhp;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize
$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);
$logger = AgentHelpers::createConsoleLogger('multi_tool', 'info');

// Define all tools (calculator, weather, stringOps, currentTime from above)
// ... tool definitions ...

// Create tool executor that handles all tools
$toolExecutor = function(string $name, array $input) use (
    $calculator, $weather, $stringOps, $currentTime
) {
    return match($name) {
        'calculate' => ($calculator->handler())($input),
        'get_weather' => ($weather->handler())($input),
        'string_operation' => ($stringOps->handler())($input),
        'get_current_time' => ($currentTime->handler())($input),
        default => "Error: Unknown tool '{$name}'"
    };
};

// Convert tools to API format
$tools = [
    AgentHelpers::createTool(
        'calculate',
        'Perform precise mathematical calculations. Supports +, -, *, /, parentheses.',
        ['expression' => ['type' => 'string', 'description' => 'Math expression']],
        ['expression']
    ),
    AgentHelpers::createTool(
        'get_weather',
        'Get current weather information for a location.',
        ['location' => ['type' => 'string', 'description' => 'City name']],
        ['location']
    ),
    AgentHelpers::createTool(
        'string_operation',
        'Perform string operations: uppercase, lowercase, reverse, length.',
        [
            'text' => ['type' => 'string', 'description' => 'Text to manipulate'],
            'operation' => ['type' => 'string', 'description' => 'Operation type']
        ],
        ['text', 'operation']
    ),
    AgentHelpers::createTool(
        'get_current_time',
        'Get current date and time for a timezone.',
        ['timezone' => ['type' => 'string', 'description' => 'Timezone (optional)']],
        []
    ),
];

// Test various requests
$requests = [
    'What is 157 * 89?',
    'What\'s the weather in San Francisco?',
    'Convert the text "Hello World" to uppercase',
    'What time is it in Tokyo?',
    'Calculate (100 + 50) * 2, then tell me the weather in Boston',
];

foreach ($requests as $request) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "Request: {$request}\n";
    echo str_repeat("=", 70) . "\n";
    
    $result = AgentHelpers::runAgentLoop(
        client: $client,
        messages: [['role' => 'user', 'content' => $request]],
        tools: $tools,
        toolExecutor: $toolExecutor,
        config: [
            'max_iterations' => 10,
            'logger' => $logger,
        ]
    );
    
    if ($result['success']) {
        $answer = AgentHelpers::extractTextContent($result['response']);
        echo "\nResponse: {$answer}\n";
        echo "Iterations: {$result['iterations']}\n";
    } else {
        echo "Error: {$result['error']}\n";
    }
}
```

## 🎯 Tool Design Best Practices

### 1. Single Responsibility

Each tool should do ONE thing well:

**❌ Bad: Swiss Army Knife Tool**
```php
Tool::create('utility')
    ->description('Does calculations, gets weather, and manipulates strings')
    // Too many responsibilities!
```

**✅ Good: Focused Tools**
```php
Tool::create('calculate')
    ->description('Perform mathematical calculations only');

Tool::create('get_weather')
    ->description('Get weather information only');
```

### 2. Clear Descriptions

Tell Claude exactly when to use each tool:

```php
->description(
    'Perform precise mathematical calculations. ' .   // What it does
    'Supports +, -, *, /, parentheses. ' .           // Capabilities
    'Use this for any math operations.'              // When to use
)
```

### 3. Descriptive Parameters

Parameter names and descriptions matter:

**❌ Bad:**
```php
->stringParam('q', 'The query')
->stringParam('t', 'Type')
```

**✅ Good:**
```php
->stringParam('location', 'City name or location (e.g., "San Francisco, CA")')
->stringParam('units', 'Temperature units: "fahrenheit" or "celsius" (default: fahrenheit)')
```

### 4. Input Validation

Always validate tool inputs:

```php
->handler(function (array $input): string {
    // Validate required fields
    if (empty($input['location'])) {
        return "Error: Location is required";
    }
    
    // Validate format
    if (strlen($input['location']) < 2) {
        return "Error: Location name too short";
    }
    
    // Validate allowed values
    $units = $input['units'] ?? 'fahrenheit';
    if (!in_array($units, ['fahrenheit', 'celsius'])) {
        return "Error: Units must be 'fahrenheit' or 'celsius'";
    }
    
    // Process...
});
```

### 5. Structured Results

Return structured data when possible:

**❌ Bad: Unstructured**
```php
return "The weather in Boston is sunny and 72 degrees";
```

**✅ Good: Structured**
```php
return json_encode([
    'location' => 'Boston, MA',
    'temperature' => 72,
    'units' => 'fahrenheit',
    'condition' => 'Sunny',
    'humidity' => 45,
    'wind_speed' => 8,
]);
```

## 🐛 Debugging Tool Selection

### Track Which Tools Are Called

```php
<?php

$logger = AgentHelpers::createConsoleLogger('agent', 'debug');

$result = AgentHelpers::runAgentLoop(
    client: $client,
    messages: $messages,
    tools: $tools,
    toolExecutor: $toolExecutor,
    config: [
        'debug' => true,  // Shows tool calls
        'logger' => $logger,
    ]
);

// Log output shows:
// [timestamp] agent.DEBUG: Tool call {"tool":"calculate","parameters":{"expression":"25 * 17"}}
```

### Common Tool Selection Issues

**Issue: Wrong Tool Selected**

**Symptom**: Claude uses calculator for weather request

**Causes**:
- Tool descriptions too similar
- Ambiguous request
- Missing keywords in description

**Fix**:
```php
// Make descriptions more specific
->description(
    'Get WEATHER information for a location. ' .  // Emphasis
    'Returns temperature, conditions, forecast. ' .
    'Use ONLY for weather and climate questions.'  // Clear boundary
)
```

**Issue: No Tool Selected**

**Symptom**: Claude answers without using tools

**Causes**:
- Tool description doesn't match request
- Claude thinks it knows the answer
- Tool name/description too vague

**Fix**:
```php
// Add system prompt encouraging tool use
$config = [
    'system' => 'You are a helpful assistant. Always use the available tools ' .
                'when they can provide accurate, up-to-date information.'
];
```

**Issue: Wrong Parameters**

**Symptom**: Tool called with incorrect or missing parameters

**Causes**:
- Parameter descriptions unclear
- Required parameters not marked
- Format not specified

**Fix**:
```php
// Be explicit about parameter format
->stringParam(
    'date',
    'Date in YYYY-MM-DD format (e.g., "2024-12-25"). Required.'
)
->required('date')
```

## 📊 Performance Considerations

### Tool Count Impact

| Tool Count | Token Overhead | Selection Accuracy |
|------------|----------------|-------------------|
| 1-5 tools | ~200-500 tokens | Excellent |
| 6-10 tools | ~500-1000 tokens | Very Good |
| 11-20 tools | ~1000-2000 tokens | Good |
| 20+ tools | ~2000+ tokens | May degrade |

**Recommendation**: Keep tool count under 10 for best performance. For more tools, consider:
- Tool categories
- Dynamic tool loading
- Hierarchical agents

### Token Usage

Each tool definition adds tokens to EVERY request:

```
Request tokens = 
    System prompt + 
    User message + 
    All tool definitions +
    Conversation history
```

**Optimization**:
```php
// Keep descriptions concise but clear
->description(
    'Calculate math expressions. Supports +,-,*,/,(). ' .
    'For any mathematical operation.'  
    // Clear and brief (under 100 chars)
)
```

## ✅ Checkpoint

Before moving on, make sure you understand:

- [ ] How to define multiple focused tools
- [ ] How Claude selects the right tool
- [ ] Why clear descriptions matter
- [ ] How to validate tool inputs
- [ ] How to debug tool selection
- [ ] Performance impacts of tool count

## 🚀 Next Steps

You now have a multi-tool agent that can handle diverse tasks. But what about production environments where things can go wrong?

**[Tutorial 4: Production-Ready Patterns →](./04-Production-Patterns.md)**

Learn error handling, retry logic, logging, and other production essentials!

## 💡 Key Takeaways

1. **One tool, one purpose** - Focused tools work better
2. **Descriptions are critical** - They guide tool selection
3. **Always validate inputs** - Prevent errors early
4. **Return structured data** - Easier to process
5. **Keep tool count reasonable** - Under 10 is ideal
6. **Test tool selection** - Verify Claude chooses correctly

## 📚 Further Reading

- [Tools Documentation](../../Tools.md)
- [Best Practices: Tool Design](../../BestPractices.md#tool-design)
- [Multi-Tool Agent Example](../../../examples/multi_tool_agent.php)
- [Tool Use Best Practices](https://docs.anthropic.com/en/docs/agents-and-tools/tool-use/overview)

