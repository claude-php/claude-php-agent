# k-NN Learning for Adaptive Agent Service

## Overview

The k-Nearest Neighbors (k-NN) enhancement transforms the Adaptive Agent Service from a rule-based system into a **learning system** that improves its agent selection over time based on historical performance.

## Key Features

### 🎯 1. Historical Task-Based Selection

Instead of relying solely on hardcoded rules, the system:
- Stores task embeddings and execution outcomes
- Finds k most similar historical tasks
- Selects agents that performed best on similar tasks
- Weights by similarity distance and recency

### 🧠 2. Task Feature Vectors

Tasks are converted into 14-dimensional feature vectors:

```
[complexity, domain×6, flags×4, quality, steps, req_count]
```

**Features:**
- Complexity (0-1): Simple to Extreme
- Domain (one-hot, 6 dimensions): General, Technical, Creative, Analytical, Conversational, Monitoring
- Binary Flags (4 dimensions): Requires tools, knowledge, reasoning, iteration
- Quality Requirement (0-1): Standard to Extreme
- Estimated Steps (normalized)
- Key Requirements Count (normalized)

### 📊 3. Adaptive Quality Threshold

The system dynamically adjusts quality expectations:
- Finds k similar historical tasks
- Calculates mean quality achieved
- Sets threshold to: `mean - 0.5σ` (achievable but challenging)
- Prevents unrealistic expectations for difficult tasks

### 🔄 4. Learning Loop

```
Task → Embed → Find Similar → Select Agent → Execute → Record → Learn
```

Each execution improves future selections!

## Architecture

### Components

```
ML/
├── KNNMatcher.php         - Core k-NN similarity search
├── TaskEmbedder.php       - Task → Feature Vector conversion
└── TaskHistoryStore.php   - Persistent learning storage
```

### KNNMatcher

Provides k-NN functionality with multiple distance metrics:

```php
use ClaudeAgents\ML\KNNMatcher;

$matcher = new KNNMatcher();

// Find 5 nearest neighbors
$nearest = $matcher->findNearest(
    queryVector: $taskVector,
    candidates: $candidateVectors,
    k: 5,
    metric: 'cosine', // or 'euclidean', 'manhattan'
    options: [
        'min_similarity' => 0.7,
        'weights' => $temporalWeights, // Recency-based weighting
    ]
);
```

**Distance Metrics:**
- `cosine`: Best for semantic similarity (default)
- `euclidean`: L2 distance in feature space
- `manhattan`: L1 distance (Manhattan distance)

**Features:**
- Temporal weighting (recent tasks more relevant)
- Similarity/distance thresholds
- Vector normalization
- Weighted Euclidean distance

### TaskEmbedder

Converts task analysis into feature vectors:

```php
use ClaudeAgents\ML\TaskEmbedder;

$embedder = new TaskEmbedder();

// Convert task analysis to vector
$vector = $embedder->embed($taskAnalysis);

// Weighted embedding (emphasize certain features)
$weightedVector = $embedder->embedWeighted($taskAnalysis, [
    'quality' => 1.5,    // Emphasize quality requirements
    'tools' => 1.2,      // Tool usage important
    'complexity' => 0.8, // Less emphasis on complexity
]);

// Get feature names for debugging
$features = $embedder->getFeatureNames();
// ['complexity', 'domain_general', 'domain_technical', ...]

// Calculate feature importance from historical data
$importance = $embedder->calculateFeatureImportance($historicalVectors);
```

### TaskHistoryStore

Persistent storage for learning:

```php
use ClaudeAgents\ML\TaskHistoryStore;

$store = new TaskHistoryStore(
    storePath: 'storage/agent_history.json',
    autoSave: true,
    maxHistorySize: 1000
);

// Record execution
$store->record([
    'id' => 'task_123',
    'task' => 'Calculate compound interest...',
    'task_vector' => $taskVector,
    'task_analysis' => $analysis,
    'agent_id' => 'react_agent',
    'success' => true,
    'quality_score' => 8.5,
    'duration' => 3.2,
]);

// Find similar tasks
$similar = $store->findSimilar($taskVector, k: 5);

// Get best agents for similar tasks
$bestAgents = $store->getBestAgentsForSimilar($taskVector, k: 10, topN: 3);

// Get adaptive threshold
$threshold = $store->getAdaptiveThreshold($taskVector, k: 10, defaultThreshold: 7.0);

// Statistics
$stats = $store->getStats();
```

## Usage

### Basic Setup

```php
use ClaudeAgents\Agents\AdaptiveAgentService;

$service = new AdaptiveAgentService($client, [
    'enable_knn' => true,  // Enable k-NN learning (default: true)
    'history_store_path' => 'storage/agent_history.json',
    'max_attempts' => 3,
    'quality_threshold' => 7.0,
]);
```

### Register Agents

```php
$service->registerAgent('react_agent', $reactAgent, [
    'type' => 'react',
    'complexity_level' => 'medium',
    'quality' => 'standard',
    // ... profile details
]);

$service->registerAgent('reflection_agent', $reflectionAgent, [
    'type' => 'reflection',
    'complexity_level' => 'complex',
    'quality' => 'extreme',
    // ... profile details
]);
```

