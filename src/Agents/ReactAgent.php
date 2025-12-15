<?php

declare(strict_types=1);

namespace ClaudeAgents\Agents;

use ClaudeAgents\Agent;
use ClaudeAgents\AgentResult;
use ClaudeAgents\Config\AgentConfig;
use ClaudeAgents\Contracts\AgentInterface;
use ClaudeAgents\Contracts\ToolInterface;
use ClaudePhp\ClaudePhp;

/**
 * ReAct (Reason-Act-Observe) Agent.
 *
 * A convenience wrapper around the base Agent class that provides
 * a simpler interface for creating ReAct agents.
 */
class ReactAgent implements AgentInterface
{
    private Agent $agent;
    private string $name;

    /**
     * @param ClaudePhp $client The Claude API client
     * @param array<string, mixed> $options Configuration options:
     *   - name: Agent name
     *   - tools: Array of ToolInterface
     *   - system: System prompt
     *   - model: Model to use
     *   - max_iterations: Maximum loop iterations
     *   - max_tokens: Maximum tokens per response
     *   - thinking: Extended thinking config
     *   - logger: PSR-3 logger
     */
    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->name = $options['name'] ?? 'react_agent';

        $config = AgentConfig::fromArray([
            'model' => $options['model'] ?? AgentConfig::DEFAULT_MODEL,
            'max_iterations' => $options['max_iterations'] ?? AgentConfig::DEFAULT_MAX_ITERATIONS,
            'max_tokens' => $options['max_tokens'] ?? AgentConfig::DEFAULT_MAX_TOKENS,
            'system_prompt' => $options['system'] ?? null,
            'thinking' => $options['thinking'] ?? [],
        ]);

        $logger = $options['logger'] ?? null;

        $this->agent = new Agent($client, $config, $logger);
        $this->agent->withName($this->name);

        // Register tools
        if (isset($options['tools']) && is_array($options['tools'])) {
            $this->agent->withTools($options['tools']);
        }
    }

    /**
     * Add a tool to the agent.
     */
    public function addTool(ToolInterface $tool): self
    {
        $this->agent->withTool($tool);

        return $this;
    }

    /**
     * Set iteration callback.
     */
    public function onIteration(callable $callback): self
    {
        $this->agent->onIteration($callback);

        return $this;
    }

    /**
     * Set tool execution callback.
     */
    public function onToolExecution(callable $callback): self
    {
        $this->agent->onToolExecution($callback);

        return $this;
    }

    public function run(string $task): AgentResult
    {
        return $this->agent->run($task);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the underlying agent instance.
     */
    public function getAgent(): Agent
    {
        return $this->agent;
    }
}
