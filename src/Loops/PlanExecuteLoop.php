<?php

declare(strict_types=1);

namespace ClaudeAgents\Loops;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Contracts\CallbackSupportingLoopInterface;
use ClaudeAgents\Tools\ToolResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Plan-Execute loop implementation.
 *
 * Separates planning from execution:
 * 1. Plan Phase: Analyze task and create detailed action plan
 * 2. Execute Phase: Systematically execute each planned step
 * 3. Monitor Phase: Track progress and detect issues
 * 4. Revise Phase: Update plan if needed based on results
 */
class PlanExecuteLoop implements CallbackSupportingLoopInterface
{
    private LoggerInterface $logger;
    private bool $allowReplan;

    /**
     * @var callable|null
     */
    private $onIteration = null;

    /**
     * @var callable|null
     */
    private $onToolExecution = null;

    /**
     * @var callable|null
     */
    private $onPlanCreated = null;

    /**
     * @var callable|null
     */
    private $onStepComplete = null;

    public function __construct(?LoggerInterface $logger = null, bool $allowReplan = true)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->allowReplan = $allowReplan;
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

    /**
     * Set plan created callback.
     *
     * @param callable $callback fn(array $steps, AgentContext $context)
     */
    public function onPlanCreated(callable $callback): self
    {
        $this->onPlanCreated = $callback;

        return $this;
    }

    /**
     * Set step complete callback.
     *
     * @param callable $callback fn(int $stepNumber, string $stepDescription, string $result)
     */
    public function onStepComplete(callable $callback): self
    {
        $this->onStepComplete = $callback;

        return $this;
    }

    public function execute(AgentContext $context): AgentContext
    {
        $config = $context->getConfig();
        $client = $context->getClient();

        try {
            // Phase 1: Create the plan
            $this->logger->debug('Phase 1: Creating execution plan');
            $context->incrementIteration();

            $plan = $this->createPlan($context);
            $steps = $this->parseSteps($plan);

            $this->logger->info('Plan created with ' . count($steps) . ' steps');

            if ($this->onPlanCreated !== null) {
                ($this->onPlanCreated)($steps, $context);
            }

            if (empty($steps)) {
                $context->fail('Failed to create a valid execution plan');

                return $context;
            }

            // Phase 2: Execute each step
            $stepResults = [];

            foreach ($steps as $i => $step) {
                if ($context->hasReachedMaxIterations()) {
                    $this->logger->warning('Max iterations reached during step execution');

                    break;
                }

                $stepNumber = $i + 1;
                $this->logger->debug("Executing step {$stepNumber}: " . substr($step, 0, 50));

                // Execute the step
                $result = $this->executeStep($context, $step, $stepResults);

                $stepResults[] = [
                    'step' => $stepNumber,
                    'description' => $step,
                    'result' => $result,
                ];

                if ($this->onStepComplete !== null) {
                    ($this->onStepComplete)($stepNumber, $step, $result);
                }

                // Phase 3: Monitor and potentially revise
                if ($this->allowReplan && $this->shouldReplan($step, $result)) {
                    $this->logger->debug("Step {$stepNumber} requires replanning");

                    if (! $context->hasReachedMaxIterations()) {
                        $remainingSteps = array_slice($steps, $i + 1);
                        $newPlan = $this->revisePlan($context, $stepResults, $remainingSteps);
                        $newSteps = $this->parseSteps($newPlan);

                        if (! empty($newSteps)) {
                            $this->logger->info('Plan revised with ' . count($newSteps) . ' new steps');
                            // Replace remaining steps with new plan
                            $steps = array_merge(
                                array_slice($steps, 0, $i + 1),
                                $newSteps
                            );
                        }
                    }
                }
            }

            // Phase 4: Synthesize final answer
            if (! $context->hasReachedMaxIterations()) {
                $this->logger->debug('Synthesizing final answer from step results');
                $finalAnswer = $this->synthesize($context, $stepResults);
                $context->complete($finalAnswer);
            } else {
                $context->fail('Maximum iterations reached before completing all steps');
            }

        } catch (\Throwable $e) {
            $this->logger->error("Plan-execute loop failed: {$e->getMessage()}");
            $context->fail($e->getMessage());
        }

        return $context;
    }

