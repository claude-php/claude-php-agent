# Template Catalog

Complete reference for all 22 templates in the Claude PHP Agent framework.

## Quick Reference

| Category | Templates | Description |
|----------|-----------|-------------|
| **Agents** | 5 | Basic and foundational patterns |
| **Chatbots** | 2 | Conversational agents with memory |
| **RAG** | 3 | Retrieval-augmented generation |
| **Workflows** | 2 | Multi-step and multi-agent systems |
| **Specialized** | 5 | Domain-specific agents |
| **Production** | 2 | Enterprise-ready configurations |

## Category: Agents

### basic-agent.json

**Difficulty**: Beginner | **Setup**: 5 minutes | **Icon**: 🤖

A simple agent with one tool for basic tasks. Perfect for getting started with the framework.

**Use Cases:**
- Learning the framework
- Simple automation tasks
- Single-tool operations

**Configuration:**
```json
{
  "agent_type": "Agent",
  "model": "claude-sonnet-4-5",
  "max_iterations": 5
}
```

**Example:**
```php
$agent = TemplateManager::instantiate('basic-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'tools' => [$calculator]
]);

$result = $agent->run('What is 15 + 27?');
```

---

### react-agent.json

**Difficulty**: Intermediate | **Setup**: 10 minutes | **Icon**: 🔄

Reason-Act-Observe pattern agent for general-purpose autonomous tasks. Uses iterative reasoning and tool execution.

**Use Cases:**
- Research tasks
- Data processing
- Multi-step problem solving
- Autonomous decision making

**Configuration:**
```json
{
  "agent_type": "ReactAgent",
  "model": "claude-sonnet-4-5",
  "max_iterations": 10
}
```

**Example:**
```php
$agent = TemplateManager::instantiate('react-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'tools' => [$searchTool, $calculatorTool]
]);

$result = $agent->run('Research PHP 8.3 features and summarize them');
```

---

### chain-of-thought-agent.json

**Difficulty**: Intermediate | **Setup**: 10 minutes | **Icon**: 🧠

Step-by-step reasoning agent that breaks down complex problems into logical steps for better accuracy.

**Use Cases:**
- Complex problem solving
- Mathematical reasoning
- Logic puzzles
- Analytical tasks

**Example:**
```php
$agent = TemplateManager::instantiate('chain-of-thought-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

$result = $agent->run('If 5 machines can make 5 widgets in 5 minutes, how long does it take 100 machines to make 100 widgets?');
```

---

### reflex-agent.json

**Difficulty**: Beginner | **Setup**: 5 minutes | **Icon**: ⚡

Rule-based response agent for fast, deterministic reactions to specific conditions.

**Use Cases:**
- FAQ responses
- Simple automation
- Conditional triggers
- Pattern matching

---

### model-based-agent.json

**Difficulty**: Intermediate | **Setup**: 15 minutes | **Icon**: 🗺️

State-aware decision making agent that maintains an internal model of the world for planning and prediction.

**Use Cases:**
- Planning tasks
- Simulation
- State-based decision making
- What-if analysis

## Category: Advanced Agents

### reflection-agent.json

**Difficulty**: Advanced | **Setup**: 15 minutes | **Icon**: 🪞

Self-improvement loop agent that generates output, reflects on quality, and refines until meeting quality threshold.

**Use Cases:**
- Code generation
- Content writing
- Quality-critical outputs
- Iterative improvement

**Configuration:**
```json
{
  "agent_type": "ReflectionAgent",
  "max_refinements": 3,
  "quality_threshold": 8
}
```

**Example:**
```php
$agent = TemplateManager::instantiate('reflection-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

$result = $agent->run('Write a function to validate email addresses with comprehensive error handling');
```

---

### plan-execute-agent.json

**Difficulty**: Advanced | **Setup**: 15 minutes | **Icon**: 📋

Multi-step planning agent that creates a plan first, then executes systematically for complex tasks.

**Use Cases:**
- Project planning
- Complex workflows
- Multi-step tasks
- Strategic execution

**Example:**
```php
$agent = TemplateManager::instantiate('plan-execute-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'allow_replan' => true
]);

$result = $agent->run('Create a complete marketing campaign for a new product launch');
```

---

### tree-of-thoughts-agent.json

