# TaskPrioritizationAgent Documentation

## Overview

The `TaskPrioritizationAgent` is a BabyAGI-style agent that implements dynamic task generation, prioritization, and execution. It breaks down complex goals into subtasks, prioritizes them intelligently, and executes them in order while dynamically generating new tasks based on progress.

## Key Features

- **Dynamic Task Generation**: Automatically generates subtasks to achieve a goal
- **Priority-Based Execution**: Executes tasks in order of priority (highest first)
- **Adaptive Task Creation**: Generates additional tasks based on progress and results
- **Progress Tracking**: Tracks completed and remaining tasks
- **Iteration Limits**: Built-in safety limits to prevent infinite loops
- **Dependency Management**: Considers completed tasks when generating new ones

## Architecture

```
┌─────────────────────────────────────┐
│   TaskPrioritizationAgent           │
├─────────────────────────────────────┤
│ - ClaudePhp client                  │
│ - Task queue (prioritized)          │
│ - Completed tasks list              │
│ - Goal tracking                     │
└──────────┬──────────────────────────┘
           │
           ├─── Task Generation
           │    ├─── Generate initial tasks
           │    └─── Generate follow-up tasks
           │
           ├─── Prioritization
           │    └─── Sort by priority score
           │
           └─── Execution
                ├─── Execute highest priority
                ├─── Track completion
                └─── Iterate
```

## How It Works

1. **Task Generation**: The agent analyzes the goal and generates 5-7 initial tasks with priorities and effort estimates
2. **Prioritization**: Tasks are sorted by priority (1-10 scale, highest first)
3. **Execution Loop**:
   - Dequeue highest priority task
   - Execute the task
   - Mark as completed
   - Generate additional tasks if needed
   - Re-prioritize the queue
   - Repeat until queue is empty or max iterations reached
4. **Result Formatting**: Compiles all results into a comprehensive report

## Classes

### TaskPrioritizationAgent

The main agent class that orchestrates task generation and execution.

**Namespace**: `ClaudeAgents\Agents`

**Implements**: `AgentInterface`

## Usage

### Basic Usage

```php
use ClaudeAgents\Agents\TaskPrioritizationAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$agent = new TaskPrioritizationAgent($client);

$result = $agent->run('Plan and design a microservices architecture');

if ($result->isSuccess()) {
    echo "Tasks completed: " . $result->getMetadata()['tasks_completed'] . "\n";
    echo "Tasks remaining: " . $result->getMetadata()['tasks_remaining'] . "\n";
    echo "\nResults:\n" . $result->getAnswer() . "\n";
}
```

### Configuration Options

```php
$agent = new TaskPrioritizationAgent($client, [
    'name' => 'my_task_agent',      // Agent name
    'goal' => 'Initial goal',        // Initial goal (overridden by run())
    'logger' => $psrLogger,          // PSR-3 logger
]);
```

### Task Structure

Each task has the following structure:

```json
{
    "description": "Implement user authentication",
    "priority": 9,
    "estimated_effort": 4
}
```

- **description**: What needs to be done
- **priority**: 1-10 (10 is highest)
- **estimated_effort**: 1-5 (5 is most effort)

### Example Workflows

#### Project Planning

```php
$agent = new TaskPrioritizationAgent($client, [
    'name' => 'project_planner',
]);

$result = $agent->run('Plan a blog website with user authentication');

if ($result->isSuccess()) {
    echo "Goal: " . $result->getMetadata()['goal'] . "\n";
    echo "Tasks completed: " . $result->getMetadata()['tasks_completed'] . "\n";
    echo "\nPlan:\n" . $result->getAnswer() . "\n";
}
```

#### Learning Path Creation

```php
$agent = new TaskPrioritizationAgent($client, [
    'name' => 'learning_planner',
]);

$result = $agent->run('Create a learning path for mastering PHP design patterns');

if ($result->isSuccess()) {
    // Process the learning path
    $iterations = $result->getIterations();
    $tasksCompleted = $result->getMetadata()['tasks_completed'];
    
    echo "Generated learning path with {$tasksCompleted} tasks in {$iterations} iterations\n";
}
```

#### API Development Plan

