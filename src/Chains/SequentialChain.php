<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains;

use ClaudeAgents\Chains\Contracts\ChainInterface;
use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use Psr\Log\LoggerInterface;

/**
 * Chain that executes multiple chains in sequence.
 *
 * The output of one chain can be mapped as input to the next chain.
 */
class SequentialChain extends Chain
{
    /**
     * @var array<string, ChainInterface>
     */
    private array $chains = [];

    /**
     * @var array<array{from: string, fromKey: string, to: string, toKey: string}>
     */
    private array $mappings = [];

    /**
     * @var array<string, callable>|null
     */
    private ?array $conditions = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
    }

    /**
     * Create a new sequential chain.
     */
    public static function create(?LoggerInterface $logger = null): self
    {
        return new self($logger);
    }

    /**
     * Add a chain to the sequence.
     *
     * @param string $name Name of the step
     * @param ChainInterface $chain The chain to add
     */
    public function addChain(string $name, ChainInterface $chain): self
    {
        $this->chains[$name] = $chain;

        return $this;
    }

    /**
     * Map output from one chain to input of another.
     *
     * @param string $fromChain Source chain name
     * @param string $fromKey Source output key
     * @param string $toChain Target chain name
     * @param string $toKey Target input key
     */
    public function mapOutput(string $fromChain, string $fromKey, string $toChain, string $toKey): self
    {
        $this->mappings[] = [
            'from' => $fromChain,
            'fromKey' => $fromKey,
            'to' => $toChain,
            'toKey' => $toKey,
        ];

        return $this;
    }

    /**
     * Set a condition for executing a chain.
     *
     * @param string $chainName Name of the chain
     * @param callable $condition fn(array $allResults): bool
     */
    public function setCondition(string $chainName, callable $condition): self
    {
        if ($this->conditions === null) {
            $this->conditions = [];
        }
        $this->conditions[$chainName] = $condition;

        return $this;
    }

    protected function execute(ChainInput $input): ChainOutput
    {
        $allResults = $input->all();  // Start with original input
        $structuredResults = [];  // For final structured output
        $allMetadata = [];

        try {
            foreach ($this->chains as $chainName => $chain) {
                $this->logger->info("Executing chain step: {$chainName}");

                // Check condition if set
                if ($this->conditions !== null && isset($this->conditions[$chainName])) {
                    $condition = $this->conditions[$chainName];
                    // Pass structured results for condition check
                    $conditionInput = array_merge(['_input' => $input->all()], $structuredResults);
                    if (! $condition($conditionInput)) {
                        $this->logger->info("Skipping chain {$chainName} due to condition");

                        continue;
                    }
                }

                // Apply mappings that target this chain BEFORE execution
                $allResults = $this->applyInputMappings($chainName, $allResults, $structuredResults);

                // Prepare input for this chain - use accumulated flattened results
                $chainInput = ChainInput::create($allResults);

                // Execute the chain
                $output = $chain->run($chainInput);

                // Store structured results (for final output)
                $structuredResults[$chainName] = $output->all();
                $allMetadata[$chainName] = $output->getMetadata();

                // Merge outputs into flattened results for next chain
                // This allows next chain to access previous outputs directly
                $allResults = array_merge($allResults, $output->all());
            }

            // Apply output mappings to prepare final structured output
            $finalOutput = $this->applyOutputMappings($structuredResults);

            // If no chains were executed, return the original input
            if (empty($structuredResults)) {
                $finalOutput = $input->all();
            }

            return ChainOutput::create($finalOutput, ['steps' => $allMetadata]);
        } catch (\Throwable $e) {
            $this->logger->error("Sequential chain execution failed: {$e->getMessage()}");

            throw new ChainExecutionException(
                "Sequential chain execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Apply input mappings for a specific chain before it executes.
     *
     * @param string $chainName
     * @param array<string, mixed> $currentInput
     * @param array<string, mixed> $structuredResults
     * @return array<string, mixed>
     */
    private function applyInputMappings(string $chainName, array $currentInput, array $structuredResults): array
    {
        foreach ($this->mappings as $mapping) {
            // Only apply mappings that target this chain
            if ($mapping['to'] !== $chainName) {
                continue;
            }

            $fromChain = $mapping['from'];
            $fromKey = $mapping['fromKey'];
            $toKey = $mapping['toKey'];

            // Get the value from source chain's structured results
            if (isset($structuredResults[$fromChain][$fromKey])) {
                $value = $structuredResults[$fromChain][$fromKey];
                // Add to current input so this chain can access it
                $currentInput[$toKey] = $value;
            }
        }

        return $currentInput;
    }

    /**
     * Apply output mappings to restructure final results.
     *
     * @param array<string, mixed> $results
     * @return array<string, mixed>
     */
    private function applyOutputMappings(array $results): array
    {
        $output = $results;

        foreach ($this->mappings as $mapping) {
            $fromChain = $mapping['from'];
            $fromKey = $mapping['fromKey'];
            $toChain = $mapping['to'];
            $toKey = $mapping['toKey'];

            // Get the value from source
            if (isset($results[$fromChain][$fromKey])) {
                $value = $results[$fromChain][$fromKey];

                // Ensure target chain output exists
                if (! isset($output[$toChain])) {
                    $output[$toChain] = [];
                }

                // Set the mapped value in final output
                $output[$toChain][$toKey] = $value;
            }
        }

        return $output;
    }
}
