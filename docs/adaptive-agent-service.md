# Adaptive Agent Service

**Intelligent agent selection, validation, and adaptation for optimal results**

---

## Overview

The **Adaptive Agent Service** is a meta-agent that intelligently selects the best agent for any given task, validates the quality of results, and adapts its approach if the initial result doesn't meet quality standards. It acts as a smart orchestration layer that ensures you always get the best possible outcome.

### The Problem It Solves

When building AI systems, you often face these challenges:

1. **Agent Selection**: Which agent is best for this specific task?
2. **Quality Assurance**: How do you know the result is good enough?
3. **Adaptation**: What if the first attempt doesn't produce a quality result?
4. **Learning**: How do you improve over time?

The Adaptive Agent Service solves all of these problems automatically.

---

## How It Works

```
┌─────────────────────────────────────────────────────────────────┐
│                     User Input Task                             │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ▼
         ┌────────────────────┐
         │  1. Task Analysis  │
         │  - Complexity      │
         │  - Domain          │
         │  - Requirements    │
         └────────┬───────────┘
                  │
                  ▼
       ┌──────────────────────┐
       │ 2. Agent Selection   │
       │ - Match profiles     │
       │ - Consider history   │
       │ - Score candidates   │
       └─────────┬────────────┘
                 │
                 ▼
       ┌──────────────────────┐
       │ 3. Execute Task      │
       │ - Run selected agent │
       │ - Track performance  │
       └─────────┬────────────┘
                 │
                 ▼
       ┌──────────────────────┐
       │ 4. Validate Result   │
       │ - Quality score      │
       │ - Correctness check  │
       │ - Completeness       │
       └─────────┬────────────┘
                 │
                 ▼
            ┌─────────┐
            │ Good?   │
            └────┬────┘
                 │
         ┌───────┴───────┐
         │               │
        Yes             No
         │               │
         ▼               ▼
    ┌────────┐    ┌──────────────┐
    │ Return │    │ 5. Adapt     │
    │ Result │    │ - Try another│
    └────────┘    │   agent      │
                  │ - Reframe    │
                  │   request    │
                  └──────┬───────┘
                         │
                         └──► Back to Step 2
```

---

## Key Features

### 🎯 **Intelligent Agent Selection**

The service analyzes your task and automatically selects the most appropriate agent based on:

- **Task complexity** (simple, medium, complex, extreme)
- **Domain requirements** (technical, creative, analytical, conversational)
- **Quality needs** (standard, high, extreme)
- **Specific capabilities** (tools, reasoning, knowledge, iteration)
- **Historical performance** (success rates, quality scores)

### ✅ **Result Validation**

Every result is automatically validated against multiple criteria:

- **Correctness**: Does it answer the question correctly?
- **Completeness**: Is the answer complete and thorough?
- **Clarity**: Is it well-structured and clear?
- **Relevance**: Is it relevant to the original task?

Each result receives a quality score (0-10) with detailed feedback.

### 🔄 **Adaptive Retry**

If a result doesn't meet the quality threshold:

1. The service tries a different agent better suited for the task
2. It can reframe the original request to be clearer
3. It learns from failures to improve future selections
4. Maximum attempts are configurable (default: 3)

### 📊 **Performance Tracking**

The service tracks performance metrics for each agent:

- Total attempts and successes
- Average quality scores
- Average execution duration
- Success rates

This data is used to improve future agent selection decisions.

### 🧠 **Continuous Learning**

The service gets smarter over time by:

- Learning which agents perform best for which tasks
- Adjusting selection scores based on historical performance
- Identifying patterns in successful vs. failed attempts

---

## Installation & Setup

### Basic Setup

```php
use ClaudeAgents\Agents\AdaptiveAgentService;
use ClaudePhp\ClaudePhp;

$client = ClaudePhp::make($apiKey);

$service = new AdaptiveAgentService($client, [
    'max_attempts' => 3,              // Try up to 3 times
    'quality_threshold' => 7.0,       // Require 7/10 quality score
    'enable_reframing' => true,       // Reframe on failure
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'adaptive_agent_service'` | Service identifier |
| `max_attempts` | int | `3` | Maximum retry attempts |
| `quality_threshold` | float | `7.0` | Minimum quality score (0-10) |
| `enable_reframing` | bool | `true` | Enable request reframing |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 logger instance |