**Difficulty**: Advanced | **Setup**: 20 minutes | **Icon**: 🌳

Exploration agent that evaluates multiple reasoning paths simultaneously.

**Use Cases:**
- Creative writing
- Optimization problems
- Strategy evaluation
- Multi-path exploration

---

### maker-agent.json

**Difficulty**: Advanced | **Setup**: 20 minutes | **Icon**: ⚙️

Million-step reliable task agent using massively decomposed agentic processes with near-zero error rates.

**Use Cases:**
- Long sequences
- Organization-level tasks
- High-reliability requirements
- Million-step processes

---

### adaptive-agent.json

**Difficulty**: Advanced | **Setup**: 25 minutes | **Icon**: 🎯

Intelligent meta-agent that automatically selects the best agent type for each task with quality validation.

**Use Cases:**
- Auto-optimization
- Quality assurance
- Agent selection
- Adaptive workflows

## Category: Specialized Agents

### hierarchical-agent.json

**Difficulty**: Advanced | **Setup**: 20 minutes | **Icon**: 👑

Master-worker pattern agent that delegates specialized subtasks to worker agents.

**Use Cases:**
- Complex reports
- Multi-domain tasks
- Team coordination
- Specialized workflows

**Example:**
```php
$agent = TemplateManager::instantiate('hierarchical-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

// Register workers
$agent->registerWorker('researcher', $researchAgent);
$agent->registerWorker('writer', $writerAgent);

$result = $agent->run('Research AI trends and write a report');
```

---

### coordinator-agent.json

**Difficulty**: Advanced | **Setup**: 25 minutes | **Icon**: 🎭

Multi-agent orchestration and load balancing for distributed systems.

**Use Cases:**
- Agent networks
- Load balancing
- Distributed systems
- Multi-agent coordination

---

### dialog-agent.json

**Difficulty**: Intermediate | **Setup**: 15 minutes | **Icon**: 💬

Conversational AI agent with context tracking and memory for natural multi-turn conversations.

**Use Cases:**
- Customer service
- Virtual assistants
- Chat interfaces
- Interactive support

**Example:**
```php
$agent = TemplateManager::instantiate('dialog-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'context_window' => 5
]);

$result1 = $agent->run('Hello, my name is Alice');
$result2 = $agent->run('What is my name?'); // Agent remembers context
```

---

### intent-classifier-agent.json

**Difficulty**: Intermediate | **Setup**: 15 minutes | **Icon**: 🎯

Intent recognition and entity extraction agent for command routing.

**Use Cases:**
- Command routing
- Intent detection
- Entity extraction
- NLU systems

---

### monitoring-agent.json

**Difficulty**: Intermediate | **Setup**: 20 minutes | **Icon**: 📊

System monitoring and anomaly detection agent for real-time performance tracking.

**Use Cases:**
- System monitoring
- Performance tracking
- Anomaly detection
- Incident management

## Category: RAG & Knowledge

### rag-agent.json

**Difficulty**: Intermediate | **Setup**: 20 minutes | **Icon**: 📚

Retrieval-Augmented Generation agent for document retrieval and question answering.

**Use Cases:**
- Document Q&A
- Knowledge retrieval
- Information extraction
- Context-aware responses

**Example:**
```php
$agent = TemplateManager::instantiate('rag-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'top_k' => 5
]);

// Add documents to vector store
$result = $agent->run('What are the key features of PHP 8.3?');
```

---

### memory-chatbot.json

**Difficulty**: Intermediate | **Setup**: 20 minutes | **Icon**: 🧠

Conversational agent with persistent memory for maintaining context across sessions.

**Use Cases:**
- Personal assistants
- Long-term conversations
- User preference tracking
- Session continuity

---

### knowledge-manager.json

**Difficulty**: Advanced | **Setup**: 25 minutes | **Icon**: 🗄️

Knowledge management and retrieval agent for organizing and accessing shared information.

**Use Cases:**
- Knowledge bases
- Document management
- Information retrieval
- Shared memory systems

## Category: Workflows

### sequential-tasks-agent.json

**Difficulty**: Intermediate | **Setup**: 15 minutes | **Icon**: 🔗

Multi-step workflow execution agent that processes tasks in sequence with state management.

