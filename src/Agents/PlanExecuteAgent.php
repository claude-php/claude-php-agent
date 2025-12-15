<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\ToolInterface;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Plan-and-Execute Agent.
 *
 * Separates planning from execution for more systematic task completion.
 * First creates a detailed plan, then executes each step, with optional
 * plan revision based on results.
 */
class PlanExecuteAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private string $model;
    private int $maxTokens;
    private bool $allowReplan;
    private LoggerInterface $logger;

    /**
     * @var array<ToolInterface>
     */
    private array $tools = [];

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - model: Model to use
     *   - max_tokens: Max tokens per response
     *   - tools: Available tools for execution
     *   - allow_replan: Whether to allow plan revision (default: true)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'plan_execute_agent';
        $this->model = $options['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $options['max_tokens'] ?? 2048;
        $this->allowReplan = $options['allow_replan'] ?? true;
        $this->tools = $options['tools'] ?? [];
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Add a tool for execution.
     */
    public function addTool(ToolInterface $tool): self
    {
        $this->tools[] = $tool;

        return $this;
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info('Starting plan-execute agent', ['task' => substr($task, 0, 100)]);

        $totalTokens = ['input' => 0, 'output' => 0];
        $iterations = 0;
        $stepResults = [];

        try {
            // Step 1: Create plan
            $this->logger->debug('Step 1: Creating plan');
            $plan = $this->createPlan($task, $totalTokens);
            $iterations++;

            $steps = $this->parseSteps($plan);
            $this->logger->debug('Plan created', ['steps' => count($steps)]);

            // Step 2: Execute each step
            foreach ($steps as $i => $step) {
                $this->logger->debug('Executing step ' . ($i + 1), ['step' => substr($step, 0, 50)]);

                $result = $this->executeStep($step, $stepResults, $totalTokens);
                $iterations++;

                $stepResults[] = [
                    'step' => $i + 1,
                    'description' => $step,
                    'result' => $result,
                ];

                // Optional: Check if replanning is needed
                if ($this->allowReplan && $this->shouldReplan($step, $result)) {
                    $this->logger->debug('Replanning after step ' . ($i + 1));
                    $remainingSteps = array_slice($steps, $i + 1);
                    $newPlan = $this->revisePlan($task, $stepResults, $remainingSteps, $totalTokens);
                    $iterations++;

                    $newSteps = $this->parseSteps($newPlan);
                    if (! empty($newSteps)) {
                        $steps = array_merge(
                            array_slice($steps, 0, $i + 1),
                            $newSteps
                        );
                    }
                }
            }

            // Step 3: Synthesize final answer
            $this->logger->debug('Step 3: Synthesizing results');
            $finalAnswer = $this->synthesize($task, $stepResults, $totalTokens);
            $iterations++;

            return AgentResult::success(
                answer: $finalAnswer,
                messages: [],
                iterations: $iterations,
                metadata: [
                    'token_usage' => [
                        'input' => $totalTokens['input'],
                        'output' => $totalTokens['output'],
                        'total' => $totalTokens['input'] + $totalTokens['output'],
                    ],
                    'plan_steps' => count($steps),
                    'step_results' => $stepResults,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Plan-execute agent failed: {$e->getMessage()}");

            return AgentResult::failure($e->getMessage());
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Create an execution plan.
     *
     * @param array{input: int, output: int} $tokenUsage
     */
    private function createPlan(string $task, array &$tokenUsage): string
    {
        $toolsList = '';
        foreach ($this->tools as $tool) {
            $toolsList .= "- {$tool->getName()}: {$tool->getDescription()}\n";
        }

        $toolsContext = ! empty($toolsList)
            ? "\n\nAvailable tools:\n{$toolsList}"
            : '';

        $prompt = "Task: {$task}{$toolsContext}\n\n" .
            "Create a detailed step-by-step plan to complete this task.\n" .
            "Format each step as:\n" .
            "1. [Step description]\n" .
            "2. [Step description]\n" .
            "...\n\n" .
            'Be specific and actionable.';

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => 'You are a systematic planner. Create clear, actionable plans.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
        $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

        return $this->extractTextContent($response->content ?? []);
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
     * @param string $step The step to execute
     * @param array<array<string, mixed>> $previousResults Results from previous steps
     * @param array{input: int, output: int} $tokenUsage
     */
    private function executeStep(string $step, array $previousResults, array &$tokenUsage): string
    {
        $context = '';
        if (! empty($previousResults)) {
            $context = "\n\nPrevious step results:\n";
            foreach ($previousResults as $prev) {
                $context .= "Step {$prev['step']}: {$prev['result']}\n";
            }
        }

        $prompt = "Execute this step: {$step}{$context}";

        // If tools are available, use an agent for execution
        if (! empty($this->tools)) {
            $agent = Agent::create($this->client)
                ->withTools($this->tools)
                ->maxIterations(5)
                ->maxTokens($this->maxTokens);

            $result = $agent->run($prompt);

            $usage = $result->getTokenUsage();
            $tokenUsage['input'] += $usage['input'];
            $tokenUsage['output'] += $usage['output'];

            return $result->getAnswer();
        }

        // Otherwise, just ask Claude directly
        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
        $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

        return $this->extractTextContent($response->content ?? []);
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
     * @param array{input: int, output: int} $tokenUsage
     */
    private function revisePlan(
        string $task,
        array $completedSteps,
        array $remainingSteps,
        array &$tokenUsage,
    ): string {
        $completedContext = '';
        foreach ($completedSteps as $step) {
            $completedContext .= "Step {$step['step']}: {$step['description']}\n";
            $completedContext .= "Result: {$step['result']}\n\n";
        }

        $remainingContext = implode("\n", array_map(
            fn ($s, $i) => ($i + 1) . ". {$s}",
            $remainingSteps,
            array_keys($remainingSteps)
        ));

        $prompt = "Original task: {$task}\n\n" .
            "Completed steps:\n{$completedContext}\n" .
            "Remaining planned steps:\n{$remainingContext}\n\n" .
            "Based on the results so far, revise the remaining steps if needed.\n" .
            "Format each step as:\n1. [Step description]\n...";

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => 'You are a systematic planner. Adapt plans based on progress.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
        $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

        return $this->extractTextContent($response->content ?? []);
    }

    /**
     * Synthesize final answer from step results.
     *
     * @param array<array<string, mixed>> $stepResults
     * @param array{input: int, output: int} $tokenUsage
     */
    private function synthesize(string $task, array $stepResults, array &$tokenUsage): string
    {
        $resultsText = '';
        foreach ($stepResults as $step) {
            $resultsText .= "Step {$step['step']} ({$step['description']}):\n{$step['result']}\n\n";
        }

        $prompt = "Original task: {$task}\n\n" .
            "Step results:\n{$resultsText}\n" .
            'Provide a comprehensive final answer based on all step results.';

        $response = $this->client->messages()->create([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $tokenUsage['input'] += $response->usage->input_tokens ?? 0;
        $tokenUsage['output'] += $response->usage->output_tokens ?? 0;

        return $this->extractTextContent($response->content ?? []);
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
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $texts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $texts);
    }
}
