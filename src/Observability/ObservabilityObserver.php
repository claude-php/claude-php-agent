<?php

declare(strict_types=1);

namespace ClaudeAgents\Observability;

use ClaudeAgents\Contracts\ObserverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Observer that automatically tracks observability metrics for agent events.
 *
 * Integrates Tracer, Metrics, and CostEstimator to provide comprehensive
 * observability for agent execution.
 */
class ObservabilityObserver implements ObserverInterface
{
    private Tracer $tracer;
    private Metrics $metrics;
    private CostEstimator $costEstimator;
    private LoggerInterface $logger;

    /**
     * @var array<string, Span> Active spans by event ID
     */
    private array $activeSpans = [];

    /**
     * @var array<string, float> Accumulated costs by trace/session
     */
    private array $costs = [];

    public function __construct(
        ?Tracer $tracer = null,
        ?Metrics $metrics = null,
        ?CostEstimator $costEstimator = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->tracer = $tracer ?? new Tracer();
        $this->metrics = $metrics ?? new Metrics();
        $this->costEstimator = $costEstimator ?? new CostEstimator();
        $this->logger = $logger ?? new NullLogger();
    }

    public function getId(): string
    {
        return 'observability_observer';
    }

    /**
     * @return array<string>
     */
    public function getEvents(): array
    {
        return [
            'agent.start',
            'agent.complete',
            'agent.error',
            'iteration.start',
            'iteration.complete',
            'tool.execute.start',
            'tool.execute.complete',
            'tool.execute.error',
            'llm.request.start',
            'llm.request.complete',
            'llm.request.error',
            'chain.start',
            'chain.complete',
            'chain.error',
        ];
    }

    public function update(string $event, array $data = []): void
    {
        match ($event) {
            'agent.start' => $this->handleAgentStart($data),
            'agent.complete' => $this->handleAgentComplete($data),
            'agent.error' => $this->handleAgentError($data),
            'iteration.start' => $this->handleIterationStart($data),
            'iteration.complete' => $this->handleIterationComplete($data),
            'tool.execute.start' => $this->handleToolStart($data),
            'tool.execute.complete' => $this->handleToolComplete($data),
            'tool.execute.error' => $this->handleToolError($data),
            'llm.request.start' => $this->handleLLMStart($data),
            'llm.request.complete' => $this->handleLLMComplete($data),
            'llm.request.error' => $this->handleLLMError($data),
            'chain.start' => $this->handleChainStart($data),
            'chain.complete' => $this->handleChainComplete($data),
            'chain.error' => $this->handleChainError($data),
            default => null,
        };
    }

    private function handleAgentStart(array $data): void
    {
        $agentName = $data['agent_name'] ?? 'unknown';
        $span = $this->tracer->startSpan("agent.{$agentName}", [
            'agent.name' => $agentName,
            'task' => $data['task'] ?? '',
            'tools' => $data['tools'] ?? [],
        ]);

        $this->activeSpans['agent'] = $span;
        $this->logger->debug("Agent started: {$agentName}");
    }

    private function handleAgentComplete(array $data): void
    {
        if (isset($this->activeSpans['agent'])) {
            $this->tracer->endSpan($this->activeSpans['agent']);
            unset($this->activeSpans['agent']);

            $this->logger->info('Agent completed', [
                'iterations' => $data['iterations'] ?? 0,
                'success' => $data['success'] ?? true,
            ]);
        }
    }

    private function handleAgentError(array $data): void
    {
        if (isset($this->activeSpans['agent'])) {
            $span = $this->activeSpans['agent'];
            $span->setStatus('ERROR');
            $span->addEvent('error', [
                'error.message' => $data['error'] ?? 'Unknown error',
                'error.type' => $data['error_type'] ?? 'Exception',
            ]);
            $this->tracer->endSpan($span);
            unset($this->activeSpans['agent']);
        }

        $this->logger->error('Agent error', [
            'error' => $data['error'] ?? 'Unknown error',
        ]);
    }

    private function handleIterationStart(array $data): void
    {
        $iteration = $data['iteration'] ?? 0;
        $span = $this->tracer->startSpan("iteration.{$iteration}", [
            'iteration.number' => $iteration,
        ], $this->activeSpans['agent'] ?? null);

        $this->activeSpans["iteration.{$iteration}"] = $span;
    }

    private function handleIterationComplete(array $data): void
    {
        $iteration = $data['iteration'] ?? 0;
        $spanKey = "iteration.{$iteration}";

        if (isset($this->activeSpans[$spanKey])) {
            $this->tracer->endSpan($this->activeSpans[$spanKey]);
            unset($this->activeSpans[$spanKey]);
        }
    }

    private function handleToolStart(array $data): void
    {
        $toolName = $data['tool'] ?? 'unknown';
        $toolId = $data['tool_id'] ?? uniqid('tool_');

        $span = $this->tracer->startSpan("tool.{$toolName}", [
            'tool.name' => $toolName,
            'tool.input' => $data['input'] ?? [],
        ], $this->getCurrentIterationSpan());

        $this->activeSpans[$toolId] = $span;
    }

