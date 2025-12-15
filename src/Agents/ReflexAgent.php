<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\AgentResult;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Support\TextContentExtractor;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Reflex Agent - Simple rule-based agent with condition-action mappings.
 *
 * Operates with deterministic if-condition → action rules without internal state.
 * Fast and predictable for well-defined scenarios.
 */
class ReflexAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $rules = [];
    private LoggerInterface $logger;
    private bool $useLLMFallback;

    /**
     * @param ClaudePhp $client Claude API client
     * @param array<string, mixed> $options Configuration:
     *   - name: Agent name
     *   - use_llm_fallback: Use LLM when no rule matches (default: true)
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'reflex_agent';
        $this->useLLMFallback = $options['use_llm_fallback'] ?? true;
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("Reflex agent processing: {$task}");

        try {
            // Try to match a rule
            foreach ($this->rules as $rule) {
                if ($this->matchesCondition($rule['condition'], $task)) {
                    $this->logger->info("Rule matched: {$rule['name']}");

                    $action = $rule['action'];
                    $result = is_callable($action) ? $action($task) : $action;

                    return AgentResult::success(
                        answer: $result,
                        messages: [],
                        iterations: 1,
                        metadata: [
                            'rule_matched' => $rule['name'],
                            'priority' => $rule['priority'],
                        ],
                    );
                }
            }

            // No rule matched - use LLM fallback if enabled
            if ($this->useLLMFallback) {
                $this->logger->info('No rule matched, using LLM fallback');

                return $this->llmFallback($task);
            }

            return AgentResult::failure(
                error: 'No matching rule found for input',
                metadata: ['rules_checked' => count($this->rules)],
            );
        } catch (\Throwable $e) {
            $this->logger->error("Reflex agent failed: {$e->getMessage()}");

            return AgentResult::failure(error: $e->getMessage());
        }
    }

    /**
     * Add a rule with condition and action.
     *
     * @param string $name Rule name/identifier
     * @param callable|string $condition Condition predicate: fn(string $input): bool or pattern string
     * @param callable|string $action Action to execute: fn(string $input): string or fixed response
     * @param int $priority Rule priority (higher = checked first, default: 0)
     */
    public function addRule(string $name, $condition, $action, int $priority = 0): self
    {
        $this->rules[] = [
            'name' => $name,
            'condition' => $condition,
            'action' => $action,
            'priority' => $priority,
        ];

        // Sort rules by priority (descending)
        usort($this->rules, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        $this->logger->debug("Added rule: {$name} with priority {$priority}");

        return $this;
    }

    /**
     * Add multiple rules at once.
     *
     * @param array<array{name: string, condition: callable|string, action: callable|string, priority?: int}> $rules
     */
    public function addRules(array $rules): self
    {
        foreach ($rules as $rule) {
            $this->addRule(
                $rule['name'],
                $rule['condition'],
                $rule['action'],
                $rule['priority'] ?? 0
            );
        }

        return $this;
    }

    /**
     * Remove a rule by name.
     */
    public function removeRule(string $name): bool
    {
        $initialCount = count($this->rules);

        $this->rules = array_filter(
            $this->rules,
            fn ($rule) => $rule['name'] !== $name
        );

        return count($this->rules) < $initialCount;
    }

    /**
     * Clear all rules.
     */
    public function clearRules(): self
    {
        $this->rules = [];
        $this->logger->debug('All rules cleared');

        return $this;
    }

    /**
     * Check if input matches a condition.
     */
    private function matchesCondition($condition, string $input): bool
    {
        if (is_callable($condition)) {
            return $condition($input);
        }

        if (is_string($condition)) {
            // Treat as regex pattern or substring match
            if (@preg_match($condition, $input)) {
                return (bool)preg_match($condition, $input);
            }

            // Fallback to substring match
            return str_contains(strtolower($input), strtolower($condition));
        }

        return false;
    }

    /**
     * LLM fallback when no rule matches.
     */
    private function llmFallback(string $task): AgentResult
    {
        try {
            $response = $this->client->messages()->create([
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 1024,
                'system' => $this->buildFallbackPrompt(),
                'messages' => [['role' => 'user', 'content' => $task]],
            ]);

            $answer = TextContentExtractor::extractFromResponse($response);

            return AgentResult::success(
                answer: $answer,
                messages: [],
                iterations: 1,
                metadata: [
                    'used_llm_fallback' => true,
                    'rules_checked' => count($this->rules),
                ],
            );
        } catch (\Throwable $e) {
            return AgentResult::failure(error: "LLM fallback failed: {$e->getMessage()}");
        }
    }

    /**
     * Build system prompt for LLM fallback.
     */
    private function buildFallbackPrompt(): string
    {
        $ruleDescriptions = array_map(
            fn ($rule) => "- {$rule['name']}",
            $this->rules
        );

        $rulesStr = empty($ruleDescriptions) ? 'None defined' : implode("\n", $ruleDescriptions);

        return <<<PROMPT
            You are a reflex agent with predefined rules. When a user input doesn't match any rule, 
            provide a helpful response.

            Known rules:
            {$rulesStr}

            Respond helpfully to inputs that don't match these rules.
            PROMPT;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get all defined rules.
     *
     * @return array<array>
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
