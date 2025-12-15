# Tutorial 5: Advanced Agent Patterns

**Time: 60 minutes** | **Difficulty: Advanced**

You've mastered the basics of building production-ready agents. Now let's explore advanced patterns that enable sophisticated reasoning, planning, and self-correction.

## 🎯 Learning Objectives

By the end of this tutorial, you'll be able to:

- Implement the Plan-Execute-Reflect-Adjust (PERA) pattern
- Use extended thinking for complex reasoning
- Build agents that can self-correct
- Decompose complex tasks into subtasks
- Understand reasoning transparency
- Choose the right pattern for your use case

## 🏗️ What We're Building

We'll build a **Plan-Execute-Reflect Agent** that can:

1. **Plan** - Break down complex tasks into steps
2. **Execute** - Carry out the planned steps
3. **Reflect** - Evaluate the results
4. **Adjust** - Modify the plan if needed

This pattern is perfect for complex, multi-stage tasks where simple ReAct isn't enough.

## 📋 Prerequisites

Make sure you have:

- Completed [Tutorial 4: Production-Ready Patterns](./04-Production-Patterns.md)
- Understanding of production agent patterns
- Claude PHP Agent Framework installed
- Familiarity with extended thinking

## 🧠 Advanced Pattern: Plan-Execute-Reflect-Adjust (PERA)

### Simple ReAct vs. PERA

**ReAct (Tutorial 2-3):**
```
Task → Reason → Act → Observe → Reason → Act → ... → Done
```

Works great for:
- Simple tasks
- Linear workflows
- Clear next steps

**PERA (Advanced):**
```
Task
  ↓
Create Plan (with thinking)
  ↓
Execute Step 1
  ↓
Reflect: Did it work?
  ↓
Adjust Plan if needed
  ↓
Execute Step 2
  ↓
... continue until complete
```

Works better for:
- Complex multi-stage tasks
- Tasks requiring strategy
- Cases where you need to adapt
- Research and analysis

### When to Use PERA

✅ **Use PERA when:**
- Task has multiple distinct phases
- Strategy matters
- You need to adapt based on results
- Complex reasoning required
- Task decomposition helps

❌ **Use Simple ReAct when:**
- Task is straightforward
- Steps are obvious
- Speed matters more than strategy
- Lower cost is priority

## 🛠️ Step 1: Understanding Extended Thinking

Extended thinking gives Claude more "internal dialogue" to reason through complex problems.

### Without Extended Thinking

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 4096,
    'messages' => $messages,
    'tools' => $tools,
]);

// Claude immediately responds
```

### With Extended Thinking

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 4096,
    'messages' => $messages,
    'tools' => $tools,
    'thinking' => [
        'type' => 'enabled',
        'budget_tokens' => 5000,  // Allow up to 5K tokens of thinking
    ],
]);

// Claude thinks deeply before responding
// Response includes thinking content blocks
```

### Response Structure

```php
$response->content = [
    [
        'type' => 'thinking',
        'thinking' => 'Let me break this down... First I need to...'
    ],
    [
        'type' => 'text',
        'text' => 'Based on my analysis...'
    ],
    [
        'type' => 'tool_use',
        'id' => 'toolu_...',
        'name' => 'search',
        'input' => [...]
    ]
];
```

## 🚀 Step 2: Implementing PERA Agent

The framework provides a built-in `PlanExecuteReflectAgent`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\PlanExecuteReflectAgent;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Setup
$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);
$logger = new Logger('pera_agent');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Define tools
$searchTool = Tool::create('search')
    ->description('Search for information on a topic')
    ->stringParam('query', 'Search query')
    ->handler(function (array $input): string {
        // Simulate search
        $query = $input['query'];
        return json_encode([
            'results' => [
                "Information about {$query}...",
                "Additional context about {$query}...",
            ]
        ]);
    });

$analyzeTool = Tool::create('analyze')
    ->description('Analyze data and extract insights')
    ->stringParam('data', 'Data to analyze')
    ->handler(function (array $input): string {
        // Simulate analysis
        return json_encode([
            'insights' => [
                'Key finding 1',
                'Key finding 2',
                'Key finding 3'
            ],
            'confidence' => 0.85
        ]);
    });

$tools = [$searchTool, $analyzeTool];

// Create PERA agent
$agent = new PlanExecuteReflectAgent(
    client: $client,
    tools: $tools,
    logger: $logger,
    config: [
        'max_iterations' => 15,
        'thinking_budget' => 5000,
        'reflection_enabled' => true,
        'adaptation_threshold' => 0.7,  // Adjust plan if confidence < 70%
    ]
);

