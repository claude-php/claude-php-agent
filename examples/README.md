# Claude PHP Agent - Examples Documentation

## Overview

This directory contains 16 comprehensive examples demonstrating various features and patterns of the Claude PHP Agent framework. Each example is production-ready and demonstrates best practices.

---

## Quick Start Examples

### 1. Basic Agent (`basic_agent.php`)

**What it demonstrates:**

- Creating a simple agent with a single tool
- Tool definition and handler implementation
- Input validation and error handling
- Monitoring tool calls and token usage

**Key Features:**

- ✅ Calculator tool with safe expression evaluation
- ✅ System prompts
- ✅ Token usage tracking
- ✅ Tool call monitoring

**Usage:**

```bash
php examples/basic_agent.php
```

**Output:**

- Solves: `(25 * 17) + (100 / 4)`
- Shows iterations, tokens used, and tool calls

---

### 2. Multi-Tool Agent (`multi_tool_agent.php`)

**What it demonstrates:**

- Agent with multiple tools
- Tool selection reasoning
- Different tool types (math, string, datetime, random)
- Callback monitoring

**Key Features:**

- ✅ 4 different tools (calculator, string ops, datetime, random)
- ✅ Intelligent tool selection
- ✅ Real-time tool execution logging
- ✅ Multiple task execution

**Usage:**

```bash
php examples/multi_tool_agent.php
```

**Tasks Demonstrated:**

- Mathematical calculations
- String manipulation
- Date/time operations
- Random number generation
- Multi-tool workflows

---

## Advanced Agent Patterns

### 3. Reflection Agent (`reflection_agent.php`)

**What it demonstrates:**

- Generate-Reflect-Refine pattern
- Iterative quality improvement
- Quality scoring and thresholds
- Multiple refinement rounds

**Key Features:**

- ✅ Self-reflection and critique
- ✅ Quality scoring (1-10)
- ✅ Configurable quality thresholds
- ✅ Refinement history tracking

**Usage:**

```bash
php examples/reflection_agent.php
```

**Examples:**

- Code generation with quality checks
- Writing improvement with iterative refinement

---

### 4. Hierarchical Agent (`hierarchical_agent.php`)

**What it demonstrates:**

- Master-worker pattern
- Specialized worker agents
- Task decomposition and delegation
- Coordinated execution

**Key Features:**

- ✅ Master coordinator agent
- ✅ Specialized workers (math, writing, research)
- ✅ Task delegation
- ✅ Result aggregation

**Usage:**

```bash
php examples/hierarchical_agent.php
```

**Workers:**

- Math Agent: Numerical calculations
- Writing Agent: Content creation
- Research Agent: Information synthesis

---

### 5. Chain of Thought (CoT) Agent (`cot_example.php`)

**What it demonstrates:**

- Zero-shot CoT reasoning
- Few-shot CoT with examples
- Step-by-step problem solving
- Mathematical reasoning

**Key Features:**

- ✅ Zero-shot CoT trigger
- ✅ Few-shot examples
- ✅ Explicit reasoning steps
- ✅ Math problem solving

**Usage:**

```bash
php examples/cot_example.php
```

**Examples:**

- Multi-step mathematical calculations
- Discount and pricing problems

---

### 6. Tree of Thoughts (ToT) Agent (`tot_example.php`)

**What it demonstrates:**

- Tree-based exploration
- Best-first search strategy
- Multiple solution paths
- Complex problem solving

**Key Features:**

- ✅ Branching factor configuration
- ✅ Depth limiting
- ✅ Path tracking
- ✅ Node evaluation

**Usage:**

```bash
php examples/tot_example.php
```

**Example:**

- Solving: "Use 3, 5, 7, 11 to make 24"

---

## Specialized Capabilities

### 7. RAG Agent (`rag_example.php`)

**What it demonstrates:**

- Retrieval-Augmented Generation
- Document management
- Knowledge base queries
- Source tracking

**Key Features:**

- ✅ Document ingestion
- ✅ Semantic retrieval
- ✅ Context-aware responses
- ✅ Source attribution

**Usage:**

```bash
php examples/rag_example.php
```

**Documents:**

- PHP Basics
- Object-Oriented Programming
- Claude API documentation

---

### 8. Autonomous Agent (`autonomous_example.php`)

**What it demonstrates:**

