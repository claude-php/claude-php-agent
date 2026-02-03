# TracingService Tutorial

Learn how to add observability to your agents with distributed tracing using LangSmith, LangFuse, and Arize Phoenix.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Tracing Providers](#tracing-providers)
- [Recording Traces](#recording-traces)
- [Spans and Metrics](#spans-and-metrics)
- [Agent Integration](#agent-integration)
- [Best Practices](#best-practices)

## Overview

The TracingService provides distributed tracing capabilities for debugging and monitoring your AI agents.

**Features:**
- Multiple tracing backends (LangSmith, LangFuse, Phoenix)
- Automatic span timing
- Metric collection
- Metadata sanitization
- Context propagation

**Supported Platforms:**
- **LangSmith** - LangChain's tracing platform
- **LangFuse** - Open-source LLM observability
- **Arize Phoenix** - ML observability platform

## Quick Start

### Enable Tracing

```php
// config/services.php
return [
    'tracing' => [
        'enabled' => true,
        'providers' => ['langsmith'], // or ['langfuse'], ['phoenix']
        'langsmith' => [
            'api_key' => getenv('LANGSMITH_API_KEY'),
            'project' => 'my-agent-project',
            'endpoint' => 'https://api.smith.langchain.com',
        ],
    ],
];
```

### Basic Usage

```php
use ClaudeAgents\Services\ServiceManager;
use ClaudeAgents\Services\ServiceType;

$manager = ServiceManager::getInstance();
$tracing = $manager->get(ServiceType::TRACING);

// Start a trace
$tracing->startTrace('trace-123', 'agent-run', [
    'user_id' => 'user-123',
    'model' => 'claude-opus-4-5',
]);

// Your agent code here...

// End the trace
$tracing->endTrace('trace-123', [
    'result' => 'success',
    'tokens_used' => 150,
]);
```

## Tracing Providers

### LangSmith

Perfect for LangChain-based applications:

```php
// .env
LANGSMITH_API_KEY=lsv2_pt_xxx
LANGCHAIN_PROJECT=my-project

// config/services.php
return [
    'tracing' => [
        'enabled' => true,
        'providers' => ['langsmith'],
        'langsmith' => [
            'api_key' => getenv('LANGSMITH_API_KEY'),
            'project' => getenv('LANGCHAIN_PROJECT', 'default'),
        ],
    ],
];
```

### LangFuse

Open-source alternative with rich analytics:

```php
// .env
LANGFUSE_PUBLIC_KEY=pk-lf-xxx
LANGFUSE_SECRET_KEY=sk-lf-xxx

// config/services.php
return [
    'tracing' => [
        'enabled' => true,
        'providers' => ['langfuse'],
        'langfuse' => [
            'public_key' => getenv('LANGFUSE_PUBLIC_KEY'),
            'secret_key' => getenv('LANGFUSE_SECRET_KEY'),
            'endpoint' => 'https://cloud.langfuse.com',
        ],
    ],
];
```

### Arize Phoenix

ML observability focused:

```php
// config/services.php
return [
    'tracing' => [
        'enabled' => true,
        'providers' => ['phoenix'],
        'phoenix' => [
            'endpoint' => 'http://localhost:6006',
        ],
    ],
];
```

### Multiple Providers

Send traces to multiple platforms:

```php
return [
    'tracing' => [
        'enabled' => true,
        'providers' => ['langsmith', 'langfuse'], // Both!
        // Configure both...
    ],
];
```

## Recording Traces

### Complete Trace

```php
$traceId = uniqid('trace_', true);

// Start
$tracing->startTrace($traceId, 'user-query', [
    'user_id' => 'user-123',
    'query' => 'What is the weather?',
    'model' => 'claude-sonnet-4-5',
]);

try {
    // Your agent logic
    $result = $agent->process($query);
    
    // Success
    $tracing->endTrace($traceId, [
        'result' => $result,
        'tokens' => 150,
    ]);
} catch (\Exception $e) {
    // Error
    $tracing->endTrace($traceId, [
        'error' => $e->getMessage(),
    ]);
    throw $e;
}
```

### Nested Traces

```php
// Parent trace
$parentId = 'parent-trace';
$tracing->startTrace($parentId, 'agent-pipeline');

// Child traces
$step1Id = 'step-1';
$tracing->startTrace($step1Id, 'retrieval-step');
// ... do retrieval ...
$tracing->endTrace($step1Id, ['docs_found' => 5]);

$step2Id = 'step-2';
$tracing->startTrace($step2Id, 'generation-step');
// ... do generation ...
$tracing->endTrace($step2Id, ['tokens' => 100]);

// End parent
$tracing->endTrace($parentId, ['status' => 'completed']);
```

## Spans and Metrics

### Recording Spans

Spans are sub-operations within a trace:

```php
// Automatic timing
$result = $tracing->recordSpan('database-query', function() {
    return $db->query('SELECT * FROM users');
});

// The span automatically records:
// - Start time
// - End time
// - Duration
// - Success/failure
// - Error message (if failed)
```

### Multiple Spans

```php
$tracing->startTrace('trace-123', 'complex-operation');

// Span 1: Load data
$data = $tracing->recordSpan('load-data', function() {
    return loadData();
});

// Span 2: Process data
$processed = $tracing->recordSpan('process-data', function() use ($data) {
    return processData($data);
});

// Span 3: Save results
$tracing->recordSpan('save-results', function() use ($processed) {
    saveResults($processed);
});

$tracing->endTrace('trace-123', ['status' => 'complete']);
```

### Recording Metrics

```php
// Record individual metrics
$tracing->recordMetric('llm.tokens.input', 100, [
    'model' => 'claude-opus-4-5',
]);

$tracing->recordMetric('llm.tokens.output', 50, [
    'model' => 'claude-opus-4-5',
]);

$tracing->recordMetric('llm.latency.ms', 1250.5, [
    'model' => 'claude-opus-4-5',
]);
```

## Agent Integration

### Simple Agent

```php
class TracedAgent
{
    private TracingService $tracing;
    
    public function __construct(TracingService $tracing)
    {
        $this->tracing = $tracing;
    }
    
    public function run(string $input): string
    {
        $traceId = uniqid('agent_', true);
        
        $this->tracing->startTrace($traceId, 'agent.run', [
            'input' => substr($input, 0, 100),
        ]);
        
        try {
            $result = $this->execute($input);
            
            $this->tracing->endTrace($traceId, [
                'result' => substr($result, 0, 100),
                'success' => true,
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->tracing->endTrace($traceId, [
                'error' => $e->getMessage(),
                'success' => false,
            ]);
            throw $e;
        }
    }
    
    private function execute(string $input): string
    {
        return $this->tracing->recordSpan('llm-call', function() use ($input) {
            // Call LLM
            return "Processed: {$input}";
        });
    }
}
```

### ReAct Agent with Tracing

```php
class ObservableReActAgent extends ReactAgent
{
    private TracingService $tracing;
    
    public function run(string $input): AgentResult
    {
        $traceId = uniqid('react_', true);
        $startTime = microtime(true);
        
        $this->tracing->startTrace($traceId, 'react-agent', [
            'input' => $input,
            'max_iterations' => $this->maxIterations,
        ]);
        
        try {
            $iteration = 0;
            
            while ($iteration < $this->maxIterations) {
                $iteration++;
                
                // Trace each iteration
                $thought = $this->tracing->recordSpan(
                    "iteration-{$iteration}",
                    fn() => $this->think($input)
                );
                
                if ($thought->isComplete) {
                    break;
                }
            }
            
            $result = $this->finalizeResult();
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->tracing->endTrace($traceId, [
                'iterations' => $iteration,
                'duration_ms' => $duration,
                'success' => true,
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->tracing->endTrace($traceId, [
                'error' => $e->getMessage(),
                'success' => false,
            ]);
            throw $e;
        }
    }
}
```

### Multi-Agent System

```php
class TracedMultiAgentSystem
{
    private TracingService $tracing;
    
    public function orchestrate(string $task): array
    {
        $traceId = 'orchestration-' . uniqid();
        
        $this->tracing->startTrace($traceId, 'multi-agent-orchestration', [
            'task' => $task,
            'agents' => ['planner', 'executor', 'reviewer'],
        ]);
        
        // Agent 1: Planner
        $plan = $this->tracing->recordSpan('planner-agent', function() use ($task) {
            return $this->plannerAgent->plan($task);
        });
        
        // Agent 2: Executor
        $result = $this->tracing->recordSpan('executor-agent', function() use ($plan) {
            return $this->executorAgent->execute($plan);
        });
        
        // Agent 3: Reviewer
        $review = $this->tracing->recordSpan('reviewer-agent', function() use ($result) {
            return $this->reviewerAgent->review($result);
        });
        
        $this->tracing->endTrace($traceId, [
            'plan_steps' => count($plan),
            'review_score' => $review['score'],
        ]);
        
        return [
            'plan' => $plan,
            'result' => $result,
            'review' => $review,
        ];
    }
}
```

## Best Practices

### 1. Use Descriptive Trace Names

```php
// ❌ Bad
$tracing->startTrace('trace-1', 'run');

// ✅ Good
$tracing->startTrace('trace-1', 'customer-support-query', [
    'category' => 'billing',
    'priority' => 'high',
]);
```

### 2. Include Relevant Metadata

```php
// ✅ Good metadata
$tracing->startTrace($id, 'agent-run', [
    'user_id' => $userId,
    'model' => 'claude-opus-4-5',
    'temperature' => 0.7,
    'max_tokens' => 1000,
    'language' => 'en',
]);
```

### 3. Always End Traces

```php
// ✅ Use try-finally
$traceId = uniqid();
$tracing->startTrace($traceId, 'operation');

try {
    $result = doWork();
    $tracing->endTrace($traceId, ['result' => $result]);
} catch (\Exception $e) {
    $tracing->endTrace($traceId, ['error' => $e->getMessage()]);
    throw $e;
}
```

### 4. Sanitize Sensitive Data

The service automatically removes API keys, passwords, etc., but be careful:

```php
// ✅ Automatically sanitized
$tracing->startTrace($id, 'api-call', [
    'api_key' => 'sk-xxx', // Removed
    'password' => 'secret', // Removed
]);

// ⚠️ Watch out for nested sensitive data
$tracing->startTrace($id, 'operation', [
    'user' => [
        'name' => 'John',
        'secret_token' => 'xxx', // Add 'secret' to sensitive keywords
    ],
]);
```

### 5. Use Spans for Sub-Operations

```php
// ✅ Good - Clear hierarchy
$tracing->startTrace($id, 'full-pipeline');

$tracing->recordSpan('step-1-retrieval', fn() => retrieve());
$tracing->recordSpan('step-2-rerank', fn() => rerank());
$tracing->recordSpan('step-3-generate', fn() => generate());

$tracing->endTrace($id);
```

### 6. Record Metrics Consistently

```php
// ✅ Consistent naming and tags
$tracing->recordMetric('agent.tokens.input', $inputTokens, [
    'model' => $model,
    'agent_type' => 'react',
]);

$tracing->recordMetric('agent.tokens.output', $outputTokens, [
    'model' => $model,
    'agent_type' => 'react',
]);

$tracing->recordMetric('agent.latency.ms', $duration, [
    'model' => $model,
    'agent_type' => 'react',
]);
```

### 7. Check if Enabled

```php
// Avoid overhead when disabled
if ($tracing->isEnabled()) {
    $tracing->startTrace($id, 'operation');
    // ... traced code ...
    $tracing->endTrace($id);
}
```

## Summary

You've learned:

✅ How to configure tracing providers  
✅ Recording traces and spans  
✅ Collecting metrics  
✅ Integrating with agents  
✅ Best practices for observability  

Next: Check out [TelemetryService Tutorial](Services_Telemetry.md) for metrics!
