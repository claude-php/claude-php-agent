<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

/**
 * Interface for skill resolvers that match skills to user queries.
 */
interface SkillResolverInterface
{
    /**
     * Resolve which skills are relevant for the given input.
     *
     * @param string $input User input or task description
     * @param float $threshold Minimum relevance score (0.0-1.0)
     * @return SkillInterface[] Matched skills sorted by relevance
     */
    public function resolve(string $input, float $threshold = 0.3): array;

    /**
     * Find the single most relevant skill for the given input.
     */
    public function resolveOne(string $input): ?SkillInterface;
}
