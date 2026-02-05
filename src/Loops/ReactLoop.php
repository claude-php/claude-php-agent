<?php

declare(strict_types=1);

namespace ClaudeAgents\Loops;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Contracts\CallbackSupportingLoopInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * ReAct (Reason-Act-Observe) loop implementation.
 *
 * This is the core agentic loop that:
 * 1. Sends the current state to Claude
 * 2. Receives a response (text or tool use)
 * 3. If tool use, executes tools and adds results
 * 4. Repeats until task is complete or max iterations reached
 */
class ReactLoop implements CallbackSupportingLoopInterface
{
    use ToolExecutionTrait;

    private LoggerInterface $logger;

    /**
     * @var callable|null
     */
    private $onIteration = null;

    /**
     * @var callable|null
     */
    private $onToolExecution = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Set iteration callback.
     *
     * @param callable $callback fn(int $iteration, mixed $response, AgentContext $context)
     */
    public function onIteration(callable $callback): self
    {
        $this->onIteration = $callback;

        return $this;
    }

    /**
     * Set tool execution callback.
     *
     * @param callable $callback fn(string $tool, array $input, ToolResult $result)
     */
    public function onToolExecution(callable $callback): self
    {
        $this->onToolExecution = $callback;

        return $this;
    }

    public function execute(AgentContext $context): AgentContext
    {
        $config = $context->getConfig();
        $client = $context->getClient();

        while (! $context->isCompleted() && ! $context->hasReachedMaxIterations()) {
            $context->incrementIteration();
            $iteration = $context->getIteration();

            $this->logger->debug("ReAct loop iteration {$iteration}");

            try {
                // Build API request parameters
                $params = array_merge(
                    $config->toApiParams(),
                    [
                        'messages' => $context->getMessages(),
                        'tools' => $context->getToolDefinitions(),
                    ]
                );

                // Call Claude API
                $response = $client->messages()->create($params);

                // Track token usage
                if (isset($response->usage)) {
                    $context->addTokenUsage(
                        $response->usage->input_tokens ?? 0,
                        $response->usage->output_tokens ?? 0
                    );
                }

                // Fire iteration callback
                if ($this->onIteration !== null) {
                    ($this->onIteration)($iteration, $response, $context);
                }

                // Add assistant response to messages with normalized content
                // to ensure tool_use.input encodes as {} not []
                $context->addMessage([
                    'role' => 'assistant',
                    'content' => $this->normalizeContentBlocks($response->content),
                ]);

                // Check stop reason
                $stopReason = $response->stop_reason ?? 'end_turn';

                if ($stopReason === 'end_turn') {
                    // Extract final answer from text blocks
                    $answer = $this->extractTextContent($response->content);
                    $context->complete($answer);
                    $this->logger->info("Agent completed in {$iteration} iterations");

                    break;
                }

                if ($stopReason === 'tool_use') {
                    // Execute tools and add results
                    // ALWAYS add tool_results message - API requires it after tool_use
                    $toolResults = $this->executeTools($context, $response->content);

                    $context->addMessage([
                        'role' => 'user',
                        'content' => $toolResults,
                    ]);
                } else {
                    $this->logger->warning("Unexpected stop reason: {$stopReason}");
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error in iteration {$iteration}: {$e->getMessage()}");
                $context->fail($e->getMessage());

                break;
            }
        }

        // Check if we hit max iterations without completing
        if (! $context->isCompleted() && $context->hasReachedMaxIterations()) {
            $maxIter = $config->getMaxIterations();
            $context->fail("Maximum iterations ({$maxIter}) reached without completion");
            $this->logger->warning('Max iterations reached');
        }

        return $context;
    }

    public function getName(): string
    {
        return 'react';
    }
}
