<?php

declare(strict_types=1);

namespace ClaudeAgents\Execution;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\FlowExecutorInterface;
use ClaudeAgents\Contracts\StreamableAgentInterface;
use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEvent;
use ClaudeAgents\Events\FlowEventManager;
use ClaudeAgents\Services\ServiceInterface;
use Generator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Generator-based streaming flow executor inspired by Langflow.
 *
 * Provides real-time streaming execution with:
 * - Token-by-token LLM responses
 * - Progress tracking and events
 * - Queue-based event handling
 * - SSE-ready output format
 *
 * Adapts Python's async/await patterns to PHP using Generators.
 *
 * @example
 * ```php
 * $executor = new StreamingFlowExecutor($eventManager, $eventQueue);
 * $agent = new ReactAgent($client, $config);
 *
 * foreach ($executor->executeWithStreaming($agent, "Task") as $event) {
 *     if ($event['type'] === 'token') {
 *         echo $event['data']['token'];
 *     }
 * }
 * ```
 */
class StreamingFlowExecutor implements FlowExecutorInterface, ServiceInterface
{
    private FlowEventManager $eventManager;
    private EventQueue $eventQueue;
    private LoggerInterface $logger;
    private bool $isRunning = false;
    private ?FlowProgress $currentProgress = null;
    private bool $initialized = false;

    /**
     * @param FlowEventManager $eventManager Event manager for emission
     * @param EventQueue $eventQueue Event queue for streaming
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(
        FlowEventManager $eventManager,
        EventQueue $eventQueue,
        ?LoggerInterface $logger = null
    ) {
        $this->eventManager = $eventManager;
        $this->eventQueue = $eventQueue;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Execute agent with streaming support.
     *
     * @param AgentInterface $agent Agent to execute
     * @param string $input Task or prompt
     * @param array<string, mixed> $options Execution options:
     *   - 'max_iterations': Maximum iterations (default: from agent config)
     *   - 'track_progress': Enable progress tracking (default: true)
     *   - 'stream_tokens': Stream individual tokens (default: true)
     * @return Generator<int, array{type: string, data: mixed}>
     */
    public function executeWithStreaming(
        AgentInterface $agent,
        string $input,
        array $options = []
    ): Generator {
        $this->isRunning = true;
        $trackProgress = $options['track_progress'] ?? true;
        $maxIterations = $options['max_iterations'] ?? 20;

        // Initialize progress tracking
        if ($trackProgress) {
            $this->currentProgress = new FlowProgress($maxIterations, [
                'agent' => $agent->getName(),
                'input' => $input,
            ]);
            $this->currentProgress->start();
        }

        try {
            // Emit flow started event
            $this->eventManager->emit(FlowEvent::FLOW_STARTED, [
                'agent' => $agent->getName(),
                'input' => $input,
                'options' => $options,
            ]);

            yield ['type' => 'flow_started', 'data' => ['agent' => $agent->getName()]];

            // Check if agent supports streaming
            if ($agent instanceof StreamableAgentInterface && $agent->supportsStreaming()) {
                yield from $this->executeStreamableAgent($agent, $input, $options);
            } else {
                yield from $this->executeStandardAgent($agent, $input, $options);
            }

            // Emit flow completed event
            $this->eventManager->emit(FlowEvent::FLOW_COMPLETED, [
                'agent' => $agent->getName(),
                'duration' => $this->currentProgress?->getDuration(),
            ]);

            if ($this->currentProgress) {
                $this->currentProgress->complete();
                yield ['type' => 'progress', 'data' => $this->currentProgress->toArray()];
            }

            yield ['type' => 'end', 'data' => ['status' => 'completed']];

        } catch (Throwable $e) {
            $this->logger->error("Flow execution failed: {$e->getMessage()}");

            $this->eventManager->emit(FlowEvent::FLOW_FAILED, [
                'error' => $e->getMessage(),
                'agent' => $agent->getName(),
            ]);

            yield ['type' => 'error', 'data' => [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]];

            throw $e;
        } finally {
            $this->isRunning = false;
            $this->currentProgress = null;
        }
    }

    /**
     * Execute agent without streaming (blocking).
     *
     * @param AgentInterface $agent Agent to execute
     * @param string $input Task or prompt
     * @param array<string, mixed> $options Execution options
     * @return AgentResult
     */
    public function execute(
        AgentInterface $agent,
        string $input,
        array $options = []
    ): AgentResult {
        // Collect all events from streaming execution
        $events = [];
        foreach ($this->executeWithStreaming($agent, $input, $options) as $event) {
            $events[] = $event;
        }

        // Return the agent result directly
        return $agent->run($input);
    }

    /**
     * Check if executor is running.
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Get executor name.
     */
    public function getName(): string
    {
        return 'streaming_flow_executor';
    }

    /**
     * Get current progress if available.
     *
     * @return array<string, mixed>|null
     */
    public function getCurrentProgress(): ?array
    {
        return $this->currentProgress?->toArray();
    }

