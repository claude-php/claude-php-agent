<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains;

use ClaudeAgents\Chains\Contracts\ChainInterface;
use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Exceptions\ConfigurationException;
use Psr\Log\LoggerInterface;

/**
 * Chain that executes multiple chains in parallel (or simulated parallel).
 *
 * Supports different aggregation strategies for combining results.
 */
class ParallelChain extends Chain
{
    /**
     * @var array<string, ChainInterface>
     */
    private array $chains = [];

    /**
     * Aggregation strategy: 'merge', 'first', 'all'
     */
    private string $aggregation = 'merge';

    /**
     * @var int Timeout in milliseconds
     */
    private int $timeout = 30000;

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    /**
     * Create a new parallel chain.
     */
    public static function create(?LoggerInterface $logger = null): self
    {
        return new self($logger);
    }

    /**
     * Add a chain to execute in parallel.
     *
     * @param string $name Name of the chain
     * @param ChainInterface $chain The chain to add
     */
    public function addChain(string $name, ChainInterface $chain): self
    {
        $this->chains[$name] = $chain;

        return $this;
    }

    /**
     * Set the aggregation strategy.
     *
     * - 'merge': Merge all results into a single array (default)
     * - 'first': Return results from the first successful chain
     * - 'all': Return all results as array keyed by chain name
     */
    public function withAggregation(string $strategy): self
    {
        if (! in_array($strategy, ['merge', 'first', 'all'])) {
            throw new ConfigurationException("Invalid aggregation strategy: {$strategy}", 'aggregation', $strategy);
        }
        $this->aggregation = $strategy;

        return $this;
    }

    /**
     * Set timeout for execution in milliseconds.
     */
    public function withTimeout(int $milliseconds): self
    {
        $this->timeout = $milliseconds;

        return $this;
    }

    protected function execute(ChainInput $input): ChainOutput
    {
        if (empty($this->chains)) {
            throw new ChainExecutionException('No chains added to ParallelChain');
        }

        $results = [];
        $metadata = [];
        $errors = [];

        try {
            // Execute all chains (simulated parallel)
            foreach ($this->chains as $name => $chain) {
                $this->logger->info("Executing parallel chain: {$name}");

                try {
                    $output = $chain->run($input);
                    $results[$name] = $output->all();
                    $metadata[$name] = $output->getMetadata();
                } catch (\Throwable $e) {
                    $this->logger->warning("Chain {$name} failed: {$e->getMessage()}");
                    $errors[$name] = $e->getMessage();

                    // For 'first' strategy, stop on first success
                    if ($this->aggregation === 'first') {
                        // Don't count this as a result, continue to next
                        continue;
                    }
                }
            }

            // Check if we have any results
            if (empty($results) && ! empty($errors)) {
                throw new ChainExecutionException(
                    'All parallel chains failed: ' . json_encode($errors)
                );
            }

            // Aggregate results based on strategy
            $aggregated = $this->aggregate($results, $errors);

            // Include metadata in the result data if there are errors
            // This allows tests/users to access error information easily
            if (! empty($errors)) {
                $aggregated['metadata'] = [
                    'errors' => $errors,
                ];
            }

            return ChainOutput::create($aggregated, [
                'chains' => array_keys($results),
                'errors' => $errors,
                'step_metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Parallel chain execution failed: {$e->getMessage()}");

            throw new ChainExecutionException(
                "Parallel chain execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Aggregate results based on strategy.
     *
     * @param array<string, mixed> $results
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function aggregate(array $results, array $errors): array
    {
        return match ($this->aggregation) {
            'merge' => $this->mergResults($results),
            'first' => $this->firstResult($results),
            'all' => $this->allResults($results, $errors),
            default => $results,
        };
    }

    /**
     * Merge all results into a single array.
     *
     * @param array<string, mixed> $results
     * @return array<string, mixed>
     */
    private function mergResults(array $results): array
    {
        $merged = [];

        foreach ($results as $chainName => $chainResult) {
            if (is_array($chainResult)) {
                // Merge each chain's results
                foreach ($chainResult as $key => $value) {
                    // Use chain_key format to avoid collisions
                    $merged["{$chainName}_{$key}"] = $value;
                }
            } else {
                $merged[$chainName] = $chainResult;
            }
        }

        return $merged;
    }

    /**
     * Return results from the first successful chain.
     *
     * @param array<string, mixed> $results
     * @return array<string, mixed>
     */
    private function firstResult(array $results): array
    {
        return reset($results) ?: [];
    }

    /**
     * Return all results keyed by chain name.
     *
     * @param array<string, mixed> $results
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function allResults(array $results, array $errors): array
    {
        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }
}
