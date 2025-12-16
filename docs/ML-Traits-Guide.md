# ML Traits and Utilities Guide

**Make Any Agent Learnable with Reusable k-NN Components**

---

## Overview

The ML traits and utilities provide **plug-and-play machine learning** capabilities that can be added to any agent. Using k-NN (k-Nearest Neighbors), agents can learn from their execution history and continuously improve.

### Available Components

**Traits** (Mix into any agent):
- `LearnableAgent` - Universal learning and performance tracking
- `ParameterOptimizer` - Automatic parameter tuning
- `StrategySelector` - Learn best execution strategies

**Utilities** (Standalone classes):
- `PerformancePredictor` - Predict duration, success, quality
- `PromptOptimizer` - Learn optimal prompt templates *(coming soon)*

---

## Quick Start

### Make Any Agent Learnable

```php
use ClaudeAgents\ML\Traits\LearnableAgent;

class MyCustomAgent extends AbstractAgent
{
    use LearnableAgent;
    
    public function run(string $task): AgentResult
    {
        $startTime = microtime(true);
        
        // Your agent logic here
        $result = $this->executeTask($task);
        
        // Automatically record for learning
        $this->recordExecution($task, $result, [
            'duration' => microtime(true) - $startTime,
        ]);
        
        return $result;
    }
}

// Enable learning
$agent = new MyCustomAgent($client);
$agent->enableLearning('storage/my_agent_history.json');

// Agent now learns from every execution!
$result = $agent->run($task);

// Check learning progress
$stats = $agent->getLearningStats();
echo "Learned from {$stats['total_records']} tasks\n";
echo "Success rate: " . ($stats['success_rate'] * 100) . "%\n";
```

---

## LearnableAgent Trait

### Features

✅ Automatic performance tracking  
✅ Historical execution storage  
✅ Learning statistics and analytics  
✅ Minimal integration required  

### Usage

```php
use ClaudeAgents\ML\Traits\LearnableAgent;

class ReactAgent extends AbstractAgent
{
    use LearnableAgent;
    
    public function run(string $task): AgentResult
    {
        $startTime = microtime(true);
        
        // Execute task
        $result = parent::run($task);
        
        // Record execution (automatically learns)
        $this->recordExecution($task, $result, [
            'duration' => microtime(true) - $startTime,
            'tool_calls' => $this->getToolCallCount(),
            'iterations' => $this->getIterationCount(),
        ]);
        
        return $result;
    }
    
    // Optional: Customize task analysis
    protected function analyzeTaskForLearning(string $task): array
    {
        return [
            'complexity' => $this->estimateComplexity($task),
            'domain' => $this->detectDomain($task),
            'requires_tools' => $this->needsTools($task),
            // ... more analysis
        ];
    }
    
    // Optional: Customize quality evaluation
    protected function evaluateResultQuality(AgentResult $result): float
    {
        // Your custom quality logic
        return $this->calculateQualityScore($result);
    }
}
```

### Methods

#### `enableLearning(string $historyPath): self`
Enable learning for the agent.

```php
$agent->enableLearning('storage/react_agent_history.json');
```

#### `disableLearning(): self`
Disable learning.

```php
$agent->disableLearning();
```

#### `getLearningStats(): array`
Get learning statistics.

```php
$stats = $agent->getLearningStats();
/*
[
    'learning_enabled' => true,
    'total_records' => 42,
    'success_rate' => 0.95,
    'avg_quality' => 8.2,
    ...
]
*/
```

#### `getLearningHistory(): ?TaskHistoryStore`
Get direct access to history store.

```php
$history = $agent->getLearningHistory();
$allRecords = $history->getAll();
```

#### `getHistoricalPerformance(string $task, int $k): array`
Get performance on similar historical tasks.

```php
$similar = $agent->getHistoricalPerformance($task, k: 10);
foreach ($similar as $record) {
    echo "Similar task: {$record['metadata']['task']}\n";
    echo "Quality: {$record['metadata']['quality_score']}\n";
}
```

---

## ParameterOptimizer Trait

### Features

✅ Learns optimal parameter values  
✅ Weighted averaging based on quality  
✅ Supports numeric and categorical parameters  
✅ Automatic parameter tuning  

### Usage

