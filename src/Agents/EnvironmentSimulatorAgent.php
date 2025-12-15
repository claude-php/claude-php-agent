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
 * Environment Simulator Agent - Models external environments for planning and testing.
 */
class EnvironmentSimulatorAgent implements AgentInterface
{
    private ClaudePhp $client;
    private string $name;
    private array $environmentState = [];
    private array $simulationHistory = [];
    private LoggerInterface $logger;

    public function __construct(ClaudePhp $client, array $options = [])
    {
        $this->client = $client;
        $this->name = $options['name'] ?? 'environment_simulator';
        $this->environmentState = $options['initial_state'] ?? [];
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    public function run(string $task): AgentResult
    {
        $this->logger->info("Simulating: {$task}");

        try {
            $simulation = $this->simulateAction($task);

            return AgentResult::success(
                answer: $simulation['description'],
                messages: [],
                iterations: 1,
                metadata: [
                    'initial_state' => $simulation['initial_state'],
                    'action' => $task,
                    'resulting_state' => $simulation['resulting_state'],
                    'outcome' => $simulation['outcome'],
                ],
            );
        } catch (\Throwable $e) {
            return AgentResult::failure(error: $e->getMessage());
        }
    }

    public function simulateAction(string $action): array
    {
        $initialState = $this->environmentState;

        $prompt = <<<PROMPT
            Environment state: {$this->formatState($initialState)}

            Action to simulate: {$action}

            Simulate the action and predict:
            1. Resulting environment state (JSON)
            2. Outcome description
            3. Any side effects or risks

            Respond in JSON format:
            {
              "resulting_state": {...},
              "outcome": "description",
              "side_effects": ["effect1", "effect2"],
              "success_probability": 0.0-1.0
            }
            PROMPT;

        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 1024,
            'system' => 'You are an environment simulator. Predict outcomes of actions. Always respond with valid JSON only, no additional text or markdown.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $json = TextContentExtractor::extractFromResponse($response);

        // Extract JSON from markdown code blocks if present
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $json, $matches)) {
            $json = $matches[1];
        } elseif (preg_match('/(\{.*\})/s', $json, $matches)) {
            $json = $matches[1];
        }

        $result = json_decode($json, true) ?: [];

        $simulation = [
            'initial_state' => $initialState,
            'action' => $action,
            'resulting_state' => $result['resulting_state'] ?? $initialState,
            'outcome' => $result['outcome'] ?? 'Unknown outcome',
            'side_effects' => $result['side_effects'] ?? [],
            'success_probability' => $result['success_probability'] ?? 0.5,
            'description' => $this->formatSimulation($result),
        ];

        $this->simulationHistory[] = $simulation;

        return $simulation;
    }

    public function setState(array $state): void
    {
        $this->environmentState = $state;
    }

    public function getState(): array
    {
        return $this->environmentState;
    }

    private function formatState(array $state): string
    {
        return json_encode($state, JSON_PRETTY_PRINT);
    }

    private function formatSimulation(array $result): string
    {
        $output = "Simulation Result\n";
        $output .= "=================\n\n";
        $output .= "Outcome: {$result['outcome']}\n";
        $output .= 'Success Probability: ' . ($result['success_probability'] * 100) . "%\n";

        if (! empty($result['side_effects'])) {
            $output .= "\nSide Effects:\n";
            foreach ($result['side_effects'] as $effect) {
                $output .= "  - {$effect}\n";
            }
        }

        return $output;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
