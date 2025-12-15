<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Chains;

use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Chains\RouterChain;
use ClaudeAgents\Chains\TransformChain;
use PHPUnit\Framework\TestCase;

class RouterChainTest extends TestCase
{
    public function testRouteToFirstMatchingChain(): void
    {
        $codeChain = TransformChain::create(fn (array $input): array => [
            'type' => 'code',
            'result' => 'code processed',
        ]);

        $textChain = TransformChain::create(fn (array $input): array => [
            'type' => 'text',
            'result' => 'text processed',
        ]);

        $router = RouterChain::create()
            ->addRoute(fn (array $input): bool => $input['type'] === 'code', $codeChain)
            ->addRoute(fn (array $input): bool => $input['type'] === 'text', $textChain);

        $result = $router->invoke(['type' => 'code', 'content' => '<?php echo "test";']);

        $this->assertEquals('code', $result['type']);
        $this->assertEquals('code processed', $result['result']);
    }

    public function testDefaultChain(): void
    {
        $codeChain = TransformChain::create(fn (array $input): array => [
            'type' => 'code',
        ]);

        $defaultChain = TransformChain::create(fn (array $input): array => [
            'type' => 'default',
            'result' => 'default processed',
        ]);

        $router = RouterChain::create()
            ->addRoute(fn (array $input): bool => $input['type'] === 'code', $codeChain)
            ->setDefault($defaultChain);

        $result = $router->invoke(['type' => 'unknown', 'content' => 'test']);

        $this->assertEquals('default', $result['type']);
        $this->assertEquals('default processed', $result['result']);
    }

    public function testRouteMetadata(): void
    {
        $chain1 = TransformChain::create(fn (array $input): array => ['data' => 'result1']);
        $chain2 = TransformChain::create(fn (array $input): array => ['data' => 'result2']);

        $router = RouterChain::create()
            ->addRoute(fn (array $input): bool => $input['route'] === 1, $chain1)
            ->addRoute(fn (array $input): bool => $input['route'] === 2, $chain2)
            ->setDefault($chain1);

        $input = ['route' => 1, 'data' => 'test'];
        $output = $router->run(\ClaudeAgents\Chains\ChainInput::create($input));

        $metadata = $output->getMetadata();
        $this->assertEquals(0, $metadata['route']); // First route (index 0)
        $this->assertEquals('matched', $metadata['type']);
    }

    public function testDefaultRouteMetadata(): void
    {
        $defaultChain = TransformChain::create(fn (array $input): array => ['data' => 'default']);

        $router = RouterChain::create()
            ->addRoute(fn (array $input): bool => false, $defaultChain)
            ->setDefault($defaultChain);

        $output = $router->run(\ClaudeAgents\Chains\ChainInput::create(['test' => 'value']));

        $metadata = $output->getMetadata();
        $this->assertEquals('default', $metadata['route']);
        $this->assertEquals('default', $metadata['type']);
    }

    public function testNoRoutesAndNoDefault(): void
    {
        $router = RouterChain::create();

        $this->expectException(ChainExecutionException::class);
        $this->expectExceptionMessage('RouterChain has no routes and no default chain');

        $router->invoke(['test' => 'value']);
    }

    public function testNoMatchingRouteAndNoDefault(): void
    {
        $chain = TransformChain::create(fn (array $input): array => ['data' => 'test']);

        $router = RouterChain::create()
            ->addRoute(fn (array $input): bool => false, $chain);

        $this->expectException(ChainExecutionException::class);
        $this->expectExceptionMessage('No routes matched and no default chain configured');

        $router->invoke(['test' => 'value']);
    }

    public function testConditionErrorHandling(): void
    {
        $chain = TransformChain::create(fn (array $input): array => ['data' => 'test']);

        $router = RouterChain::create()
            ->addRoute(function (array $input): bool {
                throw new \RuntimeException('Condition error');
            }, $chain)
            ->setDefault($chain);

        // Should fall back to default when condition throws
        $result = $router->invoke(['test' => 'value']);

        $this->assertArrayHasKey('data', $result);
    }

    public function testMultipleRoutesFirstMatchWins(): void
    {
        $chain1 = TransformChain::create(fn (array $input): array => ['route' => 1]);
        $chain2 = TransformChain::create(fn (array $input): array => ['route' => 2]);

        $router = RouterChain::create()
            ->addRoute(fn (array $input): bool => $input['value'] > 0, $chain1)
            ->addRoute(fn (array $input): bool => $input['value'] > 5, $chain2);

        // First route matches, second should not be evaluated
        $result = $router->invoke(['value' => 3]);

        $this->assertEquals(1, $result['route']);
    }
}
