<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Helpers\AgentHelpers;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Plan-Execute-Reflect-Adjust (PERA) Agent
 *
 * Advanced ReAct pattern that adds planning, reflection, and self-correction
 * capabilities for complex multi-step tasks.
 *
 * Flow:
 * 1. PLAN - Break down task into steps before acting
 * 2. EXECUTE - Standard ReAct with tools
 * 3. REFLECT - Analyze what worked and what didn't
 * 4. ADJUST - Self-correct if issues found
 *
 * Best for:
 * - Complex multi-step tasks requiring careful planning
 * - Tasks where mistakes need to be detected and corrected
 * - Research and analysis workflows
 * - Quality-critical content generation
 */
class PlanExecuteReflectAgent extends Agent
{
    private LoggerInterface $logger;
    private bool $enableExtendedThinking;
    private int $maxCycles;
    private int $thinkingBudget;

    /**
     * @param ClaudePhp $client Claude API client
     * @param AgentConfig|null $config Agent configuration
     * @param LoggerInterface|null $logger PSR-3 logger
     * @param bool $enableExtendedThinking Enable extended thinking for complex reasoning
     * @param int $maxCycles Maximum plan-execute-reflect cycles
     * @param int $thinkingBudget Thinking token budget (1K-32K)
     */
    public function __construct(
        ClaudePhp $client,
        ?AgentConfig $config = null,
        ?LoggerInterface $logger = null,
        bool $enableExtendedThinking = true,
        int $maxCycles = 3,
        int $thinkingBudget = 10000
    ) {
        parent::__construct($client, $config);
        $this->logger = $logger ?? new NullLogger();
        $this->enableExtendedThinking = $enableExtendedThinking;
        $this->maxCycles = $maxCycles;
        $this->thinkingBudget = $thinkingBudget;

        // Set system prompt with PERA instructions
        $this->withSystemPrompt($this->buildSystemPrompt());
    }

    /**
     * Run the PERA agent on a task
     *
     * @param string $task Task description
     * @return \ClaudeAgents\AgentResult Result with PERA metadata
     */
    public function run(string $task): \ClaudeAgents\AgentResult
    {
        $this->logger->info('Starting PERA agent', ['task' => $task]);

        $cycle = 0;
        $plan = null;
        $reflection = null;
        $finalResponse = null;

        while ($cycle < $this->maxCycles) {
            $cycle++;
            $this->logger->info("PERA cycle {$cycle}/{$this->maxCycles}");

            // Phase 1: PLAN
            if ($cycle === 1) {
                $plan = $this->planPhase($task);
                if (! $plan) {
                    return AgentResult::failure(
                        'Planning phase failed',
                        [],
                        $cycle,
                        ['cycles' => $cycle]
                    );
                }
            } else {
                // Re-plan based on reflection
                $this->logger->info('Re-planning based on reflection');
                $plan = $this->replanPhase($task, $reflection);
            }

            // Phase 2: EXECUTE
            $executionResult = $this->executePhase($plan, $task);
            if (! $executionResult['success']) {
                return AgentResult::failure(
                    'Execution phase failed',
                    [],
                    $cycle,
                    ['plan' => $plan, 'cycles' => $cycle]
                );
            }

            $finalResponse = $executionResult['response'];

            // Phase 3: REFLECT
            $reflection = $this->reflectPhase($plan, $executionResult);

            // Phase 4: ADJUST (decision)
            $shouldContinue = $this->shouldAdjust($reflection);

            if (! $shouldContinue) {
                $this->logger->info('Task completed successfully', ['cycles' => $cycle]);

                break;
            }

            if ($cycle >= $this->maxCycles) {
                $this->logger->warning('Max cycles reached', ['cycles' => $cycle]);
            }
        }

        return AgentResult::success(
            $finalResponse ?? '',
            [],
            $cycle,
            [
                'plan' => $plan,
                'reflection' => $reflection,
                'cycles' => $cycle,
            ]
        );
    }

