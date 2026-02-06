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

                // CRITICAL: Always check content for tool_use blocks regardless of
                // stop_reason. The API requires every tool_use to have a corresponding
                // tool_result in the next message. Relying solely on stop_reason
                // breaks when stop_reason is 'max_tokens' but the content still
                // contains complete tool_use blocks that need tool_result responses.
                $hasToolUse = $this->contentHasToolUse($response->content);

                if ($hasToolUse) {
                    // Execute tools and add results — required by the API for
                    // every tool_use block, no matter what the stop_reason is.
                    $toolResults = $this->executeTools($context, $response->content);

                    $context->addMessage([
                        'role' => 'user',
                        'content' => $toolResults,
                    ]);
                } elseif ($stopReason === 'end_turn') {
                    // No tool use — extract final answer from text blocks
                    $answer = $this->extractTextContent($response->content);
                    $context->complete($answer);
                    $this->logger->info("Agent completed in {$iteration} iterations");

                    break;
                } else {
                    // No tool use and not end_turn (e.g. max_tokens, stop_sequence).
                    // Continue the loop so the model can finish its response.
                    $this->logger->warning("Stop reason '{$stopReason}' in iteration {$iteration}, continuing");
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
