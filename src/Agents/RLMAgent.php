<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Agents\RLM\InputTools;
use ClaudeAgents\Agents\RLM\REPLContext;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\ToolInterface;
use ClaudeAgents\ML\Traits\LearnableAgent;
use ClaudeAgents\Tools\Tool;
use ClaudeAgents\Tools\ToolResult;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Recursive Language Model (RLM) Agent.
 *
 * Based on the RLM research from MIT CSAIL (arXiv:2512.24601v1), this agent
 * treats long prompts as part of an external environment rather than feeding
 * them directly into the neural network context. The agent can programmatically
 * examine, decompose, and recursively call itself on sub-tasks.
 *
 * Key features:
 * - Handles inputs up to 2 orders of magnitude beyond context window limits
 * - REPL environment where input is stored as a variable
 * - Tools for peeking, slicing, and searching the input
 * - Recursive self-invocation for decomposing complex tasks
 * - Variable storage for intermediate results
 *
 * @package ClaudeAgents\Agents
 */
class RLMAgent implements AgentInterface
{
    use LearnableAgent;

    private ClaudePhp $client;
    private string $name;
    private string $model;
    private int $maxTokens;
    private int $maxIterations;
    private int $maxRecursionDepth;
    private LoggerInterface $logger;

    /**
     * Additional user-provided tools.
     *
     * @var array<ToolInterface>
     */
    private array $userTools = [];

    /**
     * System prompt prefix.
     */
    private ?string $systemPrompt = null;

    /**
     * Whether to include extended thinking.
     *
     * @var array<string, mixed>|null
     */
    private ?array $thinking = null;

    /**
     * Current REPL context (set during execution).
     */
    private ?REPLContext $currentContext = null;

    /**
     * Callbacks for progress updates.
     *
     * @var callable|null
     */
    private $onIteration = null;
    private $onToolExecution = null;
    private $onRecursion = null;
    private $onUpdate = null;

    /**
     * Create a new RLM Agent.
     *
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration options:
     *   - name: Agent name (default: 'rlm_agent')
     *   - model: Model to use (default: 'claude-sonnet-4-5')
     *   - max_tokens: Max tokens per response (default: 4096)
     *   - max_iterations: Maximum loop iterations (default: 20)
     *   - max_recursion_depth: Maximum recursion depth (default: 10)
     *   - tools: Additional user-provided tools
     *   - system: System prompt prefix
     *   - thinking: Extended thinking configuration
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'rlm_agent';
        $this->model = $options['model'] ?? AgentConfig::DEFAULT_MODEL;
        $this->maxTokens = $options['max_tokens'] ?? 4096;
        $this->maxIterations = $options['max_iterations'] ?? 20;
        $this->maxRecursionDepth = $options['max_recursion_depth'] ?? 10;
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->userTools = $options['tools'] ?? [];
        $this->systemPrompt = $options['system'] ?? null;
        $this->thinking = $options['thinking'] ?? null;
    }

    /**
     * Add a tool to the agent.
     *
     * @param ToolInterface $tool
     * @return self
     */
    public function addTool(ToolInterface $tool): self
    {
        $this->userTools[] = $tool;
        return $this;
    }

    /**
     * Set iteration callback.
     *
     * @param callable $callback fn(int $iteration, mixed $response, mixed $context)
     * @return self
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
     * @return self
     */
    public function onToolExecution(callable $callback): self
    {
        $this->onToolExecution = $callback;
        return $this;
    }

    /**
     * Set recursion callback.
     *
     * @param callable $callback fn(int $depth, string $task, ?string $result)
     * @return self
     */
    public function onRecursion(callable $callback): self
    {
        $this->onRecursion = $callback;
        return $this;
    }

    /**
     * Set unified progress update callback.
     *
     * @param callable $callback fn(\ClaudeAgents\Progress\AgentUpdate $update): void
     * @return self
     */
    public function onUpdate(callable $callback): self
    {
        $this->onUpdate = $callback;
        return $this;
    }

    /**
     * Run the RLM agent with the given task and input.
     *
     * For standard usage, use runWithInput() instead which separates
     * the task from the input data.
     *
     * @param string $task The task prompt (may include input data for simple cases)
     * @return AgentResult
     */
    public function run(string $task): AgentResult
    {
        // For run() without separate input, treat the task as both
        return $this->runWithInput($task, '');
    }