```php
$agent = new TaskPrioritizationAgent($client);

$result = $agent->run('Design a REST API for a task management system');

if ($result->isSuccess()) {
    $plan = $result->getAnswer();
    
    // Save to file or database
    file_put_contents('api_plan.txt', $plan);
    
    echo "API plan saved with " . 
         $result->getMetadata()['tasks_completed'] . 
         " completed tasks\n";
}
```

## Agent Result

The `run()` method returns an `AgentResult` object with:

```php
$result = $agent->run('task');

// Standard properties
$result->isSuccess();      // bool
$result->getAnswer();      // string (formatted results)
$result->getError();       // string (if failed)
$result->getIterations();  // int (number of iterations)

// Metadata
$metadata = $result->getMetadata();
$metadata['goal'];              // The goal that was executed
$metadata['tasks_completed'];   // Number of tasks completed
$metadata['tasks_remaining'];   // Number of tasks still in queue
```

## Task Prioritization Logic

### Priority Sorting

Tasks are sorted in descending order by priority:

```php
// High priority tasks execute first
[
    ['description' => 'Critical task', 'priority' => 10],
    ['description' => 'Important task', 'priority' => 8],
    ['description' => 'Normal task', 'priority' => 5],
    ['description' => 'Low priority task', 'priority' => 2],
]
```

### Dynamic Task Generation

After each task execution, the agent evaluates if additional tasks are needed:

```php
// Context provided to the agent for new task generation:
// - Current goal
// - Completed tasks
// - Last result

// Agent decides:
// - Are additional tasks needed?
// - If yes, generate 1-2 new tasks with priorities
// - If no, return empty array
```

### Iteration Control

```php
$maxIterations = 20;  // Default limit

// Agent stops when:
// 1. Task queue is empty, OR
// 2. Max iterations reached

// Additional task generation stops at iteration 15
// (maxIterations - 5) to allow queue to clear
```

## Best Practices

### 1. Define Clear Goals

```php
// ✅ Good: Specific and actionable
$result = $agent->run(
    'Create a deployment plan including: infrastructure setup, ' .
    'CI/CD pipeline, monitoring, and rollback strategy'
);

// ❌ Avoid: Vague and open-ended
$result = $agent->run('Do some planning');
```

### 2. Scope Appropriately

```php
// ✅ Good: Bounded scope
$result = $agent->run('Design database schema for user management module');

// ❌ Avoid: Unbounded scope
$result = $agent->run('Build entire application');
```

### 3. Use Descriptive Names

```php
// ✅ Good: Clear purpose
$projectAgent = new TaskPrioritizationAgent($client, [
    'name' => 'api_project_planner',
]);

$learningAgent = new TaskPrioritizationAgent($client, [
    'name' => 'php_learning_path_creator',
]);
```

### 4. Monitor Progress

```php
$result = $agent->run($task);

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    echo "Progress Report:\n";
    echo "  Goal: {$metadata['goal']}\n";
    echo "  Iterations: {$result->getIterations()}\n";
    echo "  Completed: {$metadata['tasks_completed']}\n";
    echo "  Remaining: {$metadata['tasks_remaining']}\n";
    
    if ($metadata['tasks_remaining'] > 0) {
        echo "  Status: Stopped at max iterations\n";
    } else {
        echo "  Status: All tasks completed\n";
    }
}
```

### 5. Handle Errors Gracefully

```php
$result = $agent->run($task);

if (!$result->isSuccess()) {
    $logger->error('Task prioritization failed', [
        'error' => $result->getError(),
        'goal' => $task,
    ]);
    
    // Decide on recovery strategy
    // - Retry with simpler goal
    // - Break into smaller tasks
    // - Alert for manual intervention
}
```

### 6. Use Logging in Production

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('task_agent');
$logger->pushHandler(new StreamHandler('/var/log/task_agent.log', Logger::INFO));

$agent = new TaskPrioritizationAgent($client, [
    'logger' => $logger,
]);

// Logger will record:
// - Task generation
// - Task execution
// - Additional task generation
// - Errors and warnings
```

## Advanced Patterns

### Pattern 1: Hierarchical Task Planning

```php
// High-level planner
$highLevelAgent = new TaskPrioritizationAgent($client, [
    'name' => 'high_level_planner',
]);

$highLevelResult = $highLevelAgent->run('Plan major phases of project');

// Extract major phases from result
$majorPhases = parsePhasesFromResult($highLevelResult->getAnswer());