- Goal-oriented behavior
- State persistence across sessions
- Progress tracking
- Multi-session execution

**Key Features:**

- ✅ Goal definition
- ✅ State file persistence
- ✅ Session management
- ✅ Progress percentage
- ✅ Action history

**Usage:**

```bash
# Basic autonomous agent
php examples/autonomous_example.php

# Advanced autonomous agent with detailed examples
php examples/advanced_autonomous_agent.php

# Local test (no API key required)
php examples/autonomous_agent_local_test.php
```

**State Management:**

- Session tracking
- Conversation history
- Action history persistence
- Goal tracking and subgoals
- State recovery across sessions

**Additional Examples:**

- `advanced_autonomous_agent.php` - Multi-session execution, state persistence demo
- `autonomous_agent_local_test.php` - Unit-test style examples without API calls

---

### 9. Streaming Example (`streaming_example.php`)

**What it demonstrates:**

- Real-time token streaming
- StreamingLoop integration
- Console output handlers
- Progressive responses

**Key Features:**

- ✅ Real-time streaming
- ✅ Console handler
- ✅ Tool execution with streaming
- ✅ Token-by-token output

**Usage:**

```bash
php examples/streaming_example.php
```

---

### 9b. Advanced Streaming (`streaming_advanced.php`)

**What it demonstrates:**

- Multiple concurrent stream handlers
- Stream statistics and performance monitoring
- File logging and PSR-3 logging integration
- Custom callback handlers
- Error handling in streams
- Progress tracking

**Key Features:**

- ✅ Console, File, and Callback handlers simultaneously
- ✅ Real-time performance metrics (bytes/sec, chunks/sec)
- ✅ Iteration and tool execution callbacks
- ✅ Timestamped file logging
- ✅ Stream statistics (duration, throughput, chunk count)

**Usage:**

```bash
php examples/streaming_advanced.php

# Show detailed log file
php examples/streaming_advanced.php --show-log
```

**Output:**

- Real-time console output with emoji indicators
- Detailed log file with timestamps and event types
- Performance statistics (speed, duration, chunks)
- Token usage tracking per iteration

---

### 10. Debate System (`debate_example.php`)

**What it demonstrates:**

- Multi-agent debates
- Pro/Con pattern
- Round table discussions
- Agreement scoring
- Synthesis generation

**Key Features:**

- ✅ Pro/Con debates
- ✅ Round table format
- ✅ Multiple perspectives
- ✅ Agreement metrics
- ✅ Debate synthesis

**Usage:**

```bash
php examples/debate_example.php
```

**Topics:**

- Remote work policies
- Technology stack selection

---

## Production-Ready Patterns

### 11. Production Agent (`production_agent.php`)

**What it demonstrates:**

- Production best practices
- Error handling and retry logic
- Logging and monitoring
- Token tracking and cost estimation
- Configuration management

**Key Features:**

- ✅ Custom logger integration
- ✅ Retry configuration
- ✅ Timeout handling
- ✅ Token cost estimation
- ✅ Input validation
- ✅ Error recovery
- ✅ Session summaries

**Usage:**

```bash
php examples/production_agent.php
```

**Production Features:**

- PSR-3 logging
- Retry with exponential backoff
- Token usage tracking
- Cost estimation
- Error callbacks

---

### 12. AMPHP Async Example (`amphp_async_example.php`)

**What it demonstrates:**

- AMPHP-powered concurrent execution
- Batch processing with concurrency limits
- Parallel tool execution
- Promise-based async workflows
- Performance gains from parallelism

**Key Features:**

- ✅ Concurrent batch processing
- ✅ Parallel tool executor
- ✅ Promise-based async API
- ✅ Batched execution with concurrency limits
- ✅ Real-time performance comparison

**Usage:**

```bash
php examples/amphp_async_example.php
```

**Examples Covered:**

1. Batch processing multiple agent tasks concurrently
2. Parallel tool execution (weather, time, calculator)
3. Promise-based async workflows
4. Batched tool execution with concurrency limits
5. Async batch processing with promises

---

### 13. Alert Agent (`alert_agent.php`)

**What it demonstrates:**

- Intelligent alert processing and notification
- LLM-enhanced alert messages
- Multi-channel notifications
- Metric-based threshold alerting
- Alert history tracking

**Key Features:**

- ✅ Multi-channel routing (console, log file)
- ✅ LLM-enhanced alert messages
- ✅ Alert severity levels
- ✅ Metric integration
- ✅ Context-aware notifications

