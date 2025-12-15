# TaskPrioritizationAgent Tutorial

## Introduction

Welcome to the TaskPrioritizationAgent tutorial! In this guide, you'll learn how to use BabyAGI-style task generation and prioritization to break down complex goals into actionable subtasks and execute them intelligently.

### What You'll Learn

1. Creating task prioritization agents
2. Understanding dynamic task generation
3. Priority-based execution
4. Handling task dependencies
5. Monitoring progress and results
6. Building real-world applications

### Prerequisites

- PHP 8.1 or higher
- Composer installed
- Anthropic API key (for live examples)
- Basic understanding of PHP and the Claude API

### Time Required

Approximately 25-35 minutes

---

## Chapter 1: Your First Task Prioritization Agent

Let's start with a simple example that breaks down a project goal into tasks.

### Step 1: Set Up Your Environment

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use ClaudeAgents\Agents\TaskPrioritizationAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
```

### Step 2: Create Your First Agent

```php
$agent = new TaskPrioritizationAgent($client);

echo "Agent created: {$agent->getName()}\n";
```

### Step 3: Run Your First Task Prioritization

```php
$result = $agent->run('Plan a simple blog website');

if ($result->isSuccess()) {
    echo "\n✓ Task prioritization completed!\n\n";
    echo "Results:\n";
    echo $result->getAnswer();
} else {
    echo "\n✗ Failed: {$result->getError()}\n";
}
```

### What Just Happened?

1. **Task Generation**: The agent analyzed the goal and created 5-7 initial tasks
2. **Prioritization**: Tasks were sorted by priority (highest first)
3. **Execution**: Each task was executed in priority order
4. **Dynamic Generation**: After each task, new tasks were potentially added
5. **Results**: All task results were compiled into a comprehensive report

### Understanding the Output

The agent returns a formatted report like:

```
Task Prioritization Results
===========================

Goal: Plan a simple blog website

Completed Tasks: 5

1. Define core features and user requirements...
2. Design database schema for posts, users, comments...
3. Plan authentication system...
4. Outline content management features...
5. Create deployment strategy...
```

### Try It Yourself

Create an agent with a different goal:

```php
$agent = new TaskPrioritizationAgent($client);

$result = $agent->run('Create a learning path for PHP 8.4 features');

if ($result->isSuccess()) {
    echo "Tasks completed: " . $result->getMetadata()['tasks_completed'] . "\n";
    echo "Tasks remaining: " . $result->getMetadata()['tasks_remaining'] . "\n";
}
```

---

## Chapter 2: Understanding Task Generation

Learn how the agent generates and manages tasks dynamically.

### Task Structure

Each generated task has three key properties:

```json
{
    "description": "Implement user authentication",
    "priority": 9,
    "estimated_effort": 4
}
```

- **description**: What needs to be done
- **priority**: 1-10 scale (10 = highest priority)
- **estimated_effort**: 1-5 scale (5 = most effort)

### Initial Task Generation

```php
// When you run the agent, it first generates 5-7 initial tasks
$agent = new TaskPrioritizationAgent($client);

$result = $agent->run('Design a REST API for a task management system');

// The agent generates tasks like:
// 1. Define API endpoints and resources (priority: 10)
// 2. Design authentication mechanism (priority: 9)
// 3. Create data models (priority: 8)
// 4. Plan error handling strategy (priority: 7)
// 5. Design rate limiting (priority: 6)
```

### Dynamic Task Generation

After executing each task, the agent evaluates if more tasks are needed:

```php
// After completing "Define API endpoints", the agent might generate:
// - "Create OpenAPI specification" (priority: 8)
// - "Document authentication flow" (priority: 7)

// The agent considers:
// - What has been completed
// - What the last result was
// - What's needed to achieve the goal
```

### Viewing Metadata

```php
$result = $agent->run('Plan a mobile app');

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    
    echo "Goal: {$metadata['goal']}\n";
    echo "Tasks completed: {$metadata['tasks_completed']}\n";
    echo "Tasks remaining: {$metadata['tasks_remaining']}\n";
    echo "Iterations: {$result->getIterations()}\n";
}
```

### Exercise: Analyze Task Generation

```php
$agent = new TaskPrioritizationAgent($client);

