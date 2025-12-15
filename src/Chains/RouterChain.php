<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains;

use ClaudeAgents\Chains\Contracts\ChainInterface;
use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use Psr\Log\LoggerInterface;

/**
 * Chain that routes to different chains based on input conditions.
 *
 * Evaluates conditions in order and executes the first matching chain.
 */
class RouterChain extends Chain
{
    /**
     * @var array<array{condition: callable, chain: ChainInterface}>
     */
    private array $routes = [];

    /**
     * @var ChainInterface|null
     */
    private ?ChainInterface $defaultChain = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    /**
     * Create a new router chain.
     */
    public static function create(?LoggerInterface $logger = null): self
    {
        return new self($logger);
    }

    /**
     * Add a route condition and chain.
     *
     * Routes are evaluated in the order they were added. The first
     * condition that returns true will be executed.
     *
     * @param callable $condition fn(array $input): bool
     * @param ChainInterface $chain Chain to execute if condition matches
     */
    public function addRoute(callable $condition, ChainInterface $chain): self
    {
        $this->routes[] = [
            'condition' => $condition,
            'chain' => $chain,
        ];

        return $this;
    }

    /**
     * Set the default chain to execute if no routes match.
     */
    public function setDefault(ChainInterface $chain): self
    {
        $this->defaultChain = $chain;

        return $this;
    }

    protected function execute(ChainInput $input): ChainOutput
    {
        if (empty($this->routes) && $this->defaultChain === null) {
            throw new ChainExecutionException('RouterChain has no routes and no default chain');
        }

        $inputData = $input->all();

        try {
            // Evaluate routes in order
            foreach ($this->routes as $index => $route) {
                $condition = $route['condition'];
                $chain = $route['chain'];

                try {
                    $matches = $condition($inputData);

                    if ($matches) {
                        $this->logger->info("Router matched route {$index}");

                        $output = $chain->run($input);

                        return ChainOutput::create(
                            $output->all(),
                            array_merge(
                                $output->getMetadata(),
                                ['route' => $index, 'type' => 'matched']
                            )
                        );
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning("Route {$index} condition evaluation failed: {$e->getMessage()}");

                    // Continue to next route on condition error
                    continue;
                }
            }

            // No routes matched, try default
            if ($this->defaultChain !== null) {
                $this->logger->info('No routes matched, using default chain');

                $output = $this->defaultChain->run($input);

                return ChainOutput::create(
                    $output->all(),
                    array_merge(
                        $output->getMetadata(),
                        ['route' => 'default', 'type' => 'default']
                    )
                );
            }

            throw new ChainExecutionException('No routes matched and no default chain configured');
        } catch (\Throwable $e) {
            $this->logger->error("Router chain execution failed: {$e->getMessage()}");

            throw new ChainExecutionException(
                "Router chain execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