**Usage:**

```bash
php examples/alert_agent.php
```

**Examples:**

- Basic informational alerts
- Metric-based threshold alerts
- Critical alerts with metrics
- Natural language alert processing

---

### 14. Advanced Alert Agent (`advanced_alert_agent.php`)

**What it demonstrates:**

- Production alert system patterns
- Multiple notification channels (email, webhook, Slack)
- Custom message templates
- Alert aggregation and deduplication
- Conditional channel routing

**Key Features:**

- ✅ Multi-channel setup (email, webhook, Slack, PagerDuty)
- ✅ Custom templates per severity
- ✅ Alert aggregation
- ✅ Deduplication logic
- ✅ Conditional routing based on severity
- ✅ Alert statistics and reporting

**Usage:**

```bash
php examples/advanced_alert_agent.php
```

**Scenarios Covered:**

1. System monitoring (CPU, memory, disk)
2. Application performance monitoring
3. Error rate tracking with aggregation
4. Natural language alert processing

---

### 15. Context Management (`context_management.php`)

**What it demonstrates:**

- Context window monitoring and management
- Automatic context compaction
- Token estimation and tracking
- Message history manipulation
- Custom compaction strategies

**Key Features:**

- ✅ Token counting for messages and tools
- ✅ Automatic context compaction
- ✅ Usage percentage monitoring
- ✅ Context editor utilities
- ✅ Custom compaction strategies
- ✅ Agent integration

**Usage:**

```bash
php examples/context_management.php
```

**Examples Covered:**

1. Token estimation for text and messages
2. Context editor utilities
3. Keeping recent messages
4. Context manager with automatic compaction
5. Agent with context management enabled
6. Custom compaction strategies

---

### 16. Chain Composition Demo (`chain-composition-demo.php`)

**What it demonstrates:**

- Complex chain workflows
- All chain types (Sequential, Parallel, Router, Transform, LLM)
- Chain composition patterns
- Error handling in chains
- Conditional execution

**Key Features:**

- ✅ 10+ composition examples
- ✅ Sequential pipelines
- ✅ Parallel execution
- ✅ Conditional routing
- ✅ Data transformation
- ✅ Nested compositions
- ✅ Chain callbacks
- ✅ Error recovery

**Usage:**

```bash
php examples/chain-composition-demo.php
```

**Examples Covered:**

1. Simple LLM Chain
2. Sequential Chain (multi-step pipeline)
3. Parallel Chain (concurrent analysis)
4. Router Chain (conditional routing)
5. Transform Chain (data transformation)
6. Complex nested composition
7. Chain as Tool for agents
8. Callbacks for monitoring
9. Conditional sequential execution
10. Error handling and recovery

---

## Running Examples

### Prerequisites

1. **API Key Setup:**
   Create a `.env` file in the project root:

   ```env
   ANTHROPIC_API_KEY=your_api_key_here
   ```

2. **Dependencies:**
   ```bash
   composer install
   ```

### Run Individual Examples

```bash
# Basic examples
php examples/basic_agent.php
php examples/multi_tool_agent.php

# Advanced patterns
php examples/reflection_agent.php
php examples/hierarchical_agent.php
php examples/cot_example.php
php examples/tot_example.php

# Specialized
php examples/rag_example.php
php examples/autonomous_example.php
php examples/streaming_example.php
php examples/streaming_advanced.php
php examples/debate_example.php

# Async & Performance
php examples/amphp_async_example.php

# Alerts & Monitoring
php examples/alert_agent.php
php examples/advanced_alert_agent.php

# Production & Management
php examples/production_agent.php
php examples/context_management.php
php examples/chain-composition-demo.php
```

### Run All Examples

```bash
for f in examples/*.php; do
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Running: $f"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    php "$f"
    echo ""
done
```

---

## Example Categories

### 🎯 **Getting Started**

- `basic_agent.php` - Start here
- `multi_tool_agent.php` - Multiple tools

### 🧠 **Reasoning Patterns**

- `cot_example.php` - Chain of Thought
- `tot_example.php` - Tree of Thoughts
- `reflection_agent.php` - Self-reflection

### 🏗️ **Architectural Patterns**

- `hierarchical_agent.php` - Master-worker
- `debate_example.php` - Multi-agent collaboration
- `autonomous_example.php` - Goal-oriented

