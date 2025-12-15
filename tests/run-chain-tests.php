<?php

/**
 * Standalone End-to-End Test Runner for Chain Composition System
 *
 * This script runs comprehensive tests without requiring PHPUnit.
 * Run with: php tests/run-chain-tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Chains\ChainInput;
use ClaudeAgents\Chains\ChainOutput;
use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Chains\Exceptions\ChainValidationException;
use ClaudeAgents\Chains\ParallelChain;
use ClaudeAgents\Chains\RouterChain;
use ClaudeAgents\Chains\SequentialChain;
use ClaudeAgents\Chains\TransformChain;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function run(): void
    {
        echo "========================================\n";
        echo "Chain Composition System - E2E Tests\n";
        echo "========================================\n\n";

        $this->testChainInput();
        $this->testChainOutput();
        $this->testTransformChain();
        $this->testSequentialChain();
        $this->testParallelChain();
        $this->testRouterChain();
        $this->testChainComposition();
        $this->testErrorHandling();

        $this->printSummary();
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "✓ $message\n";
        } else {
            $this->failed++;
            $this->failures[] = $message;
            echo "✗ $message\n";
        }
    }

    private function testChainInput(): void
    {
        echo "\n--- Testing ChainInput ---\n";

        $input = ChainInput::create(['name' => 'test', 'count' => 42]);
        $this->assert($input->has('name'), 'ChainInput::has() works');
        $this->assert($input->get('name') === 'test', 'ChainInput::get() returns correct value');
        $this->assert($input->get('missing', 'default') === 'default', 'ChainInput::get() with default works');
        $this->assert($input->getDot('name') === 'test', 'ChainInput::getDot() works');

        // Test validation
        try {
            $input->validate(['required' => ['name', 'count']]);
            $this->assert(true, 'ChainInput validation passes for valid input');
        } catch (\Exception $e) {
            $this->assert(false, 'ChainInput validation should pass');
        }

        try {
            $input->validate(['required' => ['missing']]);
            $this->assert(false, 'ChainInput validation should fail for missing field');
        } catch (ChainValidationException $e) {
            $this->assert(true, 'ChainInput validation throws ChainValidationException');
        }
    }

    private function testChainOutput(): void
    {
        echo "\n--- Testing ChainOutput ---\n";

        $output = ChainOutput::create(['result' => 'success'], ['tokens' => 100]);
        $this->assert($output->has('result'), 'ChainOutput::has() works');
        $this->assert($output->get('result') === 'success', 'ChainOutput::get() returns correct value');
        $this->assert($output->getMetadataValue('tokens') === 100, 'ChainOutput::getMetadataValue() works');
        $this->assert(count($output->getMetadata()) === 1, 'ChainOutput::getMetadata() returns metadata');
        $this->assert(isset($output->toArray()['data']), 'ChainOutput::toArray() includes data');
        $this->assert(isset($output->toArray()['metadata']), 'ChainOutput::toArray() includes metadata');
    }

    private function testTransformChain(): void
    {
        echo "\n--- Testing TransformChain ---\n";

        $chain = TransformChain::create(fn (array $input): array => [
            'uppercase' => strtoupper($input['text'] ?? ''),
            'length' => strlen($input['text'] ?? ''),
        ]);

        $result = $chain->invoke(['text' => 'hello']);
        $this->assert($result['uppercase'] === 'HELLO', 'TransformChain transforms text');
        $this->assert($result['length'] === 5, 'TransformChain calculates length');

        // Test error handling
        $errorChain = TransformChain::create(fn (array $input): string => 'not array');

        try {
            $errorChain->invoke(['test' => 'value']);
            $this->assert(false, 'TransformChain should throw on non-array return');
        } catch (ChainExecutionException $e) {
            $this->assert(true, 'TransformChain throws ChainExecutionException for invalid return');
        }
    }

    private function testSequentialChain(): void
    {
        echo "\n--- Testing SequentialChain ---\n";

        $step1 = TransformChain::create(fn (array $input): array => [
            'doubled' => ($input['value'] ?? 0) * 2,
        ]);

        $step2 = TransformChain::create(fn (array $input): array => [
            'final' => ($input['doubled'] ?? 0) + 10,
        ]);

        $chain = SequentialChain::create()
            ->addChain('step1', $step1)
            ->addChain('step2', $step2);

        $result = $chain->invoke(['value' => 5]);
        $this->assert(isset($result['step1']), 'SequentialChain executes step1');
        $this->assert(isset($result['step2']), 'SequentialChain executes step2');
        $this->assert($result['step1']['doubled'] === 10, 'SequentialChain step1 produces correct output');
        $this->assert($result['step2']['final'] === 20, 'SequentialChain step2 receives correct input');

        // Test output mapping
        $mapped = SequentialChain::create()
            ->addChain('source', TransformChain::create(fn (array $input): array => ['data' => 'test']))
            ->addChain('target', TransformChain::create(fn (array $input): array => [
                'received' => $input['mapped'] ?? 'none',
            ]))
            ->mapOutput('source', 'data', 'target', 'mapped');

        $mappedResult = $mapped->invoke(['input' => 'value']);
        $this->assert($mappedResult['target']['received'] === 'test', 'SequentialChain output mapping works');
    }

    private function testParallelChain(): void
    {
        echo "\n--- Testing ParallelChain ---\n";

        $chain1 = TransformChain::create(fn (array $input): array => ['a' => 1]);
        $chain2 = TransformChain::create(fn (array $input): array => ['b' => 2]);

        $parallel = ParallelChain::create()
            ->addChain('chain1', $chain1)
            ->addChain('chain2', $chain2)
            ->withAggregation('merge');

        $result = $parallel->invoke(['test' => 'value']);
        $this->assert(isset($result['chain1_a']), 'ParallelChain merge includes chain1 results');
        $this->assert(isset($result['chain2_b']), 'ParallelChain merge includes chain2 results');
        $this->assert($result['chain1_a'] === 1, 'ParallelChain preserves chain1 values');
        $this->assert($result['chain2_b'] === 2, 'ParallelChain preserves chain2 values');

        // Test 'first' aggregation
        $firstParallel = ParallelChain::create()
            ->addChain('chain1', $chain1)
            ->addChain('chain2', $chain2)
            ->withAggregation('first');

        $firstResult = $firstParallel->invoke(['test' => 'value']);
        $this->assert(isset($firstResult['a']), 'ParallelChain first aggregation returns first result');

        // Test 'all' aggregation
        $allParallel = ParallelChain::create()
            ->addChain('chain1', $chain1)
            ->addChain('chain2', $chain2)
            ->withAggregation('all');

        $allResult = $allParallel->invoke(['test' => 'value']);
        $this->assert(isset($allResult['results']), 'ParallelChain all aggregation includes results key');
        $this->assert(isset($allResult['results']['chain1']), 'ParallelChain all includes chain1');
        $this->assert(isset($allResult['results']['chain2']), 'ParallelChain all includes chain2');
    }

    private function testRouterChain(): void
    {
        echo "\n--- Testing RouterChain ---\n";

        $codeChain = TransformChain::create(fn (array $input): array => ['type' => 'code']);
        $textChain = TransformChain::create(fn (array $input): array => ['type' => 'text']);
        $defaultChain = TransformChain::create(fn (array $input): array => ['type' => 'default']);

        $router = RouterChain::create()
            ->addRoute(fn (array $input): bool => $input['type'] === 'code', $codeChain)
            ->addRoute(fn (array $input): bool => $input['type'] === 'text', $textChain)
            ->setDefault($defaultChain);

        $codeResult = $router->invoke(['type' => 'code', 'content' => '<?php']);
        $this->assert($codeResult['type'] === 'code', 'RouterChain routes to code chain');

        $textResult = $router->invoke(['type' => 'text', 'content' => 'hello']);
        $this->assert($textResult['type'] === 'text', 'RouterChain routes to text chain');

        $defaultResult = $router->invoke(['type' => 'unknown']);
        $this->assert($defaultResult['type'] === 'default', 'RouterChain uses default chain');

        // Test metadata
        $input = ChainInput::create(['type' => 'code']);
        $output = $router->run($input);
        $metadata = $output->getMetadata();
        $this->assert(isset($metadata['route']), 'RouterChain includes route in metadata');
        $this->assert($metadata['type'] === 'matched', 'RouterChain metadata indicates match');
    }

    private function testChainComposition(): void
    {
        echo "\n--- Testing Chain Composition ---\n";

        // Create a complex nested chain
        $inner1 = TransformChain::create(fn (array $input): array => ['inner' => 'result1']);
        $inner2 = TransformChain::create(fn (array $input): array => ['inner' => 'result2']);

        $innerParallel = ParallelChain::create()
            ->addChain('inner1', $inner1)
            ->addChain('inner2', $inner2)
            ->withAggregation('merge');

        $preprocess = TransformChain::create(fn (array $input): array => ['preprocessed' => true]);
        $postprocess = TransformChain::create(fn (array $input): array => ['final' => 'done']);

        $outer = SequentialChain::create()
            ->addChain('preprocess', $preprocess)
            ->addChain('parallel', $innerParallel)
            ->addChain('postprocess', $postprocess);

        $result = $outer->invoke(['data' => 'test']);
        $this->assert(isset($result['preprocess']), 'Nested chain executes preprocess');
        $this->assert(isset($result['parallel']), 'Nested chain executes parallel');
        $this->assert(isset($result['postprocess']), 'Nested chain executes postprocess');
        $this->assert($result['preprocess']['preprocessed'] === true, 'Nested chain preprocess works');
        $this->assert($result['postprocess']['final'] === 'done', 'Nested chain postprocess works');
    }

    private function testErrorHandling(): void
    {
        echo "\n--- Testing Error Handling ---\n";

        // Test validation error
        $input = ChainInput::create(['name' => 'test']);

        try {
            $input->validate(['required' => ['name', 'missing']]);
            $this->assert(false, 'Should throw ChainValidationException');
        } catch (ChainValidationException $e) {
            $this->assert(true, 'ChainValidationException thrown for validation error');
        }

        // Test execution error
        $errorChain = TransformChain::create(
            fn (array $input): array => throw new \RuntimeException('Test error')
        );

        try {
            $errorChain->invoke(['test' => 'value']);
            $this->assert(false, 'Should throw ChainExecutionException');
        } catch (ChainExecutionException $e) {
            $this->assert(true, 'ChainExecutionException thrown for execution error');
        }

        // Test callback execution
        $beforeCalled = false;
        $afterCalled = false;
        $errorCalled = false;

        $chain = TransformChain::create(fn (array $input): array => ['result' => 'success'])
            ->onBefore(function (ChainInput $input) use (&$beforeCalled) {
                $beforeCalled = true;
            })
            ->onAfter(function (ChainInput $input, $output) use (&$afterCalled) {
                $afterCalled = true;
            })
            ->onError(function (ChainInput $input, \Throwable $error) use (&$errorCalled) {
                $errorCalled = true;
            });

        $chain->invoke(['test' => 'value']);
        $this->assert($beforeCalled, 'onBefore callback is called');
        $this->assert($afterCalled, 'onAfter callback is called');
        $this->assert(! $errorCalled, 'onError callback is not called on success');
    }

    private function printSummary(): void
    {
        echo "\n========================================\n";
        echo "Test Summary\n";
        echo "========================================\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo 'Total:  ' . ($this->passed + $this->failed) . "\n\n";

        if ($this->failed > 0) {
            echo "Failures:\n";
            foreach ($this->failures as $failure) {
                echo "  - $failure\n";
            }
            echo "\n";
            exit(1);
        }
        echo "All tests passed! ✓\n\n";
        exit(0);

    }
}

// Run tests
$runner = new TestRunner();
$runner->run();
