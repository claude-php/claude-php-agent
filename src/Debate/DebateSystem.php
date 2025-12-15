<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate;

use ClaudeAgents\Exceptions\ConfigurationException;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Orchestrates multi-agent debates.
 */
class DebateSystem
{
    /**
     * @var array<string, DebateAgent>
     */
    private array $agents = [];

    private DebateModerator $moderator;
    private int $rounds = 2;
    private LoggerInterface $logger;

    public function __construct(
        private readonly ClaudePhp $client,
        array $options = [],
    ) {
        $this->moderator = new DebateModerator($client, $options);
        $this->logger = $options['logger'] ?? new NullLogger();
    }

    /**
     * Create a new debate system.
     */
    public static function create(ClaudePhp $client, array $options = []): self
    {
        return new self($client, $options);
    }

    /**
     * Add a debate agent.
     *
     * @param string $id Unique agent identifier
     * @return self
     */
    public function addAgent(string $id, DebateAgent $agent): self
    {
        $this->agents[$id] = $agent;

        return $this;
    }

    /**
     * Add multiple agents.
     *
     * @param array<string, DebateAgent> $agents Map of ID to agent
     * @return self
     */
    public function addAgents(array $agents): self
    {
        foreach ($agents as $id => $agent) {
            $this->addAgent($id, $agent);
        }

        return $this;
    }

    /**
     * Set number of debate rounds.
     *
     * @return self
     */
    public function rounds(int $roundCount): self
    {
        $this->rounds = max(1, $roundCount);

        return $this;
    }

    /**
     * Conduct the debate.
     *
     * @param string $topic The debate topic/question
     * @return DebateResult The debate outcome
     */
    public function debate(string $topic): DebateResult
    {
        if (empty($this->agents)) {
            throw new ConfigurationException('No agents added to debate system', 'agents');
        }

        $this->logger->info("Starting debate on: {$topic}");
        $this->logger->debug('Number of agents: ' . count($this->agents));
        $this->logger->debug("Number of rounds: {$this->rounds}");

        $debateRounds = [];
        $sharedContext = '';
        $totalTokens = 0;

        for ($roundNum = 1; $roundNum <= $this->rounds; $roundNum++) {
            $this->logger->debug("Debate round {$roundNum}");

            $roundStatements = [];

            foreach ($this->agents as $agentId => $agent) {
                $instruction = $roundNum === 1
                    ? 'Share your initial perspective on this topic.'
                    : "Respond to others' points and add new insights.";

                $statement = $agent->speak($topic, $sharedContext, $instruction);
                $roundStatements[$agent->getName()] = $statement;

                // Add to shared context for next turns
                $sharedContext .= "\n{$agent->getName()}: {$statement}\n";
            }

            $debateRounds[] = new DebateRound($roundNum, $roundStatements);
        }

        // Get moderator synthesis
        $this->logger->debug('Synthesizing debate');
        $synthesis = $this->moderator->synthesize($topic, $debateRounds);

        // Measure agreement
        $allStatements = [];
        foreach ($debateRounds as $round) {
            $allStatements = array_merge($allStatements, array_values($round->getStatements()));
        }
        $agreementScore = $this->moderator->measureAgreement($allStatements);

        $this->logger->info('Debate complete. Agreement score: ' . round($agreementScore * 100) . '%');

        return new DebateResult(
            topic: $topic,
            rounds: $debateRounds,
            synthesis: $synthesis,
            agreementScore: $agreementScore,
            totalTokens: $totalTokens,
        );
    }

    /**
     * Get the moderator.
     */
    public function getModerator(): DebateModerator
    {
        return $this->moderator;
    }

    /**
     * Get all agents.
     *
     * @return array<string, DebateAgent>
     */
    public function getAgents(): array
    {
        return $this->agents;
    }

    /**
     * Get agent count.
     */
    public function getAgentCount(): int
    {
        return count($this->agents);
    }
}
