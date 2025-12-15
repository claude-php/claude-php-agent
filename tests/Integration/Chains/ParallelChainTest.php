<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Chains;

use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Chains\ParallelChain;
use ClaudeAgents\Chains\TransformChain;
use PHPUnit\Framework\TestCase;

class ParallelChainTest extends TestCase
{
    public function testParallelExecution(): void
    {
        $chain1 = TransformChain::create(fn (array $input): array => [
            'result1' => $input['value'] * 2,
        ]);

        $chain2 = TransformChain::create(fn (array $input): array => [
            'result2' => $input['value'] + 10,
        ]);

        $parallel = ParallelChain::create()
            ->addChain('multiply', $chain1)
            ->addChain('add', $chain2);

        $result = $parallel->invoke(['value' => 5]);

        $this->assertArrayHasKey('multiply_result1', $result);
        $this->assertArrayHasKey('add_result2', $result);
        $this->assertEquals(10, $result['multiply_result1']);
        $this->assertEquals(15, $result['add_result2']);
    }

    public function testMergeAggregation(): void
    {
        $chain1 = TransformChain::create(fn (array $input): array => ['a' => 1]);
        $chain2 = TransformChain::create(fn (array $input): array => ['b' => 2]);

        $parallel = ParallelChain::create()
            ->addChain('chain1', $chain1)
            ->addChain('chain2', $chain2)
            ->withAggregation('merge');

        $result = $parallel->invoke(['test' => 'value']);

        $this->assertEquals(1, $result['chain1_a']);
        $this->assertEquals(2, $result['chain2_b']);
    }

    public function testFirstAggregation(): void
    {
        $chain1 = TransformChain::create(fn (array $input): array => ['first' => true]);
        $chain2 = TransformChain::create(fn (array $input): array => ['second' => true]);

        $parallel = ParallelChain::create()
            ->addChain('chain1', $chain1)
            ->addChain('chain2', $chain2)
            ->withAggregation('first');

        $result = $parallel->invoke(['test' => 'value']);

        $this->assertTrue($result['first']);
        $this->assertArrayNotHasKey('second', $result);
    }

    public function testAllAggregation(): void
    {
        $chain1 = TransformChain::create(fn (array $input): array => ['data' => 1]);
        $chain2 = TransformChain::create(fn (array $input): array => ['data' => 2]);

        $parallel = ParallelChain::create()
            ->addChain('chain1', $chain1)
            ->addChain('chain2', $chain2)
            ->withAggregation('all');

        $result = $parallel->invoke(['test' => 'value']);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('chain1', $result['results']);
        $this->assertArrayHasKey('chain2', $result['results']);
    }

    public function testErrorHandling(): void
    {
        $chain1 = TransformChain::create(fn (array $input): array => ['success' => true]);
        $chain2 = TransformChain::create(
            fn (array $input): array => throw new \RuntimeException('Chain error')
        );

        $parallel = ParallelChain::create()
            ->addChain('success', $chain1)
            ->addChain('failure', $chain2)
            ->withAggregation('merge');

        $result = $parallel->invoke(['test' => 'value']);

        // Should still have success result
        $this->assertArrayHasKey('success_success', $result);
        // Should have error metadata
        $metadata = $result['metadata'] ?? [];
        $this->assertArrayHasKey('errors', $metadata);
    }

    public function testAllChainsFail(): void
    {
        $chain1 = TransformChain::create(
            fn (array $input): array => throw new \RuntimeException('Error 1')
        );
        $chain2 = TransformChain::create(
            fn (array $input): array => throw new \RuntimeException('Error 2')
        );

        $parallel = ParallelChain::create()
            ->addChain('chain1', $chain1)
            ->addChain('chain2', $chain2);

        $this->expectException(ChainExecutionException::class);
        $this->expectExceptionMessage('All parallel chains failed');

        $parallel->invoke(['test' => 'value']);
    }

    public function testEmptyChain(): void
    {
        $parallel = ParallelChain::create();

        $this->expectException(ChainExecutionException::class);
        $this->expectExceptionMessage('No chains added');

        $parallel->invoke(['test' => 'value']);
    }
}