    /**
     * Phase 1: Planning
     *
     * @param string $task Task description
     * @return string|null Plan text or null on failure
     */
    private function planPhase(string $task): ?string
    {
        $this->logger->info('Phase 1: PLANNING');

        $messages = [
            [
                'role' => 'user',
                'content' => "Task: {$task}\n\n" .
                    'First, create a detailed plan. Break down the task into clear steps, ' .
                    'identify what information is needed, and anticipate potential issues.',
            ],
        ];

        $params = [
            'model' => $this->config->getModel(),
            'max_tokens' => 2048,
            'messages' => $messages,
            'system' => $this->buildPlanningSystemPrompt(),
        ];

        if ($this->enableExtendedThinking) {
            $params['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => min($this->thinkingBudget, 5000),
            ];
        }

        try {
            $response = $this->client->messages()->create($params);
            $plan = AgentHelpers::extractTextContent($response);

            $this->logger->info('Plan created', [
                'plan_length' => strlen($plan),
                'tokens' => [
                    'input' => $response->usage->input_tokens ?? 0,
                    'output' => $response->usage->output_tokens ?? 0,
                ],
            ]);

            return $plan;
        } catch (\Exception $e) {
            $this->logger->error('Planning failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Phase 1b: Re-planning based on reflection
     *
     * @param string $task Original task
     * @param string $reflection Previous reflection
     * @return string|null Updated plan
     */
    private function replanPhase(string $task, string $reflection): ?string
    {
        $this->logger->info('Phase 1b: RE-PLANNING');

        $messages = [
            [
                'role' => 'user',
                'content' => "Original task: {$task}\n\n" .
                    "Previous attempt reflection:\n{$reflection}\n\n" .
                    'Based on the reflection, create an improved plan addressing the issues identified.',
            ],
        ];

        $params = [
            'model' => $this->config->getModel(),
            'max_tokens' => 2048,
            'messages' => $messages,
            'system' => $this->buildPlanningSystemPrompt(),
        ];

        try {
            $response = $this->client->messages()->create($params);

            return AgentHelpers::extractTextContent($response);
        } catch (\Exception $e) {
            $this->logger->error('Re-planning failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Phase 2: Execution with tools
     *
     * @param string $plan The plan to execute
     * @param string $task Original task
     * @return array Result with keys: success, response, messages
     */
    private function executePhase(string $plan, string $task): array
    {
        $this->logger->info('Phase 2: EXECUTING');

        $messages = [
            [
                'role' => 'user',
                'content' => "Task: {$task}\n\nPlan:\n{$plan}\n\nNow execute the plan step by step using the available tools.",
            ],
        ];

        // Use the agent's standard run loop (access protected property)
        $tools = $this->tools;

        $config = [
            'max_iterations' => $this->config->getMaxIterations(),
            'model' => $this->config->getModel(),
            'max_tokens' => $this->config->getMaxTokens(),
            'debug' => false,
            'system' => $this->config->getSystemPrompt(),
        ];

        if ($this->enableExtendedThinking) {
            $config['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => $this->thinkingBudget,
            ];
        }

        // Create tool executor
        $toolExecutor = function (string $name, array $input) {
            $tool = $this->tools->get($name);
            if ($tool) {
                // Execute tool by calling its __invoke method
                return call_user_func([$tool, '__invoke'], $input);
            }

            return "Tool '{$name}' not found";
        };

        $result = AgentHelpers::runAgentLoop(
            $this->client,
            $messages,
            $tools->all(),
            $toolExecutor,
            $config
        );

        $this->logger->info('Execution completed', [
            'success' => $result['success'],
            'iterations' => $result['iterations'],
        ]);

        return $result;
    }

    /**
     * Phase 3: Reflection
     *
     * @param string $plan The plan that was executed
     * @param array $executionResult Execution result
     * @return string Reflection text
     */
    private function reflectPhase(string $plan, array $executionResult): string
    {
        $this->logger->info('Phase 3: REFLECTING');

        $responseText = $executionResult['response']
            ? AgentHelpers::extractTextContent($executionResult['response'])
            : '[No response]';

        $messages = [
            [
                'role' => 'user',
                'content' => "Reflect on the execution:\n\n" .
                    "Plan:\n{$plan}\n\n" .
                    "Result:\n{$responseText}\n\n" .
                    "Please analyze:\n" .
                    "1. What worked well?\n" .
                    "2. What didn't work as expected?\n" .
                    "3. Were any steps missed or incomplete?\n" .
                    "4. Is the task truly complete and correct?\n" .
                    "5. What could be improved?\n\n" .
                    'Be critical and honest in your assessment.',
            ],
        ];

        $params = [
            'model' => $this->config->getModel(),
            'max_tokens' => 2048,
            'messages' => $messages,
            'system' => $this->buildReflectionSystemPrompt(),
        ];

        if ($this->enableExtendedThinking) {
            $params['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => min($this->thinkingBudget, 3000),
            ];
        }

        try {
            $response = $this->client->messages()->create($params);
            $reflection = AgentHelpers::extractTextContent($response);

            $this->logger->info('Reflection completed', [
                'reflection_length' => strlen($reflection),
            ]);

            return $reflection;
        } catch (\Exception $e) {
            $this->logger->error('Reflection failed', ['error' => $e->getMessage()]);

            return '[Reflection failed]';
        }
    }

    /**
     * Phase 4: Decide if adjustment needed
     *
     * @param string $reflection Reflection text
     * @return bool True if another cycle needed
     */
    private function shouldAdjust(string $reflection): bool
    {
        // Check for keywords indicating issues
        $issueKeywords = [
            'issue', 'problem', 'incorrect', 'wrong', 'error',
            'incomplete', 'missing', 'failed', 'mistake', 'needs improvement',
        ];

        $hasIssues = AgentHelpers::containsWords($reflection, $issueKeywords);

        if ($hasIssues) {
            $this->logger->info('Phase 4: ADJUST - Issues detected, another cycle needed');
        } else {
            $this->logger->info('Phase 4: No issues detected, task complete');
        }

        return $hasIssues;
    }

    /**
     * Build system prompt for PERA agent
     *
     * @return string System prompt
     */
    private function buildSystemPrompt(): string
    {
        return 'You are a thoughtful AI agent that follows the Plan-Execute-Reflect-Adjust pattern. ' .
            'You carefully plan before acting, execute systematically, and reflect on results to ensure quality.';
    }

    /**
     * Build system prompt for planning phase
     *
     * @return string Planning system prompt
     */
    private function buildPlanningSystemPrompt(): string
    {
        return "You are a meticulous planner. When planning:\n" .
            "1. Break down the task into clear, actionable steps\n" .
            "2. Identify what information or data is needed\n" .
            "3. Anticipate potential issues or edge cases\n" .
            "4. Propose a systematic strategy\n" .
            "5. Consider dependencies between steps\n\n" .
            'Be thorough and detailed in your planning.';
    }

    /**
     * Build system prompt for reflection phase
     *
     * @return string Reflection system prompt
     */
    private function buildReflectionSystemPrompt(): string
    {
        return "You are a critical reviewer. When reflecting:\n" .
            "1. Objectively assess what worked and what didn't\n" .
            "2. Identify any errors, omissions, or incomplete work\n" .
            "3. Evaluate whether the task objectives were fully met\n" .
            "4. Suggest concrete improvements\n" .
            "5. Be honest and constructive in your criticism\n\n" .
            'Your goal is to ensure high-quality results.';
    }

    /**
     * Configure extended thinking
     *
     * @param bool $enabled Enable extended thinking
     * @param int $budget Thinking token budget
     * @return self
     */
    public function withExtendedThinking(bool $enabled = true, int $budget = 10000): self
    {
        $this->enableExtendedThinking = $enabled;
        $this->thinkingBudget = $budget;

        return $this;
    }

    /**
     * Set maximum PERA cycles
     *
     * @param int $cycles Maximum cycles
     * @return self
     */
    public function withMaxCycles(int $cycles): self
    {
        $this->maxCycles = $cycles;

        return $this;
    }
}