$goal = 'Create a comprehensive testing strategy for a PHP application';
$result = $agent->run($goal);

if ($result->isSuccess()) {
    echo "Analysis:\n";
    echo "- Goal: {$result->getMetadata()['goal']}\n";
    echo "- Tasks generated and completed: {$result->getMetadata()['tasks_completed']}\n";
    echo "- Iterations used: {$result->getIterations()} / 20\n";
    echo "- Average tasks per iteration: " . 
         ($result->getMetadata()['tasks_completed'] / $result->getIterations()) . "\n";
}
```

---

## Chapter 3: Priority-Based Execution

Understand how the agent prioritizes and executes tasks.

### How Prioritization Works

```php
// Tasks are sorted by priority (descending)
// Example queue after generation:
[
    ['description' => 'Critical security setup', 'priority' => 10],
    ['description' => 'Core functionality', 'priority' => 9],
    ['description' => 'User interface', 'priority' => 7],
    ['description' => 'Nice-to-have features', 'priority' => 5],
    ['description' => 'Optional enhancements', 'priority' => 3],
]

// Execution order: 10 → 9 → 7 → 5 → 3
```

### Observing Priority in Action

```php
$agent = new TaskPrioritizationAgent($client);

// Goal that should generate tasks with varying priorities
$result = $agent->run(
    'Plan a web application deployment including security, performance, monitoring, and documentation'
);

if ($result->isSuccess()) {
    // The agent naturally prioritizes:
    // 1. Security measures (highest priority)
    // 2. Performance optimization (high)
    // 3. Monitoring setup (medium-high)
    // 4. Documentation (medium)
    
    echo $result->getAnswer();
}
```

### Re-Prioritization

After generating additional tasks, the queue is re-prioritized:

```php
// Initial queue:
// 1. Task A (priority: 10)
// 2. Task B (priority: 8)
// 3. Task C (priority: 6)

// After Task A completes, new tasks generated:
// - Task D (priority: 9)
// - Task E (priority: 7)

// Queue is re-prioritized:
// 1. Task D (priority: 9) ← New task inserted
// 2. Task B (priority: 8)
// 3. Task E (priority: 7) ← New task inserted
// 4. Task C (priority: 6)
```

### Practice Exercise

```php
// Create two agents with different goal complexities
$simpleAgent = new TaskPrioritizationAgent($client);
$complexAgent = new TaskPrioritizationAgent($client);

$simpleResult = $simpleAgent->run('Create a simple contact form');
$complexResult = $complexAgent->run('Design a complete e-commerce platform');

echo "Simple goal:\n";
echo "  Tasks: {$simpleResult->getMetadata()['tasks_completed']}\n";
echo "  Iterations: {$simpleResult->getIterations()}\n\n";

echo "Complex goal:\n";
echo "  Tasks: {$complexResult->getMetadata()['tasks_completed']}\n";
echo "  Iterations: {$complexResult->getIterations()}\n";
```

---

## Chapter 4: Iteration Control and Limits

Learn how the agent manages execution limits and when to stop.

### Max Iterations

```php
// Default max iterations: 20
// The agent stops when:
// 1. Task queue is empty, OR
// 2. Max iterations reached

$agent = new TaskPrioritizationAgent($client);

// Simple goal - will complete before max iterations
$result1 = $agent->run('Create a README file structure');
echo "Iterations used: {$result1->getIterations()}\n";  // Likely 3-5

// Complex goal - might hit max iterations
$result2 = $agent->run('Plan entire enterprise application architecture');
echo "Iterations used: {$result2->getIterations()}\n";  // Likely 20
```

### Checking if Goal Completed

```php
$result = $agent->run($complexGoal);

