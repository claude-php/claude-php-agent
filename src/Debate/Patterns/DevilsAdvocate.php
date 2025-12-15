<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate\Patterns;

use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;
use ClaudePhp\ClaudePhp;

/**
 * Devil's advocate pattern for challenging proposals.
 */
class DevilsAdvocate
{
    /**
     * Create a devil's advocate debate to stress-test a proposal.
     *
     * @param ClaudePhp $client Claude API client
     * @param int $rounds Number of challenge/response rounds
     * @return DebateSystem Configured debate system
     */
    public static function create(ClaudePhp $client, int $rounds = 2): DebateSystem
    {
        $proposer = new DebateAgent(
            $client,
            'Proposer',
            'advocate',
            'You advocate for the proposal. Explain why it should be adopted and defend it against challenges.'
        );

        $devilsAdvocate = new DebateAgent(
            $client,
            "Devil's Advocate",
            'challenger',
            'You challenge everything. Find flaws, identify risks, question assumptions, and stress-test ideas by being highly skeptical.'
        );

        return DebateSystem::create($client)
            ->addAgent('proposer', $proposer)
            ->addAgent('devil', $devilsAdvocate)
            ->rounds($rounds);
    }
}