### Execute Tasks (Learning Happens Automatically)

```php
// First execution: Rule-based selection
$result = $service->run('Calculate compound interest on $10,000 at 5% for 3 years');
// ✓ Executes with react_agent
// ✓ Records: [task_vector, agent_id, quality_score, success]

// Similar task later: k-NN based selection
$result = $service->run('Calculate interest on $5,000 at 4% for 2 years');
// ✓ Finds similar historical task
// ✓ Selects agent that performed best (react_agent)
// ✓ Higher confidence, faster selection!
```

### Get Recommendations Without Execution

```php
$recommendation = $service->recommendAgent($task);

print_r($recommendation);
/*
[
    'agent_id' => 'react_agent',
    'confidence' => 0.85,
    'method' => 'k-NN',
    'reasoning' => 'Based on 5 similar tasks with 100% success rate and 8.5 avg quality',
    'alternatives' => [
        ['agent_id' => 'cot_agent', 'score' => 0.72, ...],
        ['agent_id' => 'reflection_agent', 'score' => 0.65, ...]
    ],
    'task_analysis' => [...]
]
*/
```

### Monitor Learning

```php
// History statistics
$stats = $service->getHistoryStats();
/*
[
    'knn_enabled' => true,
    'total_records' => 42,
    'unique_agents' => 3,
    'success_rate' => 0.95,
    'avg_quality' => 8.2,
    'oldest_record' => 1702345678,
    'newest_record' => 1702456789
]
*/

// Agent performance
$performance = $service->getPerformance();
/*
[
    'react_agent' => [
        'attempts' => 15,
        'successes' => 14,
        'average_quality' => 8.3,
        'total_duration' => 45.2
    ],
    ...
]
*/

// Direct history access
$historyStore = $service->getHistoryStore();
$allHistory = $historyStore->getAll();
```

## How It Works

### Selection Algorithm

```
1. Generate Task Vector
   └─> TaskEmbedder converts analysis to 14D vector

2. Check History
   └─> Do we have similar historical tasks?
   
3a. YES → k-NN Selection
    ├─> Find k=10 most similar tasks (cosine similarity)
    ├─> Weight by temporal decay (recent tasks more relevant)
    ├─> Group by agent_id
    ├─> Calculate weighted scores:
    │   └─> score = success_rate(40%) + quality(40%) + similarity(20%)
    └─> Select top agent not yet tried

3b. NO → Rule-Based Selection
    └─> Use original hardcoded rules as fallback

4. Execute & Record
   ├─> Run task with selected agent
   ├─> Validate result quality
   └─> Store [vector, agent, outcome] for future learning
```

### Temporal Weighting

Recent executions are more relevant:

```php
weight = exp(-log(2) * age_in_days / half_life)
```

- Default half-life: 30 days
- Task from today: weight = 1.0
- Task from 30 days ago: weight = 0.5
- Task from 60 days ago: weight = 0.25

### Adaptive Threshold Calculation

```php
threshold = max(5.0, min(9.5, mean - 0.5 * stdDev))
```

Example:
- Similar tasks achieved: [7.5, 8.0, 8.5, 7.8, 8.2]
- Mean: 8.0, StdDev: 0.35
- Adaptive threshold: 8.0 - 0.5(0.35) = **7.825**

**Benefits:**
- Easy tasks → Higher threshold (expect better quality)
- Hard tasks → Lower threshold (realistic expectations)
- Prevents wasted attempts on near-impossible thresholds

## Performance Characteristics

### First Execution (No History)
- Method: Rule-based selection
- Confidence: Low (~50%)
- Speed: Fast (no k-NN search)

### After 10-20 Similar Tasks
- Method: k-NN based selection
- Confidence: Medium (60-80%)
- Speed: Fast (efficient vector search)
- Improvement: Better agent matching

### After 50+ Tasks Across Domains
- Method: k-NN with high confidence
- Confidence: High (80-95%)
- Speed: Fast
- Improvement: Optimal agent selection, adaptive thresholds

### Storage Requirements
- ~1KB per task record (JSON)
- 1000 tasks ≈ 1MB
- Efficient, scalable

## Advanced Features

### Custom Similarity Metrics

```php
// Use Euclidean distance instead of cosine
$matcher = new KNNMatcher();
$nearest = $matcher->findNearest(
    $queryVector,
    $candidates,
    k: 5,
    metric: 'euclidean'
);
```

### Feature Importance Analysis

```php
$embedder = new TaskEmbedder();
$importance = $embedder->calculateFeatureImportance($historicalVectors);

// Example output:
// [1.2, 0.8, 1.5, ...] - higher values = more discriminative features
```

### Manual History Management

```php
$historyStore = $service->getHistoryStore();

// Clear history (fresh start)
$historyStore->clear();

// Export history
$allHistory = $historyStore->getAll();
file_put_contents('backup.json', json_encode($allHistory));

// Import history
$history = json_decode(file_get_contents('backup.json'), true);
foreach ($history as $record) {
    $historyStore->record($record);
}
```

