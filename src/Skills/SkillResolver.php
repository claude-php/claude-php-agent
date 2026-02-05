<?php

declare(strict_types=1);

namespace ClaudeAgents\Skills;

use ClaudeAgents\Contracts\SkillInterface;
use ClaudeAgents\Contracts\SkillRegistryInterface;
use ClaudeAgents\Contracts\SkillResolverInterface;

/**
 * Resolves which skills are relevant for a given user input.
 *
 * Uses keyword matching and relevance scoring to determine
 * which skills should be loaded for a task. Supports progressive
 * disclosure by only returning skills above a threshold.
 */
class SkillResolver implements SkillResolverInterface
{
    public function __construct(
        private SkillRegistryInterface $registry,
    ) {
    }

    /**
     * Resolve which skills are relevant for the given input.
     *
     * @param string $input User input or task description
     * @param float $threshold Minimum relevance score (0.0-1.0)
     * @return SkillInterface[] Matched skills sorted by relevance (highest first)
     */
    public function resolve(string $input, float $threshold = 0.3): array
    {
        $scored = [];

        foreach ($this->registry->all() as $name => $skill) {
            // Skip non-auto-invocable skills
            if ($skill instanceof Skill && !$skill->isAutoInvocable()) {
                continue;
            }

            $score = $skill->relevanceScore($input);
            if ($score >= $threshold) {
                $scored[] = ['skill' => $skill, 'score' => $score];
            }
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($item) => $item['skill'], $scored);
    }

    /**
     * Find the single most relevant skill for the given input.
     */
    public function resolveOne(string $input): ?SkillInterface
    {
        $matches = $this->resolve($input);

        return $matches[0] ?? null;
    }

    /**
     * Resolve skills with scores for debugging/inspection.
     *
     * @return array<int, array{skill: SkillInterface, score: float}>
     */
    public function resolveWithScores(string $input, float $threshold = 0.3): array
    {
        $scored = [];

        foreach ($this->registry->all() as $name => $skill) {
            $score = $skill->relevanceScore($input);
            if ($score >= $threshold) {
                $scored[] = ['skill' => $skill, 'score' => $score];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * Resolve a skill by exact name.
     */
    public function resolveByName(string $name): ?SkillInterface
    {
        if ($this->registry->has($name)) {
            return $this->registry->get($name);
        }

        return null;
    }
}
