# Loop Strategies Guide

Loop strategies define how agents execute tasks and make decisions. The `claude-php-agent` library provides multiple loop strategies, each optimized for different types of tasks and use cases.

## Overview

All loop strategies implement the `LoopStrategyInterface`:

```php
interface LoopStrategyInterface
{
    public function execute(AgentContext $context): AgentContext;
    public function getName(): string;
}
```

This enables you to swap loop strategies seamlessly depending on your task requirements.

## Available Loop Strategies

### 1. ReactLoop (Standard Pattern)

**Location:** `src/Loops/ReactLoop.php`

The ReAct (Reason-Act-Observe) loop is the fundamental pattern for agentic behavior. It's the default strategy used by agents.

#### How It Works

```
┌─────────────────────────────────┐
│  1. REASON                      │
│     "What do I need to do?"     │
└───────────┬─────────────────────┘
            ↓
┌─────────────────────────────────┐
│  2. ACT                         │
│     Call tools or respond       │
└───────────┬─────────────────────┘
            ↓
┌─────────────────────────────────┐
│  3. OBSERVE                     │
│     Process results             │
└───────────┬─────────────────────┘
            ↓
        (Repeat until done)
```

#### Usage

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Loops\ReactLoop;

$loop = new ReactLoop();

// Optional: Add callbacks
$loop->onIteration(function ($iteration, $response, $context) {
    echo "Iteration {$iteration} complete\n";
});

$loop->onToolExecution(function ($toolName, $input, $result) {
    echo "Tool '{$toolName}' executed\n";
});

$agent = Agent::create($client)
    ->withLoopStrategy($loop)
    ->withTool($myTool)
    ->run("Your task here");
```

#### Best For

- ✅ General-purpose tasks
- ✅ Tasks requiring tool execution
- ✅ Interactive problem-solving
- ✅ Flexible, adaptive behavior

#### Characteristics

- **Iterations:** Variable (until task complete)
- **Planning:** Minimal (just-in-time reasoning)
- **Tool Use:** Full support with iteration
- **Flexibility:** High

---

### 2. PlanExecuteLoop

**Location:** `src/Loops/PlanExecuteLoop.php`

The Plan-Execute pattern separates planning from execution, creating a complete plan upfront before systematically executing each step.

#### How It Works

```
┌─────────────────────────────────┐
│  1. PLAN                        │
│     Analyze and break down      │
│     Create step-by-step plan    │
└───────────┬─────────────────────┘
            ↓
┌─────────────────────────────────┐
│  2. EXECUTE                     │
│     Execute step 1              │
│     Execute step 2              │
│     Execute step 3              │
│     ...                         │
└───────────┬─────────────────────┘
            ↓
┌─────────────────────────────────┐
│  3. MONITOR                     │
│     Check if replanning needed  │
└───────────┬─────────────────────┘
            ↓
┌─────────────────────────────────┐
│  4. SYNTHESIZE                  │
│     Combine all step results    │
└─────────────────────────────────┘
```

#### Usage

```php
use ClaudeAgents\Loops\PlanExecuteLoop;

$loop = new PlanExecuteLoop(
    logger: $logger,
    allowReplan: true  // Allow plan revision based on results
);

// Add callbacks
$loop->onPlanCreated(function ($steps, $context) {
    echo "Plan created with " . count($steps) . " steps\n";
});

$loop->onStepComplete(function ($stepNumber, $description, $result) {
    echo "Step {$stepNumber} complete: {$description}\n";
});

$agent = Agent::create($client)
    ->withLoopStrategy($loop)
    ->run("Complex multi-step task");