---

## Registering Agents

Each agent needs a profile describing its capabilities:

```php
$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'strengths' => ['tool usage', 'iterative problem solving'],
    'best_for' => ['calculations', 'API calls', 'multi-step tasks'],
    'complexity_level' => 'medium',  // simple|medium|complex|extreme
    'speed' => 'medium',              // fast|medium|slow
    'quality' => 'standard',          // standard|high|extreme
]);
```

### Agent Profile Fields

#### Required Fields

- **`type`**: Agent type identifier (e.g., 'react', 'reflection', 'rag', 'cot', 'maker')

#### Recommended Fields

- **`strengths`**: Array of capability descriptions
- **`best_for`**: Array of use case descriptions
- **`complexity_level`**: Task complexity this agent handles best
  - `'simple'`: Basic tasks, single-step
  - `'medium'`: Multi-step tasks, moderate complexity
  - `'complex'`: Complex workflows, multiple domains
  - `'extreme'`: Million-step scale, extreme accuracy
  
- **`speed`**: Execution speed characteristic
  - `'fast'`: Quick response (< 10 seconds)
  - `'medium'`: Moderate time (10-60 seconds)
  - `'slow'`: Longer processing (> 60 seconds)
  
- **`quality`**: Output quality level
  - `'standard'`: Good quality, single-pass
  - `'high'`: High quality, validation or refinement
  - `'extreme'`: Near-perfect, voting mechanisms

---

## Usage Examples

### Example 1: Basic Usage

```php
// Simple task - service selects best agent automatically
$result = $service->run('Calculate 25 * 17 + 100');

if ($result->isSuccess()) {
    echo "Answer: " . $result->getAnswer() . "\n";
    echo "Quality: " . $result->getMetadata()['final_quality'] . "/10\n";
    echo "Agent Used: " . $result->getMetadata()['final_agent'] . "\n";
}
```

### Example 2: Complete Setup with Multiple Agents

```php
use ClaudeAgents\Agents\{
    AdaptiveAgentService,
    ReactAgent,
    ReflectionAgent,
    ChainOfThoughtAgent,
    RAGAgent
};

$client = ClaudePhp::make($apiKey);

// Create the service
$service = new AdaptiveAgentService($client, [
    'max_attempts' => 3,
    'quality_threshold' => 7.5,
]);

// Register React agent for tool-using tasks
$service->registerAgent('react', $reactAgent, [
    'type' => 'react',
    'strengths' => ['tool orchestration', 'iterative solving'],
    'best_for' => ['calculations', 'API calls', 'data processing'],
    'complexity_level' => 'medium',
    'speed' => 'medium',
    'quality' => 'standard',
]);

// Register Reflection agent for quality-critical tasks
$service->registerAgent('reflection', $reflectionAgent, [
    'type' => 'reflection',
    'strengths' => ['quality refinement', 'self-improvement'],
    'best_for' => ['code generation', 'writing', 'critical outputs'],
    'complexity_level' => 'medium',
    'speed' => 'slow',
    'quality' => 'high',
]);

// Register Chain of Thought for reasoning tasks
$service->registerAgent('cot', $cotAgent, [
    'type' => 'cot',
    'strengths' => ['step-by-step reasoning', 'transparency'],
    'best_for' => ['math problems', 'logic puzzles', 'explanations'],
    'complexity_level' => 'medium',
    'speed' => 'fast',
    'quality' => 'standard',
]);

// Register RAG agent for knowledge-based tasks
$service->registerAgent('rag', $ragAgent, [
    'type' => 'rag',
    'strengths' => ['knowledge grounding', 'source attribution'],
    'best_for' => ['Q&A', 'documentation', 'fact-based tasks'],
    'complexity_level' => 'simple',
    'speed' => 'fast',
    'quality' => 'high',
]);

// Use the service
$tasks = [
    'Calculate the area of a circle with radius 7',
    'Write a professional email apologizing for a delay',
    'What is dependency injection in PHP?',
    'If A > B and B > C, is A > C?',
];

foreach ($tasks as $task) {
    $result = $service->run($task);
    
    if ($result->isSuccess()) {
        $meta = $result->getMetadata();
        echo "Task: {$task}\n";
        echo "Agent: {$meta['final_agent']}\n";
        echo "Quality: {$meta['final_quality']}/10\n";
        echo "Attempts: {$result->getIterations()}\n\n";
    }
}
```

