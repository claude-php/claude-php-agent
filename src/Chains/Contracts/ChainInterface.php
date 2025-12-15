<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains\Contracts;

use ClaudeAgents\Chains\ChainInput;
use ClaudeAgents\Chains\ChainOutput;

/**
 * Interface for all chain implementations.
 *
 * Chains are composable units that can transform inputs to outputs,
 * potentially calling LLMs or other tools in the process.
 */
interface ChainInterface
{
    /**
     * Run the chain with typed input/output objects.
     *
     * @param ChainInput $input The chain input
     * @throws \ClaudeAgents\Chains\Exceptions\ChainExecutionException
     * @return ChainOutput The chain output with data and metadata
     */
    public function run(ChainInput $input): ChainOutput;

    /**
     * Invoke the chain with a raw array, returning an array.
     *
     * This is the primary API for most use cases.
     *
     * @param array<string, mixed> $input Input data
     * @throws \ClaudeAgents\Chains\Exceptions\ChainExecutionException
     * @return array<string, mixed> Output data
     */
    public function invoke(array $input): array;

    /**
     * Get the input schema for validation.
     *
     * @return array<string, mixed> JSON schema for inputs
     */
    public function getInputSchema(): array;

    /**
     * Get the output schema.
     *
     * @return array<string, mixed> JSON schema for outputs
     */
    public function getOutputSchema(): array;

    /**
     * Validate input against the schema.
     *
     * @param ChainInput $input The input to validate
     * @throws \ClaudeAgents\Chains\Exceptions\ChainValidationException
     * @return bool True if valid
     */
    public function validateInput(ChainInput $input): bool;
}