if ($result->isSuccess()) {
    if ($result->getMetadata()['tasks_remaining'] === 0) {
        echo "✓ All tasks completed!\n";
    } else {
        echo "⚠ Stopped at max iterations\n";
        echo "Remaining tasks: {$result->getMetadata()['tasks_remaining']}\n";
    }
}
```

### Dynamic Task Generation Cutoff

```php
// Additional task generation stops at iteration 15
// (maxIterations - 5)
// This allows the queue to clear out

// Iterations 1-15: New tasks can be generated
// Iterations 16-20: Only execute existing tasks
```

### Handling Incomplete Goals

```php
$result = $agent->run('Design comprehensive system architecture');

if ($result->isSuccess()) {
    $remaining = $result->getMetadata()['tasks_remaining'];
    
    if ($remaining > 0) {
        echo "Goal not fully completed. {$remaining} tasks remaining.\n";
        echo "Consider breaking this into smaller goals:\n";
        
        // Example: Break down into phases
        $phases = [
            'Design high-level architecture',
            'Design data layer',
            'Design application layer',
            'Design presentation layer',
        ];
        
        foreach ($phases as $phase) {
            $phaseResult = $agent->run($phase);
            echo "  ✓ {$phase}: {$phaseResult->getMetadata()['tasks_completed']} tasks\n";
        }
    }
}
```

---

## Chapter 5: Custom Configuration

Learn how to customize the agent's behavior.

### Setting a Custom Name

```php
$agent = new TaskPrioritizationAgent($client, [
    'name' => 'api_planner',
]);

echo "Agent name: {$agent->getName()}\n";  // api_planner
```

### Adding Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('task_agent');
$logger->pushHandler(new StreamHandler('./logs/task_agent.log', Logger::INFO));

$agent = new TaskPrioritizationAgent($client, [
    'name' => 'logged_agent',
    'logger' => $logger,
]);

// Now all operations are logged
$result = $agent->run('Plan database schema');

// Log file will contain:
// - Task generation started
// - Tasks generated: 5
// - Executing task: ...
// - Task completed
// - Additional tasks generated: 2
// - etc.
```

### Configuration Best Practices

```php
// Production setup
$productionAgent = new TaskPrioritizationAgent($client, [
    'name' => 'production_planner_' . uniqid(),  // Unique name
    'logger' => $productionLogger,                // Proper logging
]);

// Development setup
$devAgent = new TaskPrioritizationAgent($client, [
    'name' => 'dev_test_agent',
    'logger' => new NullLogger(),  // Or verbose console logger
]);
```

### Exercise: Logging Analysis

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('analysis');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$agent = new TaskPrioritizationAgent($client, [
    'logger' => $logger,
]);

$result = $agent->run('Create API documentation strategy');

// Watch the log output to understand:
// - When tasks are generated
// - How many tasks at each step
// - When additional tasks are created
// - How the queue changes
```

---

## Chapter 6: Real-World Applications

Build practical applications using TaskPrioritizationAgent.

### Application 1: Project Planner

```php
class ProjectPlanner
{
    private TaskPrioritizationAgent $agent;
    
    public function __construct(ClaudePhp $client)
    {
        $this->agent = new TaskPrioritizationAgent($client, [
            'name' => 'project_planner',
        ]);
    }
    
    public function planProject(string $projectName, array $requirements): string
    {
        $requirementsText = implode(', ', $requirements);
        
        $goal = "Plan project: {$projectName}. Requirements: {$requirementsText}";
        
        $result = $this->agent->run($goal);
        
        if ($result->isSuccess()) {
            return $result->getAnswer();
        }
        
        throw new RuntimeException('Planning failed: ' . $result->getError());
    }
    
    public function getLastPlanMetadata(): array
    {
        // Return metadata from last execution
        return [
            'tasks_completed' => 0,  // Track this from result
            'iterations' => 0,
        ];
    }
}

// Usage
$planner = new ProjectPlanner($client);

$plan = $planner->planProject('E-commerce Website', [
    'User authentication',
    'Product catalog',
    'Shopping cart',
    'Payment integration',
    'Order management',
]);

echo "Project Plan:\n";
echo $plan;
```

### Application 2: Learning Path Creator

```php
class LearningPathCreator
{
    private TaskPrioritizationAgent $agent;
    
