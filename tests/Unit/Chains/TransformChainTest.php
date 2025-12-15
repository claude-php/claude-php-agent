<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Chains;

use ClaudeAgents\Chains\ChainInput;
use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Chains\TransformChain;
use PHPUnit\Framework\TestCase;

class TransformChainTest extends TestCase
{
    public function testTransformData(): void
    {
        $transformer = fn (array $input): array => [
            'uppercase' => strtoupper($input['text'] ?? ''),
            'length' => strlen($input['text'] ?? ''),
        ];

        $chain = TransformChain::create($transformer);
        $result = $chain->invoke(['text' => 'hello']);

        $this->assertEquals('HELLO', $result['uppercase']);
        $this->assertEquals(5, $result['length']);
    }

    public function testTransformWithComplexData(): void
    {
        $transformer = fn (array $input): array => [
            'sum' => ($input['a'] ?? 0) + ($input['b'] ?? 0),
            'product' => ($input['a'] ?? 0) * ($input['b'] ?? 0),
        ];

        $chain = TransformChain::create($transformer);
        $result = $chain->invoke(['a' => 5, 'b' => 3]);

        $this->assertEquals(8, $result['sum']);
        $this->assertEquals(15, $result['product']);
    }

    public function testTransformReturnsNonArray(): void
    {
        $transformer = fn (array $input): string => 'not an array';

        $chain = TransformChain::create($transformer);

        $this->expectException(ChainExecutionException::class);
        $this->expectExceptionMessage('Transform function must return an array');

        $chain->invoke(['test' => 'value']);
    }

    public function testTransformWithException(): void
    {
        $transformer = fn (array $input): array => throw new \RuntimeException('Transform error');

        $chain = TransformChain::create($transformer);

        $this->expectException(ChainExecutionException::class);
        $this->expectExceptionMessage('Transform execution failed');

        $chain->invoke(['test' => 'value']);
    }

    public function testTransformMetadata(): void
    {
        $transformer = fn (array $input): array => ['transformed' => true];

        $chain = TransformChain::create($transformer);
        $input = ChainInput::create(['data' => 'test']);
        $output = $chain->run($input);

        $this->assertTrue($output->getMetadataValue('transformed'));
    }
}