### Example 3: Handling Failed Attempts

```php
$result = $service->run($difficultTask);

if (!$result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    // Get information about what was tried
    echo "Failed after {$metadata['best_attempt']['attempt']} attempts\n";
    echo "Best quality achieved: {$metadata['best_attempt']['validation']['quality_score']}/10\n";
    
    // See all attempts
    foreach ($metadata['attempts'] as $attempt) {
        echo "Attempt {$attempt['attempt']}: ";
        echo "{$attempt['agent_type']} agent - ";
        echo "Quality: {$attempt['validation']['quality_score']}/10\n";
        
        // See specific issues
        if (!empty($attempt['validation']['issues'])) {
            echo "  Issues:\n";
            foreach ($attempt['validation']['issues'] as $issue) {
                echo "    - {$issue}\n";
            }
        }
    }
}
```

### Example 4: Performance Monitoring

```php
// After running multiple tasks
$performance = $service->getPerformance();

foreach ($performance as $agentId => $stats) {
    if ($stats['attempts'] > 0) {
        $successRate = ($stats['successes'] / $stats['attempts']) * 100;
        
        echo "{$agentId} Agent:\n";
        echo "  Attempts: {$stats['attempts']}\n";
        echo "  Success Rate: " . round($successRate, 1) . "%\n";
        echo "  Avg Quality: " . round($stats['average_quality'], 1) . "/10\n";
        echo "  Avg Duration: " . round($stats['total_duration'] / $stats['attempts'], 2) . "s\n\n";
    }
}
```

---

## Agent Selection Algorithm

The service uses a scoring algorithm to select the best agent:

### 1. Complexity Matching (0-10 points)

Matches task complexity to agent capability:

| Task Complexity | Best Agent Levels |
|----------------|-------------------|
| Simple | Simple (10pts), Medium (5pts) |
| Medium | Medium (10pts), Complex (7pts) |
| Complex | Complex (10pts), Extreme (8pts) |
| Extreme | Extreme (10pts), Complex (5pts) |

### 2. Quality Matching (0-10 points)

Matches quality requirements to agent output level:

| Required Quality | Best Agent Quality |
|-----------------|-------------------|
| Standard | Standard (10pts), High (5pts) |
| High | High (10pts), Extreme (7pts) |
| Extreme | Extreme (10pts), High (5pts) |

### 3. Performance History (0-8 points)

- Success rate: 0-5 points
- Average quality: 0-3 points

### 4. Capability Bonuses (0-7 points each)

- Task requires tools → React agent (+5pts)
- High quality needed → Reflection/Maker agent (+5-7pts)
- Knowledge required → RAG agent (+5pts)
- Reasoning needed → CoT/ToT agent (+5pts)
- Conversational → Dialog agent (+5pts)

### 5. Retry Penalty (-10 points)

Agents that have already been tried get a penalty (unless all agents have been tried).

**Final Selection**: Agent with highest total score is selected.

---

## Task Analysis

The service analyzes each task to determine:

### Complexity Classification

- **Simple**: Single-step, straightforward tasks
- **Medium**: Multi-step, moderate reasoning
- **Complex**: Multiple domains, advanced reasoning
- **Extreme**: Very large scale, extreme accuracy needed

### Domain Detection

- **General**: General-purpose tasks
- **Technical**: Programming, systems, APIs
- **Creative**: Writing, design, content
- **Analytical**: Analysis, research, data
- **Conversational**: Dialog, chat, Q&A
- **Monitoring**: System watching, alerting

### Requirement Flags

- `requires_tools`: Needs external tool usage
- `requires_quality`: Output quality is critical
- `requires_knowledge`: Needs specific domain knowledge
- `requires_reasoning`: Needs logical reasoning
- `requires_iteration`: Needs multiple refinement passes

