<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Async;

use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Async\BatchProcessor;
use ClaudeAgents\Async\Promise;
use Mockery;
use PHPUnit\Framework\TestCase;

class BatchProcessorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAddTask(): void
    {
        $agent = Mockery::mock(Agent::class);
        $processor = BatchProcessor::create($agent);

        $result = $processor->add('task1', 'Do something');

        $this->assertSame($processor, $result);
    }

    public function testAddMany(): void
    {
        $agent = Mockery::mock(Agent::class);
        $processor = BatchProcessor::create($agent);

        $tasks = [
            'task1' => 'First task',
            'task2' => 'Second task',
        ];

        $result = $processor->addMany($tasks);

        $this->assertSame($processor, $result);
    }

    public function testRunWithSuccess(): void
    {
        $agent = Mockery::mock(Agent::class);

        $result1 = AgentResult::success(
            answer: 'Result 1',
            messages: [],
            iterations: 1,
            metadata: ['token_usage' => ['input_tokens' => 10, 'output_tokens' => 20]]
        );

        $result2 = AgentResult::success(
            answer: 'Result 2',
            messages: [],
            iterations: 1,
            metadata: ['token_usage' => ['input_tokens' => 15, 'output_tokens' => 25]]
        );

        $agent->shouldReceive('run')
            ->with('Task 1')
            ->once()
            ->andReturn($result1);

        $agent->shouldReceive('run')
            ->with('Task 2')
            ->once()
            ->andReturn($result2);

        $processor = BatchProcessor::create($agent);
        $processor->addMany([
            'task1' => 'Task 1',
            'task2' => 'Task 2',
        ]);

        $results = $processor->run(concurrency: 2);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('task1', $results);
        $this->assertArrayHasKey('task2', $results);
        $this->assertEquals('Result 1', $results['task1']->getAnswer());
        $this->assertEquals('Result 2', $results['task2']->getAnswer());
    }

    public function testGetResult(): void
    {
        $agent = Mockery::mock(Agent::class);
        $result = AgentResult::success(answer: 'Success', messages: [], iterations: 1);

        $agent->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $processor = BatchProcessor::create($agent);
        $processor->add('task1', 'Do something');
        $processor->run();

        $retrieved = $processor->getResult('task1');
        $this->assertNotNull($retrieved);
        $this->assertEquals('Success', $retrieved->getAnswer());
    }

    public function testGetResults(): void
    {
        $agent = Mockery::mock(Agent::class);
        $result = AgentResult::success(answer: 'Success', messages: [], iterations: 1);

        $agent->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $processor = BatchProcessor::create($agent);
        $processor->add('task1', 'Do something');
        $processor->run();

        $results = $processor->getResults();
        $this->assertCount(1, $results);
    }

    public function testGetSuccessful(): void
    {
        $agent = Mockery::mock(Agent::class);

        $success = AgentResult::success(answer: 'Success', messages: [], iterations: 1);
        $failure = AgentResult::failure(error: 'Failed', messages: []);

        $agent->shouldReceive('run')
            ->with('Task 1')
            ->once()
            ->andReturn($success);

        $agent->shouldReceive('run')
            ->with('Task 2')
            ->once()
            ->andReturn($failure);

        $processor = BatchProcessor::create($agent);
        $processor->addMany([
            'task1' => 'Task 1',
            'task2' => 'Task 2',
        ]);
        $processor->run();

        $successful = $processor->getSuccessful();
        $this->assertCount(1, $successful);
        $this->assertArrayHasKey('task1', $successful);
    }

    public function testGetFailed(): void
    {
        $agent = Mockery::mock(Agent::class);

        $success = AgentResult::success(answer: 'Success', messages: [], iterations: 1);
        $failure = AgentResult::failure(error: 'Failed', messages: []);

        $agent->shouldReceive('run')
            ->with('Task 1')
            ->once()
            ->andReturn($success);

        $agent->shouldReceive('run')
            ->with('Task 2')
            ->once()
            ->andReturn($failure);

        $processor = BatchProcessor::create($agent);
        $processor->addMany([
            'task1' => 'Task 1',
            'task2' => 'Task 2',
        ]);
        $processor->run();

        $failed = $processor->getFailed();
        $this->assertCount(1, $failed);
        $this->assertArrayHasKey('task2', $failed);
    }

    public function testGetStats(): void
    {
        $agent = Mockery::mock(Agent::class);

        $success = AgentResult::success(
            answer: 'Success',
            messages: [],
            iterations: 1,
            metadata: ['token_usage' => ['input' => 100, 'output' => 200]]
        );

        $failure = AgentResult::failure(error: 'Failed', messages: []);

        $agent->shouldReceive('run')
            ->with('Task 1')
            ->once()
            ->andReturn($success);

        $agent->shouldReceive('run')
            ->with('Task 2')
            ->once()
            ->andReturn($failure);

        $processor = BatchProcessor::create($agent);
        $processor->addMany([
            'task1' => 'Task 1',
            'task2' => 'Task 2',
        ]);
        $processor->run();

        $stats = $processor->getStats();

        $this->assertEquals(2, $stats['total_tasks']);
        $this->assertEquals(1, $stats['successful']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(0.5, $stats['success_rate']);
        $this->assertEquals(100, $stats['total_tokens']['input']);
        $this->assertEquals(200, $stats['total_tokens']['output']);
        $this->assertEquals(300, $stats['total_tokens']['total']);
    }

    public function testRunAsync(): void
    {
        $agent = Mockery::mock(Agent::class);
        $result = AgentResult::success(answer: 'Success', messages: [], iterations: 1);

        $agent->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $processor = BatchProcessor::create($agent);
        $processor->add('task1', 'Do something');

        $promises = $processor->runAsync();

        $this->assertCount(1, $promises);
        $this->assertArrayHasKey('task1', $promises);
        $this->assertInstanceOf(Promise::class, $promises['task1']);

        // Wait for promise to resolve
        $promiseResult = $promises['task1']->wait();
        $this->assertEquals('Success', $promiseResult->getAnswer());
    }

    public function testReset(): void
    {
        $agent = Mockery::mock(Agent::class);
        $result = AgentResult::success(answer: 'Success', messages: [], iterations: 1);

        $agent->shouldReceive('run')
            ->once()
            ->andReturn($result);

        $processor = BatchProcessor::create($agent);
        $processor->add('task1', 'Do something');
        $processor->run();

        $this->assertCount(1, $processor->getResults());

        $processor->reset();

        $this->assertCount(0, $processor->getResults());
    }

    public function testErrorHandling(): void
    {
        $agent = Mockery::mock(Agent::class);

        $agent->shouldReceive('run')
            ->once()
            ->andThrow(new \RuntimeException('Agent error'));

        $processor = BatchProcessor::create($agent);
        $processor->add('task1', 'Do something');
        $results = $processor->run();

        $this->assertCount(1, $results);
        $this->assertFalse($results['task1']->isSuccess());
        $this->assertStringContainsString('Agent error', $results['task1']->getError());
    }

    public function testConcurrencyLimit(): void
    {
        $agent = Mockery::mock(Agent::class);

        // Create multiple tasks
        for ($i = 1; $i <= 5; $i++) {
            $agent->shouldReceive('run')
                ->with("Task {$i}")
                ->once()
                ->andReturn(AgentResult::success(answer: "Result {$i}", messages: [], iterations: 1));
        }

        $processor = BatchProcessor::create($agent);

        for ($i = 1; $i <= 5; $i++) {
            $processor->add("task{$i}", "Task {$i}");
        }

        // Run with concurrency of 2
        $start = microtime(true);
        $results = $processor->run(concurrency: 2);
        $duration = microtime(true) - $start;

        $this->assertCount(5, $results);

        // All tasks should complete successfully
        foreach ($results as $result) {
            $this->assertTrue($result->isSuccess());
        }
    }
}