// Detailed planning for each phase
foreach ($majorPhases as $phase) {
    $detailAgent = new TaskPrioritizationAgent($client, [
        'name' => "phase_{$phase}_planner",
    ]);
    
    $detailResult = $detailAgent->run("Create detailed plan for: {$phase}");
    
    // Store or process detailed plan
    savePlan($phase, $detailResult->getAnswer());
}
```

### Pattern 2: Iterative Refinement

```php
$iterations = 0;
$maxRefinements = 3;
$currentPlan = '';

while ($iterations < $maxRefinements) {
    $prompt = $iterations === 0
        ? 'Create initial plan for e-commerce platform'
        : "Refine this plan with more detail:\n\n{$currentPlan}";
    
    $result = $agent->run($prompt);
    
    if ($result->isSuccess()) {
        $currentPlan = $result->getAnswer();
        echo "Refinement {$iterations + 1}: " .
             "{$result->getMetadata()['tasks_completed']} tasks\n";
    }
    
    $iterations++;
}

echo "\nFinal Plan:\n{$currentPlan}\n";
```

### Pattern 3: Parallel Planning

```php
use Amp\Parallel\Worker;

$tasks = [
    'Plan frontend architecture',
    'Plan backend architecture',
    'Plan database schema',
    'Plan deployment strategy',
];

$results = [];

// Create agents for each task (in parallel if using async)
foreach ($tasks as $task) {
    $agent = new TaskPrioritizationAgent($client, [
        'name' => 'planner_' . md5($task),
    ]);
    
    $results[$task] = $agent->run($task);
}

// Combine results
foreach ($results as $task => $result) {
    if ($result->isSuccess()) {
        echo "=== {$task} ===\n";
        echo $result->getAnswer() . "\n\n";
    }
}
```

### Pattern 4: Progress Tracking

```php
class TaskProgressTracker
{
    private array $history = [];
    
    public function track(string $goal, AgentResult $result): void
    {
        $this->history[] = [
            'goal' => $goal,
            'timestamp' => time(),
            'tasks_completed' => $result->getMetadata()['tasks_completed'],
            'tasks_remaining' => $result->getMetadata()['tasks_remaining'],
            'iterations' => $result->getIterations(),
        ];
    }
    
    public function getReport(): string
    {
        $report = "Task Progress Report\n";
        $report .= "===================\n\n";
        
        foreach ($this->history as $entry) {
            $report .= sprintf(
                "[%s] %s: %d completed, %d remaining (%d iterations)\n",
                date('Y-m-d H:i:s', $entry['timestamp']),
                $entry['goal'],
                $entry['tasks_completed'],
                $entry['tasks_remaining'],
                $entry['iterations']
            );
        }
        
        return $report;
    }
}

// Usage
$tracker = new TaskProgressTracker();

$result1 = $agent->run('Plan phase 1');
$tracker->track('Phase 1', $result1);

$result2 = $agent->run('Plan phase 2');
$tracker->track('Phase 2', $result2);

echo $tracker->getReport();
```

## Performance Considerations

### Task Generation Overhead

Each task generation requires an API call. For complex goals:

```php
// Typical execution:
// 1 call for initial tasks (generates 5-7 tasks)
// N calls for task execution (N = number of tasks)
// M calls for additional task generation (M ≤ N)

// Total API calls ≈ 1 + N + M
// With 5 initial tasks and 2 additional: ~1 + 5 + 2 = 8 calls
```

### Iteration Limits

```php
// Default max iterations: 20
// Each iteration: 1 task execution + optional additional task generation

// Worst case: 20 executions + 20 additional task checks
// Typical: 5-10 executions with fewer additional task checks
```

### Optimization Tips

```php
// 1. Use specific goals to reduce task generation
$agent->run('Design user table schema');  // Focused
// vs
$agent->run('Design entire database');     // Broad

// 2. Monitor remaining tasks
$result = $agent->run($goal);
if ($result->getMetadata()['tasks_remaining'] > 0) {
    // Goal might be too complex, consider breaking down
}

