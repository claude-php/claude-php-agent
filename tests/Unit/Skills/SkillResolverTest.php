<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillMetadata;
use ClaudeAgents\Skills\SkillRegistry;
use ClaudeAgents\Skills\SkillResolver;
use PHPUnit\Framework\TestCase;

class SkillResolverTest extends TestCase
{
    private SkillRegistry $registry;
    private SkillResolver $resolver;

    protected function setUp(): void
    {
        $this->registry = new SkillRegistry();
        $this->resolver = new SkillResolver($this->registry);

        $this->registry->registerMany([
            new Skill(
                metadata: new SkillMetadata(
                    name: 'code-review',
                    description: 'Review PHP code for quality and security',
                    metadata: ['tags' => ['php', 'review', 'security']],
                ),
                instructions: 'Code review instructions',
                path: '',
            ),
            new Skill(
                metadata: new SkillMetadata(
                    name: 'api-testing',
                    description: 'Test REST API endpoints',
                    metadata: ['tags' => ['api', 'testing', 'rest']],
                ),
                instructions: 'API testing instructions',
                path: '',
            ),
            new Skill(
                metadata: new SkillMetadata(
                    name: 'data-analysis',
                    description: 'Analyze datasets and generate statistics',
                    metadata: ['tags' => ['data', 'analysis', 'statistics']],
                ),
                instructions: 'Data analysis instructions',
                path: '',
            ),
            new Skill(
                metadata: new SkillMetadata(
                    name: 'manual-skill',
                    description: 'A manual-only skill',
                    disableModelInvocation: true,
                ),
                instructions: 'Manual skill',
                path: '',
            ),
        ]);
    }

    public function test_resolve_returns_matching_skills(): void
    {
        $results = $this->resolver->resolve('review code');

        $this->assertNotEmpty($results);
        $this->assertEquals('code-review', $results[0]->getName());
    }

    public function test_resolve_sorts_by_relevance(): void
    {
        $results = $this->resolver->resolve('code review');

        // code-review should be first (matches name)
        $this->assertNotEmpty($results);
        $names = array_map(fn($s) => $s->getName(), $results);
        $this->assertEquals('code-review', $names[0]);
    }

    public function test_resolve_respects_threshold(): void
    {
        $lowThreshold = $this->resolver->resolve('code', 0.1);
        $highThreshold = $this->resolver->resolve('code', 0.9);

        $this->assertGreaterThanOrEqual(count($highThreshold), count($lowThreshold));
    }

    public function test_resolve_skips_disabled_skills(): void
    {
        $results = $this->resolver->resolve('manual skill');

        $names = array_map(fn($s) => $s->getName(), $results);
        $this->assertNotContains('manual-skill', $names);
    }

    public function test_resolve_one_returns_best_match(): void
    {
        $result = $this->resolver->resolveOne('API testing');

        $this->assertNotNull($result);
        $this->assertEquals('api-testing', $result->getName());
    }

    public function test_resolve_one_returns_null_for_no_match(): void
    {
        $result = $this->resolver->resolveOne('completely unrelated topic');

        $this->assertNull($result);
    }

    public function test_resolve_with_scores(): void
    {
        $results = $this->resolver->resolveWithScores('code review');

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('skill', $results[0]);
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertGreaterThan(0, $results[0]['score']);
    }

    public function test_resolve_with_scores_sorted_descending(): void
    {
        $results = $this->resolver->resolveWithScores('code', 0.1);

        $this->assertNotEmpty($results, 'Expected at least one result');

        for ($i = 0; $i < count($results) - 1; $i++) {
            $this->assertGreaterThanOrEqual($results[$i + 1]['score'], $results[$i]['score']);
        }
    }

    public function test_resolve_by_name(): void
    {
        $result = $this->resolver->resolveByName('api-testing');

        $this->assertNotNull($result);
        $this->assertEquals('api-testing', $result->getName());
    }

    public function test_resolve_by_name_returns_null(): void
    {
        $result = $this->resolver->resolveByName('nonexistent');

        $this->assertNull($result);
    }

    public function test_resolve_empty_input(): void
    {
        $results = $this->resolver->resolve('');

        // Empty input might match everything or nothing depending on implementation
        $this->assertIsArray($results);
    }

    public function test_resolve_multi_word_query(): void
    {
        $results = $this->resolver->resolve('analyze data statistics');

        $this->assertNotEmpty($results);
        $this->assertEquals('data-analysis', $results[0]->getName());
    }
}
