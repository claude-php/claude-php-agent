<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate\Patterns;

use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;
use ClaudePhp\ClaudePhp;

/**
 * Two-sided pro/con debate pattern.
 */
class ProConDebate
{
    /**
     * Create a pro/con debate on a topic.
     *
     * @param ClaudePhp $client Claude API client
     * @param string $topic The topic to debate
     * @param int $rounds Number of debate rounds
     * @return DebateSystem Configured debate system
     */
    public static function create(ClaudePhp $client, string $topic, int $rounds = 2): DebateSystem
    {
        $proAgent = new DebateAgent(
            $client,
            'Proponent',
            'support',
            'You advocate for the proposal. Present benefits, opportunities, and positive outcomes. Be persuasive but fair.'
        );

        $conAgent = new DebateAgent(
            $client,
            'Opponent',
            'oppose',
            'You challenge the proposal. Identify risks, drawbacks, and potential problems. Be critical but constructive.'
        );

        return DebateSystem::create($client)
            ->addAgent('pro', $proAgent)
            ->addAgent('con', $conAgent)
            ->rounds($rounds);
    }
}
