<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use ClaudeAgents\Scheduling\Schedule;
use ClaudeAgents\Scheduling\Task;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function test_creates_task_with_name_and_callback(): void
    {
        $called = false;
        $callback = function () use (&$called) {
            $called = true;

            return 'result';
        };

        $task = new Task('test_task', $callback);

        $this->assertSame('test_task', $task->getName());
        $this->assertNotEmpty($task->getId());
        $this->assertStringContainsString('task_', $task->getId());
    }

    public function test_creates_task_with_schedule(): void
    {
        $schedule = Schedule::every(60);
        $task = new Task('scheduled_task', fn () => null, $schedule);

        $this->assertSame($schedule, $task->getSchedule());
        $this->assertNotNull($task->getNextRun());
    }

    public function test_creates_task_with_dependencies(): void
    {
        $dependencies = ['task_1', 'task_2'];
        $task = new Task('dependent_task', fn () => null, null, $dependencies);

        $this->assertSame($dependencies, $task->getDependencies());
    }

    public function test_creates_task_with_metadata(): void
    {
        $metadata = ['priority' => 'high', 'owner' => 'admin'];
        $task = new Task('meta_task', fn () => null, null, [], $metadata);

        $array = $task->toArray();
        $this->assertSame($metadata, $array['metadata']);
    }

    public function test_executes_task_callback(): void
    {
        $executed = false;
        $callback = function () use (&$executed) {
            $executed = true;

            return 'success';
        };

        $task = new Task('exec_task', $callback);
        $result = $task->execute();

        $this->assertTrue($executed);
        $this->assertSame('success', $result);
    }

    public function test_execute_updates_last_run(): void
    {
        $task = new Task('test_task', fn () => 'done');

        $this->assertNull($task->getLastRun());

        $beforeExec = microtime(true);
        $task->execute();
        $afterExec = microtime(true);

        $lastRun = $task->getLastRun();
        $this->assertNotNull($lastRun);
        $this->assertGreaterThanOrEqual($beforeExec, $lastRun);
        $this->assertLessThanOrEqual($afterExec, $lastRun);
    }

    public function test_execute_increments_execution_count(): void
    {
        $task = new Task('count_task', fn () => null);

        $this->assertSame(0, $task->getExecutionCount());

        $task->execute();
        $this->assertSame(1, $task->getExecutionCount());

        $task->execute();
        $this->assertSame(2, $task->getExecutionCount());

        $task->execute();
        $this->assertSame(3, $task->getExecutionCount());
    }

    public function test_execute_updates_next_run_for_recurring_task(): void
    {
        $schedule = Schedule::every(60);
        $task = new Task('recurring_task', fn () => null, $schedule);

        $firstNextRun = $task->getNextRun();
        sleep(1); // Ensure time passes
        $task->execute();
        $secondNextRun = $task->getNextRun();

        $this->assertNotNull($firstNextRun);
        $this->assertNotNull($secondNextRun);
        $this->assertGreaterThan($firstNextRun, $secondNextRun);
    }

    public function test_execute_clears_next_run_for_one_time_task(): void
    {
        $schedule = Schedule::at(microtime(true) + 3600);
        $task = new Task('one_time_task', fn () => null, $schedule);

        $this->assertNotNull($task->getNextRun());

        $task->execute();

        $this->assertNull($task->getNextRun());
    }

    public function test_is_due_returns_true_when_time_passed(): void
    {
        $pastTime = microtime(true) - 100;
        $schedule = Schedule::at($pastTime);
        $task = new Task('past_task', fn () => null, $schedule);

        // Manually set next run to past (since Schedule::at won't schedule past times)
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('nextRun');
        $property->setAccessible(true);
        $property->setValue($task, $pastTime);

        $this->assertTrue($task->isDue());
    }

    public function test_is_due_returns_false_when_time_not_passed(): void
    {
        $futureTime = microtime(true) + 3600;
        $schedule = Schedule::at($futureTime);
        $task = new Task('future_task', fn () => null, $schedule);

        $this->assertFalse($task->isDue());
    }

    public function test_is_due_returns_false_when_no_next_run(): void
    {
        $task = new Task('no_schedule_task', fn () => null);

        $this->assertFalse($task->isDue());
    }

    public function test_is_due_accepts_custom_current_time(): void
    {
        $futureTime = microtime(true) + 100;
        $schedule = Schedule::at($futureTime);
        $task = new Task('custom_time_task', fn () => null, $schedule);

        $currentTime = microtime(true);

        // Not due at current time (before scheduled time)
        $this->assertFalse($task->isDue($currentTime));

        // Due after the scheduled time
        $this->assertTrue($task->isDue($futureTime + 10));
    }

    public function test_is_recurring_returns_true_for_recurring_schedule(): void
    {
        $cronSchedule = Schedule::cron('0 * * * *');
        $cronTask = new Task('cron_task', fn () => null, $cronSchedule);
        $this->assertTrue($cronTask->isRecurring());

        $intervalSchedule = Schedule::every(300);
        $intervalTask = new Task('interval_task', fn () => null, $intervalSchedule);
        $this->assertTrue($intervalTask->isRecurring());
    }

    public function test_is_recurring_returns_false_for_one_time_schedule(): void
    {
        $atSchedule = Schedule::at(microtime(true) + 3600);
        $atTask = new Task('at_task', fn () => null, $atSchedule);
        $this->assertFalse($atTask->isRecurring());

        $inSchedule = Schedule::in('+1 hour');
        $inTask = new Task('in_task', fn () => null, $inSchedule);
        $this->assertFalse($inTask->isRecurring());
    }

    public function test_is_recurring_returns_false_for_no_schedule(): void
    {
        $task = new Task('no_schedule', fn () => null);

        $this->assertFalse($task->isRecurring());
    }

    public function test_to_array_returns_complete_info(): void
    {
        $schedule = Schedule::every(60);
        $dependencies = ['dep1', 'dep2'];
        $metadata = ['key' => 'value'];

        $task = new Task('array_task', fn () => null, $schedule, $dependencies, $metadata);
        $task->execute();

        $array = $task->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('schedule', $array);
        $this->assertArrayHasKey('dependencies', $array);
        $this->assertArrayHasKey('last_run', $array);
        $this->assertArrayHasKey('next_run', $array);
        $this->assertArrayHasKey('execution_count', $array);
        $this->assertArrayHasKey('metadata', $array);

        $this->assertSame('array_task', $array['name']);
        $this->assertSame($dependencies, $array['dependencies']);
        $this->assertSame($metadata, $array['metadata']);
        $this->assertSame(1, $array['execution_count']);
        $this->assertNotNull($array['last_run']);
        $this->assertNotNull($array['next_run']);
    }

    public function test_callback_is_retrievable(): void
    {
        $callback = fn () => 'test';
        $task = new Task('callback_task', $callback);

        $retrievedCallback = $task->getCallback();

        $this->assertSame($callback, $retrievedCallback);
        $this->assertSame('test', ($retrievedCallback)());
    }

    public function test_task_id_is_unique(): void
    {
        $task1 = new Task('task1', fn () => null);
        $task2 = new Task('task2', fn () => null);

        $this->assertNotSame($task1->getId(), $task2->getId());
    }

    public function test_execute_returns_callback_result(): void
    {
        $task = new Task('result_task', fn () => ['status' => 'success', 'data' => 123]);

        $result = $task->execute();

        $this->assertIsArray($result);
        $this->assertSame('success', $result['status']);
        $this->assertSame(123, $result['data']);
    }
}