// 3. Use appropriate context
// More context = better task generation
$agent->run(
    'Create API endpoints for user management. ' .
    'Must include: registration, login, profile update, password reset'
);
```

## Testing

### Unit Tests

The TaskPrioritizationAgent has comprehensive unit tests:

```bash
./vendor/bin/phpunit tests/Unit/Agents/TaskPrioritizationAgentTest.php
```

Tests cover:
- Task generation
- Task prioritization
- Task execution
- Dynamic task creation
- Error handling
- Iteration limits
- Result formatting

### Integration Tests

```bash
# Test with API (requires ANTHROPIC_API_KEY)
php examples/task_prioritization_example.php
```

## Troubleshooting

### Issue 1: Too Many Iterations

**Symptom**: Agent hits max iterations with many tasks remaining

**Solution**:

```php
// Make goal more specific
// Before
$result = $agent->run('Plan entire application');

// After
$result = $agent->run('Plan user authentication module');

// Or break into phases
$phases = ['Authentication', 'Database', 'API', 'Frontend'];
foreach ($phases as $phase) {
    $result = $agent->run("Plan {$phase} phase");
}
```

### Issue 2: Tasks Not Generated

**Symptom**: Agent completes with 0 tasks

**Solution**:

```php
$result = $agent->run($goal);

if ($result->getMetadata()['tasks_completed'] === 0) {
    // Goal might be too vague or unclear
    // Try making it more specific
    $result = $agent->run(
        $goal . '. Break this down into 5-7 specific actionable tasks.'
    );
}
```

### Issue 3: Low Quality Tasks

**Symptom**: Generated tasks are too generic

**Solution**:

```php
// Add more context to the goal
$result = $agent->run(
    'Plan a REST API for task management. ' .
    'Include: user authentication, task CRUD operations, ' .
    'task assignment, priority levels, and status tracking. ' .
    'Consider security, performance, and scalability.'
);
```

### Issue 4: API Rate Limiting

**Symptom**: Errors about rate limits

**Solution**:

```php
// Implement retry with backoff
$maxRetries = 3;
$result = null;

for ($i = 0; $i < $maxRetries; $i++) {
    $result = $agent->run($goal);
    
    if ($result->isSuccess()) {
        break;
    }
    
    if (str_contains($result->getError(), 'rate limit')) {
        $waitTime = (2 ** $i) * 1000000; // Exponential backoff in microseconds
        usleep($waitTime);
    } else {
        break;  // Non-rate-limit error
    }
}
```

## Comparison with Other Agents

### vs AutonomousAgent

- **TaskPrioritizationAgent**: Focuses on breaking down and prioritizing tasks
- **AutonomousAgent**: Focuses on goal-oriented multi-session execution with state persistence

Use TaskPrioritizationAgent when you need:
- Dynamic task breakdown
- Priority-based execution
- One-time planning sessions

Use AutonomousAgent when you need:
- Long-running goal tracking
- State persistence across sessions
- Progress monitoring over time

### vs CoordinatorAgent

- **TaskPrioritizationAgent**: Single agent that generates and executes its own tasks
- **CoordinatorAgent**: Delegates tasks to multiple specialized agents

Use TaskPrioritizationAgent when:
- Single agent is sufficient
- Tasks are conceptually similar

Use CoordinatorAgent when:
- Tasks require different specialized agents
- Need parallel execution by different agents

## API Reference

### Constructor

```php
public function __construct(
    ClaudePhp $client,
    array $options = []
)
```

**Options:**
- `name` (optional): Agent name (default: 'task_prioritization_agent')
- `goal` (optional): Initial goal (will be overridden by `run()`)
- `logger` (optional): PSR-3 logger

### Methods

#### run(string $task): AgentResult

Execute task prioritization for the given goal.

**Parameters:**
- `$task`: The goal to achieve

**Returns:** `AgentResult` with:
- `answer`: Formatted results
- `iterations`: Number of iterations executed
- `metadata['goal']`: The goal that was executed
- `metadata['tasks_completed']`: Number of tasks completed
- `metadata['tasks_remaining']`: Number of tasks still in queue

#### getName(): string

Get the agent name.

**Returns:** Agent name string

## Examples

See the `/examples` directory for complete working examples:

- `task_prioritization_example.php` - Basic usage and multiple scenarios

## Related Documentation

- [Agent Selection Guide](agent-selection-guide.md)
- [AutonomousAgent Documentation](AutonomousAgent.md)
- [CoordinatorAgent Documentation](CoordinatorAgent.md)
- [Examples README](../examples/README.md)

## License

MIT License - See [LICENSE](../LICENSE) file for details.

