# SchedulerAgent Documentation

## Overview

The `SchedulerAgent` is a powerful task scheduling system that manages timed tasks with support for cron-style scheduling, one-time tasks, recurring intervals, and task dependencies. It enables you to schedule and execute tasks automatically based on time or dependencies.

## Features

- ⏰ **Cron-Style Scheduling**: Support for traditional cron expressions
- 🔄 **Recurring Tasks**: Schedule tasks to run at regular intervals
- ⏱️ **One-Time Tasks**: Execute tasks at specific times or relative times
- 🔗 **Task Dependencies**: Define execution order with task dependencies
- 📊 **Execution History**: Track task execution with success/failure status
- 🔍 **Task Management**: Add, remove, and inspect scheduled tasks
- 📝 **Logging Support**: Built-in PSR-3 logger integration
- 🎯 **Precision Timing**: Microsecond-level timing accuracy

## Installation

The SchedulerAgent is included in the `claude-php-agent` package. Ensure you have the package installed:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\SchedulerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$scheduler = new SchedulerAgent($client);

// Schedule a recurring task
$scheduler->scheduleEvery('health_check', 60, function () {
    echo "Health check running...\n";
    return 'healthy';
});

// Schedule a one-time task
$scheduler->scheduleAt('report', time() + 3600, function () {
    echo "Generating report...\n";
    return 'report_complete';
});