### Estimated Steps

Rough estimate of how many steps/iterations the task might require.

---

## Result Validation

Each result is validated against multiple criteria:

### Validation Criteria

1. **Correctness** (0-10)
   - Does it correctly answer the question?
   - Are there factual errors?
   - Is the logic sound?

2. **Completeness** (0-10)
   - Is the answer complete?
   - Are all parts addressed?
   - Is anything missing?

3. **Clarity** (0-10)
   - Is it well-structured?
   - Is it easy to understand?
   - Is formatting good?

4. **Relevance** (0-10)
   - Is it relevant to the task?
   - Does it stay on topic?
   - Is there unnecessary content?

### Quality Score Calculation

The service returns a composite quality score (0-10) based on these criteria.

### Validation Output

```php
[
    'quality_score' => 8.5,
    'is_correct' => true,
    'is_complete' => true,
    'issues' => ['Minor: Could be more detailed'],
    'strengths' => ['Clear', 'Well-structured', 'Accurate']
]
```

---

## Request Reframing

When results don't meet quality standards, the service can automatically reframe the request to be clearer and more specific.

### When Reframing Occurs

- Quality score is significantly below threshold (> 2 points)
- Validation identifies specific issues
- Service has remaining attempts

### Reframing Strategy

The service analyzes the original task and identified issues, then creates a clearer, more specific version:

**Original**: "Tell me about PHP"

**Issues**: Too vague, lacks focus

**Reframed**: "Explain the key features of PHP as a programming language, including its variable syntax, function definitions, and class structure."

### Controlling Reframing

```php
// Disable reframing
$service = new AdaptiveAgentService($client, [
    'enable_reframing' => false,
]);

// Or enable only for very low quality
$service = new AdaptiveAgentService($client, [
    'enable_reframing' => true,
    'quality_threshold' => 7.0,  // Reframe if < 5.0
]);
```

---

## Performance Tracking

The service tracks detailed metrics for each agent:

### Tracked Metrics

```php
[
    'attempts' => 15,              // Total times this agent was used
    'successes' => 12,             // Times it met quality threshold
    'failures' => 3,               // Times it didn't meet threshold
    'average_quality' => 7.8,      // Average quality score
    'total_duration' => 45.6,      // Total execution time (seconds)
]
```

### Using Performance Data

```php
// Get all performance data
$performance = $service->getPerformance();

// Find best performing agent
$bestAgent = null;
$bestScore = 0;

foreach ($performance as $agentId => $stats) {
    if ($stats['attempts'] > 0) {
        $score = $stats['average_quality'];
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestAgent = $agentId;
        }
    }
}

echo "Best performing agent: {$bestAgent} ({$bestScore}/10)\n";
```

---

## Advanced Usage

### Custom Validation Logic

You can extend the service to add custom validation:

```php
class MyAdaptiveService extends AdaptiveAgentService
{
    protected function validateResult(string $task, AgentResult $result, array $taskAnalysis): array
    {
        $validation = parent::validateResult($task, $result, $taskAnalysis);
        
        // Add custom validation logic
        $answer = $result->getAnswer();
        
        // Example: Penalize very short answers
        if (strlen($answer) < 100) {
            $validation['quality_score'] -= 2;
            $validation['issues'][] = 'Answer is too brief';
        }
        
        // Example: Bonus for including code examples
        if (strpos($answer, '```') !== false) {
            $validation['quality_score'] += 1;
            $validation['strengths'][] = 'Includes code examples';
        }
        
        return $validation;
    }
}
```

### Dynamic Agent Registration

Register agents on-the-fly based on requirements:

```php
// Start with basic agents
$service = new AdaptiveAgentService($client);
$service->registerAgent('basic', $basicAgent, [...]);

// Later, add specialized agents as needed
if ($needsAdvancedReasoning) {
    $service->registerAgent('maker', new MakerAgent($client), [
        'type' => 'maker',
        'complexity_level' => 'extreme',
        'quality' => 'extreme',
    ]);
}
```

### Logging and Monitoring

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('adaptive-service');
$logger->pushHandler(new StreamHandler('logs/adaptive.log', Logger::DEBUG));

$service = new AdaptiveAgentService($client, [
    'logger' => $logger,
]);

// All operations are now logged
$result = $service->run($task);
```