    public function __construct(ClaudePhp $client)
    {
        $this->agent = new TaskPrioritizationAgent($client, [
            'name' => 'learning_path_creator',
        ]);
    }
    
    public function createPath(string $topic, string $level = 'beginner'): array
    {
        $goal = "Create a {$level} learning path for: {$topic}";
        
        $result = $this->agent->run($goal);
        
        if (!$result->isSuccess()) {
            throw new RuntimeException('Failed to create learning path');
        }
        
        return [
            'path' => $result->getAnswer(),
            'metadata' => $result->getMetadata(),
        ];
    }
    
    public function createProgressivePathway(string $topic): array
    {
        $levels = ['beginner', 'intermediate', 'advanced'];
        $pathway = [];
        
        foreach ($levels as $level) {
            $pathway[$level] = $this->createPath($topic, $level);
        }
        
        return $pathway;
    }
}

// Usage
$creator = new LearningPathCreator($client);

// Single level path
$beginnerPath = $creator->createPath('PHP Design Patterns', 'beginner');
echo $beginnerPath['path'];

// Progressive pathway
$fullPathway = $creator->createProgressivePathway('PHP Design Patterns');

foreach ($fullPathway as $level => $data) {
    echo "\n=== {$level} Level ===\n";
    echo "Tasks: {$data['metadata']['tasks_completed']}\n";
    echo substr($data['path'], 0, 200) . "...\n";
}
```

### Application 3: API Development Planner

```php
class APIPlanner
{
    private TaskPrioritizationAgent $agent;
    private array $planHistory = [];
    
    public function __construct(ClaudePhp $client)
    {
        $this->agent = new TaskPrioritizationAgent($client, [
            'name' => 'api_planner',
        ]);
    }
    
    public function planAPI(string $apiPurpose, array $resources): array
    {
        $resourceList = implode(', ', $resources);
        
        $goal = "Design a REST API for {$apiPurpose}. Resources: {$resourceList}";
        
        $result = $this->agent->run($goal);
        
        if (!$result->isSuccess()) {
            throw new RuntimeException('API planning failed');
        }
        
        $plan = [
            'purpose' => $apiPurpose,
            'resources' => $resources,
            'plan' => $result->getAnswer(),
            'tasks_completed' => $result->getMetadata()['tasks_completed'],
            'timestamp' => time(),
        ];
        
        $this->planHistory[] = $plan;
        
        return $plan;
    }
    
    public function exportPlan(array $plan, string $format = 'markdown'): string
    {
        if ($format === 'markdown') {
            $output = "# API Plan: {$plan['purpose']}\n\n";
            $output .= "**Generated**: " . date('Y-m-d H:i:s', $plan['timestamp']) . "\n\n";
            $output .= "**Resources**: " . implode(', ', $plan['resources']) . "\n\n";
            $output .= "**Tasks Completed**: {$plan['tasks_completed']}\n\n";
            $output .= "## Plan Details\n\n";
            $output .= $plan['plan'];
            
            return $output;
        }
        
        // Add other formats (JSON, HTML, etc.)
        return json_encode($plan, JSON_PRETTY_PRINT);
    }
    
    public function getPlanHistory(): array
    {
        return $this->planHistory;
    }
}

// Usage
$apiPlanner = new APIPlanner($client);

$plan = $apiPlanner->planAPI('Task Management System', [
    'users',
    'tasks',
    'projects',
    'comments',
]);

// Export to markdown
$markdown = $apiPlanner->exportPlan($plan, 'markdown');
file_put_contents('api_plan.md', $markdown);

echo "API plan saved to api_plan.md\n";
echo "Tasks completed: {$plan['tasks_completed']}\n";
```

### Application 4: Content Strategy Planner

```php
class ContentStrategyPlanner
{
    private TaskPrioritizationAgent $agent;
    
    public function __construct(ClaudePhp $client)
    {
        $this->agent = new TaskPrioritizationAgent($client, [
            'name' => 'content_strategist',
        ]);
    }
    