// Execute complex task
$task = 'Research AI agents and create a comprehensive report on their applications in healthcare';

$result = $agent->execute($task);

if ($result['success']) {
    echo "Plan:\n";
    print_r($result['plan']);
    
    echo "\nExecution:\n";
    foreach ($result['execution_log'] as $step) {
        echo "- Step {$step['number']}: {$step['description']}\n";
        echo "  Status: {$step['status']}\n";
        echo "  Result: {$step['result']}\n";
    }
    
    echo "\nReflection:\n";
    echo $result['reflection'];
    
    echo "\nFinal Answer:\n";
    echo $result['answer'];
} else {
    echo "Error: {$result['error']}\n";
}
```

## 🔧 Step 3: Manual PERA Implementation

Understanding how PERA works under the hood:

```php
<?php

use ClaudeAgents\Helpers\AgentHelpers;

class ManualPERAAgent
{
    private $client;
    private $tools;
    private $logger;
    
    public function __construct($client, array $tools, $logger)
    {
        $this->client = $client;
        $this->tools = $tools;
        $this->logger = $logger;
    }
    
    public function execute(string $task): array
    {
        $this->logger->info('Starting PERA execution', ['task' => $task]);
        
        // PHASE 1: PLAN
        $plan = $this->createPlan($task);
        $this->logger->info('Plan created', ['plan' => $plan]);
        
        // PHASE 2: EXECUTE
        $executionResults = [];
        foreach ($plan['steps'] as $stepNum => $step) {
            $this->logger->info('Executing step', ['step' => $stepNum + 1]);
            
            $result = $this->executeStep($step, $executionResults);
            $executionResults[] = $result;
            
            // PHASE 3: REFLECT
            $reflection = $this->reflect($step, $result);
            $this->logger->info('Reflection', ['reflection' => $reflection]);
            
            // PHASE 4: ADJUST
            if ($reflection['should_adjust']) {
                $this->logger->info('Adjusting plan');
                $plan = $this->adjustPlan($plan, $reflection, $stepNum);
            }
        }
        
        // Generate final answer
        $answer = $this->synthesize($task, $plan, $executionResults);
        
        return [
            'success' => true,
            'plan' => $plan,
            'execution_results' => $executionResults,
            'answer' => $answer,
        ];
    }
    
