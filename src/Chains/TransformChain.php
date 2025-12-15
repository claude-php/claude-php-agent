<?php

declare(strict_types=1);

namespace ClaudeAgents\Chains;

use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use Psr\Log\LoggerInterface;

/**
 * Chain that transforms input data to output data.
 *
 * Useful for reshaping data between chain steps or for simple transformations.
 */
class TransformChain extends Chain
{
    /**
     * @var callable
     */
    private $transformer;

    /**
     * @param callable $transformer fn(array $input): array
     */
    public function __construct(
        callable $transformer,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
        $this->transformer = $transformer;
    }

    /**
     * Create a new transform chain.
     *
     * @param callable $transformer fn(array $input): array
     */
    public static function create(callable $transformer, ?LoggerInterface $logger = null): self
    {
        return new self($transformer, $logger);
    }

    protected function execute(ChainInput $input): ChainOutput
    {
        try {
            $this->logger->info('Executing transform chain');

            $transformer = $this->transformer;
            $result = $transformer($input->all());

            if (! is_array($result)) {
                throw new ChainExecutionException(
                    'Transform function must return an array, got ' . gettype($result)
                );
            }

            return ChainOutput::create($result, [
                'transformed' => true,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Transform execution failed: {$e->getMessage()}");

            throw new ChainExecutionException(
                "Transform execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
