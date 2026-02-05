<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;
use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillMetadata;
use ClaudeAgents\Skills\SkillRegistry;
use PHPUnit\Framework\TestCase;

class SkillRegistryTest extends TestCase
{
    private SkillRegistry $registry;
    private Skill $skill1;
    private Skill $skill2;
    private Skill $skill3;

    protected function setUp(): void
    {
        $this->registry = new SkillRegistry();

        $this->skill1 = new Skill(
            metadata: new SkillMetadata(
                name: 'code-review',
                description: 'Review code for quality',
                metadata: ['tags' => ['php', 'quality']],
            ),
            instructions: 'Review instructions',
            path: '/skills/code-review',
        );

        $this->skill2 = new Skill(
            metadata: new SkillMetadata(
                name: 'api-testing',
                description: 'Test REST APIs',
                metadata: ['tags' => ['api', 'testing']],
            ),
            instructions: 'API testing instructions',
            path: '/skills/api-testing',
        );

        $this->skill3 = new Skill(
            metadata: new SkillMetadata(
                name: 'debug-mode',
                description: 'Enable debug mode',
                mode: true,
            ),
            instructions: 'Debug mode instructions',
            path: '/skills/debug-mode',
        );
    }

    public function test_register_and_get(): void
    {
        $this->registry->register($this->skill1);

        $skill = $this->registry->get('code-review');

        $this->assertSame($this->skill1, $skill);
    }

    public function test_register_many(): void
    {
        $this->registry->registerMany([$this->skill1, $this->skill2]);

        $this->assertEquals(2, $this->registry->count());
        $this->assertTrue($this->registry->has('code-review'));
        $this->assertTrue($this->registry->has('api-testing'));
    }

    public function test_has(): void
    {
        $this->assertFalse($this->registry->has('code-review'));

        $this->registry->register($this->skill1);

        $this->assertTrue($this->registry->has('code-review'));
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function test_get_throws_on_not_found(): void
    {
        $this->expectException(SkillNotFoundException::class);

        $this->registry->get('nonexistent');
    }

    public function test_unregister(): void
    {
        $this->registry->register($this->skill1);
        $this->assertTrue($this->registry->has('code-review'));

        $this->registry->unregister('code-review');
        $this->assertFalse($this->registry->has('code-review'));
    }

    public function test_unregister_throws_on_not_found(): void
    {
        $this->expectException(SkillNotFoundException::class);

        $this->registry->unregister('nonexistent');
    }

    public function test_all(): void
    {
        $this->registry->registerMany([$this->skill1, $this->skill2]);

        $all = $this->registry->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('code-review', $all);
        $this->assertArrayHasKey('api-testing', $all);
    }

    public function test_count(): void
    {
        $this->assertEquals(0, $this->registry->count());

        $this->registry->register($this->skill1);
        $this->assertEquals(1, $this->registry->count());

        $this->registry->register($this->skill2);
        $this->assertEquals(2, $this->registry->count());
    }

    public function test_search(): void
    {
        $this->registry->registerMany([$this->skill1, $this->skill2]);

        $results = $this->registry->search('code');
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('code-review', $results);

        $results = $this->registry->search('api');
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('api-testing', $results);

        $results = $this->registry->search('nonexistent');
        $this->assertCount(0, $results);
    }

    public function test_names(): void
    {
        $this->registry->registerMany([$this->skill1, $this->skill2]);

        $names = $this->registry->names();

        $this->assertContains('code-review', $names);
        $this->assertContains('api-testing', $names);
    }

    public function test_summaries(): void
    {
        $this->registry->registerMany([$this->skill1, $this->skill2]);

        $summaries = $this->registry->summaries();

        $this->assertCount(2, $summaries);
        $this->assertEquals('code-review', $summaries['code-review']['name']);
        $this->assertEquals('Review code for quality', $summaries['code-review']['description']);
    }

    public function test_get_auto_invocable(): void
    {
        $manualSkill = new Skill(
            metadata: new SkillMetadata(
                name: 'manual-only',
                description: 'Manual invocation only',
                disableModelInvocation: true,
            ),
            instructions: 'test',
            path: '',
        );

        $this->registry->registerMany([$this->skill1, $manualSkill]);

        $autoInvocable = $this->registry->getAutoInvocable();

        $this->assertCount(1, $autoInvocable);
        $this->assertArrayHasKey('code-review', $autoInvocable);
    }

    public function test_get_modes(): void
    {
        $this->registry->registerMany([$this->skill1, $this->skill2, $this->skill3]);

        $modes = $this->registry->getModes();

        $this->assertCount(1, $modes);
        $this->assertArrayHasKey('debug-mode', $modes);
    }

    public function test_clear(): void
    {
        $this->registry->registerMany([$this->skill1, $this->skill2]);
        $this->assertEquals(2, $this->registry->count());

        $this->registry->clear();
        $this->assertEquals(0, $this->registry->count());
    }

    public function test_overwrites_on_re_register(): void
    {
        $this->registry->register($this->skill1);

        $newSkill = new Skill(
            metadata: new SkillMetadata(
                name: 'code-review',
                description: 'Updated description',
            ),
            instructions: 'Updated instructions',
            path: '/new/path',
        );

        $this->registry->register($newSkill);

        $this->assertEquals(1, $this->registry->count());
        $this->assertEquals('Updated description', $this->registry->get('code-review')->getDescription());
    }
}