    /**
     * Run the RLM agent with a task and separate input data.
     *
     * This is the primary method for RLM usage. The input is stored
     * in the REPL environment and not included in the LLM context.
     *
     * @param string $task The task/question to answer
     * @param string $input The input data to process (stored in REPL)
     * @param REPLContext|null $existingContext Optional existing context for recursive calls
     * @return AgentResult
     */
    public function runWithInput(
        string $task,
        string $input,
        ?REPLContext $existingContext = null
    ): AgentResult {
        $startTime = microtime(true);

        // Create or use existing REPL context
        $context = $existingContext ?? new REPLContext($input, $this->maxRecursionDepth);
        $this->currentContext = $context;

        $this->logger->info("Starting RLM agent '{$this->name}'", [
            'task' => substr($task, 0, 100),
            'input_chars' => strlen($input),
            'input_lines' => $context->getLineCount(),
            'recursion_depth' => $context->getRecursionDepth(),
        ]);

        try {
            // Build the system prompt with REPL context information
            $systemPrompt = $this->buildSystemPrompt($context);

            // Build tools array
            $tools = $this->buildTools($context);

            // Create the underlying agent
            $config = AgentConfig::fromArray([
                'model' => $this->model,
                'max_iterations' => $this->maxIterations,
                'max_tokens' => $this->maxTokens,
                'system_prompt' => $systemPrompt,
                'thinking' => $this->thinking ?? [],
            ]);

            $agent = new Agent($this->client, $config, $this->logger);
            $agent->withName($this->name . '_inner')
                  ->withTools($tools);

            // Configure callbacks
            $this->configureAgentCallbacks($agent);

            // Build the task prompt with REPL info
            $taskPrompt = $this->buildTaskPrompt($task, $context);

            // Execute
            $result = $agent->run($taskPrompt);

            $duration = microtime(true) - $startTime;

            // Enhance result with RLM metadata
            $metadata = $result->getMetadata();
            $metadata['rlm'] = [
                'input_chars' => strlen($input),
                'input_lines' => $context->getLineCount(),
                'input_words' => $context->getWordCount(),
                'recursion_depth' => $context->getRecursionDepth(),
                'recursion_history' => $context->getRecursionHistory(),
                'variables' => $context->getVariableNames(),
                'duration' => $duration,
            ];

            if ($result->isSuccess()) {
                $this->logger->info("RLM agent completed successfully", [
                    'iterations' => $result->getIterations(),
                    'duration' => $duration,
                ]);

                return AgentResult::success(
                    answer: $result->getAnswer(),
                    messages: $result->getMessages(),
                    iterations: $result->getIterations(),
                    metadata: $metadata
                );
            }

            return AgentResult::failure(
                error: $result->getError() ?? 'Unknown error',
                messages: $result->getMessages(),
                iterations: $result->getIterations(),
                metadata: $metadata
            );
        } catch (\Throwable $e) {
            $this->logger->error("RLM agent failed: {$e->getMessage()}");

            return AgentResult::failure(
                error: $e->getMessage(),
                messages: [],
                iterations: 0,
                metadata: [
                    'rlm' => [
                        'input_chars' => strlen($input),
                        'recursion_depth' => $context->getRecursionDepth(),
                    ],
                ]
            );
        } finally {
            $this->currentContext = null;
        }
    }

    /**
     * Build the system prompt with REPL context information.
     *
     * @param REPLContext $context
     * @return string
     */
    private function buildSystemPrompt(REPLContext $context): string
    {
        $basePrompt = $this->systemPrompt ?? '';

        $rlmPrompt = <<<PROMPT
You are an RLM (Recursive Language Model) agent. You have access to a REPL environment 
where a potentially very large input is stored as a variable.

REPL Environment State:
- Input: {$context->getCharCount()} characters, {$context->getLineCount()} lines, ~{$context->getWordCount()} words
- Estimated tokens: ~{$this->estimateTokens($context->getCharCount())}
- Current recursion depth: {$context->getRecursionDepth()}/{$context->getMaxRecursionDepth()}
- Available variables: {$this->formatVariableList($context)}

IMPORTANT: The input is NOT in your context window. You MUST use the provided tools 
to examine the input. Do not assume or hallucinate content you haven't seen.

Strategy for large inputs:
1. Use get_input_info to understand the input structure
2. Use peek_input or slice_input to examine relevant portions
3. Use search_input to find specific patterns or content
4. Use set_variable to store intermediate results
5. Use recursive_call to process sub-tasks if the input is very large
6. Synthesize results from your examination

Always verify your findings by examining the actual input content.
PROMPT;

        if (!empty($basePrompt)) {
            return $basePrompt . "\n\n" . $rlmPrompt;
        }

        return $rlmPrompt;
    }

