<?php

declare(strict_types=1);

namespace ClaudeAgents\Debate\Patterns;

use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;
use ClaudePhp\ClaudePhp;

/**
 * Multi-agent round table discussion pattern.
 */
class RoundTableDebate
{
    /**
     * Create a round table discussion with multiple perspectives.
     *
     * @param ClaudePhp $client Claude API client
     * @param int $rounds Number of discussion rounds
     * @return DebateSystem Configured debate system
     */
    public static function create(ClaudePhp $client, int $rounds = 2): DebateSystem
    {
        $userAdvocate = new DebateAgent(
            $client,
            'User Advocate',
            'user-focused',
            'You represent user needs and experience. Prioritize what users want and need. Focus on usability and satisfaction.'
        );

        $engineer = new DebateAgent(
            $client,
            'Engineer',
            'technical',
            'You assess technical feasibility, complexity, and maintainability. Be pragmatic about what\'s possible.'
        );

        $businessAnalyst = new DebateAgent(
            $client,
            'Business Analyst',
            'business',
            'You analyze ROI, market fit, and business impact. Focus on bottom-line results and strategic value.'
        );

        $designer = new DebateAgent(
            $client,
            'Designer',
            'design',
            'You consider UX, design consistency, and platform capabilities. Think about the overall user experience.'
        );

        return DebateSystem::create($client)
            ->addAgent('user', $userAdvocate)
            ->addAgent('engineer', $engineer)
            ->addAgent('business', $businessAnalyst)
            ->addAgent('designer', $designer)
            ->rounds($rounds);
    }
}
