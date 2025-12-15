<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for agents participating in debates.
 */
interface DebateAgentInterface
{
    /**
     * Get the agent's name/role.
     */
    public function getName(): string;

    /**
     * Get the agent's perspective/stance.
     */
    public function getPerspective(): string;

    /**
     * Get the agent's system prompt.
     */
    public function getSystemPrompt(): string;

    /**
     * Speak/provide statement on a topic.
     *
     * @param string $topic The topic or question
     * @param string $context Previous discussion context
     * @param string $instruction Special instruction for this turn
     * @return string The agent's statement
     */
    public function speak(string $topic, string $context = '', string $instruction = ''): string;
}