---

## Best Practices

### 1. Register Diverse Agents

Include agents with different strengths:

```php
// Speed: Fast agents for simple tasks
$service->registerAgent('reflex', $reflexAgent, [...]);

// Quality: High-quality agents for critical tasks  
$service->registerAgent('reflection', $reflectionAgent, [...]);

// Scale: Extreme-scale agents for massive tasks
$service->registerAgent('maker', $makerAgent, [...]);

// Knowledge: Domain-specific agents
$service->registerAgent('rag', $ragAgent, [...]);
```

### 2. Set Appropriate Thresholds

```php
// For production systems - require high quality
$service = new AdaptiveAgentService($client, [
    'quality_threshold' => 8.0,
    'max_attempts' => 5,
]);

// For experimentation - more lenient
$service = new AdaptiveAgentService($client, [
    'quality_threshold' => 6.0,
    'max_attempts' => 2,
]);
```

### 3. Profile Agents Accurately

Be honest about agent capabilities:

```php
// Don't over-promise
$service->registerAgent('simple_agent', $agent, [
    'complexity_level' => 'simple',  // Not 'extreme'
    'quality' => 'standard',          // Not 'extreme'
    'speed' => 'fast',
]);
```

### 4. Monitor Performance

Regularly check which agents are performing best:

```php
// Log performance after batch processing
$tasks = [...];
foreach ($tasks as $task) {
    $service->run($task);
}

// Review and adjust
$performance = $service->getPerformance();
// Analyze and potentially adjust agent profiles
```

### 5. Handle Failures Gracefully

```php
$result = $service->run($task);

if (!$result->isSuccess()) {
    // Log for analysis
    $logger->error('Adaptive service failed', [
        'task' => $task,
        'attempts' => $result->getMetadata()['attempts'],
    ]);
    
    // Fallback to manual handling or default response
    $fallbackResponse = handleManually($task);
}
```

---

## Common Use Cases

### Use Case 1: Customer Support System

```php
// Register agents for different support scenarios
$service->registerAgent('faq', $reflexAgent, [...]); // Fast FAQ
$service->registerAgent('dialog', $dialogAgent, [...]);  // Conversations
$service->registerAgent('technical', $reactAgent, [...]); // Technical issues

// Service automatically routes to appropriate agent
$response = $service->run($customerQuestion);
```

### Use Case 2: Content Generation Pipeline

```php
// Quality-critical content generation
$service->registerAgent('writer', $reflectionAgent, [
    'quality' => 'high',
]);
$service->registerAgent('editor', $reflectionAgent, [
    'quality' => 'extreme',
]);

$article = $service->run('Write an article about ' . $topic);
// Automatically gets multiple refinement passes if needed
```

### Use Case 3: Code Review Assistant

```php
$service->registerAgent('analyzer', $reactAgent, [...]);
$service->registerAgent('validator', $reflectionAgent, [...]);

$review = $service->run("Review this code: {$code}");
// Service ensures high-quality, thorough review
```

### Use Case 4: Research Assistant

```php
$service->registerAgent('search', $reactAgent, [...]);
$service->registerAgent('knowledge', $ragAgent, [...]);
$service->registerAgent('synthesize', $reflectionAgent, [...]);

$research = $service->run("Research and summarize: {$topic}");
// Appropriate agent selected based on knowledge requirements
```

---

## API Reference

### Constructor

```php
public function __construct(
    ClaudePhp $client, 
    array $options = []
)
```

### Methods

#### `registerAgent()`

```php
public function registerAgent(
    string $id, 
    AgentInterface $agent, 
    array $profile = []
): void
```

Register an agent with the service.

#### `run()`

```php
public function run(string $task): AgentResult
```

Execute a task with intelligent agent selection and validation.

#### `getPerformance()`

```php
public function getPerformance(): array
```

Get performance metrics for all registered agents.

#### `getRegisteredAgents()`

```php
public function getRegisteredAgents(): array
```

Get list of registered agent IDs.