### 🔧 **Advanced Features**

- `rag_example.php` - Knowledge retrieval
- `streaming_example.php` - Real-time output
- `streaming_advanced.php` - Advanced streaming with multiple handlers
- `chain-composition-demo.php` - Workflows

### 📊 **Monitoring & Alerts**

- `alert_agent.php` - Intelligent alerting
- `advanced_alert_agent.php` - Production alert system

### ⚡ **Async & Performance**

- `amphp_async_example.php` - Concurrent execution

### 🚀 **Production Ready**

- `production_agent.php` - Best practices
- `context_management.php` - Context window management

---

## Key Concepts Demonstrated

### Tool System

- Tool definition with parameters
- Handler implementation
- Input validation
- Error handling
- Tool selection reasoning

### Agent Configuration

- Model selection
- Token limits
- Iteration limits
- System prompts
- Timeout configuration

### Callbacks & Monitoring

- Iteration callbacks
- Tool execution callbacks
- Error callbacks
- Token tracking
- Performance monitoring

### Error Handling

- Retry logic
- Exponential backoff
- Graceful degradation
- Error recovery
- Logging

### State Management

- In-memory state
- File persistence
- Session tracking
- Goal progress
- History management

### Chain Composition

- Sequential execution
- Parallel processing
- Conditional routing
- Data transformation
- Error propagation

---

## Testing Examples

All examples include:

- ✅ Valid PHP syntax
- ✅ Error handling
- ✅ Proper documentation
- ✅ Clear output formatting
- ✅ Token usage reporting

To validate all examples:

```bash
cd examples
for f in *.php; do
    echo "Validating $f..."
    php -l "$f"
done
```

---

## Extending Examples

### Adding a New Tool

```php
$myTool = Tool::create('my_tool')
    ->description('What the tool does')
    ->stringParam('input', 'Description')
    ->handler(function (array $input): string {
        // Your logic here
        return "result";
    });

$agent->withTool($myTool);
```

### Creating Custom Agents

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Config\AgentConfig;

$config = AgentConfig::fromArray([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 4096,
    'max_iterations' => 10,
]);

$agent = Agent::create($client)
    ->withConfig($config)
    ->withSystemPrompt('Your custom prompt')
    ->onIteration(function($iter, $response, $context) {
        // Monitor execution
    });
```

---

## Troubleshooting

### Common Issues

1. **Missing API Key:**

   ```
   Error: ANTHROPIC_API_KEY not set
   ```

   Solution: Create `.env` file with your API key

2. **Timeout Errors:**
   Increase timeout in config:

   ```php
   'timeout' => 60.0
   ```

3. **Token Limits:**
   Adjust max_tokens:
   ```php
   'max_tokens' => 8192
   ```

---

## Best Practices from Examples

1. **Always validate tool inputs** (see `production_agent.php`)
2. **Use appropriate error handling** (see all examples)
3. **Track token usage** (see `production_agent.php`)
4. **Log important events** (see `production_agent.php`)
5. **Set reasonable iteration limits** (see all examples)
6. **Use system prompts effectively** (see all examples)
7. **Monitor tool execution** (see `multi_tool_agent.php`)
8. **Persist state when needed** (see `autonomous_example.php`)
9. **Use callbacks for observability** (see `production_agent.php`)
10. **Compose chains for complex workflows** (see `chain-composition-demo.php`)

---

## Performance Considerations

From the examples:

- **Token Efficiency:** Track and optimize token usage
- **Iteration Limits:** Set appropriate max_iterations
- **Timeout Configuration:** Balance responsiveness and completion
- **Tool Selection:** Design tools for specific purposes
- **Caching:** Use conversation memory appropriately
- **Parallel Execution:** Use ParallelChain for independent tasks
- **Retry Logic:** Configure retries for production reliability

---

## Further Reading

- [README.md](../README.md) - Project overview
- [FEATURES.md](../FEATURES.md) - Complete feature list
- [Test Coverage](../TEST_COVERAGE_COMPLETE.md) - Testing documentation

---

## Contributing

To add a new example:

1. Create `my_example.php` in `/examples`
2. Include comprehensive comments
3. Add error handling
4. Test thoroughly
5. Update this documentation
6. Submit a pull request

---

**All 16 examples are production-ready and demonstrate real-world usage patterns.**
