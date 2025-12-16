# ML-Enhanced Agents Examples

This directory contains examples demonstrating the ML capabilities of enhanced agents.

## Overview

All examples show how agents can learn and improve automatically using the k-NN based ML framework.

## Examples

### 1. `coordinator-ml-example.php`
**CoordinatorAgent with ML Worker Selection**

Demonstrates how the Coordinator learns which workers perform best on different task types.

**Key Features:**
- Automatic worker selection based on historical performance
- Load balancing with learned preferences
- Performance tracking per worker
- Workload distribution analysis

**Run:**
```bash
php examples/ml-enhanced/coordinator-ml-example.php
```

### 2. `all-agents-ml-showcase.php`
**Comprehensive ML Showcase**

Demonstrates all ML-enhanced agents working together:
- CoordinatorAgent - Worker selection learning
- TreeOfThoughtsAgent - Strategy and parameter learning
- ReflectionAgent - Adaptive refinement
- RAGAgent - Retrieval optimization

**Run:**
```bash
php examples/ml-enhanced/all-agents-ml-showcase.php
```

## ML Features Demonstrated

### CoordinatorAgent
- ✅ Learns optimal worker selection
- ✅ Balances load with performance awareness
- ✅ Tracks success rates per worker

### TreeOfThoughtsAgent
- ✅ Learns best search strategy (BFS/DFS/Best-First)
- ✅ Optimizes branch_count parameter
- ✅ Optimizes max_depth parameter
- ✅ Reduces exploration time by 20-30%

### ReflectionAgent
- ✅ Learns optimal refinement count
- ✅ Adapts quality threshold
- ✅ Detects diminishing returns
- ✅ Reduces unnecessary API calls by 15-25%

### RAGAgent
- ✅ Learns optimal topK (retrieval count)
- ✅ Adapts to query complexity
- ✅ Improves relevance by 10-20%

## Expected Output

Each example will show:

1. **Agent Configuration** - ML settings and parameters
2. **Execution Results** - Task outcomes with metadata
3. **Learning Statistics** - Performance metrics and learning progress
4. **Improvement Over Time** - How agents get better with more executions

## Learning Process

### First Run
- Agents use default parameters
- Start building learning history
- May not show optimal performance yet

### After 10-20 Runs
- Clear learning patterns emerge
- Parameter optimization becomes evident
- Performance improvements visible

### After 50+ Runs
- Mature learning models
- Consistent optimal parameter selection
- Significant performance gains

## Tips

1. **Run Multiple Times** - Learning improves with more data
2. **Vary Tasks** - Diverse tasks help agents learn better
3. **Check Statistics** - Use `getLearningStats()` to monitor progress
4. **Compare Performance** - Note improvements over time

## Storage

Learning history is stored in `storage/` directory:
- `coordinator_ml.json` - Coordinator learning data
- `showcase_tot.json` - TreeOfThoughts learning data
- `showcase_reflection.json` - Reflection learning data
- `showcase_rag.json` - RAG learning data

## Troubleshooting

### "No learning improvement"
**Solution:** Need more diverse training data. Run 20-30 varied tasks.

### "Parameters not optimizing"
**Solution:** Similar tasks not found in history. Continue running to build history.

### "Low confidence scores"
**Solution:** Need more examples of each scenario. Run each agent 10+ times.

## Next Steps

1. Run examples multiple times to build learning history
2. Observe performance improvements
3. Check learning statistics regularly
4. Apply ML traits to your own custom agents!

## Related Documentation

- [ML Traits Guide](../../docs/ML-Traits-Guide.md)
- [k-NN Learning Guide](../../docs/knn-learning.md)
- [ML Opportunities Tracker](../../docs/ML-OPPORTUNITIES-TRACKER.md)

---

**The future of intelligent agents is here! 🚀**