```

#### Configuration Options

- **`allowReplan`** (bool, default: `true`): Whether to allow plan revision if steps fail or have unexpected results

#### Best For

- ✅ Complex multi-step tasks
- ✅ Tasks benefiting from upfront planning
- ✅ Systematic, structured execution
- ✅ Tasks with clear sequential dependencies

#### Characteristics

- **Iterations:** Predictable (plan + execute + synthesize)
- **Planning:** Comprehensive upfront planning
- **Tool Use:** Full support during execution
- **Flexibility:** Medium (plan can be revised)

---

### 3. ReflectionLoop

**Location:** `src/Loops/ReflectionLoop.php`

The Reflection pattern iteratively improves output quality through self-evaluation and refinement.

#### How It Works

```
┌─────────────────────────────────┐
│  1. GENERATE                    │
│     Create initial output       │
└───────────┬─────────────────────┘
            ↓
┌─────────────────────────────────┐
│  2. REFLECT                     │
│     Evaluate quality            │
│     Identify improvements       │
│     Rate score (1-10)           │
└───────────┬─────────────────────┘
            ↓
        Quality ≥ Threshold?
            ↓ No
┌─────────────────────────────────┐
│  3. REFINE                      │
│     Apply improvements          │
└───────────┬─────────────────────┘
            ↓
        (Repeat until threshold
         or max refinements)
```

#### Usage

```php
use ClaudeAgents\Loops\ReflectionLoop;

$loop = new ReflectionLoop(
    logger: $logger,
    maxRefinements: 3,           // Max refinement iterations
    qualityThreshold: 8,         // Stop when score ≥ 8/10
    criteria: 'clarity, accuracy, and completeness'  // Custom evaluation criteria
);

// Add reflection callback
$loop->onReflection(function ($refinement, $score, $feedback) {
    echo "Refinement {$refinement}: Score {$score}/10\n";
    echo "Feedback: {$feedback}\n";
});

$agent = Agent::create($client)
    ->withLoopStrategy($loop)
    ->run("Create high-quality output");

// Access reflection metadata
$result = $agent->run($task);
$metadata = $result->getMetadata();
echo "Final score: {$metadata['final_score']}/10\n";
```

#### Configuration Options

- **`maxRefinements`** (int, default: `3`): Maximum number of refinement iterations
- **`qualityThreshold`** (int, default: `8`): Quality score (1-10) to stop iterating
- **`criteria`** (string|null): Custom evaluation criteria (default: "correctness, completeness, clarity, and quality")

#### Best For

- ✅ Quality-focused outputs (writing, code, analysis)
- ✅ Tasks where perfection matters
- ✅ Content creation and refinement
- ✅ Self-improving agents

#### Characteristics

- **Iterations:** Predictable (generate + refine × N)
- **Planning:** Minimal (focus on quality)
- **Tool Use:** Full support during generation/refinement
- **Flexibility:** Medium (focused on improvement)

---

### 4. StreamingLoop

**Location:** `src/Streaming/StreamingLoop.php`

A streaming variant of ReactLoop that provides real-time token streaming through configurable handlers.

#### Usage

```php
use ClaudeAgents\Streaming\StreamingLoop;
use ClaudeAgents\Streaming\Handlers\ConsoleHandler;

$loop = new StreamingLoop();
$loop->addHandler(new ConsoleHandler());

// Or use callback
$loop->onStream(function ($event) {
    echo $event->getText();
});

$agent = Agent::create($client)
    ->withLoopStrategy($loop)
    ->run("Your task");