```php
use ClaudeAgents\ML\Traits\ParameterOptimizer;

class TreeOfThoughtsAgent extends AbstractAgent
{
    use ParameterOptimizer;
    
    public function run(string $task): AgentResult
    {
        // Learn optimal parameters from similar tasks
        $params = $this->learnOptimalParameters($task, [
            'branch_count',
            'max_depth',
            'search_strategy',
        ], k: 10);
        
        // Use learned parameters (or defaults if no history)
        $branchCount = $params['branch_count'] ?? 3;
        $maxDepth = $params['max_depth'] ?? 4;
        $strategy = $params['search_strategy'] ?? 'best_first';
        
        // Execute with optimized parameters
        $result = $this->executeWithParameters($task, $branchCount, $maxDepth, $strategy);
        
        // Record performance for future learning
        $this->recordParameterPerformance(
            $task,
            parameters: [
                'branch_count' => $branchCount,
                'max_depth' => $maxDepth,
                'search_strategy' => $strategy,
            ],
            success: $result->isSuccess(),
            qualityScore: $this->evaluateQuality($result),
            duration: $result->getDuration()
        );
        
        return $result;
    }
}

// Enable parameter optimization
$agent = new TreeOfThoughtsAgent($client);
$agent->enableParameterOptimization(
    historyPath: 'storage/tot_params.json',
    defaults: [
        'branch_count' => 3,
        'max_depth' => 4,
        'search_strategy' => 'best_first',
    ]
);
```

### Methods

#### `enableParameterOptimization(string $historyPath, array $defaults): self`
Enable parameter learning.

#### `learnOptimalParameters(string $task, array $paramNames, int $k): array`
Learn optimal parameters from similar tasks.

#### `recordParameterPerformance(...)`
Record parameter performance for learning.

#### `getParameterStats(): array`
Get parameter optimization statistics.

---

## StrategySelector Trait

### Features

✅ Learns which strategy works best for which tasks  
✅ Supports any strategy type (loop patterns, search algorithms, etc.)  
✅ Confidence scoring  
✅ Performance analytics per strategy  

### Usage

```php
use ClaudeAgents\ML\Traits\StrategySelector;

class AdaptiveReActAgent extends AbstractAgent
{
    use StrategySelector;
    
    private const STRATEGIES = ['react', 'plan_execute', 'reflection'];
    
    public function run(string $task): AgentResult
    {
        // Learn best strategy from similar tasks
        $strategyInfo = $this->getStrategyConfidence($task);
        $strategy = $strategyInfo['strategy'];
        
        echo "Selected strategy: {$strategy}\n";
        echo "Confidence: " . ($strategyInfo['confidence'] * 100) . "%\n";
        echo "Reasoning: {$strategyInfo['reasoning']}\n";
        
        // Execute with learned strategy
        $result = match ($strategy) {
            'react' => $this->executeReact($task),
            'plan_execute' => $this->executePlanExecute($task),
            'reflection' => $this->executeReflection($task),
        };
        
        // Record strategy performance
        $this->recordStrategyPerformance(
            $task,
            strategy: $strategy,
            success: $result->isSuccess(),
            qualityScore: $this->evaluateQuality($result),
            duration: $result->getDuration()
        );
        
        return $result;
    }
}

// Enable strategy learning
$agent = new AdaptiveReActAgent($client);
$agent->enableStrategyLearning(
    strategies: ['react', 'plan_execute', 'reflection'],
    defaultStrategy: 'react',
    historyPath: 'storage/strategy_history.json'
);
```

### Methods

#### `enableStrategyLearning(array $strategies, string $default, string $path): self`
Enable strategy selection learning.

#### `selectBestStrategy(string $task, int $k): string`
Select best strategy based on historical performance.

#### `getStrategyConfidence(string $task, int $k): array`
Get strategy selection with confidence score.

#### `recordStrategyPerformance(...)`
Record strategy performance for learning.

#### `getStrategyPerformance(): array`
Get performance breakdown by strategy.

```php
$performance = $agent->getStrategyPerformance();
/*
[
    'react' => [
        'attempts' => 20,
        'success_rate' => 0.85,
        'avg_quality' => 7.8,
        'avg_duration' => 12.3,
    ],
    'plan_execute' => [
        'attempts' => 15,
        'success_rate' => 0.93,
        'avg_quality' => 8.5,
        'avg_duration' => 18.7,
    ],
    ...
]
*/
```

---

## PerformancePredictor Utility

### Features

✅ Predict execution duration  
✅ Predict success probability  
✅ Predict quality scores  
✅ Confidence intervals  

### Usage

