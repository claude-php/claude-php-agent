<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate\Patterns;

use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;
use ClaudePhp\ClaudePhp;

/**
 * Consensus building pattern for finding common ground.
 */
class ConsensusBuilder
{
    /**
     * Create a consensus-building discussion with pragmatic participants.
     *
     * @param ClaudePhp $client Claude API client
     * @param int $rounds Number of discussion rounds
     * @return DebateSystem Configured debate system
     */
    public static function create(ClaudePhp $client, int $rounds = 3): DebateSystem
    {
        $pragmatist = new DebateAgent(
            $client,
            'Pragmatist',
            'practical',
            'You balance multiple concerns. You look for practical solutions that work well enough and can be implemented.'
        );

        $idealist = new DebateAgent(
            $client,
            'Idealist',
            'vision',
            'You focus on optimal solutions and long-term vision. You want to do things right, not just get them done.'
        );

        $mediator = new DebateAgent(
            $client,
            'Mediator',
            'consensus',
            'You facilitate understanding and find common ground. You help bridge gaps and build agreement among different viewpoints.'
        );

        return DebateSystem::create($client)
            ->addAgent('pragmatist', $pragmatist)
            ->addAgent('idealist', $idealist)
            ->addAgent('mediator', $mediator)
            ->rounds($rounds);
    }
}
