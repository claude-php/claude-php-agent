<?php

declare(strict_types=1);

namespace Tests\Integration\Agents;

use ClaudeAgents\Agents\SchedulerAgent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for SchedulerAgent.
 * These tests verify the agent works with real scheduling scenarios.
 */
class SchedulerAgentIntegrationTest extends TestCase
{
    private ?ClaudePhp $client = null;
    private ?SchedulerAgent $agent = null;

    protected function setUp(): void
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY environment variable not set');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
        $this->agent = new SchedulerAgent($this->client, [
            'name' => 'integration_test_scheduler',
        ]);
    }

    public function test_schedules_and_executes_immediate_task(): void
    {
        $executed = false;
        $result = null;

        // Schedule task in the past so it executes immediately
        $pastTime = microtime(true) - 1;
        $task = $this->agent->scheduleAt('immediate_task', $pastTime, function () use (&$executed, &$result) {
            $executed = true;
            $result = 'Task completed';

            return $result;
        });

        // Make task due by setting next run
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('nextRun');
        $property->setAccessible(true);
        $property->setValue($task, $pastTime);

        // Execute pending tasks
        $agentResult = $this->agent->run('Execute immediate task');

        $this->assertTrue($executed, 'Task should have been executed');
        $this->assertSame('Task completed', $result);
        $this->assertTrue($agentResult->isSuccess());
        $this->assertSame(1, $agentResult->getMetadata()['tasks_executed']);
    }

    public function test_schedules_multiple_tasks_with_different_schedules(): void
    {
        $results = [];
        $pastTime = microtime(true) - 1;

        // Schedule multiple tasks
        $task1 = $this->agent->scheduleAt('task1', $pastTime, function () use (&$results) {
            $results[] = 'task1';
        });

        $task2 = $this->agent->scheduleEvery('task2', 60, function () use (&$results) {
            $results[] = 'task2';
        });

        $task3 = $this->agent->schedule('task3', '0 * * * *', function () use (&$results) {
            $results[] = 'task3';
        });

        // Make task1 and task2 due
        foreach ([$task1, $task2] as $task) {
            $reflection = new \ReflectionClass($task);
            $property = $reflection->getProperty('nextRun');
            $property->setAccessible(true);
            $property->setValue($task, $pastTime);
        }

        // Execute
        $this->agent->run('Execute multiple tasks');

        $this->assertContains('task1', $results);
        $this->assertContains('task2', $results);
        // task3 (cron) is not due yet, so should not be in results
    }

    public function test_dependency_chain_execution(): void
    {
        $executionOrder = [];
        $pastTime = microtime(true) - 1;

        // Create a chain: task1 -> task2 -> task3
        $task1 = $this->agent->scheduleAt('task1', $pastTime, function () use (&$executionOrder) {
            $executionOrder[] = 'task1';
            usleep(10000); // 10ms delay to ensure distinct timing
        });

        $task2 = $this->agent->scheduleAt('task2', $pastTime, function () use (&$executionOrder) {
            $executionOrder[] = 'task2';
            usleep(10000);
        }, [$task1->getId()]);

        $task3 = $this->agent->scheduleAt('task3', $pastTime, function () use (&$executionOrder) {
            $executionOrder[] = 'task3';
        }, [$task2->getId()]);

        // Make all tasks due
        foreach ([$task1, $task2, $task3] as $task) {
            $reflection = new \ReflectionClass($task);
            $property = $reflection->getProperty('nextRun');
            $property->setAccessible(true);
            $property->setValue($task, $pastTime);
        }

        // Execute
        $this->agent->run('Execute dependency chain');

        $this->assertSame(['task1', 'task2', 'task3'], $executionOrder);
    }

    public function test_recurring_task_scheduling(): void
    {
        $executionCount = 0;
        $pastTime = microtime(true) - 1;

        $task = $this->agent->scheduleEvery('recurring_task', 1, function () use (&$executionCount) {
            $executionCount++;

            return 'executed';
        });

        // Make task due
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('nextRun');
        $property->setAccessible(true);

        // Execute multiple times
        for ($i = 0; $i < 3; $i++) {
            $property->setValue($task, $pastTime);
            $this->agent->run("Execute recurring task iteration {$i}");

            // Verify task has a new next run time
            $this->assertNotNull($task->getNextRun());
        }

        $this->assertSame(3, $executionCount);
    }

    public function test_task_execution_history_tracking(): void
    {
        $pastTime = microtime(true) - 1;

        // Execute several tasks
        for ($i = 0; $i < 5; $i++) {
            $task = $this->agent->scheduleAt("history_task_{$i}", $pastTime, function () use ($i) {
                return "result_{$i}";
            });

            $reflection = new \ReflectionClass($task);
            $property = $reflection->getProperty('nextRun');
            $property->setAccessible(true);
            $property->setValue($task, $pastTime);

            $this->agent->run("Execute history task {$i}");
        }

        $history = $this->agent->getExecutionHistory();

        $this->assertGreaterThanOrEqual(5, count($history));

        foreach ($history as $entry) {
            $this->assertArrayHasKey('task_id', $entry);
            $this->assertArrayHasKey('task_name', $entry);
            $this->assertArrayHasKey('timestamp', $entry);
            $this->assertArrayHasKey('success', $entry);
            $this->assertArrayHasKey('duration', $entry);
            $this->assertTrue($entry['success']);
        }
    }

    public function test_task_failure_handling(): void
    {
        $pastTime = microtime(true) - 1;

        $task = $this->agent->scheduleAt('failing_task', $pastTime, function () {
            throw new \RuntimeException('Intentional failure');
        });

        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('nextRun');
        $property->setAccessible(true);
        $property->setValue($task, $pastTime);

        // Should not throw exception
        $result = $this->agent->run('Execute failing task');

        $this->assertTrue($result->isSuccess()); // Agent run succeeds even if task fails

        $history = $this->agent->getExecutionHistory(1);
        $this->assertNotEmpty($history);
        $this->assertFalse($history[0]['success']);
        $this->assertStringContainsString('Intentional failure', $history[0]['error']);
    }

    public function test_unscheduling_task(): void
    {
        $executed = false;
        $pastTime = microtime(true) - 1;

        $task = $this->agent->scheduleAt('removable_task', $pastTime, function () use (&$executed) {
            $executed = true;
        });

        $taskId = $task->getId();

        // Verify task exists
        $this->assertNotNull($this->agent->getTask($taskId));

        // Unschedule it
        $this->assertTrue($this->agent->unschedule($taskId));

        // Verify it's gone
        $this->assertNull($this->agent->getTask($taskId));

        // Try to execute - nothing should happen
        $this->agent->run('Execute after unschedule');

        $this->assertFalse($executed);
    }

    public function test_complex_scheduling_scenario(): void
    {
        $results = [];
        $pastTime = microtime(true) - 1;

        // Setup:
        // - database_backup runs first (no dependencies)
        // - cleanup runs after database_backup
        // - notification runs after cleanup
        // - report runs independently but only if it's due

        $backup = $this->agent->scheduleAt('database_backup', $pastTime, function () use (&$results) {
            $results['backup'] = microtime(true);

            return 'backup_complete';
        });

        $cleanup = $this->agent->scheduleAt('cleanup', $pastTime, function () use (&$results) {
            $results['cleanup'] = microtime(true);

            return 'cleanup_complete';
        }, [$backup->getId()]);

        $notification = $this->agent->scheduleAt('notification', $pastTime, function () use (&$results) {
            $results['notification'] = microtime(true);

            return 'notification_sent';
        }, [$cleanup->getId()]);

        $report = $this->agent->scheduleAt('report', microtime(true) + 3600, function () use (&$results) {
            $results['report'] = microtime(true);

            return 'report_generated';
        });

        // Make backup, cleanup, and notification due
        foreach ([$backup, $cleanup, $notification] as $task) {
            $reflection = new \ReflectionClass($task);
            $property = $reflection->getProperty('nextRun');
            $property->setAccessible(true);
            $property->setValue($task, $pastTime);
        }

        // Execute all pending
        $agentResult = $this->agent->run('Execute complex scenario');

        // Verify execution
        $this->assertTrue($agentResult->isSuccess());
        $this->assertArrayHasKey('backup', $results);
        $this->assertArrayHasKey('cleanup', $results);
        $this->assertArrayHasKey('notification', $results);
        $this->assertArrayNotHasKey('report', $results); // Not due

        // Verify execution order
        $this->assertLessThan($results['cleanup'], $results['backup']);
        $this->assertLessThan($results['notification'], $results['cleanup']);

        // Verify metadata
        $metadata = $agentResult->getMetadata();
        $this->assertSame(3, $metadata['tasks_executed']);
        $this->assertSame(4, $metadata['total_tasks']);
    }

    public function test_get_all_tasks(): void
    {
        $this->agent->schedule('cron_task', '0 * * * *', fn () => null);
        $this->agent->scheduleEvery('interval_task', 300, fn () => null);
        $this->agent->scheduleAt('one_time_task', microtime(true) + 3600, fn () => null);

        $tasks = $this->agent->getTasks();

        $this->assertCount(3, $tasks);

        $names = array_map(fn ($task) => $task->getName(), $tasks);
        $this->assertContains('cron_task', $names);
        $this->assertContains('interval_task', $names);
        $this->assertContains('one_time_task', $names);
    }

    public function test_agent_result_structure(): void
    {
        $result = $this->agent->run('Test result structure');

        $this->assertTrue($result->isSuccess());
        $this->assertIsString($result->getAnswer());
        $this->assertIsArray($result->getMessages());
        $this->assertGreaterThan(0, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('tasks_executed', $metadata);
        $this->assertArrayHasKey('total_tasks', $metadata);
        $this->assertArrayHasKey('execution_history', $metadata);
    }
}
