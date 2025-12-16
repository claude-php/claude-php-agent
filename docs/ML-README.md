# Machine Learning Components

This directory contains machine learning utilities that enhance agent performance through learning and adaptation.

## Overview

The ML components provide k-Nearest Neighbors (k-NN) based learning capabilities for the Adaptive Agent Service, enabling the system to learn from historical task executions and improve agent selection over time.

## Components

### 📊 KNNMatcher

**Purpose:** Core k-NN similarity search functionality

**Location:** `src/ML/KNNMatcher.php`

**Features:**
- Multiple distance metrics (cosine, Euclidean, Manhattan)
- Temporal weighting for recency-based relevance
- Configurable similarity thresholds
- Vector normalization and weighted distances

**Usage:**
```php
use ClaudeAgents\ML\KNNMatcher;

$matcher = new KNNMatcher();
$nearest = $matcher->findNearest($queryVector, $candidates, k: 5, metric: 'cosine');
```

### 🎯 TaskEmbedder

**Purpose:** Convert task analysis into numerical feature vectors

**Location:** `src/ML/TaskEmbedder.php`

**Features:**
- 14-dimensional feature space
- Task characteristics encoding (complexity, domain, requirements)
- Weighted embedding support
- Feature importance analysis

**Vector Structure:**
```
[complexity, domain×6, flags×4, quality, steps, req_count] = 14 dimensions
```

**Usage:**
```php
use ClaudeAgents\ML\TaskEmbedder;

$embedder = new TaskEmbedder();
$vector = $embedder->embed($taskAnalysis);
```

### 💾 TaskHistoryStore

**Purpose:** Persistent storage for task execution history

**Location:** `src/ML/TaskHistoryStore.php`

**Features:**
- JSON-based persistent storage
- k-NN similarity search on historical tasks
- Agent performance analytics
- Adaptive quality threshold calculation
- Automatic history pruning (configurable max size)

**Usage:**
```php
use ClaudeAgents\ML\TaskHistoryStore;

$store = new TaskHistoryStore('storage/agent_history.json');
$store->record([...]);
$similar = $store->findSimilar($taskVector, k: 5);
```

## Integration

### Adaptive Agent Service

The ML components are integrated into the `AdaptiveAgentService` to provide learning-based agent selection:

```php
use ClaudeAgents\Agents\AdaptiveAgentService;

$service = new AdaptiveAgentService($client, [
    'enable_knn' => true,  // Enable ML-based learning
    'history_store_path' => 'storage/agent_history.json',
]);

// The service now:
// 1. Records every task execution with outcomes
// 2. Uses k-NN to find similar historical tasks
// 3. Selects agents based on historical performance
// 4. Adapts quality thresholds based on task difficulty
// 5. Improves selection confidence over time
```

## How It Works

### Learning Loop

```
┌─────────────────────────────────────────────────┐
│                                                 │
│  Task → Embed → k-NN Search → Select Agent     │
│           ↓                          ↓          │
│      Store Vector              Execute Task     │
│                                       ↓          │
│                              Record Outcome     │
│                                       ↓          │
│                            ┌──────────┴────────┐│
│                            │   Learning Loop   ││
│                            │  (Improves over   ││
│                            │     time)         ││
│                            └───────────────────┘│
└─────────────────────────────────────────────────┘
```

### Selection Algorithm

1. **Generate Task Vector** - Convert task analysis to 14D vector
2. **Check History** - Do we have similar tasks?
3. **k-NN Selection** (if history available):
   - Find k=10 most similar historical tasks
   - Weight by temporal decay (recent = more relevant)
   - Group by agent_id
   - Calculate weighted scores
   - Select best performing agent
4. **Rule-Based Fallback** (if no history):
   - Use original hardcoded selection rules
5. **Execute & Record** - Run task, store outcome for future learning

### Key Benefits

✅ **Improves Over Time** - Every execution teaches the system  
✅ **Adaptive Thresholds** - Adjusts expectations based on task difficulty  
✅ **High Confidence** - 80-95% confidence after sufficient data  
✅ **Automatic Learning** - No manual tuning required  
✅ **Fallback Safe** - Uses rule-based selection when no history available  

## Examples

### Basic Usage

See: `examples/adaptive-agent-knn.php`

```php
// Register agents
$service->registerAgent('react_agent', $reactAgent, [...]);
$service->registerAgent('reflection_agent', $reflectionAgent, [...]);

// Execute tasks - learning happens automatically
$result = $service->run('Calculate compound interest...');
// First time: rule-based selection
// Similar tasks later: k-NN based selection with high confidence!

// Monitor learning progress
$stats = $service->getHistoryStats();
echo "Total records: {$stats['total_records']}\n";
echo "Success rate: {$stats['success_rate']}\n";
```

### Performance Analysis

```php
// Get agent performance comparison
$performance = $service->getPerformance();

foreach ($performance as $agentId => $perf) {
    $successRate = ($perf['successes'] / $perf['attempts']) * 100;
    echo "{$agentId}: {$successRate}% success, avg quality {$perf['average_quality']}\n";
}

// Get recommendations without execution
$recommendation = $service->recommendAgent($task);
echo "Best agent: {$recommendation['agent_id']} ({$recommendation['confidence']})\n";
echo "Reasoning: {$recommendation['reasoning']}\n";
```

## Performance Characteristics

