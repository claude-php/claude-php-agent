<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate;

/**
 * Result of a debate with synthesis and analysis.
 */
class DebateResult
{
    /**
     * @param string $topic The debate topic
     * @param array<DebateRound> $rounds All debate rounds
     * @param string $synthesis The moderator's synthesis
     * @param float $agreementScore Agreement score from 0.0 to 1.0
     * @param int $totalTokens Total tokens used
     */
    public function __construct(
        private readonly string $topic,
        private readonly array $rounds,
        private readonly string $synthesis,
        private readonly float $agreementScore,
        private readonly int $totalTokens = 0,
    ) {
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    /**
     * @return array<DebateRound>
     */
    public function getRounds(): array
    {
        return $this->rounds;
    }

    public function getSynthesis(): string
    {
        return $this->synthesis;
    }

    public function getAgreementScore(): float
    {
        return $this->agreementScore;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function getRoundCount(): int
    {
        return count($this->rounds);
    }

    /**
     * Get transcripts from all rounds.
     *
     * @return string Full debate transcript
     */
    public function getTranscript(): string
    {
        $transcript = "=== Debate: {$this->topic} ===\n\n";

        foreach ($this->rounds as $round) {
            $transcript .= "--- Round {$round->getRoundNumber()} ---\n";
            foreach ($round->getStatements() as $agent => $statement) {
                $transcript .= "\n{$agent}:\n{$statement}\n";
            }
            $transcript .= "\n";
        }

        return $transcript;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'rounds' => array_map(fn ($r) => $r->toArray(), $this->rounds),
            'synthesis' => $this->synthesis,
            'agreement_score' => $this->agreementScore,
            'round_count' => $this->getRoundCount(),
            'total_tokens' => $this->totalTokens,
        ];
    }
}
