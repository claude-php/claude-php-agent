<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use ClaudeAgents\Scheduling\Schedule;
use PHPUnit\Framework\TestCase;

class ScheduleTest extends TestCase
{
    public function test_creates_cron_schedule(): void
    {
        $schedule = Schedule::cron('0 * * * *');

        $this->assertTrue($schedule->isRecurring());
        $this->assertSame('cron:0 * * * *', $schedule->toString());
    }

    public function test_creates_cron_schedule_with_timezone(): void
    {
        $schedule = Schedule::cron('0 0 * * *', 'America/New_York');

        $this->assertTrue($schedule->isRecurring());
        $this->assertStringContainsString('cron:', $schedule->toString());
    }

    public function test_creates_at_schedule(): void
    {
        $timestamp = microtime(true) + 3600; // 1 hour from now
        $schedule = Schedule::at($timestamp);

        $this->assertFalse($schedule->isRecurring());
        $this->assertStringContainsString('at:', $schedule->toString());
    }

    public function test_creates_in_schedule(): void
    {
        $schedule = Schedule::in('+1 hour');

        $this->assertFalse($schedule->isRecurring());
        $this->assertNotNull($schedule->getNextRunTime());
    }

    public function test_creates_every_schedule(): void
    {
        $schedule = Schedule::every(60);

        $this->assertTrue($schedule->isRecurring());
        $this->assertSame('every:60s', $schedule->toString());
    }

    public function test_get_next_run_time_for_cron(): void
    {
        $schedule = Schedule::cron('0 * * * *'); // Every hour
        $now = microtime(true);

        $nextRun = $schedule->getNextRunTime($now);

        $this->assertNotNull($nextRun);
        $this->assertGreaterThan($now, $nextRun);
    }

    public function test_get_next_run_time_for_at_schedule(): void
    {
        $future = microtime(true) + 3600;
        $schedule = Schedule::at($future);

        $nextRun = $schedule->getNextRunTime();

        $this->assertSame($future, $nextRun);
    }

    public function test_get_next_run_time_for_at_schedule_in_past(): void
    {
        $past = microtime(true) - 3600;
        $schedule = Schedule::at($past);

        $nextRun = $schedule->getNextRunTime();

        $this->assertNull($nextRun);
    }

    public function test_get_next_run_time_for_interval(): void
    {
        $schedule = Schedule::every(300); // Every 5 minutes
        $now = microtime(true);

        $nextRun = $schedule->getNextRunTime($now);

        $this->assertNotNull($nextRun);
        $this->assertEqualsWithDelta($now + 300, $nextRun, 0.01);
    }

    public function test_cron_schedule_is_recurring(): void
    {
        $schedule = Schedule::cron('0 0 * * *');

        $this->assertTrue($schedule->isRecurring());
    }

    public function test_interval_schedule_is_recurring(): void
    {
        $schedule = Schedule::every(60);

        $this->assertTrue($schedule->isRecurring());
    }

    public function test_at_schedule_is_not_recurring(): void
    {
        $schedule = Schedule::at(microtime(true) + 3600);

        $this->assertFalse($schedule->isRecurring());
    }

    public function test_in_schedule_is_not_recurring(): void
    {
        $schedule = Schedule::in('+1 hour');

        $this->assertFalse($schedule->isRecurring());
    }

    public function test_parses_hourly_cron(): void
    {
        $schedule = Schedule::cron('0 * * * *');
        $now = microtime(true);

        $nextRun = $schedule->getNextRunTime($now);

        $this->assertNotNull($nextRun);
        $this->assertGreaterThan($now, $nextRun);
    }

    public function test_parses_daily_cron(): void
    {
        $schedule = Schedule::cron('0 0 * * *');
        $now = microtime(true);

        $nextRun = $schedule->getNextRunTime($now);

        $this->assertNotNull($nextRun);
        $this->assertGreaterThan($now, $nextRun);
    }

    public function test_handles_invalid_cron_gracefully(): void
    {
        $schedule = Schedule::cron('invalid');
        $now = microtime(true);

        $nextRun = $schedule->getNextRunTime($now);

        // Should default to 1 hour
        $this->assertNotNull($nextRun);
        $this->assertGreaterThan($now, $nextRun);
    }

    public function test_to_string_formats_correctly(): void
    {
        $cronSchedule = Schedule::cron('*/5 * * * *');
        $this->assertStringContainsString('cron:', $cronSchedule->toString());

        $atSchedule = Schedule::at(microtime(true) + 3600);
        $this->assertStringContainsString('at:', $atSchedule->toString());

        $everySchedule = Schedule::every(300);
        $this->assertStringContainsString('every:', $everySchedule->toString());
        $this->assertStringContainsString('300s', $everySchedule->toString());
    }

    public function test_get_next_run_time_uses_default_current_time(): void
    {
        $future = time() + 7200;
        $schedule = Schedule::at((float)$future);

        $nextRun = $schedule->getNextRunTime();

        $this->assertNotNull($nextRun);
        $this->assertEqualsWithDelta($future, $nextRun, 1.0);
    }
}