#### `getAgentProfile()`

```php
public function getAgentProfile(string $agentId): ?array
```

Get the profile for a specific agent.

#### `getName()`

```php
public function getName(): string
```

Get the service name.

---

## Troubleshooting

### Issue: Service Always Selects Same Agent

**Cause**: One agent's profile matches most tasks too well.

**Solution**: Adjust agent profiles to be more specific, or add more diverse agents.

```php
// Too broad
'best_for' => ['everything']

// Better
'best_for' => ['calculations', 'API calls', 'data processing']
```

### Issue: Quality Validation Too Strict

**Cause**: Quality threshold is too high for your agents' capabilities.

**Solution**: Lower the threshold or improve agent capabilities.

```php
// From
'quality_threshold' => 9.0,  // Very strict

// To
'quality_threshold' => 7.0,  // More reasonable
```

### Issue: Too Many Retries

**Cause**: No agent can meet the quality threshold.

**Solution**: 
- Add more capable agents
- Lower quality threshold
- Improve task clarity

### Issue: Slow Performance

**Cause**: Multiple attempts with slow agents.

**Solution**:
- Register faster agents for simple tasks
- Reduce `max_attempts`
- Set time limits

```php
$service = new AdaptiveAgentService($client, [
    'max_attempts' => 2,  // Limit retries
]);
```

---

## Performance Considerations

### Token Usage

The service uses tokens for:
1. Task analysis (~100-200 tokens)
2. Agent execution (varies by agent)
3. Result validation (~200-300 tokens)
4. Reframing (if needed, ~100 tokens)

**Per attempt**: ~400-600 tokens + agent execution

**Total**: Multiply by number of attempts (typically 1-3)

### Execution Time

- **Single attempt**: Agent time + ~2-5 seconds overhead
- **Multiple attempts**: Multiply by attempts
- **With reframing**: Add ~2 seconds per reframe

### Cost Optimization

```php
// Minimize attempts for cost control
$service = new AdaptiveAgentService($client, [
    'max_attempts' => 2,
    'enable_reframing' => false,  // Skip reframing to save tokens
]);
```

---

## Comparison with Other Approaches

### vs. CoordinatorAgent

| Feature | AdaptiveAgentService | CoordinatorAgent |
|---------|---------------------|------------------|
| Agent Selection | Task analysis + scoring | Capability matching |
| Validation | Full quality scoring | None |
| Retry Logic | ✅ Yes | ❌ No |
| Reframing | ✅ Yes | ❌ No |
| Learning | ✅ Performance tracking | Basic workload |
| Best For | Quality-critical tasks | Load balancing |

### vs. Manual Agent Selection

| Feature | AdaptiveAgentService | Manual Selection |
|---------|---------------------|------------------|
| Ease of Use | High - automatic | Low - manual |
| Optimization | ✅ Self-optimizing | Manual tuning |
| Quality Assurance | ✅ Built-in | Must implement |
| Adaptation | ✅ Automatic | Manual retry |
| Best For | Production systems | Simple projects |

---

## Future Enhancements

Potential improvements for future versions:

1. **A/B Testing**: Compare multiple agents in parallel
2. **Cost Optimization**: Factor in token costs in selection
3. **Async Execution**: Try multiple agents concurrently
4. **User Feedback**: Incorporate user ratings
5. **Caching**: Cache selections for similar tasks
6. **Fine-tuning**: Learn optimal selection over time

---

## Related Documentation

- [Agent Selection Guide](./agent-selection-guide.md) - Comprehensive agent overview
- [Agent Taxonomy](../AGENT_TAXONOMY.md) - Complete agent reference
- [CoordinatorAgent](../src/Agents/CoordinatorAgent.php) - Load-balancing alternative
- [Examples](../examples/adaptive_agent_service_example.php) - Working code examples

---

## Conclusion

The Adaptive Agent Service provides intelligent orchestration for your AI agents, ensuring you always get the best possible results through:

- ✅ Smart agent selection
- ✅ Automatic quality validation
- ✅ Adaptive retry with different approaches
- ✅ Continuous learning and improvement

Use it whenever you need reliable, high-quality AI outputs without manual agent management!

