#!/usr/bin/env php
<?php
/**
 * Advanced Scheduler Agent Example
 *
 * Demonstrates advanced scheduling patterns including:
 * - Complex dependency chains
 * - Error handling and retry logic
 * - Task monitoring and statistics
 * - Real-world scheduling scenarios
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\SchedulerAgent;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Advanced console logger with colors
class ColorLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $color = match ($level) {
            'error' => "\033[31m",    // Red
            'warning' => "\033[33m",   // Yellow
            'info' => "\033[36m",      // Cyan
            'debug' => "\033[90m",     // Gray
            default => "\033[0m",      // Default
        };
        $reset = "\033[0m";
        
        echo "{$color}[{$timestamp}] [{$level}] {$message}{$reset}\n";
    }
}

// Helper function to make tasks due immediately
function makeTaskDue($task): void {
    $reflection = new \ReflectionClass($task);
    $property = $reflection->getProperty('nextRun');
    $property->setAccessible(true);
    $property->setValue($task, microtime(true) - 1);
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

echo "\033[1;36m";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                   Advanced Scheduler Agent Example                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\033[0m\n";

$logger = new ColorLogger();
$scheduler = new SchedulerAgent($client, [
    'name' => 'advanced_scheduler',
    'logger' => $logger,
]);

// ============================================================================
// Scenario 1: ETL Pipeline with Dependencies
// ============================================================================
echo "\033[1;33m";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Scenario 1: ETL Pipeline\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\033[0m\n";

$extractedData = [];

$extract = $scheduler->scheduleAt(
    'extract_data',
    microtime(true),
    function () use (&$extractedData) {
        echo "  🔍 Extracting data from sources...\n";
        sleep(1);
        
        // Simulate data extraction
        $extractedData = [
            ['id' => 1, 'name' => 'Product A', 'price' => 100],
            ['id' => 2, 'name' => 'Product B', 'price' => 200],
            ['id' => 3, 'name' => 'Product C', 'price' => 150],
        ];
        
        echo "  ✅ Extracted " . count($extractedData) . " records\n";
        return $extractedData;
    }
);

$transform = $scheduler->scheduleAt(
    'transform_data',
    microtime(true),
    function () use (&$extractedData) {
        echo "  ⚙️  Transforming data...\n";
        sleep(1);
        
        // Simulate data transformation
        foreach ($extractedData as &$item) {
            $item['price_with_tax'] = $item['price'] * 1.1;
            $item['category'] = 'Electronics';
        }
        
        echo "  ✅ Transformed " . count($extractedData) . " records\n";
        return $extractedData;
    },
    [$extract->getId()]
);

$load = $scheduler->scheduleAt(
    'load_data',
    microtime(true),
    function () use (&$extractedData) {
        echo "  💾 Loading data to warehouse...\n";
        sleep(1);
        
        echo "  ✅ Loaded " . count($extractedData) . " records to warehouse\n";
        return 'load_complete';
    },
    [$transform->getId()]
);

$notify = $scheduler->scheduleAt(
    'send_completion_notification',
    microtime(true),
    function () {
        echo "  📧 Sending completion notification to team\n";
        return 'notification_sent';
    },
    [$load->getId()]
);

// Make all ETL tasks due
foreach ([$extract, $transform, $load, $notify] as $task) {
    makeTaskDue($task);
}

echo "Pipeline configured: Extract → Transform → Load → Notify\n\n";

// ============================================================================
// Scenario 2: Backup Strategy with Multiple Paths
// ============================================================================
echo "\033[1;33m";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Scenario 2: Multi-Stage Backup Strategy\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\033[0m\n";

$dbBackup = $scheduler->scheduleAt(
    'database_backup',
    microtime(true),
    function () {
        echo "  🗄️  Backing up database...\n";
        sleep(1);
        echo "  ✅ Database backup complete (size: 2.4 GB)\n";
        return 'db_backup_complete';
    }
);

$fileBackup = $scheduler->scheduleAt(
    'file_backup',
    microtime(true),
    function () {
        echo "  📁 Backing up files...\n";
        sleep(1);
        echo "  ✅ File backup complete (size: 1.2 GB)\n";
        return 'file_backup_complete';
    }
);

$compression = $scheduler->scheduleAt(
    'compress_backups',
    microtime(true),
    function () {
        echo "  🗜️  Compressing backups...\n";
        sleep(1);
        echo "  ✅ Compression complete (saved 40% space)\n";
        return 'compression_complete';
    },
    [$dbBackup->getId(), $fileBackup->getId()] // Depends on both backups
);

$upload = $scheduler->scheduleAt(
    'upload_to_cloud',
    microtime(true),
    function () {
        echo "  ☁️  Uploading to cloud storage...\n";
        sleep(2);
        echo "  ✅ Upload complete to S3\n";
        return 'upload_complete';
    },
    [$compression->getId()]
);

// Make all backup tasks due
foreach ([$dbBackup, $fileBackup, $compression, $upload] as $task) {
    makeTaskDue($task);
}

echo "Backup strategy configured with parallel backup operations\n\n";

// ============================================================================
// Scenario 3: Monitoring and Alerting
// ============================================================================
echo "\033[1;33m";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Scenario 3: System Monitoring Tasks\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\033[0m\n";

$checkCPU = $scheduler->scheduleEvery(
    'check_cpu_usage',
    60, // Check every minute
    function () {
        $usage = rand(20, 95); // Simulate CPU usage
        echo "  💻 CPU Usage: {$usage}%\n";
        
        if ($usage > 90) {
            echo "  ⚠️  HIGH CPU ALERT!\n";
        }
        
        return ['cpu_usage' => $usage];
    }
);

$checkMemory = $scheduler->scheduleEvery(
    'check_memory',
    60,
    function () {
        $usage = rand(40, 85);
        echo "  🧠 Memory Usage: {$usage}%\n";
        
        if ($usage > 80) {
            echo "  ⚠️  HIGH MEMORY ALERT!\n";
        }
        
        return ['memory_usage' => $usage];
    }
);

$checkDisk = $scheduler->scheduleEvery(
    'check_disk_space',
    300, // Every 5 minutes
    function () {
        $free = rand(10, 60);
        echo "  💾 Disk Space Free: {$free}%\n";
        
        if ($free < 15) {
            echo "  ⚠️  LOW DISK SPACE ALERT!\n";
        }
        
        return ['disk_free' => $free];
    }
);

echo "Monitoring tasks scheduled (recurring)\n\n";

// ============================================================================
// Scenario 4: Task with Error and Recovery
// ============================================================================
echo "\033[1;33m";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Scenario 4: Error Handling\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\033[0m\n";

$unstableTask = $scheduler->scheduleAt(
    'unstable_api_call',
    microtime(true),
    function () {
        echo "  🌐 Calling external API...\n";
        
        // Simulate random failure
        if (rand(1, 3) === 1) {
            throw new RuntimeException('API timeout');
        }
        
        echo "  ✅ API call successful\n";
        return 'api_success';
    }
);

makeTaskDue($unstableTask);

echo "Unstable task scheduled (may fail)\n\n";

// ============================================================================
// Execute All Scheduled Tasks
// ============================================================================
echo "\033[1;36m";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Executing All Scheduled Tasks\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\033[0m\n";

$startTime = microtime(true);
$result = $scheduler->run('Execute all scheduled tasks');
$duration = round((microtime(true) - $startTime) * 1000, 2);

if ($result->isSuccess()) {
    echo "\n\033[1;32m✅ All tasks completed successfully!\033[0m\n";
    echo "Total execution time: {$duration}ms\n\n";
    
    $metadata = $result->getMetadata();
    echo "📊 Execution Summary:\n";
    echo "  • Tasks executed: {$metadata['tasks_executed']}\n";
    echo "  • Total scheduled: {$metadata['total_tasks']}\n";
} else {
    echo "\n\033[1;31m❌ Task execution failed: {$result->getError()}\033[0m\n";
}

// ============================================================================
// Detailed Execution History
// ============================================================================
echo "\n\033[1;36m";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Detailed Execution History\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\033[0m\n";

$history = $scheduler->getExecutionHistory(20);

$successful = 0;
$failed = 0;
$totalDuration = 0;

foreach ($history as $entry) {
    if ($entry['success']) {
        $successful++;
    } else {
        $failed++;
    }
    $totalDuration += $entry['duration'];
}

echo "📊 Statistics:\n";
echo "  • Total executions: " . count($history) . "\n";
echo "  • Successful: \033[32m{$successful}\033[0m\n";
echo "  • Failed: \033[31m{$failed}\033[0m\n";
if (count($history) > 0) {
    echo "  • Average duration: " . round(($totalDuration / count($history)) * 1000, 2) . "ms\n";
}
echo "\n";

echo "📜 Execution Log:\n\n";

foreach ($history as $i => $entry) {
    $timestamp = date('H:i:s', (int)$entry['timestamp']);
    $status = $entry['success'] ? "\033[32m✅\033[0m" : "\033[31m❌\033[0m";
    $duration = round($entry['duration'] * 1000, 2);
    
    echo sprintf(
        "%2d. %s %s [%s] (%sms)\n",
        $i + 1,
        $status,
        $entry['task_name'],
        $timestamp,
        $duration
    );
    
    if (!$entry['success'] && $entry['error']) {
        echo "    \033[31mError: {$entry['error']}\033[0m\n";
    }
}

// ============================================================================
// Task Status Overview
// ============================================================================
echo "\n\033[1;36m";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Task Status Overview\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\033[0m\n";

$allTasks = $scheduler->getTasks();

echo sprintf(
    "%-30s | %-20s | %-10s | %s\n",
    "Task Name",
    "Schedule",
    "Executions",
    "Status"
);
echo str_repeat("-", 100) . "\n";

foreach ($allTasks as $task) {
    $taskArray = $task->toArray();
    $schedule = $taskArray['schedule'] ?? 'one-time';
    $executions = $taskArray['execution_count'];
    
    $status = $task->isRecurring() ? "\033[33mRecurring\033[0m" : 
              ($executions > 0 ? "\033[32mCompleted\033[0m" : "\033[90mPending\033[0m");
    
    echo sprintf(
        "%-30s | %-20s | %-10d | %s\n",
        substr($taskArray['name'], 0, 28),
        substr($schedule, 0, 18),
        $executions,
        $status
    );
}

// ============================================================================
// Advanced Feature: Simulating Multiple Execution Cycles
// ============================================================================
echo "\n\033[1;36m";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Simulating Multiple Execution Cycles\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\033[0m\n";

echo "Running 3 additional execution cycles...\n\n";

for ($cycle = 1; $cycle <= 3; $cycle++) {
    echo "Cycle {$cycle}: ";
    
    // Make recurring tasks due again
    $recurringTasks = array_filter($allTasks, fn($t) => $t->isRecurring());
    foreach ($recurringTasks as $task) {
        makeTaskDue($task);
    }
    
    $cycleResult = $scheduler->run("Execution cycle {$cycle}");
    $tasksExecuted = $cycleResult->getMetadata()['tasks_executed'];
    
    echo "{$tasksExecuted} tasks executed\n";
    sleep(1);
}

echo "\n\033[1;32m";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                  Advanced Scheduler Example Complete!                     ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\033[0m\n";

$finalHistory = $scheduler->getExecutionHistory();
echo "Total task executions: " . count($finalHistory) . "\n";
echo "Active scheduled tasks: " . count($scheduler->getTasks()) . "\n";

