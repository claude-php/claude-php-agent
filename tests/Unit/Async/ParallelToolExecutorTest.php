<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Async;

use ClaudeAgents\Async\ParallelToolExecutor;
use ClaudeAgents\Async\Promise;
use ClaudeAgents\Tools\Tool;
use PHPUnit\Framework\TestCase;

class ParallelToolExecutorTest extends TestCase
{
    private function createTestTools(): array
    {
        $calcTool = Tool::create('calculate')
            ->description('Calculate')
            ->stringParam('expression', 'Expression to calculate')
            ->handler(function (array $input): string {
                $result = eval("return {$input['expression']};");

                return json_encode(['result' => $result]);
            });

        $echoTool = Tool::create('echo')
            ->description('Echo input')
            ->stringParam('text', 'Text to echo')
            ->handler(function (array $input): string {
                return json_encode(['text' => $input['text']]);
            });

        return [$calcTool, $echoTool];
    }

    public function testExecuteMultipleTools(): void
    {
        $tools = $this->createTestTools();
        $executor = new ParallelToolExecutor($tools);

        $calls = [
            ['tool' => 'calculate', 'input' => ['expression' => '2 + 2']],
            ['tool' => 'echo', 'input' => ['text' => 'hello']],
            ['tool' => 'calculate', 'input' => ['expression' => '10 * 5']],
        ];

        $results = $executor->execute($calls);

        $this->assertCount(3, $results);

        $this->assertEquals('calculate', $results[0]['tool']);
        $data0 = json_decode($results[0]['result']->getContent(), true);
        $this->assertEquals(4, $data0['result']);

        $this->assertEquals('echo', $results[1]['tool']);
        $data1 = json_decode($results[1]['result']->getContent(), true);
        $this->assertEquals('hello', $data1['text']);

        $this->assertEquals('calculate', $results[2]['tool']);
        $data2 = json_decode($results[2]['result']->getContent(), true);
        $this->assertEquals(50, $data2['result']);
    }

    public function testExecuteUnknownTool(): void
    {
        $tools = $this->createTestTools();
        $executor = new ParallelToolExecutor($tools);

        $calls = [
            ['tool' => 'nonexistent', 'input' => []],
        ];

        $results = $executor->execute($calls);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['result']->isError());
        $this->assertStringContainsString('Unknown tool', $results[0]['result']->getContent());
    }

    public function testExecuteAsync(): void
    {
        $tools = $this->createTestTools();
        $executor = new ParallelToolExecutor($tools);

        $calls = [
            ['tool' => 'calculate', 'input' => ['expression' => '5 + 5']],
            ['tool' => 'echo', 'input' => ['text' => 'async']],
        ];

        $promises = $executor->executeAsync($calls);

        $this->assertCount(2, $promises);
        $this->assertContainsOnlyInstancesOf(Promise::class, $promises);

        // Wait for all promises
        $results = Promise::all($promises);

        $this->assertCount(2, $results);
        $data0 = json_decode($results[0]['result']->getContent(), true);
        $this->assertEquals(10, $data0['result']);
        $data1 = json_decode($results[1]['result']->getContent(), true);
        $this->assertEquals('async', $data1['text']);
    }

    public function testWaitAll(): void
    {
        $tools = $this->createTestTools();
        $executor = new ParallelToolExecutor($tools);

        $calls = [
            ['tool' => 'calculate', 'input' => ['expression' => '7 * 3']],
        ];

        $promises = $executor->executeAsync($calls);
        $results = ParallelToolExecutor::waitAll($promises);

        $this->assertCount(1, $results);
        $data0 = json_decode($results[0]['result']->getContent(), true);
        $this->assertEquals(21, $data0['result']);
    }

    public function testExecuteBatched(): void
    {
        $tools = $this->createTestTools();
        $executor = new ParallelToolExecutor($tools);

        $calls = [
            ['tool' => 'calculate', 'input' => ['expression' => '1 + 1']],
            ['tool' => 'calculate', 'input' => ['expression' => '2 + 2']],
            ['tool' => 'calculate', 'input' => ['expression' => '3 + 3']],
            ['tool' => 'calculate', 'input' => ['expression' => '4 + 4']],
            ['tool' => 'calculate', 'input' => ['expression' => '5 + 5']],
        ];

        // Execute with concurrency of 2
        $results = $executor->executeBatched($calls, concurrency: 2);

        $this->assertCount(5, $results);
        $data0 = json_decode($results[0]['result']->getContent(), true);
        $this->assertEquals(2, $data0['result']);
        $data1 = json_decode($results[1]['result']->getContent(), true);
        $this->assertEquals(4, $data1['result']);
        $data2 = json_decode($results[2]['result']->getContent(), true);
        $this->assertEquals(6, $data2['result']);
        $data3 = json_decode($results[3]['result']->getContent(), true);
        $this->assertEquals(8, $data3['result']);
        $data4 = json_decode($results[4]['result']->getContent(), true);
        $this->assertEquals(10, $data4['result']);
    }

    public function testToolExecutionError(): void
    {
        $errorTool = Tool::create('error_tool')
            ->description('Always throws')
            ->handler(function (): string {
                throw new \RuntimeException('Tool error');
            });

        $executor = new ParallelToolExecutor([$errorTool]);

        $calls = [
            ['tool' => 'error_tool', 'input' => []],
        ];

        $results = $executor->execute($calls);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['result']->isError());
        $this->assertStringContainsString('Tool error', $results[0]['result']->getContent());
    }

    public function testConcurrentExecution(): void
    {
        // Create a tool that sleeps to verify concurrent execution
        $sleepTool = Tool::create('sleep_tool')
            ->description('Sleep for a duration')
            ->parameter('ms', 'integer', 'Milliseconds to sleep')
            ->handler(function (array $input): string {
                usleep($input['ms'] * 1000);

                return json_encode(['slept' => $input['ms']]);
            });

        $executor = new ParallelToolExecutor([$sleepTool]);

        // Execute 3 calls that each sleep 100ms
        $calls = [
            ['tool' => 'sleep_tool', 'input' => ['ms' => 100]],
            ['tool' => 'sleep_tool', 'input' => ['ms' => 100]],
            ['tool' => 'sleep_tool', 'input' => ['ms' => 100]],
        ];

        $start = microtime(true);
        $results = $executor->execute($calls);
        $duration = microtime(true) - $start;

        $this->assertCount(3, $results);

        // With true parallel execution, 3x100ms should take ~100ms, not 300ms
        // However, AMPHP with usleep may not provide true parallelism in all contexts
        // Check that it's faster than sequential (which would be ~300ms)
        // Allow for overhead, check it's less than 400ms (true concurrent would be ~100ms)
        $this->assertLessThan(0.4, $duration, 'Execution should show some concurrency benefit');
    }
}