    private function createPlan(string $task): array
    {
        // Use extended thinking to create a detailed plan
        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => 5000,
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Create a detailed step-by-step plan to accomplish this task:\n\n{$task}\n\n" .
                                "For each step, specify:\n" .
                                "1. What to do\n" .
                                "2. Which tool to use\n" .
                                "3. Expected outcome\n" .
                                "4. Success criteria\n\n" .
                                "Return the plan as a JSON array of steps."
                ]
            ],
            'tools' => $this->tools,
        ]);
        
        $planText = AgentHelpers::extractTextContent($response);
        
        // Parse plan (simplified - in production, use better parsing)
        return [
            'task' => $task,
            'steps' => $this->parsePlan($planText),
            'created_at' => time(),
        ];
    }
    
    private function executeStep(array $step, array $priorResults): array
    {
        // Build context from prior results
        $context = "Previous results:\n";
        foreach ($priorResults as $i => $result) {
            $context .= "Step " . ($i + 1) . ": " . json_encode($result['output']) . "\n";
        }
        
        // Execute this step with context
        $result = AgentHelpers::runAgentLoop(
            client: $this->client,
            messages: [
                [
                    'role' => 'user',
                    'content' => "{$context}\n\nNow execute this step:\n{$step['description']}\n\n" .
                                "Use the {$step['tool']} tool to accomplish this."
                ]
            ],
            tools: $this->tools,
            toolExecutor: fn($name, $input) => $this->executeTool($name, $input),
            config: [
                'max_iterations' => 5,
                'thinking' => ['type' => 'enabled', 'budget_tokens' => 2000],
                'logger' => $this->logger,
            ]
        );
        
        return [
            'step' => $step,
            'output' => AgentHelpers::extractTextContent($result['response']),
            'success' => $result['success'],
            'iterations' => $result['iterations'],
        ];
    }
    
    private function reflect(array $step, array $result): array
    {
        // Use extended thinking to reflect on the result
        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 2048,
            'thinking' => [
                'type' => 'enabled',
                'budget_tokens' => 3000,
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Reflect on this execution:\n\n" .
                                "Planned step: " . json_encode($step) . "\n" .
                                "Actual result: " . json_encode($result) . "\n\n" .
                                "Answer these questions:\n" .
                                "1. Did we achieve the expected outcome?\n" .
                                "2. Is the result high quality?\n" .
                                "3. Should we adjust our plan?\n" .
                                "4. What did we learn?\n\n" .
                                "Respond with JSON: {quality: 0-1, should_adjust: bool, learnings: string}"
                ]
            ]
        ]);
        
        $reflectionText = AgentHelpers::extractTextContent($response);
        
        return [
            'quality' => 0.8,  // Simplified - parse from response
            'should_adjust' => false,
            'learnings' => $reflectionText,
        ];
    }
    
    private function adjustPlan(array $plan, array $reflection, int $currentStep): array
    {
        // Adjust remaining steps based on reflection
        $this->logger->info('Plan adjustment needed', [
            'current_step' => $currentStep,
            'reason' => $reflection['learnings']
        ]);
        
        // In production, regenerate remaining steps
        // For now, keep existing plan
        return $plan;
    }
    
    private function synthesize(string $task, array $plan, array $results): string
    {
        // Synthesize final answer from all results
        $context = "Task: {$task}\n\nExecution results:\n";
        foreach ($results as $i => $result) {
            $context .= "Step " . ($i + 1) . ":\n";
            $context .= $result['output'] . "\n\n";
        }
        
        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "{$context}\n\n" .
                                "Based on all the above, provide a comprehensive final answer to the original task."
                ]
            ]
        ]);
        
        return AgentHelpers::extractTextContent($response);
    }
    
    private function parsePlan(string $planText): array
    {
        // Simplified parser - in production, use robust JSON extraction
        return [
            ['description' => 'Step 1', 'tool' => 'search'],
            ['description' => 'Step 2', 'tool' => 'analyze'],
        ];
    }
    
    private function executeTool(string $name, array $input): string
    {
        foreach ($this->tools as $tool) {
            if ($tool->name === $name) {
                return ($tool->handler())($input);
            }
        }
        return "Error: Unknown tool {$name}";
    }
}
```

## 🎯 Pattern Comparison

### When to Use Each Pattern

| Pattern | Best For | Complexity | Cost | Speed |
|---------|----------|------------|------|-------|
| **Simple API** | Single questions | Low | $ | Fast |
| **Basic ReAct** | Multi-step tasks | Medium | $$ | Medium |
| **Multi-Tool ReAct** | Diverse tasks | Medium | $$$ | Medium |
| **PERA** | Complex strategy | High | $$$$ | Slow |
| **Tree of Thoughts** | Multiple solutions | Very High | $$$$$ | Very Slow |

### Pattern Selection Guide

**Use Simple API when:**
```php
// Task: Single question with no tools needed
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 1024,
    'messages' => [['role' => 'user', 'content' => 'What is the capital of France?']]
]);
```

**Use Basic ReAct when:**
```php
// Task: Calculate (25 * 17) + (100 / 4)
// Needs multiple tool calls in sequence
$result = AgentHelpers::runAgentLoop($client, $messages, $tools, $executor);
```

**Use PERA when:**
```php
// Task: Research market trends, analyze data, generate report
// Needs planning and adaptation
$agent = new PlanExecuteReflectAgent($client, $tools, $logger);
$result = $agent->execute($complexTask);
```

## 💡 Best Practices for Advanced Patterns

### 1. Use Extended Thinking Wisely

**❌ Don't use for simple tasks:**
```php
// Wasteful - simple question doesn't need thinking
'thinking' => ['type' => 'enabled', 'budget_tokens' => 5000]
// for "What is 2+2?"
```

**✅ Use for complex reasoning:**
```php
// Appropriate - complex strategy task
'thinking' => ['type' => 'enabled', 'budget_tokens' => 5000]
// for "Design a scalable microservices architecture"
```

### 2. Set Appropriate Budgets

```php
// Task complexity → Thinking budget
$budgets = [
    'simple' => 0,          // No thinking
    'moderate' => 2000,     // Some thinking
    'complex' => 5000,      // Deep thinking
    'very_complex' => 10000, // Extensive thinking
];
```

### 3. Monitor Costs

Extended thinking uses tokens!

```php
use ClaudeAgents\Helpers\AgentLogger;

