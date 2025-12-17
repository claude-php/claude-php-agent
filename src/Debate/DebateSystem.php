<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate;

use ClaudeAgents\Exceptions\ConfigurationException;
use ClaudeAgents\ML\Traits\LearnableAgent;
use ClaudeAgents\ML\Traits\ParameterOptimizer;
use ClaudePhp\ClaudePhp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Orchestrates multi-agent debates with ML-enhanced optimization.
 *
 * **ML-Enhanced Features:**
 * - Learns optimal number of debate rounds
 * - Learns optimal agent speaking order
 * - Learns when consensus is achieved (early stopping)
 * - Predicts debate quality based on topic
 *
 * @package ClaudeAgents\Debate
 */
class DebateSystem
{
    use LearnableAgent;
    use ParameterOptimizer;

    /**
     * @var array<string, DebateAgent>
     */
    private array $agents = [];

    private DebateModerator $moderator;
    private int $rounds = 2;
    private LoggerInterface $logger;
    private bool $useMLOptimization = false;
    private bool $enableEarlyStopping = true;

    public function __construct(
        private readonly ClaudePhp $client,
        array $options = [],
    ) {
        $this->moderator = new DebateModerator($client, $options);
        $this->logger = $options['logger'] ?? new NullLogger();
        $this->rounds = $options['rounds'] ?? 2;
        $this->enableEarlyStopping = $options['enable_early_stopping'] ?? true;
        $this->useMLOptimization = $options['enable_ml_optimization'] ?? false;

        // Enable ML features if requested
        if ($this->useMLOptimization) {
            $historyPath = $options['ml_history_path'] ?? 'storage/debate_history.json';
            
            $this->enableLearning($historyPath);
            
            $this->enableParameterOptimization(
                historyPath: str_replace('.json', '_params.json', $historyPath),
                defaults: [
                    'optimal_rounds' => $this->rounds,
                    'consensus_threshold' => 0.75,
                ]
            );
        }
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

        $startTime = microtime(true);

        // Learn optimal parameters if ML enabled
        $optimalRounds = $this->rounds;
        $consensusThreshold = 0.75;
        
        if ($this->useMLOptimization) {
            $params = $this->learnOptimalParameters($topic, ['optimal_rounds', 'consensus_threshold']);
            $optimalRounds = (int)($params['optimal_rounds'] ?? $this->rounds);
            $consensusThreshold = (float)($params['consensus_threshold'] ?? 0.75);
            
            $this->logger->info("ML-optimized debate parameters", [
                'optimal_rounds' => $optimalRounds,
                'consensus_threshold' => $consensusThreshold,
            ]);
        }

        $this->logger->info("Starting debate on: {$topic}");
        $this->logger->debug('Number of agents: ' . count($this->agents));
        $this->logger->debug("Number of rounds: {$optimalRounds}");

        $debateRounds = [];
        $sharedContext = '';
        $totalTokens = 0;
        $earlyStopRound = null;

        for ($roundNum = 1; $roundNum <= $optimalRounds; $roundNum++) {
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

            // Check for early consensus if enabled
            if ($this->enableEarlyStopping && $roundNum >= 2) {
                $allStatements = [];
                foreach ($debateRounds as $round) {
                    $allStatements = array_merge($allStatements, array_values($round->getStatements()));
                }
                $currentAgreement = $this->moderator->measureAgreement($allStatements);
                
                if ($currentAgreement >= $consensusThreshold) {
                    $earlyStopRound = $roundNum;
                    $this->logger->info("Early consensus reached at round {$roundNum} (agreement: " . round($currentAgreement * 100) . "%)");
                    break;
                }
            }
        }

        // Get moderator synthesis
        $this->logger->debug('Synthesizing debate');
        $synthesis = $this->moderator->synthesize($topic, $debateRounds);

        // Measure final agreement
        $allStatements = [];
        foreach ($debateRounds as $round) {
            $allStatements = array_merge($allStatements, array_values($round->getStatements()));
        }
        $agreementScore = $this->moderator->measureAgreement($allStatements);

        $duration = microtime(true) - $startTime;
        $actualRounds = count($debateRounds);

        $this->logger->info('Debate complete. Agreement score: ' . round($agreementScore * 100) . '%');

        // Record for ML learning
        if ($this->useMLOptimization) {
            $qualityScore = $this->evaluateDebateQuality($agreementScore, $actualRounds, $optimalRounds);
            $this->recordDebatePerformance(
                $topic,
                $actualRounds,
                $consensusThreshold,
                $agreementScore,
                $qualityScore,
                $duration,
                $earlyStopRound !== null
            );
        }

        $result = new DebateResult(
            topic: $topic,
            rounds: $debateRounds,
            synthesis: $synthesis,
            agreementScore: $agreementScore,
            totalTokens: $totalTokens,
        );

        return $result;
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

    /**
     * Record debate performance for learning.
     */
    private function recordDebatePerformance(
        string $topic,
        int $actualRounds,
        float $consensusThreshold,
        float $agreementScore,
        float $qualityScore,
        float $duration,
        bool $earlyStop
    ): void {
        $result = \ClaudeAgents\AgentResult::success(
            answer: "Debate on: {$topic}",
            messages: [],
            iterations: $actualRounds
        );
        
        $this->recordExecution($topic, $result, [
            'duration' => $duration,
            'agreement_score' => $agreementScore,
            'quality_score' => $qualityScore,
        ]);
        
        $this->recordParameterPerformance(
            $topic,
            parameters: [
                'optimal_rounds' => $actualRounds,
                'consensus_threshold' => $consensusThreshold,
            ],
            success: $agreementScore >= $consensusThreshold,
            qualityScore: $qualityScore,
            duration: $duration
        );
    }

    /**
     * Evaluate debate quality based on agreement and efficiency.
     */
    private function evaluateDebateQuality(float $agreementScore, int $actualRounds, int $plannedRounds): float
    {
        // Base score from agreement
        $agreementPoints = $agreementScore * 5.0;
        
        // Efficiency bonus (finishing earlier is better)
        $efficiencyRatio = $actualRounds / max(1, $plannedRounds);
        $efficiencyPoints = match(true) {
            $efficiencyRatio <= 0.5 => 3.0,  // Very efficient
            $efficiencyRatio <= 0.75 => 2.0, // Efficient
            $efficiencyRatio <= 1.0 => 1.0,  // Normal
            default => 0.5, // Took longer than planned
        };
        
        // Agreement quality bonus
        $agreementBonus = match(true) {
            $agreementScore >= 0.9 => 2.0,
            $agreementScore >= 0.75 => 1.5,
            $agreementScore >= 0.5 => 1.0,
            default => 0.0,
        };
        
        return min(10.0, $agreementPoints + $efficiencyPoints + $agreementBonus);
    }

    /**
     * Override to analyze debate topics for learning.
     */
    protected function analyzeTaskForLearning(string $task): array
    {
        $wordCount = str_word_count($task);
        $hasControversy = preg_match('/\b(debate|argue|vs|versus|compare|contrast)\b/i', $task);

        return [
            'complexity' => match (true) {
                $wordCount > 30 => 'complex',
                $wordCount > 15 => 'medium',
                default => 'simple',
            },
            'domain' => 'debate',
            'requires_tools' => false,
            'requires_knowledge' => true,
            'requires_reasoning' => true,
            'requires_iteration' => true,
            'requires_quality' => 'high',
            'estimated_steps' => $this->rounds,
            'key_requirements' => $hasControversy ? ['argumentation', 'consensus'] : ['discussion', 'synthesis'],
        ];
    }

    /**
     * Get agent identifier for learning traits.
     */
    protected function getAgentIdentifier(): string
    {
        return 'debate_system';
    }
}