    public function getName(): string
    {
        return 'plan_execute';
    }

    /**
     * Create an execution plan.
     */
    private function createPlan(AgentContext $context): string
    {
        $config = $context->getConfig();
        $client = $context->getClient();
        $task = $context->getTask();

        $toolsList = '';
        $tools = $context->getToolDefinitions();
        if (! empty($tools)) {
            $toolsList = "\n\nAvailable tools:\n";
            foreach ($tools as $tool) {
                $toolsList .= "- {$tool['name']}: {$tool['description']}\n";
            }
        }

        $prompt = "Task: {$task}{$toolsList}\n\n" .
            "Create a detailed step-by-step plan to complete this task.\n" .
            "Format each step as:\n" .
            "1. [Step description]\n" .
            "2. [Step description]\n" .
            "...\n\n" .
            'Be specific and actionable. Each step should be clear and executable.';

        $params = array_merge(
            $config->toApiParams(),
            [
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'system' => 'You are a systematic planner. Create clear, actionable plans.',
            ]
        );

        $response = $client->messages()->create($params);

        // Track token usage
        if (isset($response->usage)) {
            $context->addTokenUsage(
                $response->usage->input_tokens ?? 0,
                $response->usage->output_tokens ?? 0
            );
        }

        if ($this->onIteration !== null) {
            ($this->onIteration)($context->getIteration(), $response, $context);
        }

        return $this->extractTextContent($response->content);
    }

    /**
     * Parse steps from plan text.
     *
     * @return array<string>
     */
    private function parseSteps(string $plan): array
    {
        $steps = [];
        $lines = explode("\n", $plan);

        foreach ($lines as $line) {
            if (preg_match('/^\d+\.\s+(.+)$/', trim($line), $matches)) {
                $steps[] = trim($matches[1]);
            }
        }

        return $steps;
    }

    /**
     * Execute a single step.
     *
     * @param array<array<string, mixed>> $previousResults
     */
    private function executeStep(AgentContext $context, string $step, array $previousResults): string
    {
        $context->incrementIteration();

        $config = $context->getConfig();
        $client = $context->getClient();

        // Build context from previous results
        $contextStr = '';
        if (! empty($previousResults)) {
            $contextStr = "\n\nPrevious step results:\n";
            foreach ($previousResults as $prev) {
                $contextStr .= "Step {$prev['step']}: {$prev['result']}\n";
            }
        }

        $prompt = "Execute this step: {$step}{$contextStr}";

        // Build messages including previous context
        $messages = [['role' => 'user', 'content' => $prompt]];

        $params = array_merge(
            $config->toApiParams(),
            [
                'messages' => $messages,
                'tools' => $context->getToolDefinitions(),
            ]
        );

        $response = $client->messages()->create($params);

        // Track token usage
        if (isset($response->usage)) {
            $context->addTokenUsage(
                $response->usage->input_tokens ?? 0,
                $response->usage->output_tokens ?? 0
            );
        }

        if ($this->onIteration !== null) {
            ($this->onIteration)($context->getIteration(), $response, $context);
        }

        // Check if tools were used
        $stopReason = $response->stop_reason ?? 'end_turn';

        if ($stopReason === 'tool_use') {
            // Execute tools
            $toolResults = $this->executeTools($context, $response->content);

            // Get final response after tool execution
            if (! empty($toolResults)) {
                $followUpMessages = array_merge($messages, [
                    ['role' => 'assistant', 'content' => $response->content],
                    ['role' => 'user', 'content' => $toolResults],
                ]);

                $context->incrementIteration();

                $followUpParams = array_merge(
                    $config->toApiParams(),
                    [
                        'messages' => $followUpMessages,
                        'tools' => $context->getToolDefinitions(),
                    ]
                );

                $followUpResponse = $client->messages()->create($followUpParams);

                if (isset($followUpResponse->usage)) {
                    $context->addTokenUsage(
                        $followUpResponse->usage->input_tokens ?? 0,
                        $followUpResponse->usage->output_tokens ?? 0
                    );
                }

                return $this->extractTextContent($followUpResponse->content);
            }
        }

        return $this->extractTextContent($response->content);
    }