$agentLogger = new AgentLogger($logger);

// After execution
$metrics = $agentLogger->getMetrics();

echo "Thinking tokens: {$metrics['thinking_tokens']}\n";
echo "Regular tokens: {$metrics['regular_tokens']}\n";
echo "Cost: \${$metrics['estimated_cost_usd']}\n";
```

### 4. Implement Timeouts

```php
$startTime = time();
$timeout = 300; // 5 minutes

while ($iteration < $maxIterations) {
    if (time() - $startTime > $timeout) {
        throw new Exception('Agent timeout - task too complex');
    }
    
    // Execute iteration...
}
```

### 5. Cache Planning Results

```php
// For similar tasks, reuse plans
$planCache = [];
$planKey = hash('sha256', $task);

if (isset($planCache[$planKey])) {
    $plan = $planCache[$planKey];
} else {
    $plan = $this->createPlan($task);
    $planCache[$planKey] = $plan;
}
```

## ✅ Checkpoint

Before finishing, make sure you understand:

- [ ] Plan-Execute-Reflect-Adjust pattern
- [ ] When to use extended thinking
- [ ] How to implement PERA manually
- [ ] Pattern selection for different tasks
- [ ] Cost implications of advanced patterns
- [ ] Performance vs. quality tradeoffs

## 🚀 Congratulations!

You've completed the **entire Getting Started series**! 🎉

You now know:
- ✅ Core agent concepts
- ✅ Building basic agents
- ✅ ReAct loop implementation
- ✅ Multi-tool agents
- ✅ Production-ready patterns
- ✅ Advanced reasoning patterns

### What's Next?

#### Design Patterns (Recommended Next Step)

Learn industry-standard patterns for production code:

- **[Design Patterns Tutorial](./06-Design-Patterns.md)** - Factory, Builder, Observer patterns
- **[Design Patterns Guide](../../DesignPatterns.md)** - Complete pattern reference

#### Specialized Agent Patterns

Explore specialized agents for specific use cases:

- **[RAG Agent](../../RAGAgent.md)** - Retrieval-augmented generation
- **[Hierarchical Agents](../../HierarchicalAgent.md)** - Manager-worker patterns
- **[Autonomous Agents](../../AutonomousAgent.md)** - Self-directed agents
- **[Multi-Agent Systems](../../MultiAgent.md)** - Collaborative agents

#### Advanced Topics

Deep dive into advanced concepts:

- **[Tree of Thoughts](../../TreeOfThoughtsAgent.md)** - Explore multiple solution paths
- **[Debate Systems](../../DebateSystem.md)** - Multi-agent reasoning
- **[Learning Agents](../../LearningAgent.md)** - Agents that improve over time
- **[Context Management](../../ContextManagement.md)** - Handle large contexts

#### Build Real Applications

Check out complete examples:

- Customer support bot
- Research assistant
- Data analysis agent
- Content generation pipeline
- Code review assistant

See the [examples directory](../../../examples/) for inspiration!

## 💡 Key Takeaways

1. **Choose the right pattern** - Simpler is often better
2. **Extended thinking costs tokens** - Use judiciously
3. **PERA for complex tasks** - Planning helps with strategy
4. **Monitor costs** - Advanced patterns are expensive
5. **Start simple, add complexity** - Only when needed
6. **Test thoroughly** - Complex patterns have more failure modes

## 📚 Further Reading

- **[Design Patterns Tutorial](./06-Design-Patterns.md)** - Next recommended tutorial
- [Plan-Execute-Reflect Agent](../../PlanExecuteAgent.md)
- [Best Practices Guide](../../BestPractices.md)
- [PERA Example](../../../examples/pera_agent_example.php)
- [Extended Thinking Guide](https://docs.anthropic.com/en/docs/build-with-claude/extended-thinking)
- [Agent Patterns Overview](../../agent-selection-guide.md)

## 🎓 Your Agent Journey

```
✅ Tutorial 0: Concepts
✅ Tutorial 1: First Agent  
✅ Tutorial 2: ReAct Loop
✅ Tutorial 3: Multi-Tool
✅ Tutorial 4: Production
✅ Tutorial 5: Advanced Patterns

→ You're now ready to build production AI agents!
```

---

**Thank you for completing the Getting Started series!** 🚀

We can't wait to see what you build. Share your agent projects, ask questions, and contribute back to the community!

---

*Last Updated: December 2024*  
*Framework Version: 2.0+*