    /**
     * Execute a streamable agent with token streaming.
     *
     * @param StreamableAgentInterface $agent
     * @param string $input
     * @param array<string, mixed> $options
     * @return Generator<int, array{type: string, data: mixed}>
     */
    private function executeStreamableAgent(
        StreamableAgentInterface $agent,
        string $input,
        array $options
    ): Generator {
        $agent->setFlowEventManager($this->eventManager);

        $iteration = 0;
        $fullResponse = '';

        // Stream tokens from the agent
        foreach ($agent->runStreaming($input) as $token) {
            $fullResponse .= $token;

            // Emit token event
            $this->eventManager->emit(FlowEvent::TOKEN_RECEIVED, [
                'token' => $token,
                'iteration' => $iteration,
            ]);

            yield ['type' => 'token', 'data' => ['token' => $token]];

            // Yield progress periodically
            if ($this->currentProgress && strlen($fullResponse) % 50 === 0) {
                yield ['type' => 'progress', 'data' => $this->currentProgress->toArray()];
            }
        }

        // Get agent progress if available
        if ($this->currentProgress) {
            $agentProgress = $agent->getProgress();
            if ($agentProgress) {
                foreach ($agentProgress as $key => $value) {
                    $this->currentProgress->setMetadata($key, $value);
                }
            }
        }
    }

    /**
     * Execute a standard agent and stream events from queue.
     *
     * @param AgentInterface $agent
     * @param string $input
     * @param array<string, mixed> $options
     * @return Generator<int, array{type: string, data: mixed}>
     */
    private function executeStandardAgent(
        AgentInterface $agent,
        string $input,
        array $options
    ): Generator {
        // Start agent execution in a separate process conceptually
        // Since PHP doesn't have native async, we run it and then stream queued events
        
        $result = $agent->run($input);

        // Stream all queued events
        while (!$this->eventQueue->isEmpty()) {
            $event = $this->eventQueue->dequeue();

            if ($event === null) {
                break;
            }

            // Convert FlowEvent to output format
            $eventData = $this->convertEventToOutput($event);

            yield $eventData;

            // Update progress for certain events
            if ($this->currentProgress && $event->type === FlowEvent::ITERATION_COMPLETED) {
                $iteration = $event->data['iteration'] ?? 0;
                $this->currentProgress->startIteration($iteration);
                yield ['type' => 'progress', 'data' => $this->currentProgress->toArray()];
            }
        }

        // Yield final result
        yield ['type' => 'result', 'data' => [
            'output' => $result->getAnswer(),
            'success' => $result->isSuccess(),
            'iterations' => $result->getIterations(),
        ]];
    }

    /**
     * Convert FlowEvent to output format.
     *
     * @param FlowEvent $event
     * @return array{type: string, data: mixed}
     */
    private function convertEventToOutput(FlowEvent $event): array
    {
        return match ($event->type) {
            FlowEvent::TOKEN_RECEIVED => ['type' => 'token', 'data' => $event->data],
            FlowEvent::ITERATION_STARTED => ['type' => 'iteration_start', 'data' => $event->data],
            FlowEvent::ITERATION_COMPLETED => ['type' => 'iteration_end', 'data' => $event->data],
            FlowEvent::TOOL_EXECUTION_STARTED => ['type' => 'tool_start', 'data' => $event->data],
            FlowEvent::TOOL_EXECUTION_COMPLETED => ['type' => 'tool_end', 'data' => $event->data],
            FlowEvent::ERROR => ['type' => 'error', 'data' => $event->data],
            FlowEvent::PROGRESS_UPDATE => ['type' => 'progress', 'data' => $event->data],
            default => ['type' => 'event', 'data' => $event->toArray()],
        };
    }

    /**
     * Stream events to SSE format.
     *
     * @param AgentInterface $agent
     * @param string $input
     * @param array<string, mixed> $options
     * @return Generator<int, string> SSE-formatted events
     */
    public function streamSSE(
        AgentInterface $agent,
        string $input,
        array $options = []
    ): Generator {
        foreach ($this->executeWithStreaming($agent, $input, $options) as $event) {
            $flowEvent = new FlowEvent(
                type: $event['type'],
                data: $event['data'],
                timestamp: microtime(true)
            );

            yield $flowEvent->toSSE();
        }
    }

    /**
     * Initialize service (ServiceInterface implementation).
     */
    public function initialize(): void
    {
        if (!$this->initialized) {
            $this->logger->debug('StreamingFlowExecutor initialized');
            $this->initialized = true;
        }
    }

    /**
     * Teardown service (ServiceInterface implementation).
     */
    public function teardown(): void
    {
        $this->isRunning = false;
        $this->currentProgress = null;
        $this->initialized = false;
        $this->logger->debug('StreamingFlowExecutor torn down');
    }

    /**
     * Check if service is ready (ServiceInterface implementation).
     */
    public function isReady(): bool
    {
        return $this->initialized;
    }

    /**
     * Get service schema (ServiceInterface implementation).
     *
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'type' => 'flow_executor',
            'is_running' => $this->isRunning,
            'supports_streaming' => true,
            'supports_sse' => true,
            'features' => [
                'token_streaming',
                'progress_tracking',
                'event_emission',
                'multiple_listeners',
            ],
        ];
    }
}
