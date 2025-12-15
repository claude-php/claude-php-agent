<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Chains;

use ClaudeAgents\Chains\SequentialChain;
use ClaudeAgents\Chains\TransformChain;
use PHPUnit\Framework\TestCase;

class SequentialChainTest extends TestCase
{
    public function testSequentialExecution(): void
    {
        $step1 = TransformChain::create(fn (array $input): array => [
            'step1_output' => $input['value'] * 2,
        ]);

        $step2 = TransformChain::create(fn (array $input): array => [
            'step2_output' => $input['step1_output'] + 10,
        ]);

        $chain = SequentialChain::create()
            ->addChain('step1', $step1)
            ->addChain('step2', $step2);

        $result = $chain->invoke(['value' => 5]);

        $this->assertEquals(10, $result['step1']['step1_output']);
        $this->assertEquals(20, $result['step2']['step2_output']);
    }

    public function testOutputMapping(): void
    {
        $step1 = TransformChain::create(fn (array $input): array => [
            'result' => $input['text'] . ' processed',
        ]);

        $step2 = TransformChain::create(fn (array $input): array => [
            'final' => strtoupper($input['input_text'] ?? ''),
        ]);

        $chain = SequentialChain::create()
            ->addChain('process', $step1)
            ->addChain('format', $step2)
            ->mapOutput('process', 'result', 'format', 'input_text');

        $result = $chain->invoke(['text' => 'hello']);

        $this->assertArrayHasKey('format', $result);
        $this->assertEquals('HELLO PROCESSED', $result['format']['final']);
    }

    public function testConditionalExecution(): void
    {
        $step1 = TransformChain::create(fn (array $input): array => [
            'count' => $input['count'] ?? 0,
        ]);

        $step2 = TransformChain::create(fn (array $input): array => [
            'doubled' => ($input['count'] ?? 0) * 2,
        ]);

        $chain = SequentialChain::create()
            ->addChain('count', $step1)
            ->addChain('double', $step2)
            ->setCondition(
                'double',
                fn (array $results): bool => ($results['count']['count'] ?? 0) > 5
            );

        // Should execute both steps
        $result1 = $chain->invoke(['count' => 10]);
        $this->assertArrayHasKey('double', $result1);

        // Should skip double step
        $result2 = $chain->invoke(['count' => 3]);
        $this->assertArrayNotHasKey('double', $result2);
    }

    public function testMultipleMappings(): void
    {
        $extract = TransformChain::create(fn (array $input): array => [
            'entities' => ['person' => 'John', 'place' => 'NYC'],
        ]);

        $analyze = TransformChain::create(fn (array $input): array => [
            'analysis' => 'Found: ' . ($input['entities']['person'] ?? 'unknown'),
        ]);

        $format = TransformChain::create(fn (array $input): array => [
            'formatted' => json_encode($input['analysis'] ?? ''),
        ]);

        $chain = SequentialChain::create()
            ->addChain('extract', $extract)
            ->addChain('analyze', $analyze)
            ->addChain('format', $format)
            ->mapOutput('extract', 'entities', 'analyze', 'entities')
            ->mapOutput('analyze', 'analysis', 'format', 'analysis');

        $result = $chain->invoke(['text' => 'test']);

        $this->assertArrayHasKey('format', $result);
        $this->assertStringContainsString('Found: John', $result['format']['formatted']);
    }

    public function testEmptyChain(): void
    {
        $chain = SequentialChain::create();

        $result = $chain->invoke(['input' => 'test']);

        $this->assertEquals(['input' => 'test'], $result);
    }
}
