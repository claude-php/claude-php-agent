<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for loop strategies that support iteration and tool execution callbacks.
 *
 * Implementing this interface allows loop strategies to receive callbacks
 * for iteration events and tool executions, enabling better observability
 * and custom handling.
 */
interface CallbackSupportingLoopInterface extends LoopStrategyInterface
{
    /**
     * Set iteration callback.
     *
     * @param callable $callback fn(int $iteration, mixed $response, AgentContext $context)
     */
    public function onIteration(callable $callback): self;

    /**
     * Set tool execution callback.
     *
     * @param callable $callback fn(string $tool, array $input, ToolResultInterface $result)
     */
    public function onToolExecution(callable $callback): self;
}