**Use Cases:**
- Data pipelines
- Workflow automation
- Sequential processing
- State machines

**Example:**
```php
$agent = TemplateManager::instantiate('sequential-tasks-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY')
]);

$result = $agent->run('Process user registration: validate email, create account, send welcome email');
```

---

### debate-system.json

**Difficulty**: Advanced | **Setup**: 25 minutes | **Icon**: 🗣️

Multi-agent debate and consensus building system for solution refinement.

**Use Cases:**
- Decision making
- Solution refinement
- Consensus building
- Multi-perspective analysis

## Category: Production

### production-agent.json

**Difficulty**: Advanced | **Setup**: 30 minutes | **Icon**: 🏭

Production-ready agent with comprehensive error handling, logging, monitoring, and retry logic.

**Features:**
- Retry logic with exponential backoff
- Comprehensive error handling
- Structured logging
- Performance monitoring
- Health checks

**Use Cases:**
- Production deployments
- Enterprise applications
- Mission-critical tasks
- High-reliability systems

**Example:**
```php
$agent = TemplateManager::instantiate('production-agent', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'retry_max_attempts' => 3,
    'retry_delay_ms' => 1000,
    'timeout' => 30.0
]);

$agent->onError(function($error, $attempt) {
    logger()->error('Agent error', [
        'error' => $error->getMessage(),
        'attempt' => $attempt
    ]);
});

$result = $agent->run('Critical business task');
```

---

### async-batch-processor.json

**Difficulty**: Advanced | **Setup**: 25 minutes | **Icon**: ⚡

Concurrent task processing agent using AMPHP for high-throughput parallel execution.

**Features:**
- Concurrent execution
- Batch processing
- Progress tracking
- Error handling per task

**Use Cases:**
- Batch processing
- Concurrent execution
- High-throughput tasks
- Parallel workflows

**Example:**
```php
$agent = TemplateManager::instantiate('async-batch-processor', [
    'api_key' => getenv('ANTHROPIC_API_KEY'),
    'concurrency' => 5,
    'batch_size' => 10
]);

$tasks = [
    'Process document 1',
    'Process document 2',
    // ... more tasks
];

$results = $agent->processBatch($tasks);
```

## Template Comparison

### By Difficulty

**Beginner (2 templates)**
- Basic Agent
- Reflex Agent

**Intermediate (9 templates)**
- ReAct Agent
- Chain-of-Thought Agent
- Model-Based Agent
- Dialog Agent
- Intent Classifier Agent
- Monitoring Agent
- RAG Agent
- Memory Chatbot
- Sequential Tasks Agent

**Advanced (11 templates)**
- Reflection Agent
- Plan-Execute Agent
- Tree-of-Thoughts Agent
- MAKER Agent
- Adaptive Agent
- Hierarchical Agent
- Coordinator Agent
- Knowledge Manager
- Debate System
- Production Agent
- Async Batch Processor

### By Setup Time

**Quick (≤10 minutes): 5 templates**
- Basic Agent
- ReAct Agent
- Chain-of-Thought Agent
- Reflex Agent
- Sequential Tasks Agent

**Medium (15-20 minutes): 10 templates**
- Model-Based Agent
- Reflection Agent
- Plan-Execute Agent
- Tree-of-Thoughts Agent
- MAKER Agent
- Hierarchical Agent
- Dialog Agent
- Intent Classifier Agent
- Monitoring Agent
- RAG Agent
- Memory Chatbot

**Extended (25-30 minutes): 7 templates**
- Adaptive Agent
- Coordinator Agent
- Knowledge Manager
- Debate System
- Async Batch Processor
- Production Agent

### By Scalability

| Template | Max Steps | Scalability |
|----------|-----------|-------------|
| Reflex Agent | ~10 | Instant |
| Basic Agent | ~100 | Low |
| ReAct Agent | ~500 | Medium |
| Plan-Execute Agent | ~1,000 | High |
| Hierarchical Agent | ~5,000 | Very High |
| MAKER Agent | 1,000,000+ | Extreme |

## See Also

- [Template System Guide](README.md) - Complete usage guide
- [Creating Templates](CREATING_TEMPLATES.md) - Template creation guide
- [Templates Tutorial](../tutorials/Templates_Tutorial.md) - Step-by-step tutorial