    private function handleToolComplete(array $data): void
    {
        $toolId = $data['tool_id'] ?? null;

        if ($toolId && isset($this->activeSpans[$toolId])) {
            $span = $this->activeSpans[$toolId];
            $span->setStatus('OK');
            $this->tracer->endSpan($span);
            unset($this->activeSpans[$toolId]);
        }
    }

    private function handleToolError(array $data): void
    {
        $toolId = $data['tool_id'] ?? null;

        if ($toolId && isset($this->activeSpans[$toolId])) {
            $span = $this->activeSpans[$toolId];
            $span->setStatus('ERROR');
            $span->addEvent('error', [
                'error.message' => $data['error'] ?? 'Unknown error',
            ]);
            $this->tracer->endSpan($span);
            unset($this->activeSpans[$toolId]);
        }
    }

    private function handleLLMStart(array $data): void
    {
        $requestId = $data['request_id'] ?? uniqid('llm_');

        $span = $this->tracer->startSpan('llm.request', [
            'llm.model' => $data['model'] ?? 'unknown',
            'llm.max_tokens' => $data['max_tokens'] ?? null,
        ], $this->getCurrentIterationSpan());

        $this->activeSpans[$requestId] = $span;
    }

    private function handleLLMComplete(array $data): void
    {
        $requestId = $data['request_id'] ?? null;

        if ($requestId && isset($this->activeSpans[$requestId])) {
            $span = $this->activeSpans[$requestId];
            $span->setStatus('OK');

            $inputTokens = $data['input_tokens'] ?? 0;
            $outputTokens = $data['output_tokens'] ?? 0;
            $model = $data['model'] ?? 'unknown';

            // Calculate cost
            $cost = $this->costEstimator->estimateCost($model, $inputTokens, $outputTokens);

            // Track in session costs
            $sessionId = $data['session_id'] ?? 'default';
            $this->costs[$sessionId] = ($this->costs[$sessionId] ?? 0) + $cost;

            // Record metrics
            $this->metrics->recordRequest(
                success: true,
                tokensInput: $inputTokens,
                tokensOutput: $outputTokens,
                duration: $span->getDuration(),
            );

            $this->tracer->endSpan($span);
            unset($this->activeSpans[$requestId]);

            $this->logger->debug('LLM request complete', [
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost' => CostEstimator::formatCost($cost),
            ]);
        }
    }

    private function handleLLMError(array $data): void
    {
        $requestId = $data['request_id'] ?? null;

        if ($requestId && isset($this->activeSpans[$requestId])) {
            $span = $this->activeSpans[$requestId];
            $span->setStatus('ERROR');
            $span->addEvent('error', [
                'error.message' => $data['error'] ?? 'Unknown error',
            ]);

            // Record failed request
            $this->metrics->recordRequest(
                success: false,
                tokensInput: 0,
                tokensOutput: 0,
                duration: $span->getDuration(),
                error: $data['error'] ?? 'Unknown error',
            );

            $this->tracer->endSpan($span);
            unset($this->activeSpans[$requestId]);
        }
    }

    private function handleChainStart(array $data): void
    {
        $chainName = $data['chain_name'] ?? 'unknown';
        $chainId = $data['chain_id'] ?? uniqid('chain_');

        $span = $this->tracer->startSpan("chain.{$chainName}", [
            'chain.name' => $chainName,
            'chain.type' => $data['chain_type'] ?? 'unknown',
        ]);

        $this->activeSpans[$chainId] = $span;
    }

    private function handleChainComplete(array $data): void
    {
        $chainId = $data['chain_id'] ?? null;

        if ($chainId && isset($this->activeSpans[$chainId])) {
            $span = $this->activeSpans[$chainId];
            $span->setStatus('OK');
            $this->tracer->endSpan($span);
            unset($this->activeSpans[$chainId]);
        }
    }

    private function handleChainError(array $data): void
    {
        $chainId = $data['chain_id'] ?? null;

        if ($chainId && isset($this->activeSpans[$chainId])) {
            $span = $this->activeSpans[$chainId];
            $span->setStatus('ERROR');
            $span->addEvent('error', [
                'error.message' => $data['error'] ?? 'Unknown error',
            ]);
            $this->tracer->endSpan($span);
            unset($this->activeSpans[$chainId]);
        }
    }

    private function getCurrentIterationSpan(): ?Span
    {
        // Find the most recent iteration span
        foreach ($this->activeSpans as $key => $span) {
            if (str_starts_with($key, 'iteration.')) {
                return $span;
            }
        }

        return $this->activeSpans['agent'] ?? null;
    }

    public function getTracer(): Tracer
    {
        return $this->tracer;
    }

    public function getMetrics(): Metrics
    {
        return $this->metrics;
    }

    public function getCostEstimator(): CostEstimator
    {
        return $this->costEstimator;
    }

    /**
     * Get total cost for a session.
     */
    public function getSessionCost(string $sessionId = 'default'): float
    {
        return $this->costs[$sessionId] ?? 0.0;
    }

    /**
     * Get all session costs.
     *
     * @return array<string, float>
     */
    public function getAllCosts(): array
    {
        return $this->costs;
    }

    /**
     * Reset all observability data.
     */
    public function reset(): void
    {
        $this->tracer = new Tracer();
        $this->metrics->reset();
        $this->activeSpans = [];
        $this->costs = [];
    }
}