// Execute pending tasks
$result = $scheduler->run('Execute pending tasks');
```

## Configuration

The SchedulerAgent accepts configuration options in its constructor:

```php
$scheduler = new SchedulerAgent($client, [
    'name' => 'my_scheduler',           // Agent name
    'timezone' => 'America/New_York',    // Default timezone
    'logger' => $logger,                 // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'scheduler_agent'` | Unique name for the scheduler |
| `timezone` | string | `'UTC'` | Default timezone for scheduling |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Scheduling Methods

### Cron-Style Scheduling

Schedule tasks using cron expressions:

```php
// Run every hour at minute 0
$task = $scheduler->schedule('hourly_report', '0 * * * *', function () {
    return 'report_generated';
});

// Run daily at midnight
$task = $scheduler->schedule('daily_backup', '0 0 * * *', function () {
    return 'backup_complete';
});

// Run every 5 minutes
$task = $scheduler->schedule('frequent_check', '*/5 * * * *', function () {
    return 'check_complete';
});
```

**Cron Expression Format**: `minute hour day month weekday`

Common patterns:
- `* * * * *` - Every minute
- `0 * * * *` - Every hour
- `0 0 * * *` - Daily at midnight
- `0 0 * * 0` - Weekly on Sunday
- `0 0 1 * *` - Monthly on the 1st

### Interval-Based Scheduling

Schedule tasks to run at regular intervals:

```php
// Run every 60 seconds
$task = $scheduler->scheduleEvery('monitor', 60, function () {
    return checkSystemStatus();
});

// Run every 5 minutes (300 seconds)
$task = $scheduler->scheduleEvery('cleanup', 300, function () {
    return cleanupTempFiles();
});
```

### One-Time Tasks

Schedule tasks to run once at a specific time:

```php
// Schedule at specific timestamp
$futureTime = time() + 3600; // 1 hour from now
$task = $scheduler->scheduleAt('delayed_task', $futureTime, function () {
    return 'task_executed';
});

// Schedule with relative time
$task = $scheduler->scheduleOnce('reminder', '+30 minutes', function () {
    return 'reminder_sent';
});

// Common relative time strings
$scheduler->scheduleOnce('task1', '+1 hour', $callback);
$scheduler->scheduleOnce('task2', '+1 day', $callback);
$scheduler->scheduleOnce('task3', 'tomorrow noon', $callback);
```

## Task Dependencies

Schedule tasks that depend on other tasks completing first:

```php
// Create independent tasks
$task1 = $scheduler->scheduleAt('prepare', time(), function () {
    echo "Preparing data...\n";
    return 'prepared';
});

// Task that depends on task1
$task2 = $scheduler->scheduleAt('process', time(), function () {
    echo "Processing data...\n";
    return 'processed';
}, [$task1->getId()]);

// Task that depends on task2
$task3 = $scheduler->scheduleAt('notify', time(), function () {
    echo "Sending notification...\n";
    return 'sent';
}, [$task2->getId()]);

// Execution order will be: task1 → task2 → task3
$scheduler->run('Execute dependency chain');
```

### Complex Dependencies

Tasks can have multiple dependencies:

```php
$backup1 = $scheduler->scheduleAt('backup_db', time(), $dbBackupFn);
$backup2 = $scheduler->scheduleAt('backup_files', time(), $fileBackupFn);

// This task waits for both backups to complete
$compress = $scheduler->scheduleAt('compress', time(), $compressFn, [
    $backup1->getId(),
    $backup2->getId()
]);
```

## Task Management

### Retrieving Tasks

```php
// Get all scheduled tasks
$allTasks = $scheduler->getTasks();

// Get specific task by ID
$task = $scheduler->getTask($taskId);

// Check task information
$taskArray = $task->toArray();
print_r($taskArray);
// [
//     'id' => 'task_12345',
//     'name' => 'health_check',
//     'schedule' => 'every:60s',
//     'dependencies' => [],
//     'last_run' => 1234567890.123,
//     'next_run' => 1234567950.123,
//     'execution_count' => 5,
//     'metadata' => []
// ]
```

### Unscheduling Tasks

```php
// Remove a task by ID
$taskId = $task->getId();
$scheduler->unschedule($taskId);

// Verify removal
$task = $scheduler->getTask($taskId); // Returns null
```

## Execution History

Track task execution with detailed history:

```php
// Get recent execution history
$history = $scheduler->getExecutionHistory(10); // Last 10 executions

foreach ($history as $entry) {
    echo "{$entry['task_name']}: ";
    echo $entry['success'] ? 'Success' : 'Failed';
    echo " ({$entry['duration']}s)\n";
    
    if (!$entry['success']) {
        echo "Error: {$entry['error']}\n";
    }
}
```

History entry structure:
```php
[
    'task_id' => 'task_12345',
    'task_name' => 'health_check',
    'timestamp' => 1234567890.123,
    'success' => true,
    'error' => null,
    'duration' => 0.045
]
```

## Running the Scheduler

### Manual Execution

Execute pending tasks on demand:

```php
$result = $scheduler->run('Check and execute pending tasks');

if ($result->isSuccess()) {
    $metadata = $result->getMetadata();
    echo "Executed {$metadata['tasks_executed']} tasks\n";
}
```

### Continuous Loop

Run the scheduler continuously (for daemon processes):

```php
// Start the scheduler loop (blocking)
$scheduler->start(1); // Check every 1 second

// In another part of your code (signal handler, etc.)
$scheduler->stop(); // Stop the scheduler loop
```

**Note**: The `start()` method is blocking. Use it in dedicated scheduler processes or with proper process management.

## Error Handling

Tasks that throw exceptions are caught and recorded:

```php
$scheduler->scheduleAt('risky_task', time(), function () {
    if (rand(1, 10) > 7) {
        throw new RuntimeException('Task failed');
    }
    return 'success';
});

$scheduler->run('Execute risky task');

// Check execution history for failures
$history = $scheduler->getExecutionHistory(1);
if (!$history[0]['success']) {
    echo "Task failed: {$history[0]['error']}\n";
}
```

The scheduler continues executing other tasks even if one fails.

## Advanced Patterns

### ETL Pipeline

```php
$extract = $scheduler->scheduleAt('extract', time(), function () {
    return extractData();
});

$transform = $scheduler->scheduleAt('transform', time(), function () {
    return transformData();
}, [$extract->getId()]);

$load = $scheduler->scheduleAt('load', time(), function () {
    return loadData();
}, [$transform->getId()]);

$scheduler->run('Execute ETL pipeline');
```

### Parallel Execution with Join

```php
// Parallel tasks
$task1 = $scheduler->scheduleAt('parallel_1', time(), $fn1);
$task2 = $scheduler->scheduleAt('parallel_2', time(), $fn2);
$task3 = $scheduler->scheduleAt('parallel_3', time(), $fn3);

// Join task waits for all parallel tasks
$join = $scheduler->scheduleAt('join', time(), $joinFn, [
    $task1->getId(),
    $task2->getId(),
    $task3->getId()
]);
```

### Monitoring System

```php
// System checks every minute
$scheduler->scheduleEvery('check_cpu', 60, function () {
    return checkCPU();
});

$scheduler->scheduleEvery('check_memory', 60, function () {
    return checkMemory();
});

$scheduler->scheduleEvery('check_disk', 60, function () {
    return checkDisk();
});

// Daily report at 9 AM
$scheduler->schedule('daily_report', '0 9 * * *', function () {
    return generateReport();
});
```

## Task Object

The `Task` object provides useful information:

```php
$task = $scheduler->schedule('my_task', '0 * * * *', $callback);

// Task properties
$task->getId();              // Unique task ID
$task->getName();            // Task name
$task->getSchedule();        // Schedule object
$task->getDependencies();    // Array of dependency IDs
$task->getLastRun();         // Timestamp of last execution
$task->getNextRun();         // Timestamp of next execution
$task->getExecutionCount();  // Number of times executed
$task->isRecurring();        // Whether task repeats
$task->isDue();              // Whether task should run now
$task->toArray();            // Complete task information
```

## Best Practices

### 1. Use Appropriate Scheduling Methods

- Use `schedule()` for predictable times (cron expressions)
- Use `scheduleEvery()` for regular intervals
- Use `scheduleAt()` for one-time future tasks
- Use `scheduleOnce()` for relative time tasks

### 2. Handle Task Failures Gracefully

```php
$scheduler->scheduleEvery('important_task', 60, function () {
    try {
        return performCriticalOperation();
    } catch (\Exception $e) {
        // Log error, send alert, etc.
        throw $e; // Re-throw to record in history
    }
});
```

### 3. Keep Tasks Lightweight

Tasks should be quick to execute. For long-running operations:

```php
// Instead of this (blocking)
$scheduler->scheduleEvery('process', 60, function () {
    processMillionsOfRecords(); // Takes 10 minutes
});

// Do this (queue-based)
$scheduler->scheduleEvery('enqueue', 60, function () {
    queueProcessingJob(); // Returns immediately
});
```

### 4. Monitor Execution History

Regularly check execution history for failures:

```php
$history = $scheduler->getExecutionHistory(100);
$failures = array_filter($history, fn($e) => !$e['success']);

if (count($failures) > 10) {
    // Alert administrators
}
```

### 5. Use Dependencies for Order

When task order matters, use dependencies instead of timing:

```php
// Bad: Relies on timing
$scheduler->scheduleAt('task1', time(), $fn1);
$scheduler->scheduleAt('task2', time() + 1, $fn2); // Fragile

// Good: Uses dependencies
$task1 = $scheduler->scheduleAt('task1', time(), $fn1);
$scheduler->scheduleAt('task2', time(), $fn2, [$task1->getId()]);
```

## Integration with Other Agents

The SchedulerAgent works well with other agents:

```php
use ClaudeAgents\Agents\SchedulerAgent;
use ClaudeAgents\Agents\AlertAgent;

$scheduler = new SchedulerAgent($client);
$alertAgent = new AlertAgent($client);

// Schedule periodic alert checks
$scheduler->scheduleEvery('check_alerts', 60, function () use ($alertAgent) {
    // Check for alert conditions and process
    if ($systemIssueDetected) {
        $alert = new Alert('System Issue', 'Problem detected');
        $alertAgent->processAlert($alert);
    }
});
```

## Performance Considerations

- The scheduler checks tasks at the interval specified in `start()` or when `run()` is called
- Task execution is synchronous - tasks run one at a time in dependency order
- Keep execution history reasonable (default max: 1000 entries)
- For high-frequency tasks (< 1 second), consider using dedicated queue systems

## Limitations

1. **Cron Parsing**: The built-in cron parser supports common patterns. For complex cron expressions, consider integrating a dedicated cron library.

2. **Persistent Storage**: Tasks are stored in memory. For persistent scheduling across restarts, implement custom storage.

3. **Distributed Scheduling**: The scheduler is single-process. For distributed systems, use dedicated job schedulers like Kubernetes CronJobs or Laravel Scheduler.

## Troubleshooting

### Tasks Not Executing

1. Check if tasks are actually due:
```php
$task = $scheduler->getTask($taskId);
echo $task->isDue() ? 'Due' : 'Not due yet';
```

2. Verify dependencies are satisfied
3. Check execution history for errors

### High Memory Usage

If execution history grows too large, limit it:

```php
// Get only recent history
$history = $scheduler->getExecutionHistory(50);
```

### Tasks Executing Out of Order

Ensure dependencies are properly set:

```php
$task1 = $scheduler->scheduleAt('first', time(), $fn1);
$task2 = $scheduler->scheduleAt('second', time(), $fn2, [$task1->getId()]);
```

## Examples

See complete examples in the `examples/` directory:

- `scheduler_agent.php` - Basic scheduling patterns
- `advanced_scheduler_agent.php` - Complex scenarios with dependencies

## API Reference

### SchedulerAgent Methods

- `schedule(string $name, string $cronExpression, callable $callback, array $dependencies = []): Task`
- `scheduleAt(string $name, float $timestamp, callable $callback, array $dependencies = []): Task`
- `scheduleOnce(string $name, string $relativeTime, callable $callback, array $dependencies = []): Task`
- `scheduleEvery(string $name, int $seconds, callable $callback, array $dependencies = []): Task`
- `unschedule(string $taskId): bool`
- `getTasks(): array`
- `getTask(string $taskId): ?Task`
- `getExecutionHistory(int $limit = 100): array`
- `run(string $task): AgentResult`
- `start(int $checkInterval = 1): void`
- `stop(): void`
- `getName(): string`

## See Also

- [Tutorial: SchedulerAgent](tutorials/SchedulerAgent_Tutorial.md)
- [AlertAgent Documentation](AlertAgent.md)
- [MonitoringAgent Documentation](MonitoringAgent.md)