```

#### Best For

- ✅ Real-time user interfaces
- ✅ Progress indicators
- ✅ Interactive experiences
- ✅ Low perceived latency

---

## Choosing the Right Strategy

| Task Type | Recommended Strategy | Why |
|-----------|---------------------|-----|
| General Q&A | ReactLoop | Flexible, efficient |
| Multi-step workflow | PlanExecuteLoop | Systematic execution |
| Content creation | ReflectionLoop | Quality refinement |
| Research task | ReactLoop | Tool-heavy iteration |
| Complex planning | PlanExecuteLoop | Upfront structure |
| Code generation | ReflectionLoop | Self-correction |
| Real-time UI | StreamingLoop | Progressive display |

## Comparison Matrix

| Feature | ReactLoop | PlanExecuteLoop | ReflectionLoop | StreamingLoop |
|---------|-----------|-----------------|----------------|---------------|
| **Default Strategy** | ✅ Yes | ❌ No | ❌ No | ❌ No |
| **Tool Support** | ✅ Full | ✅ Full | ✅ Full | ✅ Full |
| **Upfront Planning** | ❌ No | ✅ Yes | ❌ No | ❌ No |
| **Quality Focus** | ⚠️ Medium | ⚠️ Medium | ✅ High | ⚠️ Medium |
| **Flexibility** | ✅ High | ⚠️ Medium | ⚠️ Medium | ✅ High |
| **Predictability** | ⚠️ Variable | ✅ High | ✅ High | ⚠️ Variable |
| **Real-time Output** | ❌ No | ❌ No | ❌ No | ✅ Yes |

## Advanced Usage

### Custom Loop Strategy

You can implement your own loop strategy by implementing `LoopStrategyInterface`:

```php
use ClaudeAgents\Contracts\LoopStrategyInterface;
use ClaudeAgents\AgentContext;

class MyCustomLoop implements LoopStrategyInterface
{
    public function execute(AgentContext $context): AgentContext
    {
        // Your custom loop logic
        $client = $context->getClient();
        $config = $context->getConfig();
        
        // ... implement your pattern ...
        
        $context->complete($result);
        return $context;
    }
    
    public function getName(): string
    {
        return 'my_custom_loop';
    }
}

// Use it
$agent = Agent::create($client)
    ->withLoopStrategy(new MyCustomLoop())
    ->run($task);
```

### Combining with Other Features

Loop strategies work seamlessly with other agent features:

```php
$agent = Agent::create($client)
    ->withLoopStrategy(new PlanExecuteLoop())
    ->withTools([$tool1, $tool2])
    ->withMemory($memory)
    ->withThinking(10000)  // Extended thinking
    ->withContextManagement(100000)  // Auto context management
    ->maxIterations(20)
    ->run($task);
```

### Callbacks and Monitoring

All loop strategies support callbacks for monitoring:

```php
$loop = new PlanExecuteLoop();

$loop->onIteration(function ($iteration, $response, $context) {
    // Track iterations
});

$loop->onToolExecution(function ($tool, $input, $result) {
    // Monitor tool usage
});

// PlanExecuteLoop specific
$loop->onPlanCreated(function ($steps, $context) {
    // Examine the plan
});

$loop->onStepComplete(function ($stepNum, $desc, $result) {
    // Track progress
});

// ReflectionLoop specific
$reflectionLoop = new ReflectionLoop();
$reflectionLoop->onReflection(function ($refinement, $score, $feedback) {
    // Monitor quality improvements
});
```

## Performance Considerations

### Token Usage

Different strategies have different token usage patterns:

- **ReactLoop**: Variable, depends on iterations
- **PlanExecuteLoop**: Higher upfront (planning), then predictable
- **ReflectionLoop**: Higher overall (multiple refinements)
- **StreamingLoop**: Same as ReactLoop, but with streaming

### Latency

- **ReactLoop**: Lowest latency for simple tasks
- **PlanExecuteLoop**: Higher initial latency (planning phase)
- **ReflectionLoop**: Highest total latency (multiple iterations)
- **StreamingLoop**: Lowest perceived latency (progressive)

### Cost Optimization

To optimize costs:

1. **Use ReactLoop** for most tasks (efficient)
2. **Limit refinements** in ReflectionLoop (set `maxRefinements`)
3. **Set quality thresholds** in ReflectionLoop (stop early)
4. **Disable replanning** in PlanExecuteLoop if not needed

## Examples

See [`examples/loop_strategies_demo.php`](../examples/loop_strategies_demo.php) for comprehensive examples of all loop strategies.

## See Also

- [Agent Selection Guide](agent-selection-guide.md)
- [Agent Configuration](../src/Config/AgentConfig.php)
- [Context Management](../CONTEXT_SYSTEM_IMPLEMENTATION.md)
- [Tool Development](tools.md)