    /**
     * Execute tools from response content.
     *
     * @param array<mixed> $content Response content blocks
     * @return array<array<string, mixed>> Tool results for API
     */
    private function executeTools(AgentContext $context, array $content): array
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

            $this->logger->debug("Executing tool: {$toolName}", ['input' => $toolInput]);

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

            $results[] = $result->toApiFormat($toolId);
        }

        return $results;
    }

    /**
     * Check if replanning is needed based on step result.
     */
    private function shouldReplan(string $step, string $result): bool
    {
        // Simple heuristic: replan if result indicates failure or unexpected outcome
        $failureIndicators = ['error', 'failed', 'unable', 'cannot', 'impossible'];

        $resultLower = strtolower($result);
        foreach ($failureIndicators as $indicator) {
            if (str_contains($resultLower, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Revise the plan based on current progress.
     *
     * @param array<array<string, mixed>> $completedSteps
     * @param array<string> $remainingSteps
     */
    private function revisePlan(AgentContext $context, array $completedSteps, array $remainingSteps): string
    {
        $context->incrementIteration();

        $config = $context->getConfig();
        $client = $context->getClient();
        $task = $context->getTask();

        $completedContext = '';
        foreach ($completedSteps as $step) {
            $completedContext .= "Step {$step['step']}: {$step['description']}\n";
            $completedContext .= "Result: {$step['result']}\n\n";
        }

        $remainingContext = '';
        if (! empty($remainingSteps)) {
            $remainingContext = "\n\nRemaining planned steps:\n" .
                implode("\n", array_map(
                    fn ($s, $i) => ($i + 1) . ". {$s}",
                    $remainingSteps,
                    array_keys($remainingSteps)
                ));
        }

        $prompt = "Original task: {$task}\n\n" .
            "Completed steps:\n{$completedContext}" .
            "{$remainingContext}\n\n" .
            "Based on the results so far, revise the remaining steps if needed.\n" .
            "Format each step as:\n1. [Step description]\n2. [Step description]\n...";

        $params = array_merge(
            $config->toApiParams(),
            [
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'system' => 'You are a systematic planner. Adapt plans based on progress.',
            ]
        );

        $response = $client->messages()->create($params);

        if (isset($response->usage)) {
            $context->addTokenUsage(
                $response->usage->input_tokens ?? 0,
                $response->usage->output_tokens ?? 0
            );
        }

        if ($this->onIteration !== null) {
            ($this->onIteration)($context->getIteration(), $response, $context);
        }

        return $this->extractTextContent($response->content);
    }

    /**
     * Synthesize final answer from step results.
     *
     * @param array<array<string, mixed>> $stepResults
     */
    private function synthesize(AgentContext $context, array $stepResults): string
    {
        $context->incrementIteration();

        $config = $context->getConfig();
        $client = $context->getClient();
        $task = $context->getTask();

        $resultsText = '';
        foreach ($stepResults as $step) {
            $resultsText .= "Step {$step['step']} ({$step['description']}):\n{$step['result']}\n\n";
        }

        $prompt = "Original task: {$task}\n\n" .
            "Step results:\n{$resultsText}\n" .
            'Provide a comprehensive final answer based on all step results.';

        $params = array_merge(
            $config->toApiParams(),
            [
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]
        );

        $response = $client->messages()->create($params);

        if (isset($response->usage)) {
            $context->addTokenUsage(
                $response->usage->input_tokens ?? 0,
                $response->usage->output_tokens ?? 0
            );
        }

        if ($this->onIteration !== null) {
            ($this->onIteration)($context->getIteration(), $response, $context);
        }

        return $this->extractTextContent($response->content);
    }

    /**
     * Extract text content from response blocks.
     *
     * @param array<mixed> $content
     */
    private function extractTextContent(array $content): string
    {
        $texts = [];

        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            if ($type === 'text' && isset($block['text'])) {
                $texts[] = $block['text'];
            }
        }

        return implode("\n", $texts);
    }
}