| Stage | Records | Method | Confidence | Selection Quality |
|-------|---------|--------|------------|-------------------|
| Initial | 0-5 | Rule-based | 50% | Good |
| Learning | 5-20 | Mixed | 60-70% | Better |
| Learned | 20+ | k-NN | 80-95% | Optimal |

## Configuration

### Storage Location

```php
new AdaptiveAgentService($client, [
    'history_store_path' => 'custom/path/history.json',
]);
```

### History Size Limits

```php
$store = new TaskHistoryStore(
    storePath: 'history.json',
    maxHistorySize: 1000  // Keep last 1000 records
);
```

### Disable k-NN (Rule-Based Only)

```php
new AdaptiveAgentService($client, [
    'enable_knn' => false,  // Disable learning
]);
```

## Best Practices

### 1. Build Initial History
Run 10-20 diverse representative tasks initially:
```php
$diverseTasks = [
    'Calculate...',      // Math/tools
    'Write...',          // Quality writing
    'Explain...',        // Reasoning
    'Analyze...',        // Analysis
];
```

### 2. Monitor Learning
```php
$stats = $service->getHistoryStats();
if ($stats['total_records'] >= 20) {
    echo "System has learned from {$stats['total_records']} tasks!\n";
    echo "Success rate: " . ($stats['success_rate'] * 100) . "%\n";
}
```

### 3. Backup History
```php
// Periodic backup
$history = $service->getHistoryStore()->getAll();
file_put_contents(
    'backup_' . date('Y-m-d') . '.json',
    json_encode($history, JSON_PRETTY_PRINT)
);
```

### 4. Use Appropriate k Values
- Small history (<20): `k=3`
- Medium (20-100): `k=5-7`
- Large (>100): `k=10-15`

## Documentation

- **[k-NN Learning Guide](knn-learning.md)** - Comprehensive documentation
- **[Adaptive Agent Service](adaptive-agent-service.md)** - Main service docs
- **[Agent Selection Guide](agent-selection-guide.md)** - Selection strategies

## Metrics & Monitoring

### Key Metrics

```php
// History statistics
$stats = $service->getHistoryStats();
- total_records: Number of historical executions
- unique_agents: Number of different agents used
- success_rate: Overall success rate
- avg_quality: Average quality score
- oldest_record: Timestamp of oldest record
- newest_record: Timestamp of newest record

// Agent performance
$perf = $service->getPerformance();
- attempts: Times agent was used
- successes: Successful executions
- average_quality: Average quality score
- total_duration: Total execution time
```

### Monitoring Example

```php
function logMetrics($service) {
    $stats = $service->getHistoryStats();
    
    echo "=== Learning Metrics ===\n";
    echo "Records: {$stats['total_records']}\n";
    echo "Success: " . number_format($stats['success_rate'] * 100, 1) . "%\n";
    echo "Quality: " . number_format($stats['avg_quality'], 1) . "/10\n";
    
    if ($stats['success_rate'] > 0.9) {
        echo "✅ System performing excellently!\n";
    }
}
```

## Technical Details

### Feature Vector Dimensions

```
Dimension | Feature               | Type      | Range
----------|----------------------|-----------|-------
0         | complexity           | Float     | 0-1
1-6       | domain (one-hot)     | Binary    | 0/1
7         | requires_tools       | Binary    | 0/1
8         | requires_knowledge   | Binary    | 0/1
9         | requires_reasoning   | Binary    | 0/1
10        | requires_iteration   | Binary    | 0/1
11        | requires_quality     | Float     | 0-1
12        | estimated_steps      | Float     | 0-1 (normalized)
13        | key_requirements     | Float     | 0-1 (normalized)
```

### Similarity Calculation

Default: Cosine similarity
```
similarity = (A · B) / (||A|| × ||B||)
```

Temporal weighting:
```
weight = exp(-ln(2) × age_days / 30)
```

### Storage Format

JSON structure:
```json
{
  "id": "task_abc123",
  "task": "Calculate compound interest...",
  "task_vector": [0.5, 0, 1, 0, 0, 0, 0, 1, 0, 1, 0, 0.66, 0.2, 0.3],
  "task_analysis": {...},
  "agent_id": "react_agent",
  "agent_type": "react",
  "success": true,
  "quality_score": 8.5,
  "duration": 3.2,
  "timestamp": 1702456789,
  "metadata": {...}
}
```

## Troubleshooting

### Issue: Low k-NN confidence
**Solution:** Build history with 10-20 diverse tasks

### Issue: Wrong agent selected
**Solution:** Review task vectors, add more examples in that domain

### Issue: Slow selection (large history)
**Solution:** Reduce `maxHistorySize` or implement pruning

### Issue: Adaptive threshold too high/low
**Solution:** Override with fixed threshold for specific task types

## Future Roadmap

Potential enhancements:
- [ ] Multi-modal embeddings (text + structured)
- [ ] Weighted feature learning
- [ ] Agent clustering (specialist identification)
- [ ] Active learning (smart data collection)
- [ ] Cross-task transfer learning
- [ ] Vector database integration (Pinecone, Qdrant)
- [ ] Real-time performance dashboards

## Contributing

When adding ML features:
1. Add comprehensive PHPDoc
2. Include usage examples
3. Add unit tests
4. Update this README
5. Maintain backward compatibility

## License

Same as parent project (see root LICENSE file)