    /**
     * Build the task prompt.
     *
     * @param string $task
     * @param REPLContext $context
     * @return string
     */
    private function buildTaskPrompt(string $task, REPLContext $context): string
    {
        $depthIndicator = '';
        if ($context->getRecursionDepth() > 0) {
            $depthIndicator = "[Recursive call depth: {$context->getRecursionDepth()}] ";
        }

        return $depthIndicator . $task;
    }

    /**
     * Build the tools array including RLM tools and user tools.
     *
     * @param REPLContext $context
     * @return array<ToolInterface>
     */
    private function buildTools(REPLContext $context): array
    {
        // Start with input tools
        $tools = InputTools::all($context);

        // Add recursive_call tool
        $tools[] = $this->createRecursiveCallTool($context);

        // Add user-provided tools
        foreach ($this->userTools as $tool) {
            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Create the recursive_call tool.
     *
     * @param REPLContext $context
     * @return Tool
     */
    private function createRecursiveCallTool(REPLContext $context): Tool
    {
        $agent = $this;

        return Tool::create('recursive_call')
            ->description(
                'Recursively invoke the RLM agent on a sub-task. ' .
                'Use this to decompose complex tasks or process portions of the input separately. ' .
                'Current depth: ' . $context->getRecursionDepth() . '/' . $context->getMaxRecursionDepth() . '. ' .
                ($context->canRecurse()
                    ? 'Recursion available.'
                    : 'WARNING: Maximum recursion depth reached!')
            )
            ->stringParam('task', 'The sub-task to process')
            ->stringParam(
                'input_source',
                'Source of input for the sub-task: "full" for entire input, ' .
                '"slice:START:END" for line range, "variable:NAME" for a stored variable',
                false
            )
            ->handler(function (array $input) use ($agent, $context): ToolResult {
                $task = $input['task'] ?? '';
                $inputSource = $input['input_source'] ?? 'full';

                if (empty($task)) {
                    return ToolResult::error('Task parameter is required');
                }

                if (!$context->canRecurse()) {
                    return ToolResult::error(
                        'Maximum recursion depth (' . $context->getMaxRecursionDepth() . ') reached. ' .
                        'Cannot recurse further. Please synthesize results from available data.'
                    );
                }

                // Determine the input for the recursive call
                $subInput = $agent->resolveInputSource($inputSource, $context);

                if ($subInput === null) {
                    return ToolResult::error("Invalid input_source: {$inputSource}");
                }

                // Notify recursion callback
                if ($agent->onRecursion !== null) {
                    ($agent->onRecursion)(
                        $context->getRecursionDepth() + 1,
                        $task,
                        null
                    );
                }

                try {
                    // Enter recursion
                    $context->enterRecursion($task);

                    // Create child context
                    $childContext = $context->createChildContext($subInput);

                    // Execute recursive call
                    $result = $agent->runWithInput($task, $subInput, $childContext);

                    // Exit recursion
                    $answer = $result->isSuccess() ? $result->getAnswer() : $result->getError();
                    $context->exitRecursion($answer ?? 'No result');

                    // Notify recursion callback with result
                    if ($agent->onRecursion !== null) {
                        ($agent->onRecursion)(
                            $context->getRecursionDepth(),
                            $task,
                            $answer
                        );
                    }

                    if ($result->isSuccess()) {
                        return ToolResult::success([
                            'success' => true,
                            'answer' => $result->getAnswer(),
                            'iterations' => $result->getIterations(),
                            'depth' => $childContext->getRecursionDepth(),
                        ]);
                    }

                    return ToolResult::success([
                        'success' => false,
                        'error' => $result->getError(),
                        'iterations' => $result->getIterations(),
                    ]);
                } catch (\Throwable $e) {
                    $context->exitRecursion('Error: ' . $e->getMessage());

                    return ToolResult::error('Recursive call failed: ' . $e->getMessage());
                }
            });
    }

    /**
     * Resolve an input source specification to actual input content.
     *
     * @param string $source Input source specification
     * @param REPLContext $context
     * @return string|null
     */
    public function resolveInputSource(string $source, REPLContext $context): ?string
    {
        // Full input
        if ($source === 'full') {
            return $context->getInput();
        }

        // Line slice: "slice:START:END"
        if (str_starts_with($source, 'slice:')) {
            $parts = explode(':', $source);
            if (count($parts) >= 3) {
                $startLine = (int) $parts[1];
                $endLine = (int) $parts[2];
                return $context->slice($startLine, $endLine);
            }
            return null;
        }

        // Variable: "variable:NAME"
        if (str_starts_with($source, 'variable:')) {
            $varName = substr($source, 9);
            if ($context->hasVariable($varName)) {
                $value = $context->getVariable($varName);
                return is_string($value) ? $value : json_encode($value);
            }
            return null;
        }

        return null;
    }

    /**
     * Configure callbacks on the inner agent.
     *
     * @param Agent $agent
     */
    private function configureAgentCallbacks(Agent $agent): void
    {
        if ($this->onIteration !== null) {
            $callback = $this->onIteration;
            $agent->onIteration(function ($iteration, $response, $context) use ($callback) {
                $callback($iteration, $response, $context);
            });
        }

        if ($this->onToolExecution !== null) {
            $callback = $this->onToolExecution;
            $agent->onToolExecution(function ($tool, $input, $result) use ($callback) {
                $callback($tool, $input, $result);
            });
        }

        if ($this->onUpdate !== null) {
            $agent->onUpdate($this->onUpdate);
        }
    }

    /**
     * Estimate tokens from character count.
     *
     * @param int $charCount
     * @return int
     */
    private function estimateTokens(int $charCount): int
    {
        // Rough estimate: ~4 characters per token
        return (int) ($charCount / 4);
    }

    /**
     * Format variable list for system prompt.
     *
     * @param REPLContext $context
     * @return string
     */
    private function formatVariableList(REPLContext $context): string
    {
        $names = $context->getVariableNames();
        if (empty($names)) {
            return 'none';
        }
        return implode(', ', array_map(fn($n) => '$' . $n, $names));
    }

    /**
     * Get agent identifier for learning traits.
     */
    protected function getAgentIdentifier(): string
    {
        return $this->name;
    }

    /**
     * Get the agent name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get current REPL context (if executing).
     */
    public function getCurrentContext(): ?REPLContext
    {
        return $this->currentContext;
    }

    /**
     * Analyze task for learning.
     */
    protected function analyzeTaskForLearning(string $task): array
    {
        $wordCount = str_word_count($task);
        $length = strlen($task);
        $inputSize = $this->currentContext?->getCharCount() ?? 0;

        return [
            'complexity' => match (true) {
                $inputSize > 100000 => 'very_complex',
                $inputSize > 10000 || $length > 300 => 'complex',
                $inputSize > 1000 || $length > 150 => 'medium',
                default => 'simple',
            },
            'domain' => 'rlm',
            'requires_tools' => true,
            'requires_knowledge' => false,
            'requires_reasoning' => true,
            'requires_iteration' => true,
            'requires_quality' => 'high',
            'input_size' => $inputSize,
            'estimated_steps' => max(3, min(20, (int) ($inputSize / 5000))),
            'key_requirements' => ['input_examination', 'decomposition', 'synthesis'],
        ];
    }

    /**
     * Evaluate result quality for learning.
     */
    protected function evaluateResultQuality(AgentResult $result): float
    {
        if (!$result->isSuccess()) {
            return 0.0;
        }

        $iterations = $result->getIterations();
        $answerLength = strlen($result->getAnswer());
        $metadata = $result->getMetadata();
        $rlmData = $metadata['rlm'] ?? [];

        // Base score from answer quality
        $baseScore = match (true) {
            $answerLength < 20 => 4.0,
            $answerLength < 100 => 6.0,
            $answerLength < 500 => 8.0,
            default => 9.0,
        };

        // Efficiency bonus
        $efficiencyBonus = match (true) {
            $iterations <= 5 => 1.0,
            $iterations <= 10 => 0.5,
            default => 0.0,
        };

        // Recursion bonus (using recursion effectively)
        $recursionHistory = $rlmData['recursion_history'] ?? [];
        $recursionBonus = count($recursionHistory) > 0 ? 0.5 : 0.0;

        return min(10.0, $baseScore + $efficiencyBonus + $recursionBonus);
    }
}