    public function planContentStrategy(string $topic, array $platforms): array
    {
        $platformList = implode(', ', $platforms);
        
        $goal = "Create a content strategy for {$topic} across platforms: {$platformList}";
        
        $result = $this->agent->run($goal);
        
        if (!$result->isSuccess()) {
            throw new RuntimeException('Strategy planning failed');
        }
        
        return [
            'strategy' => $result->getAnswer(),
            'topic' => $topic,
            'platforms' => $platforms,
            'tasks' => $result->getMetadata()['tasks_completed'],
        ];
    }
    
    public function createEditorialCalendar(string $topic, int $weeks = 4): string
    {
        $goal = "Create a {$weeks}-week editorial calendar for: {$topic}";
        
        $result = $this->agent->run($goal);
        
        return $result->isSuccess() ? $result->getAnswer() : '';
    }
}

// Usage
$contentPlanner = new ContentStrategyPlanner($client);

$strategy = $contentPlanner->planContentStrategy('PHP Web Development', [
    'Blog',
    'Twitter',
    'YouTube',
    'Newsletter',
]);

echo "Content Strategy:\n";
echo $strategy['strategy'];
echo "\n\nTasks completed: {$strategy['tasks']}\n";

$calendar = $contentPlanner->createEditorialCalendar('PHP Web Development', 8);
echo "\nEditorial Calendar:\n";
echo $calendar;
```

---

## Chapter 7: Advanced Patterns

### Pattern 1: Hierarchical Planning

```php
// High-level planning first
$highLevelAgent = new TaskPrioritizationAgent($client, [
    'name' => 'high_level_planner',
]);

$highLevel = $highLevelAgent->run('Plan major phases of a CRM system');

// Extract phases from result (simplified)
$phases = ['User Management', 'Contact Management', 'Sales Pipeline', 'Reporting'];

// Detail planning for each phase
foreach ($phases as $phase) {
    $detailAgent = new TaskPrioritizationAgent($client, [
        'name' => "detail_planner_{$phase}",
    ]);
    
    $detail = $detailAgent->run("Create detailed plan for: {$phase}");
    
    echo "=== {$phase} ===\n";
    echo $detail->getAnswer() . "\n\n";
}
```

### Pattern 2: Iterative Refinement

```php
$currentPlan = '';
$maxRefinements = 3;

for ($i = 0; $i < $maxRefinements; $i++) {
    $agent = new TaskPrioritizationAgent($client);
    
    if ($i === 0) {
        $goal = 'Create initial architecture plan for microservices system';
    } else {
        $goal = "Refine and add more detail to this plan:\n\n{$currentPlan}";
    }
    
    $result = $agent->run($goal);
    
    if ($result->isSuccess()) {
        $currentPlan = $result->getAnswer();
        echo "Refinement " . ($i + 1) . ": " .
             "{$result->getMetadata()['tasks_completed']} tasks\n";
    }
}

echo "\nFinal Refined Plan:\n";
echo $currentPlan;
```

### Pattern 3: Parallel Planning

```php
$planningTasks = [
    'frontend' => 'Plan frontend architecture with React',
    'backend' => 'Plan backend architecture with PHP',
    'database' => 'Plan database schema and relationships',
    'deployment' => 'Plan deployment and DevOps strategy',
];

$results = [];

foreach ($planningTasks as $key => $task) {
    $agent = new TaskPrioritizationAgent($client, [
        'name' => "{$key}_planner",
    ]);
    
    $results[$key] = $agent->run($task);
}

// Combine results
echo "=== Complete System Plan ===\n\n";

foreach ($results as $key => $result) {
    if ($result->isSuccess()) {
        echo "## " . ucfirst($key) . "\n";
        echo $result->getAnswer() . "\n\n";
    }
}
```

### Pattern 4: Progress Tracking

```php
class TaskProgressTracker
{
    private array $executions = [];
    