```php
use ClaudeAgents\ML\PerformancePredictor;

$predictor = new PerformancePredictor('storage/performance_history.json');

// Predict all metrics
$prediction = $predictor->predict($task, agentType: 'react', k: 10);

print_r($prediction);
/*
[
    'duration' => [
        'estimated_duration' => 15.3,
        'min_duration' => 8.2,
        'max_duration' => 24.1,
        'confidence' => 0.87,
        'sample_size' => 10,
    ],
    'success' => [
        'success_probability' => 0.90,
        'confidence' => 0.87,
        'sample_size' => 10,
    ],
    'quality' => [
        'expected_quality' => 8.2,
        'min_quality' => 7.1,
        'max_quality' => 9.0,
        'confidence' => 0.87,
        'sample_size' => 10,
    ],
]
*/

// Predict individual metrics
$duration = $predictor->predictDuration($task);
$success = $predictor->predictSuccess($task);
$quality = $predictor->predictQuality($task);

// Record actual performance for learning
$predictor->recordPerformance(
    task: $task,
    agentType: 'react',
    success: true,
    duration: 14.8,
    qualityScore: 8.5
);
```

### Use Cases

1. **Budget Management** - Set timeouts based on predicted duration
2. **Task Routing** - Route to agents with highest success probability
3. **Quality Assurance** - Flag tasks predicted to have low quality
4. **Capacity Planning** - Estimate resource requirements

---

## Combining Traits

Agents can use multiple traits together:

```php
use ClaudeAgents\ML\Traits\{LearnableAgent, ParameterOptimizer, StrategySelector};

class SuperSmartAgent extends AbstractAgent
{
    use LearnableAgent;
    use ParameterOptimizer;
    use StrategySelector;
    
    public function run(string $task): AgentResult
    {
        // 1. Select best strategy
        $strategy = $this->selectBestStrategy($task);
        
        // 2. Learn optimal parameters
        $params = $this->learnOptimalParameters($task, ['max_iterations']);
        
        // 3. Execute
        $result = $this->executeWithStrategyAndParams($task, $strategy, $params);
        
        // 4. Record everything for learning
        $this->recordExecution($task, $result, [
            'strategy' => $strategy,
            'parameters' => $params,
        ]);
        
        return $result;
    }
}
```

---

## Best Practices

### 1. Start with LearnableAgent
Begin by adding basic learning:

```php
use LearnableAgent;

// Enable and forget - it just works!
$agent->enableLearning();
```

### 2. Add Specialized Traits as Needed
Add ParameterOptimizer or StrategySelector when you have:
- Multiple parameter configurations to choose from
- Multiple execution strategies

### 3. Customize Analysis and Evaluation
Override methods for better learning:

```php
protected function analyzeTaskForLearning(string $task): array
{
    return [
        'complexity' => $this->yourComplexityLogic($task),
        'domain' => $this->yourDomainDetection($task),
        // ... domain-specific analysis
    ];
}

protected function evaluateResultQuality(AgentResult $result): float
{
    // Your quality metrics
    return $this->yourQualityCalculation($result);
}
```

### 4. Monitor Learning Progress
Regularly check stats:

```php
$stats = $agent->getLearningStats();
if ($stats['total_records'] > 20) {
    echo "Agent has learned from {$stats['total_records']} tasks!\n";
    echo "Success rate: " . ($stats['success_rate'] * 100) . "%\n";
}
```

### 5. Backup History
Periodically backup learning data:

```php
$history = $agent->getLearningHistory();
$backup = json_encode($history->getAll(), JSON_PRETTY_PRINT);
file_put_contents('backup.json', $backup);
```

---

## Examples

See the `examples/ml-traits/` directory for complete working examples:

- `learnable-agent-example.php` - Basic LearnableAgent usage
- `parameter-optimization-example.php` - Parameter tuning
- `strategy-selection-example.php` - Strategy learning
- `combined-traits-example.php` - Using multiple traits together

---

## Performance Considerations

- **Storage:** ~1KB per execution record
- **Lookup Speed:** O(n) for k-NN search (very fast for <10,000 records)
- **Memory:** Minimal impact (history loaded lazily)
- **Overhead:** <100ms per learning operation

---

## Troubleshooting

### Issue: Learning not improving performance

**Solution:** Need more diverse training data. Run 20-30 varied tasks.

### Issue: Parameter optimization returning defaults

**Solution:** No similar historical tasks found. Build history first.

### Issue: Strategy selection confidence low

**Solution:** Need more examples of each strategy. Run each strategy 10+ times.

---

## Next Steps

1. **Apply to Your Agents** - Add `LearnableAgent` to your custom agents
2. **Optimize Parameters** - Use `ParameterOptimizer` for tunable agents
3. **Learn Strategies** - Use `StrategySelector` for multi-strategy agents
4. **Monitor and Iterate** - Check stats regularly and adjust

---

## Related Documentation

- [k-NN Learning Guide](knn-learning.md)
- [ML Components README](ML-README.md)
- [Adaptive Agent Service](adaptive-agent-service.md)

---

**Make your agents smarter, automatically! 🧠✨**

