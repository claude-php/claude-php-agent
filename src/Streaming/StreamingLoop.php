<?php

declare(strict_types=1);

namespace ClaudeAgents\Streaming;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Contracts\CallbackSupportingLoopInterface;
use ClaudeAgents\Contracts\StreamHandlerInterface;
use ClaudeAgents\Events\FlowEvent;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Loops\ToolExecutionTrait;
use ClaudeAgents\Tools\ToolResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * ReAct loop with streaming support.
 *
 * Extends the standard ReAct loop to support real-time token streaming
 * through configurable handlers.
 */
class StreamingLoop implements CallbackSupportingLoopInterface
{
    use ToolExecutionTrait;

    private LoggerInterface $logger;

    /**
     * @var array<StreamHandlerInterface>
     */
    private array $handlers = [];

    /**
     * @var callable|null
     */
    private $onIteration = null;

    /**
     * @var callable|null
     */
    private $onToolExecution = null;

    private ?FlowEventManager $flowEventManager = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Set the flow event manager for enhanced event tracking.
     *
     * @param FlowEventManager $eventManager Event manager instance
     * @return self
     */
    public function setFlowEventManager(FlowEventManager $eventManager): self
    {
        $this->flowEventManager = $eventManager;
        return $this;
    }

    /**
     * Add a stream event handler.
     */
    public function addHandler(StreamHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * Add a callback handler.
     *
     * @param callable $callback Function that receives StreamEvent
     */
    public function onStream(callable $callback): self
    {
        $this->handlers[] = new Handlers\CallbackHandler($callback);

        return $this;
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

            $this->logger->debug("Streaming ReAct loop iteration {$iteration}");

            // Emit iteration started event
            if ($this->flowEventManager) {
                $this->flowEventManager->emit(FlowEvent::ITERATION_STARTED, [
                    'iteration' => $iteration,
                ]);
            }

            try {
                // Build API request parameters
                $params = array_merge(
                    $config->toApiParams(),
                    [
                        'messages' => $context->getMessages(),
                        'tools' => $context->getToolDefinitions(),
                        'stream' => true, // Enable streaming
                    ]
                );

                // Create streaming buffer
                $buffer = new StreamBuffer();

                try {
                    // Call Claude API with streaming
                    $stream = $client->messages()->stream($params);

                    foreach ($stream as $event) {
                        $this->handleStreamEvent($event, $buffer, $context);
                    }

                    // Finalize the buffer
                    $buffer->finishBlock();

                    // Build response from buffer
                    $response = $this->buildResponse($buffer, $stream);
                } catch (\Exception $e) {
                    // Fallback to non-streaming if stream fails
                    $this->logger->warning('Streaming failed, falling back to non-streaming: ' . $e->getMessage());
                    $response = $client->messages()->create(array_filter($params, fn ($k) => $k !== 'stream', ARRAY_FILTER_USE_KEY));
                }

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

                // Emit iteration completed event
                if ($this->flowEventManager) {
                    $this->flowEventManager->emit(FlowEvent::ITERATION_COMPLETED, [
                        'iteration' => $iteration,
                        'tokens' => [
                            'input' => $response->usage->input_tokens ?? 0,
                            'output' => $response->usage->output_tokens ?? 0,
                        ],
                    ]);
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
                    $toolResults = $this->executeStreamingTools($context, $response->content);

                    if (! empty($toolResults)) {
                        $context->addMessage([
                            'role' => 'user',
                            'content' => $toolResults,
                        ]);
                    }
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
        return 'streaming';
    }

    /**
     * Handle a stream event.
     */
    private function handleStreamEvent(mixed $event, StreamBuffer $buffer, AgentContext $context): void
    {
        // Extract text from event if present
        if (is_object($event) && isset($event->delta) && isset($event->delta->text)) {
            $streamEvent = StreamEvent::delta($event->delta->text);
            $buffer->addText($event->delta->text);

            // Emit token event to flow event manager
            if ($this->flowEventManager) {
                $this->flowEventManager->emit(FlowEvent::TOKEN_RECEIVED, [
                    'token' => $event->delta->text,
                    'iteration' => $context->getIteration(),
                ]);
            }
        } else {
            $streamEvent = StreamEvent::text('');
        }

        // Fire handlers
        foreach ($this->handlers as $handler) {
            $handler->handle($streamEvent);
        }
    }

    /**
     * Build a complete response from the stream buffer.
     *
     * @param object|mixed $stream The stream object
     */
    private function buildResponse(StreamBuffer $buffer, mixed $stream): object
    {
        // Extract final message from stream if available
        $finalMessage = null;
        if (method_exists($stream, 'getFinalMessage')) {
            $finalMessage = $stream->getFinalMessage();
        } elseif (method_exists($stream, 'finalMessage')) {
            $finalMessage = $stream->finalMessage();
        }

        // If we have a final message from the stream, use it
        if ($finalMessage !== null && is_object($finalMessage)) {
            return $finalMessage;
        }

        // Fallback: create a response object from buffer
        return (object)[
            'content' => $buffer->getBlocks(),
            'stop_reason' => 'end_turn',
            'usage' => (object)[
                'input_tokens' => 0,
                'output_tokens' => 0,
            ],
        ];
    }

    /**
     * Execute tools from response content with FlowEvent tracking.
     *
     * @param AgentContext $context
     * @param array<mixed> $content Response content blocks
     * @return array<array<string, mixed>> Tool results for API
     */
    private function executeStreamingTools(AgentContext $context, array $content): array
    {
        $results = [];

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            if ($type !== 'tool_use') {
                continue;
            }

            $toolName = $block['name'] ?? '';
            $toolInput = $block['input'] ?? [];
            $toolId = $block['id'] ?? '';

            // Ensure toolInput is an array for tool execution
            // (may be stdClass after normalizeContentBlocks)
            if ($toolInput instanceof \stdClass) {
                $toolInput = (array) $toolInput;
            }

            $this->logger->debug("Executing tool: {$toolName}", ['input' => $toolInput]);

            // Emit tool execution started event
            if ($this->flowEventManager) {
                $this->flowEventManager->emit(FlowEvent::TOOL_EXECUTION_STARTED, [
                    'tool' => $toolName,
                    'input' => $toolInput,
                ]);
            }

            $tool = $context->getTool($toolName);

            if ($tool === null) {
                $result = ToolResult::error("Unknown tool: {$toolName}");
            } else {
                $result = $tool->execute($toolInput);
            }

            // Record the tool call
            $context->recordToolCall(
                $toolName,
                $toolInput,
                $result->getContent(),
                $result->isError()
            );

            // Fire tool execution callback
            if ($this->onToolExecution !== null) {
                ($this->onToolExecution)($toolName, $toolInput, $result);
            }

            // Emit tool execution completed event
            if ($this->flowEventManager) {
                $this->flowEventManager->emit(FlowEvent::TOOL_EXECUTION_COMPLETED, [
                    'tool' => $toolName,
                    'result' => $result->getContent(),
                    'is_error' => $result->isError(),
                ]);
            }

            $results[] = $result->toApiFormat($toolId);
        }

        return $results;
    }
}