### Agent Performance on Similar Tasks

```php
$historyStore = $service->getHistoryStore();

$performance = $historyStore->getAgentPerformanceOnSimilar(
    $taskVector,
    'react_agent',
    k: 10
);

/*
[
    'attempts' => 5,
    'successes' => 4,
    'success_rate' => 0.8,
    'avg_quality' => 8.1,
    'avg_duration' => 3.2,
    'sample_size' => 5,
    'avg_similarity' => 0.87
]
*/
```

## Configuration Options

```php
new AdaptiveAgentService($client, [
    // k-NN Settings
    'enable_knn' => true,               // Enable learning (default: true)
    'history_store_path' => 'path.json', // Storage location
    
    // Quality Settings
    'quality_threshold' => 7.0,         // Base threshold
    'adaptive_threshold' => true,       // Adjust based on history
    
    // Attempt Settings
    'max_attempts' => 3,                // Max attempts per task
    'enable_reframing' => true,         // Reframe on failure
    
    // Logging
    'logger' => $psrLogger,             // PSR-3 logger
]);
```

## Best Practices

### 1. **Start with Representative Tasks**
Run diverse tasks initially to build a good baseline:
```php
$tasks = [
    'Calculate interest...',     // Math/tools
    'Write a professional...',   // Quality writing
    'Explain the concept...',    // Reasoning
    'Analyze this data...',      // Analysis
];
```

### 2. **Monitor Learning Progress**
```php
// Check after every 10 tasks
if (count($historyStore->getAll()) % 10 === 0) {
    $stats = $historyStore->getStats();
    echo "Success rate: {$stats['success_rate']}\n";
    echo "Avg quality: {$stats['avg_quality']}\n";
}
```

### 3. **Periodic History Cleanup**
```php
// Keep last 1000 most recent tasks
$store = new TaskHistoryStore(
    storePath: 'history.json',
    maxHistorySize: 1000  // Auto-prunes old entries
);
```

### 4. **Use Appropriate k Values**
- Small history (<20): `k=3`
- Medium history (20-100): `k=5-7`
- Large history (>100): `k=10-15`

### 5. **Backup History Periodically**
```php
// Weekly backup
$history = $historyStore->getAll();
$backup = "backup_" . date('Y-m-d') . ".json";
file_put_contents($backup, json_encode($history, JSON_PRETTY_PRINT));
```

## Comparison: Rule-Based vs k-NN

| Aspect | Rule-Based | k-NN Enhanced |
|--------|------------|---------------|
| **Initial Performance** | Good | Good (uses rules) |
| **Learning** | None | Improves over time |
| **Adaptability** | Fixed rules | Learns patterns |
| **Confidence** | ~50% | 60-95% (with data) |
| **Quality Threshold** | Fixed | Adaptive |
| **Maintenance** | Manual rule tuning | Self-improving |
| **Cold Start** | Ready immediately | Needs 5-10 examples |

## Example Scenarios

### Scenario 1: Math/Calculation Tasks

```php
// First time
$result = $service->run('Calculate 15% of 240 plus square root of 144');
// → Uses react_agent (rule-based: requires tools)
// → Records: quality=8.5, success=true

// Second similar task
$result = $service->run('What is 20% of 500 minus 30?');
// → Uses react_agent (k-NN: similar to first task)
// → Confidence: 0.85 (based on previous success)
```

### Scenario 2: Quality Writing Tasks

```php
// First time
$result = $service->run('Write a professional email apologizing...');
// → Uses reflection_agent (rule-based: requires quality=extreme)
// → Records: quality=9.2, success=true

// Second similar task
$result = $service->run('Write a formal letter to...');
// → Uses reflection_agent (k-NN: similar high-quality writing)
// → Threshold adapted: 8.5 (based on historical quality)
```

## Troubleshooting

### Low k-NN Confidence

**Cause:** Insufficient historical data
**Solution:** Run 10-20 diverse tasks to build baseline

### Wrong Agent Selected

**Cause:** Poor task similarity or biased history
**Solution:** 
- Review task feature vector
- Check similar historical tasks
- Add more diverse examples

### Slow Selection

**Cause:** Large history (>10,000 records)
**Solution:** Reduce `maxHistorySize` or implement pruning

### Quality Threshold Issues

**Cause:** Adaptive threshold too high/low
**Solution:** 
- Set min/max bounds in `getAdaptiveThreshold()`
- Override with fixed threshold for specific tasks

## Future Enhancements

Potential improvements:
1. **Multi-modal embeddings** (combine task text + structured features)
2. **Weighted feature selection** (learn which features matter most)
3. **Agent clustering** (identify specialist vs generalist agents)
4. **Active learning** (identify tasks that would improve learning most)
5. **Cross-task transfer** (learn from related task types)

## See Also

- [Adaptive Agent Service Documentation](adaptive-agent-service.md)
- [Agent Selection Guide](agent-selection-guide.md)
- [Example: adaptive-agent-knn.php](../examples/adaptive-agent-knn.php)

