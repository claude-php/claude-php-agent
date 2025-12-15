# Tutorial 0: Introduction to Agentic AI

**Time: 20 minutes** | **Difficulty: Beginner**

Welcome! This tutorial introduces the fundamental concepts of AI agents and sets the foundation for building your own intelligent agents with the Claude PHP Agent Framework.

## 🎯 What You'll Learn

- What AI agents are and how they differ from chatbots
- Core concepts: autonomy, tools, and the ReAct pattern
- When to use agents vs simple API calls
- The agent taxonomy and which agent to choose

## 🤖 What is an AI Agent?

An **AI agent** is an AI system that can:

1. **Perceive** its environment
2. **Reason** about what to do
3. **Act** by using tools and taking actions
4. **Learn** from the results of its actions

### Agent vs Chatbot

| Aspect              | Chatbot          | Agent                       |
| ------------------- | ---------------- | --------------------------- |
| **Purpose**         | Answer questions | Accomplish tasks            |
| **Interaction**     | Conversational   | Tool-based + conversational |
| **Decision Making** | Reactive         | Autonomous                  |
| **Tools**           | None             | Multiple specialized tools  |
| **Iterations**      | Single turn      | Multi-step loops            |

**Example - Chatbot:**

```
User: "What's 157 × 89?"
Chatbot: "I calculate that to be approximately 13,973"
```

**Example - Agent:**

```
User: "What's 157 × 89?"
Agent: [Thinks] "I need exact precision"
       [Uses calculator tool] calculate("157 * 89")
       [Receives] 13973
       [Responds] "157 × 89 equals exactly 13,973"
```

## 🧠 Core Concepts

### 1. Autonomy

Agents make their own decisions about:

- Which tools to use
- When to use them
- How to interpret results
- When the task is complete

### 2. Tools (Function Calling)

Tools give agents **capabilities** beyond their training data:

```php
// Weather tool
$weatherTool = Tool::create('get_weather')
    ->description('Get current weather for a location')
    ->stringParam('location', 'City name or coordinates')
    ->handler(function($input) {
        return fetchWeatherAPI($input['location']);
    });
```

**Common Tool Types:**

- 📊 **Data tools**: Databases, APIs, files
- 🧮 **Computation tools**: Calculators, analyzers
- 🔍 **Search tools**: Web search, document search
- 💾 **Memory tools**: Save and recall information
- 🛠️ **System tools**: Execute commands, run scripts

### 3. The ReAct Pattern

**ReAct** = **Reason** + **Act** + **Observe**

This is the core loop that powers autonomous agents:

```
┌─────────────────────────────────────┐
│  1. REASON                          │
│     "What should I do next?"        │
│     "What information do I need?"   │
└────────────┬────────────────────────┘
             ↓
┌─────────────────────────────────────┐
│  2. ACT                             │
│     Execute a tool                  │
│     Or provide final answer         │
└────────────┬────────────────────────┘
             ↓
┌─────────────────────────────────────┐
│  3. OBSERVE                         │
│     Analyze tool results            │
│     Update understanding            │
└────────────┬────────────────────────┘
             │
        ┌────┴────┐
        │  Done?  │
        └────┬────┘
             │
       No ───┴─── Yes
       │           │
       └──> Repeat └──> Answer
```

**Why It's Powerful:**

- Handles multi-step tasks
- Adapts based on results
- Can self-correct
- Works with incomplete information

## 📊 Agent Taxonomy

The Claude PHP Agent Framework provides many specialized agent types:

### Simple Agents

- **ReflexAgent**: Immediate stimulus-response (fastest)
- **ReactAgent**: Standard ReAct loop with tools
- **ChainOfThoughtAgent**: Step-by-step reasoning

### Advanced Agents

- **PlanExecuteReflectAgent**: Plans, executes, and self-corrects
- **AutonomousAgent**: Long-running with persistent goals
- **HierarchicalAgent**: Master-worker delegation

### Specialized Agents

- **RAGAgent**: Retrieval-augmented generation
- **TreeOfThoughtsAgent**: Explores multiple solution paths
- **LearningAgent**: Adapts based on experience

**Don't know which to use?** Use **AdaptiveAgentService** - it automatically selects the best agent for your task!

## 🎪 When to Use Agents

### ✅ Use Agents When:

- Task requires multiple steps
- Need to access external data/APIs
- Requires tool usage (calculation, search, etc.)
- Task complexity varies
- Need autonomous decision-making

### ❌ Use Simple API Calls When:

- Single question answering
- No external data needed
- No tools required
- Response from training data sufficient
- Speed is critical

## 💡 Real-World Examples

### Example 1: Research Assistant

```php
$agent = Agent::create($client)
    ->withTool($searchTool)
    ->withTool($documentTool)
    ->withTool($summaryTool);

$result = $agent->run(
    "Research the top 3 AI breakthroughs in " . date("Y") . " and create a summary"
);

// Agent will:
// 1. Search for AI breakthroughs
// 2. Analyze search results
// 3. Read relevant documents
// 4. Summarize findings
```

### Example 2: Data Analyst

```php
$agent = Agent::create($client)
    ->withTool($databaseTool)
    ->withTool($calculatorTool)
    ->withTool($chartTool);

$result = $agent->run(
    "Analyze sales data for Q4 2024 and identify top performing products"
);

// Agent will:
// 1. Query database for Q4 sales
// 2. Calculate metrics
// 3. Identify top products
// 4. Generate charts
```

### Example 3: Customer Support

```php
$agent = Agent::create($client)
    ->withTool($knowledgeBaseTool)
    ->withTool($ticketTool)
    ->withTool($emailTool);

$result = $agent->run(
    "Help customer with order #12345 - they haven't received it"
);

// Agent will:
// 1. Search knowledge base for policies
// 2. Look up order status
// 3. Check shipping information
// 4. Create support ticket or send email
```

## 🏗️ Building Blocks

Every agent system has three core components:

### 1. **Model** (The Brain)

```php
'model' => 'claude-sonnet-4-20250514'
```

### 2. **Tools** (The Hands)

```php
$tools = [
    $calculatorTool,
    $weatherTool,
    $databaseTool,
];
```

### 3. **Agent** (The Orchestrator)

```php
$agent = Agent::create($client)
    ->withTools($tools)
    ->run($task);
```

## 🎓 Key Takeaways

1. **Agents are autonomous** - They decide what to do and when
2. **Tools extend capabilities** - Give agents access to external systems
3. **ReAct enables multi-step reasoning** - Agents iterate until task complete
4. **Choose the right agent type** - Different patterns for different tasks
5. **Start simple** - Begin with ReactAgent, add complexity as needed

## ✅ Checkpoint

Before moving on, make sure you understand:

- [ ] The difference between agents and chatbots
- [ ] What tools are and why they're important
- [ ] The ReAct pattern (Reason → Act → Observe)
- [ ] When to use agents vs simple API calls
- [ ] The basic agent taxonomy

## 🚀 Next Steps

Ready to build your first agent? Let's start with a simple example!

**[Tutorial 1: Your First Agent →](./01-First-Agent.md)**

You'll create a working calculator agent that demonstrates the complete agent lifecycle.

## 📚 Further Reading

- [Main Documentation](../../../README.md)
- [Agent Selection Guide](../../agent-selection-guide.md)
- [Tools Documentation](../../Tools.md)
- [ReAct Paper](https://arxiv.org/abs/2210.03629)