    public function track(string $goal, AgentResult $result): void
    {
        $this->executions[] = [
            'goal' => $goal,
            'timestamp' => microtime(true),
            'tasks_completed' => $result->getMetadata()['tasks_completed'],
            'tasks_remaining' => $result->getMetadata()['tasks_remaining'],
            'iterations' => $result->getIterations(),
            'success' => $result->isSuccess(),
        ];
    }
    
    public function getReport(): string
    {
        $report = "Task Prioritization Report\n";
        $report .= str_repeat("=", 50) . "\n\n";
        
        $totalTasks = 0;
        $totalIterations = 0;
        
        foreach ($this->executions as $i => $exec) {
            $report .= sprintf(
                "%d. %s\n   Tasks: %d, Iterations: %d, Status: %s\n\n",
                $i + 1,
                substr($exec['goal'], 0, 50),
                $exec['tasks_completed'],
                $exec['iterations'],
                $exec['success'] ? '✓' : '✗'
            );
            
            $totalTasks += $exec['tasks_completed'];
            $totalIterations += $exec['iterations'];
        }
        
        $report .= sprintf(
            "Summary: %d executions, %d tasks, %d iterations\n",
            count($this->executions),
            $totalTasks,
            $totalIterations
        );
        
        return $report;
    }
}

// Usage
$tracker = new TaskProgressTracker();
$agent = new TaskPrioritizationAgent($client);

$goals = [
    'Plan authentication system',
    'Plan data storage strategy',
    'Plan API endpoints',
];

foreach ($goals as $goal) {
    $result = $agent->run($goal);
    $tracker->track($goal, $result);
}

echo $tracker->getReport();
```

---

## Chapter 8: Best Practices

### 1. Write Clear, Specific Goals

```php
// ✅ Good: Specific and actionable
$agent->run(
    'Design a REST API for a blog system including: ' .
    'post CRUD, user authentication, comments, categories, and tags'
);

// ❌ Avoid: Vague
$agent->run('Make a blog API');
```

### 2. Scope Appropriately

```php
// ✅ Good: Reasonable scope
$agent->run('Plan the user authentication module');

// ❌ Avoid: Too broad
$agent->run('Plan the entire application');
```

### 3. Monitor and Adapt

```php
$result = $agent->run($goal);

if ($result->isSuccess()) {
    $remaining = $result->getMetadata()['tasks_remaining'];
    
    if ($remaining > 5) {
        echo "Warning: Goal may be too complex\n";
        // Consider breaking into smaller goals
    }
}
```

### 4. Use Logging

```php
$logger = new Logger('task_agent');
$logger->pushHandler(new StreamHandler('./logs/agent.log', Logger::INFO));

$agent = new TaskPrioritizationAgent($client, [
    'logger' => $logger,
]);
```

### 5. Handle Errors Gracefully

```php
$result = $agent->run($goal);

if (!$result->isSuccess()) {
    $logger->error('Task prioritization failed', [
        'goal' => $goal,
        'error' => $result->getError(),
    ]);
    
    // Implement recovery strategy
}
```

---

## Conclusion

You've now learned how to:

✅ Create and use TaskPrioritizationAgent  
✅ Understand dynamic task generation  
✅ Work with priority-based execution  
✅ Control iterations and limits  
✅ Build real-world applications  
✅ Implement advanced patterns  
✅ Follow best practices

### Next Steps

1. **Experiment**: Try different types of goals and observe behavior
2. **Build**: Create your own task planning application
3. **Explore**: Check out other agent types (AutonomousAgent, CoordinatorAgent, etc.)
4. **Optimize**: Fine-tune goals for better task generation
5. **Share**: Contribute your patterns and examples

### Resources

- [TaskPrioritizationAgent Documentation](../TaskPrioritizationAgent.md)
- [Agent Selection Guide](../agent-selection-guide.md)
- [Examples](../../examples/)
- [GitHub Repository](https://github.com/your-repo/claude-php-agent)

### Getting Help

- Check the [documentation](../TaskPrioritizationAgent.md)
- Review the [examples](../../examples/)
- Open an issue on GitHub
- Join the community discussions

---

Happy planning with TaskPrioritizationAgent! 🚀

