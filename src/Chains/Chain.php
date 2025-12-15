<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains;

use ClaudeAgents\Chains\Contracts\ChainInterface;
use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Chains\Exceptions\ChainValidationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for all chain implementations.
 *
 * Provides common functionality for input/output validation,
 * logging, and callback hooks.
 */
abstract class Chain implements ChainInterface
{
    /**
     * @var LoggerInterface|object
     */
    protected $logger;

    /**
     * @var callable|null
     */
    protected $onBefore = null;

    /**
     * @var callable|null
     */
    protected $onAfter = null;

    /**
     * @var callable|null
     */
    protected $onError = null;

    /**
     * @var array<string, mixed>
     */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [],
        'required' => [],
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $outputSchema = [
        'type' => 'object',
        'properties' => [],
    ];

    public function __construct(
        ?LoggerInterface $logger = null,
    ) {
        if ($logger === null) {
            // Try to use PSR-3 NullLogger, fallback to a simple implementation
            if (class_exists(\Psr\Log\NullLogger::class)) {
                $this->logger = new \Psr\Log\NullLogger();
            } else {
                // Fallback: create a simple logger that does nothing
                // Use object type instead of interface to avoid dependency
                $this->logger = new class () {
                    public function emergency($message, array $context = []): void
                    {
                    }

                    public function alert($message, array $context = []): void
                    {
                    }

                    public function critical($message, array $context = []): void
                    {
                    }

                    public function error($message, array $context = []): void
                    {
                    }

                    public function warning($message, array $context = []): void
                    {
                    }

                    public function notice($message, array $context = []): void
                    {
                    }

                    public function info($message, array $context = []): void
                    {
                    }

                    public function debug($message, array $context = []): void
                    {
                    }

                    public function log($level, $message, array $context = []): void
                    {
                    }
                };
            }
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * Set callback to run before chain execution.
     *
     * @param callable $callback fn(ChainInput $input): void
     */
    public function onBefore(callable $callback): self
    {
        $this->onBefore = $callback;

        return $this;
    }

    /**
     * Set callback to run after successful execution.
     *
     * @param callable $callback fn(ChainInput $input, ChainOutput $output): void
     */
    public function onAfter(callable $callback): self
    {
        $this->onAfter = $callback;

        return $this;
    }

    /**
     * Set callback to run on error.
     *
     * @param callable $callback fn(ChainInput $input, Throwable $error): void
     */
    public function onError(callable $callback): self
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * Set the logger.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    final public function run(ChainInput $input): ChainOutput
    {
        try {
            // Validate input
            $this->validateInput($input);

            // Call before hook
            if ($this->onBefore !== null) {
                ($this->onBefore)($input);
            }

            // Execute the chain
            $output = $this->execute($input);

            // Validate output
            $this->validateOutput($output);

            // Call after hook
            if ($this->onAfter !== null) {
                ($this->onAfter)($input, $output);
            }

            return $output;
        } catch (\Throwable $e) {
            // Call error hook
            if ($this->onError !== null) {
                ($this->onError)($input, $e);
            }

            $this->logger->error("Chain execution failed: {$e->getMessage()}");

            if ($e instanceof ChainExecutionException || $e instanceof ChainValidationException) {
                throw $e;
            }

            throw new ChainExecutionException(
                "Chain execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    final public function invoke(array $input): array
    {
        $chainInput = ChainInput::create($input);
        $output = $this->run($chainInput);

        return $output->all();
    }

    public function getInputSchema(): array
    {
        return $this->inputSchema;
    }

    public function getOutputSchema(): array
    {
        return $this->outputSchema;
    }

    public function validateInput(ChainInput $input): bool
    {
        try {
            return $input->validate($this->inputSchema);
        } catch (\Throwable $e) {
            throw new ChainValidationException(
                "Input validation failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Validate output against the output schema.
     *
     * @throws ChainValidationException
     */
    protected function validateOutput(ChainOutput $output): bool
    {
        // Basic schema validation - can be overridden by subclasses
        return true;
    }

    /**
     * Execute the chain logic. Must be implemented by subclasses.
     *
     * @throws ChainExecutionException
     */
    abstract protected function execute(ChainInput $input): ChainOutput;
}
