#!/usr/bin/env php
<?php
/**
 * Scheduler Agent Basic Example
 *
 * Demonstrates basic usage of the SchedulerAgent for managing timed tasks,
 * cron-style scheduling, and task dependencies.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\SchedulerAgent;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Simple console logger
class ConsoleLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        $emoji = match ($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📝',
        };
        echo "[{$timestamp}] {$emoji} [{$level}] {$message}\n";
    }
}

// Load environment
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? throw new RuntimeException('ANTHROPIC_API_KEY not set');
$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                      Scheduler Agent Basic Example                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create scheduler agent with logger
$logger = new ConsoleLogger();
$scheduler = new SchedulerAgent($client, [
    'name' => 'demo_scheduler',
    'logger' => $logger,
]);

echo "🕐 Setting up scheduled tasks...\n\n";

// Example 1: Schedule a task to run immediately
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Immediate Task (One-Time)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$task1 = $scheduler->scheduleAt(
    'welcome_message',
    microtime(true), // Schedule for now
    function () {
        echo "  👋 Welcome! This task executed immediately.\n";
        return 'Welcome task completed';
    }
);

// Make the task due by setting its nextRun to the past
$reflection = new \ReflectionClass($task1);
$property = $reflection->getProperty('nextRun');
$property->setAccessible(true);
$property->setValue($task1, microtime(true) - 1);

echo "✅ Task scheduled: {$task1->getName()} (ID: {$task1->getId()})\n\n";

// Example 2: Schedule recurring tasks with intervals
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Recurring Tasks (Every N Seconds)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$task2 = $scheduler->scheduleEvery(
    'health_check',
    300, // Every 5 minutes
    function () {
        echo "  🏥 Health check: All systems operational\n";
        return 'healthy';
    }
);

echo "✅ Recurring task scheduled: {$task2->getName()} (every 300s)\n\n";

// Example 3: Schedule with cron expression
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Cron-Style Schedule\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$task3 = $scheduler->schedule(
    'hourly_report',
    '0 * * * *', // Every hour at minute 0
    function () {
        echo "  📊 Generating hourly report...\n";
        return 'report_generated';
    }
);

echo "✅ Cron task scheduled: {$task3->getName()} (cron: 0 * * * *)\n\n";

// Example 4: Tasks with dependencies
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Tasks with Dependencies\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$taskA = $scheduler->scheduleAt(
    'prepare_data',
    microtime(true),
    function () {
        echo "  📦 Preparing data...\n";
        sleep(1);
        return 'data_ready';
    }
);

$taskB = $scheduler->scheduleAt(
    'process_data',
    microtime(true),
    function () {
        echo "  ⚙️  Processing data...\n";
        sleep(1);
        return 'data_processed';
    },
    [$taskA->getId()] // Depends on taskA
);

$taskC = $scheduler->scheduleAt(
    'send_notification',
    microtime(true),
    function () {
        echo "  📧 Sending notification...\n";
        return 'notification_sent';
    },
    [$taskB->getId()] // Depends on taskB
);

// Make all tasks due
foreach ([$taskA, $taskB, $taskC] as $task) {
    $reflection = new \ReflectionClass($task);
    $property = $reflection->getProperty('nextRun');
    $property->setAccessible(true);
    $property->setValue($task, microtime(true) - 1);
}

echo "✅ Dependency chain created: prepare → process → notify\n\n";

// Execute pending tasks
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Executing Pending Tasks\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$result = $scheduler->run('Execute all pending tasks');

if ($result->isSuccess()) {
    echo "\n✅ Scheduler executed successfully!\n";
    echo "📊 Result: {$result->getAnswer()}\n\n";

    $metadata = $result->getMetadata();
    echo "Statistics:\n";
    echo "  • Tasks executed: {$metadata['tasks_executed']}\n";
    echo "  • Total tasks: {$metadata['total_tasks']}\n";
} else {
    echo "\n❌ Scheduler execution failed: {$result->getError()}\n";
}

// Show all scheduled tasks
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "All Scheduled Tasks\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$allTasks = $scheduler->getTasks();
foreach ($allTasks as $i => $task) {
    $taskArray = $task->toArray();
    echo ($i + 1) . ". {$taskArray['name']}\n";
    echo "   • Schedule: {$taskArray['schedule']}\n";
    echo "   • Executions: {$taskArray['execution_count']}\n";
    
    if (!empty($taskArray['dependencies'])) {
        echo "   • Dependencies: " . implode(', ', $taskArray['dependencies']) . "\n";
    }
    
    if ($taskArray['next_run']) {
        $nextRun = date('Y-m-d H:i:s', (int)$taskArray['next_run']);
        echo "   • Next run: {$nextRun}\n";
    }
    
    echo "\n";
}

// Show execution history
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Execution History\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$history = $scheduler->getExecutionHistory(10);
echo "📜 Last " . count($history) . " executions:\n\n";

foreach ($history as $i => $entry) {
    $timestamp = date('H:i:s', (int)$entry['timestamp']);
    $status = $entry['success'] ? '✅' : '❌';
    $duration = round($entry['duration'] * 1000, 2);
    
    echo ($i + 1) . ". {$status} {$entry['task_name']} at {$timestamp} ({$duration}ms)\n";
    
    if (!$entry['success']) {
        echo "   Error: {$entry['error']}\n";
    }
}

// Example 5: Unscheduling a task
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Unscheduling a Task\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Tasks before unscheduling: " . count($scheduler->getTasks()) . "\n";

if ($scheduler->unschedule($task2->getId())) {
    echo "✅ Successfully unscheduled: {$task2->getName()}\n";
}

echo "Tasks after unscheduling: " . count($scheduler->getTasks()) . "\n";

echo "\n" . str_repeat("═", 80) . "\n";
echo "✨ Scheduler Agent example completed!\n";
echo str_repeat("═", 80) . "\n";

