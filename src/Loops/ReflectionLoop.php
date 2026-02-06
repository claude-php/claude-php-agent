<?php

declare(strict_types=1);

namespace ClaudeAgents\Loops;

use ClaudeAgents\AgentContext;
use ClaudeAgents\Contracts\CallbackSupportingLoopInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Reflection loop implementation (Generate-Reflect-Refine Pattern).
 *
 * Iteratively improves output quality through reflection:
 * 1. Generate: Create initial output for the task
 * 2. Reflect: Evaluate quality and identify improvements
 * 3. Refine: Apply improvements based on reflection
 * 4. Repeat until quality threshold met or max refinements reached
 */
class ReflectionLoop implements CallbackSupportingLoopInterface
{
    use ToolExecutionTrait;

    private LoggerInterface $logger;
    private int $maxRefinements;
    private int $qualityThreshold;
    private ?string $criteria;

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
    private $onReflection = null;

    /**
     * @param LoggerInterface|null $logger
     * @param int $maxRefinements Maximum refinement iterations (default: 3)
     * @param int $qualityThreshold Score threshold to stop (default: 8)
     * @param string|null $criteria Custom evaluation criteria
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        int $maxRefinements = 3,
        int $qualityThreshold = 8,
        ?string $criteria = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->maxRefinements = $maxRefinements;
        $this->qualityThreshold = $qualityThreshold;
        $this->criteria = $criteria;
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
     * Set reflection callback.
     *
     * @param callable $callback fn(int $refinement, int $score, string $feedback)
     */
    public function onReflection(callable $callback): self
    {
        $this->onReflection = $callback;

        return $this;
    }

    public function execute(AgentContext $context): AgentContext
    {
        $config = $context->getConfig();
        $client = $context->getClient();

        try {
            // Phase 1: Initial generation
            $this->logger->debug('Phase 1: Initial generation');
            $output = $this->generate($context);

            if (empty($output)) {
                $context->fail('Failed to generate initial output');

                return $context;
            }

            // Phase 2: Reflect and refine loop
            $reflections = [];

            for ($i = 0; $i < $this->maxRefinements; $i++) {
                if ($context->hasReachedMaxIterations()) {
                    $this->logger->warning('Max iterations reached during refinement');

                    break;
                }

                $refinementNumber = $i + 1;
                $this->logger->debug("Refinement iteration {$refinementNumber}");

                // Reflect on current output
                $reflection = $this->reflect($context, $output);
                $score = $this->extractScore($reflection);

                $reflections[] = [
                    'iteration' => $refinementNumber,
                    'score' => $score,
                    'feedback' => substr($reflection, 0, 200),
                ];

                $this->logger->debug("Reflection score: {$score}");

                if ($this->onReflection !== null) {
                    ($this->onReflection)($refinementNumber, $score, $reflection);
                }

                // Check if quality threshold met
                if ($score >= $this->qualityThreshold) {
                    $this->logger->info("Quality threshold met at refinement {$refinementNumber}");

                    break;
                }

                // Refine based on reflection
                $this->logger->debug('Refining output based on reflection');
                $output = $this->refine($context, $output, $reflection);
            }

            // Store reflection metadata
            $context->addMetadata('reflections', $reflections);
            $context->addMetadata('final_score', $reflections[count($reflections) - 1]['score'] ?? 0);

            $context->complete($output);

        } catch (\Throwable $e) {
            $this->logger->error("Reflection loop failed: {$e->getMessage()}");
            $context->fail($e->getMessage());
        }

        return $context;
    }

    public function getName(): string
    {
        return 'reflection';
    }

    /**
     * Generate initial output for the task.
     */
    private function generate(AgentContext $context): string
    {
        $context->incrementIteration();

        $config = $context->getConfig();
        $client = $context->getClient();
        $task = $context->getTask();

        $params = array_merge(
            $config->toApiParams(),
            [
                'messages' => [['role' => 'user', 'content' => $task]],
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

        // Handle tool use if present — check content rather than stop_reason
        // to handle edge cases like stop_reason='max_tokens' with tool_use blocks.
        $hasToolUse = $this->contentHasToolUse($response->content);

        if ($hasToolUse) {
            // API requires tool_result for every tool_use
            $toolResults = $this->executeTools($context, $response->content);

            $context->incrementIteration();

            $followUpParams = array_merge(
                $config->toApiParams(),
                [
                    'messages' => [
                        ['role' => 'user', 'content' => $task],
                        ['role' => 'assistant', 'content' => $this->normalizeContentBlocks($response->content)],
                        ['role' => 'user', 'content' => $toolResults],
                    ],
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

        return $this->extractTextContent($response->content);
    }

    /**
     * Reflect on the output quality.
     */
    private function reflect(AgentContext $context, string $output): string
    {
        $context->incrementIteration();

        $config = $context->getConfig();
        $client = $context->getClient();
        $task = $context->getTask();

        $criteria = $this->criteria ?? 'correctness, completeness, clarity, and quality';

        $prompt = "Task: {$task}\n\n" .
            "Current output:\n{$output}\n\n" .
            "Evaluate this output on {$criteria}:\n" .
            "1. What's working well?\n" .
            "2. What issues or problems exist?\n" .
            "3. How can it be improved?\n" .
            "4. Overall quality score (1-10)\n\n" .
            'Be constructive but critical. Focus on actionable improvements.';

        $params = array_merge(
            $config->toApiParams(),
            [
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'system' => 'You are an expert evaluator. Be constructive but critical in your assessment.',
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
     * Refine the output based on reflection.
     */
    private function refine(AgentContext $context, string $output, string $reflection): string
    {
        $context->incrementIteration();

        $config = $context->getConfig();
        $client = $context->getClient();
        $task = $context->getTask();

        $prompt = "Task: {$task}\n\n" .
            "Current output:\n{$output}\n\n" .
            "Reflection:\n{$reflection}\n\n" .
            'Improve the output by addressing the issues identified in the reflection. ' .
            'Maintain what works well while fixing the problems.';

        $params = array_merge(
            $config->toApiParams(),
            [
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'tools' => $context->getToolDefinitions(),
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

        // Handle tool use if present — check content rather than stop_reason
        // to handle edge cases like stop_reason='max_tokens' with tool_use blocks.
        $hasToolUse = $this->contentHasToolUse($response->content);

        if ($hasToolUse) {
            // API requires tool_result for every tool_use
            $toolResults = $this->executeTools($context, $response->content);

            $context->incrementIteration();

            $followUpParams = array_merge(
                $config->toApiParams(),
                [
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                        ['role' => 'assistant', 'content' => $this->normalizeContentBlocks($response->content)],
                        ['role' => 'user', 'content' => $toolResults],
                    ],
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

        return $this->extractTextContent($response->content);
    }

    /**
     * Extract quality score from reflection text.
     */
    private function extractScore(string $text): int
    {
        // Look for patterns like "Score: 7/10" or "Quality: 8" or "score of 7" or "rating of 6"
        if (preg_match('/(?:score|quality|rating)(?:\s+of\s+|[:\s]+)(\d+)(?:\/10)?/i', $text, $matches)) {
            return min(10, max(1, (int) $matches[1]));
        }

        // Look for standalone numbers near "10" like "7/10"
        if (preg_match('/(\d+)\s*\/\s*10/i', $text, $matches)) {
            return min(10, max(1, (int) $matches[1]));
        }

        // Look for numbers followed by "out of 10"
        if (preg_match('/(\d+)\s*out\s*of\s*10/i', $text, $matches)) {
            return min(10, max(1, (int) $matches[1]));
        }

        return 5; // Default if no score found
    }
}
