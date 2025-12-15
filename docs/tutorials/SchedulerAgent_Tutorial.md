# SchedulerAgent Tutorial: Building a Task Scheduling System

## Introduction

This tutorial will guide you through building a production-ready task scheduling system using the SchedulerAgent. We'll start with basic concepts and progress to advanced patterns used in real-world applications.

By the end of this tutorial, you'll be able to:

- Schedule tasks with cron expressions, intervals, and specific times
- Create task dependency chains
- Monitor task execution and handle failures
- Build complex scheduling workflows
- Implement production-ready scheduling patterns

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and cron expressions

## Table of Contents

1. [Getting Started](#getting-started)
2. [Your First Scheduled Task](#your-first-scheduled-task)
3. [Scheduling Methods](#scheduling-methods)
4. [Task Dependencies](#task-dependencies)
5. [Monitoring and History](#monitoring-and-history)
6. [Building Real-World Workflows](#building-real-world-workflows)
7. [Production Best Practices](#production-best-practices)

## Getting Started

### Installation

First, ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to test the SchedulerAgent:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\SchedulerAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the scheduler agent
$scheduler = new SchedulerAgent($client, [
    'name' => 'tutorial_scheduler',
]);

echo "Scheduler agent ready!\n";
```

## Your First Scheduled Task

Let's create a simple task that executes immediately.

### Step 1: Create a Task Callback

```php
// Define what the task should do
$taskCallback = function () {
    echo "Hello from scheduled task!\n";
    return 'Task completed successfully';
};
```

### Step 2: Schedule the Task

```php
// Schedule task to run immediately
$task = $scheduler->scheduleAt(
    'welcome_task',
    microtime(true), // Current time = execute now
    $taskCallback
);

echo "Task scheduled with ID: {$task->getId()}\n";
```

### Step 3: Execute Pending Tasks

```php
// Run the scheduler to execute pending tasks
$result = $scheduler->run('Execute pending tasks');

if ($result->isSuccess()) {
    echo "Success! {$result->getAnswer()}\n";
    
    $metadata = $result->getMetadata();
    echo "Tasks executed: {$metadata['tasks_executed']}\n";
}
```

**Complete First Example:**

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\SchedulerAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$scheduler = new SchedulerAgent($client);

// Schedule a task
$task = $scheduler->scheduleAt('hello', microtime(true), function () {
    echo "Hello, World!\n";
    return 'done';
});

// Execute it
$result = $scheduler->run('Execute tasks');
echo "Result: {$result->getAnswer()}\n";
```

## Scheduling Methods

The SchedulerAgent provides four main scheduling methods. Let's explore each one.

### Method 1: Cron-Style Scheduling

Perfect for tasks that need to run at specific times.

```php
// Every hour at minute 0
$scheduler->schedule('hourly_report', '0 * * * *', function () {
    echo "Generating hourly report...\n";
    return generateReport();
});

// Daily at 2 AM
$scheduler->schedule('daily_backup', '0 2 * * *', function () {
    echo "Running daily backup...\n";
    return backupDatabase();
});

// Every Monday at 9 AM
$scheduler->schedule('weekly_summary', '0 9 * * 1', function () {
    echo "Sending weekly summary...\n";
    return sendWeeklySummary();
});
```

**Cron Format Reference:**

```
┌───────────── minute (0 - 59)
│ ┌───────────── hour (0 - 23)
│ │ ┌───────────── day of month (1 - 31)
│ │ │ ┌───────────── month (1 - 12)
│ │ │ │ ┌───────────── day of week (0 - 6) (Sunday to Saturday)
│ │ │ │ │
* * * * *
```

**Common Patterns:**

- `* * * * *` - Every minute
- `0 * * * *` - Every hour
- `0 0 * * *` - Daily at midnight
- `0 0 * * 0` - Weekly on Sunday
- `0 0 1 * *` - Monthly on the 1st
- `*/5 * * * *` - Every 5 minutes

### Method 2: Interval-Based Scheduling

Perfect for regular checks and maintenance tasks.

```php
// Every 60 seconds
$scheduler->scheduleEvery('health_check', 60, function () {
    echo "Checking system health...\n";
    return checkHealth();
});

// Every 5 minutes (300 seconds)
$scheduler->scheduleEvery('cleanup_temp', 300, function () {
    echo "Cleaning temporary files...\n";
    return cleanupTempFiles();
});

// Every 30 seconds
$scheduler->scheduleEvery('process_queue', 30, function () {
    echo "Processing queue...\n";
    return processNextQueueItem();
});
```

### Method 3: One-Time at Specific Time

Perfect for scheduled events at specific timestamps.

```php
// Schedule for 1 hour from now
$futureTime = time() + 3600;
$scheduler->scheduleAt('reminder', $futureTime, function () {
    echo "Reminder: Meeting in 5 minutes!\n";
    return 'reminder_sent';
});

// Schedule for specific date/time
$meetingTime = strtotime('2024-12-20 14:00:00');
$scheduler->scheduleAt('meeting_prep', $meetingTime, function () {
    echo "Preparing meeting room...\n";
    return 'room_prepared';
});
```

### Method 4: One-Time with Relative Time

Perfect for human-readable delays.

```php
// 30 minutes from now
$scheduler->scheduleOnce('follow_up', '+30 minutes', function () {
    echo "Sending follow-up email...\n";
    return 'email_sent';
});

// Tomorrow at noon
$scheduler->scheduleOnce('lunch_reminder', 'tomorrow noon', function () {
    echo "Time for lunch!\n";
    return 'reminder_sent';
});

// Next week
$scheduler->scheduleOnce('weekly_task', '+1 week', function () {
    echo "Weekly task executing...\n";
    return 'completed';
});
```

## Task Dependencies

One of the most powerful features is the ability to create task dependencies.

### Simple Dependency Chain

```php
// Step 1: Prepare data
$prepare = $scheduler->scheduleAt('prepare', time(), function () {
    echo "1. Preparing data...\n";
    sleep(1);
    return 'data_prepared';
});

// Step 2: Process data (depends on prepare)
$process = $scheduler->scheduleAt('process', time(), function () {
    echo "2. Processing data...\n";
    sleep(1);
    return 'data_processed';
}, [$prepare->getId()]);

// Step 3: Save results (depends on process)
$save = $scheduler->scheduleAt('save', time(), function () {
    echo "3. Saving results...\n";
    sleep(1);
    return 'results_saved';
}, [$process->getId()]);

// Execute: prepare → process → save
$scheduler->run('Execute chain');
```

### Multiple Dependencies

A task can wait for multiple tasks to complete:

```php
// Parallel tasks
$fetchUsers = $scheduler->scheduleAt('fetch_users', time(), function () {
    echo "Fetching users...\n";
    return getUserData();
});

$fetchOrders = $scheduler->scheduleAt('fetch_orders', time(), function () {
    echo "Fetching orders...\n";
    return getOrderData();
});

$fetchProducts = $scheduler->scheduleAt('fetch_products', time(), function () {
    echo "Fetching products...\n";
    return getProductData();
});

// This runs only after all three complete
$generateReport = $scheduler->scheduleAt('generate_report', time(), function () {
    echo "Generating comprehensive report...\n";
    return generateFullReport();
}, [
    $fetchUsers->getId(),
    $fetchOrders->getId(),
    $fetchProducts->getId()
]);

$scheduler->run('Execute parallel workflow');
```

### Complex Workflow Example

```php
// ETL Pipeline
$extract = $scheduler->scheduleAt('extract', time(), function () {
    echo "Extracting data from sources...\n";
    return extractData();
});

$validate = $scheduler->scheduleAt('validate', time(), function () {
    echo "Validating extracted data...\n";
    return validateData();
}, [$extract->getId()]);

$transform = $scheduler->scheduleAt('transform', time(), function () {
    echo "Transforming data...\n";
    return transformData();
}, [$validate->getId()]);

$load = $scheduler->scheduleAt('load', time(), function () {
    echo "Loading to warehouse...\n";
    return loadData();
}, [$transform->getId()]);

$index = $scheduler->scheduleAt('index', time(), function () {
    echo "Updating search index...\n";
    return updateIndex();
}, [$load->getId()]);

$notify = $scheduler->scheduleAt('notify', time(), function () {
    echo "Notifying stakeholders...\n";
    return sendNotifications();
}, [$index->getId()]);

// Execution order: extract → validate → transform → load → index → notify
$scheduler->run('Execute ETL pipeline');
```

## Monitoring and History

Track task execution and handle failures.

### Checking Task Status

```php
// Get all tasks
$allTasks = $scheduler->getTasks();

foreach ($allTasks as $task) {
    echo "Task: {$task->getName()}\n";
    echo "  Executions: {$task->getExecutionCount()}\n";
    echo "  Recurring: " . ($task->isRecurring() ? 'Yes' : 'No') . "\n";
    
    if ($nextRun = $task->getNextRun()) {
        $date = date('Y-m-d H:i:s', (int)$nextRun);
        echo "  Next run: {$date}\n";
    }
    
    if ($lastRun = $task->getLastRun()) {
        $date = date('Y-m-d H:i:s', (int)$lastRun);
        echo "  Last run: {$date}\n";
    }
    
    echo "\n";
}
```

### Viewing Execution History

```php
// Get recent execution history
$history = $scheduler->getExecutionHistory(20);

echo "Recent Executions:\n";
echo str_repeat("-", 80) . "\n";

foreach ($history as $entry) {
    $time = date('H:i:s', (int)$entry['timestamp']);
    $status = $entry['success'] ? '✅' : '❌';
    $duration = round($entry['duration'] * 1000, 2);
    
    echo "{$status} {$entry['task_name']} at {$time} ({$duration}ms)\n";
    
    if (!$entry['success']) {
        echo "   Error: {$entry['error']}\n";
    }
}
```

### Handling Task Failures

```php
// Create a task that might fail
$scheduler->scheduleEvery('api_check', 60, function () {
    echo "Checking API...\n";
    
    try {
        $response = file_get_contents('https://api.example.com/health');
        return 'api_healthy';
    } catch (\Exception $e) {
        // Log the error
        error_log("API check failed: {$e->getMessage()}");
        
        // Re-throw to record in history
        throw $e;
    }
});

// Run and check for failures
$result = $scheduler->run('Check API');

// Review failures
$history = $scheduler->getExecutionHistory(10);
$failures = array_filter($history, fn($e) => !$e['success']);

if (!empty($failures)) {
    echo "⚠️ Recent failures detected:\n";
    foreach ($failures as $failure) {
        echo "  - {$failure['task_name']}: {$failure['error']}\n";
    }
}
```

## Building Real-World Workflows

Let's build some practical, real-world scheduling workflows.

### Workflow 1: Automated Backup System

```php
class BackupScheduler
{
    private SchedulerAgent $scheduler;
    
    public function __construct(SchedulerAgent $scheduler)
    {
        $this->scheduler = $scheduler;
    }
    
    public function setup(): void
    {
        // Daily database backup at 2 AM
        $this->scheduler->schedule('db_backup', '0 2 * * *', function () {
            echo "Backing up database...\n";
            
            $filename = 'backup_' . date('Y-m-d') . '.sql';
            exec("mysqldump -u user -p database > {$filename}");
            
            echo "Database backed up to {$filename}\n";
            return $filename;
        });
        
        // Cleanup old backups weekly (Sunday at 3 AM)
        $this->scheduler->schedule('cleanup_backups', '0 3 * * 0', function () {
            echo "Cleaning up old backups...\n";
            
            $files = glob('backup_*.sql');
            $deleted = 0;
            
            foreach ($files as $file) {
                if (filemtime($file) < strtotime('-30 days')) {
                    unlink($file);
                    $deleted++;
                }
            }
            
            echo "Deleted {$deleted} old backup files\n";
            return $deleted;
        });
        
        // Upload to cloud storage daily at 4 AM
        $this->scheduler->schedule('upload_backups', '0 4 * * *', function () {
            echo "Uploading backups to cloud...\n";
            
            // Upload logic here
            
            echo "Backups uploaded successfully\n";
            return 'uploaded';
        });
    }
}

// Usage
$backupScheduler = new BackupScheduler($scheduler);
$backupScheduler->setup();
```

### Workflow 2: Data Processing Pipeline

```php
class DataPipeline
{
    private SchedulerAgent $scheduler;
    private array $data = [];
    
    public function __construct(SchedulerAgent $scheduler)
    {
        $this->scheduler = $scheduler;
    }
    
    public function setupPipeline(): void
    {
        // Stage 1: Extract
        $extract = $this->scheduler->scheduleEvery('extract', 300, function () {
            echo "Extracting data from sources...\n";
            
            $this->data = [
                // Simulate data extraction
                ['id' => 1, 'value' => 100],
                ['id' => 2, 'value' => 200],
            ];
            
            echo "Extracted " . count($this->data) . " records\n";
            return count($this->data);
        });
        
        // Stage 2: Transform
        $transform = $this->scheduler->scheduleEvery('transform', 300, function () {
            echo "Transforming data...\n";
            
            foreach ($this->data as &$item) {
                $item['processed'] = true;
                $item['timestamp'] = time();
            }
            
            echo "Transformed " . count($this->data) . " records\n";
            return count($this->data);
        }, [$extract->getId()]);
        
        // Stage 3: Load
        $load = $this->scheduler->scheduleEvery('load', 300, function () {
            echo "Loading data to warehouse...\n";
            
            // Simulate loading
            sleep(1);
            
            echo "Loaded " . count($this->data) . " records\n";
            return count($this->data);
        }, [$transform->getId()]);
        
        // Stage 4: Notify
        $this->scheduler->scheduleEvery('notify', 300, function () {
            echo "Sending completion notification...\n";
            return 'notification_sent';
        }, [$load->getId()]);
    }
}

// Usage
$pipeline = new DataPipeline($scheduler);
$pipeline->setupPipeline();
```

### Workflow 3: Monitoring System

```php
class SystemMonitor
{
    private SchedulerAgent $scheduler;
    private array $metrics = [];
    
    public function __construct(SchedulerAgent $scheduler)
    {
        $this->scheduler = $scheduler;
    }
    
    public function setup(): void
    {
        // Check CPU every minute
        $this->scheduler->scheduleEvery('monitor_cpu', 60, function () {
            $load = sys_getloadavg();
            $cpuUsage = $load[0];
            
            $this->metrics['cpu'] = $cpuUsage;
            
            if ($cpuUsage > 5.0) {
                echo "⚠️ HIGH CPU LOAD: {$cpuUsage}\n";
                $this->sendAlert('High CPU load detected');
            }
            
            return $cpuUsage;
        });
        
        // Check memory every minute
        $this->scheduler->scheduleEvery('monitor_memory', 60, function () {
            $free = shell_exec('free -m | grep Mem | awk \'{print $4}\'');
            $freeMemory = (int)trim($free);
            
            $this->metrics['memory'] = $freeMemory;
            
            if ($freeMemory < 500) {
                echo "⚠️ LOW MEMORY: {$freeMemory}MB\n";
                $this->sendAlert('Low memory warning');
            }
            
            return $freeMemory;
        });
        
        // Check disk space every 5 minutes
        $this->scheduler->scheduleEvery('monitor_disk', 300, function () {
            $free = disk_free_space('/');
            $total = disk_total_space('/');
            $percentFree = ($free / $total) * 100;
            
            $this->metrics['disk'] = $percentFree;
            
            if ($percentFree < 10) {
                echo "⚠️ LOW DISK SPACE: {$percentFree}%\n";
                $this->sendAlert('Low disk space warning');
            }
            
            return $percentFree;
        });
        
        // Generate hourly report
        $this->scheduler->schedule('generate_report', '0 * * * *', function () {
            echo "Generating system health report...\n";
            
            $report = [
                'timestamp' => date('Y-m-d H:i:s'),
                'metrics' => $this->metrics,
            ];
            
            file_put_contents(
                'reports/health_' . date('Y-m-d_H') . '.json',
                json_encode($report, JSON_PRETTY_PRINT)
            );
            
            echo "Report generated\n";
            return 'report_complete';
        });
    }
    
    private function sendAlert(string $message): void
    {
        // Send alert via email, Slack, etc.
        error_log("ALERT: {$message}");
    }
}

// Usage
$monitor = new SystemMonitor($scheduler);
$monitor->setup();
```

## Production Best Practices

### 1. Error Handling

Always wrap task logic in try-catch blocks:

```php
$scheduler->scheduleEvery('critical_task', 60, function () {
    try {
        // Task logic here
        $result = performCriticalOperation();
        return $result;
    } catch (\Exception $e) {
        // Log the error
        error_log("Critical task failed: {$e->getMessage()}");
        
        // Send alert
        notifyAdministrators($e);
        
        // Re-throw to record in history
        throw $e;
    }
});
```

### 2. Task Timeouts

Ensure tasks don't run indefinitely:

```php
$scheduler->scheduleEvery('long_task', 300, function () {
    $timeout = 60; // 60 seconds
    $start = time();
    
    while (hasMoreWork()) {
        if (time() - $start > $timeout) {
            throw new RuntimeException('Task timeout exceeded');
        }
        
        processNextItem();
    }
    
    return 'completed';
});
```

### 3. Logging

Use PSR-3 loggers for better observability:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('scheduler');
$logger->pushHandler(new StreamHandler('logs/scheduler.log', Logger::INFO));

$scheduler = new SchedulerAgent($client, [
    'name' => 'production_scheduler',
    'logger' => $logger,
]);
```

### 4. Monitoring Execution Health

```php
// Check for failing tasks
$history = $scheduler->getExecutionHistory(100);
$recentFailures = array_filter($history, function ($entry) {
    return !$entry['success'] && 
           $entry['timestamp'] > time() - 3600; // Last hour
});

if (count($recentFailures) > 10) {
    // Alert administrators
    sendCriticalAlert("High failure rate detected in scheduler");
}
```

### 5. Graceful Shutdown

```php
// Register shutdown handler
$running = true;

pcntl_signal(SIGTERM, function () use (&$running, $scheduler) {
    echo "Shutdown signal received\n";
    $running = false;
    $scheduler->stop();
});

// Run scheduler loop
while ($running) {
    $scheduler->run('Execute pending tasks');
    sleep(1);
    pcntl_signal_dispatch();
}

echo "Scheduler stopped gracefully\n";
```

### 6. Task Idempotency

Ensure tasks can be safely retried:

```php
$scheduler->scheduleEvery('process_payments', 60, function () {
    // Use idempotency key
    $idempotencyKey = 'payment_batch_' . date('Y-m-d_H-i');
    
    if (isAlreadyProcessed($idempotencyKey)) {
        echo "Batch already processed\n";
        return 'skipped';
    }
    
    processPayments();
    markAsProcessed($idempotencyKey);
    
    return 'processed';
});
```

## Conclusion

You now have a comprehensive understanding of the SchedulerAgent! You've learned:

- ✅ How to schedule tasks using different methods
- ✅ Creating complex task dependencies
- ✅ Monitoring task execution
- ✅ Building real-world workflows
- ✅ Production best practices

### Next Steps

1. Review the [SchedulerAgent Documentation](../SchedulerAgent.md) for complete API reference
2. Check out the [examples directory](../../examples/) for more patterns
3. Explore integration with other agents like AlertAgent and MonitoringAgent

### Additional Resources

- [Cron Expression Guide](https://crontab.guru/)
- [PHP DateTime Documentation](https://www.php.net/manual/en/class.datetime.php)
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)

Happy scheduling! 🚀

