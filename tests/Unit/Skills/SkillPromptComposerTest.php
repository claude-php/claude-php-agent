<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillMetadata;
use ClaudeAgents\Skills\SkillPromptComposer;
use PHPUnit\Framework\TestCase;

class SkillPromptComposerTest extends TestCase
{
    private SkillPromptComposer $composer;

    protected function setUp(): void
    {
        $this->composer = new SkillPromptComposer();
    }

    public function test_compose_with_no_skills(): void
    {
        $result = $this->composer->compose('Base prompt', []);

        $this->assertEquals('Base prompt', $result);
    }

    public function test_compose_with_skills(): void
    {
        $skills = [
            new Skill(
                metadata: new SkillMetadata(name: 'test-skill', description: 'A test skill'),
                instructions: 'Follow these instructions.',
                path: '',
            ),
        ];

        $result = $this->composer->compose('Base prompt', $skills);

        $this->assertStringContainsString('Base prompt', $result);
        $this->assertStringContainsString('Active Skills', $result);
        $this->assertStringContainsString('test-skill', $result);
        $this->assertStringContainsString('A test skill', $result);
        $this->assertStringContainsString('Follow these instructions.', $result);
    }

    public function test_compose_with_multiple_skills(): void
    {
        $skills = [
            new Skill(
                metadata: new SkillMetadata(name: 'skill-1', description: 'First skill'),
                instructions: 'Skill 1 instructions.',
                path: '',
            ),
            new Skill(
                metadata: new SkillMetadata(name: 'skill-2', description: 'Second skill'),
                instructions: 'Skill 2 instructions.',
                path: '',
            ),
        ];

        $result = $this->composer->compose('Base', $skills);

        $this->assertStringContainsString('skill-1', $result);
        $this->assertStringContainsString('skill-2', $result);
        $this->assertStringContainsString('Skill 1 instructions.', $result);
        $this->assertStringContainsString('Skill 2 instructions.', $result);
    }

    public function test_compose_includes_resources(): void
    {
        $skills = [
            new Skill(
                metadata: new SkillMetadata(name: 'resource-skill', description: 'Has resources'),
                instructions: 'Instructions',
                path: '',
                scripts: ['helper.php', 'util.php'],
                references: ['guide.md'],
                assets: ['template.json'],
            ),
        ];

        $result = $this->composer->compose('Base', $skills);

        $this->assertStringContainsString('Available Resources', $result);
        $this->assertStringContainsString('Scripts', $result);
        $this->assertStringContainsString('helper.php', $result);
        $this->assertStringContainsString('References', $result);
        $this->assertStringContainsString('guide.md', $result);
    }

    public function test_build_skills_index(): void
    {
        $summaries = [
            'skill-1' => ['name' => 'skill-1', 'description' => 'First skill'],
            'skill-2' => ['name' => 'skill-2', 'description' => 'Second skill'],
        ];

        $result = $this->composer->buildSkillsIndex($summaries);

        $this->assertStringContainsString('Available Skills', $result);
        $this->assertStringContainsString('skill-1', $result);
        $this->assertStringContainsString('First skill', $result);
        $this->assertStringContainsString('skill-2', $result);
    }

    public function test_build_skills_index_empty(): void
    {
        $result = $this->composer->buildSkillsIndex([]);

        $this->assertEquals('', $result);
    }

    public function test_compose_with_discovery(): void
    {
        $loadedSkills = [
            new Skill(
                metadata: new SkillMetadata(name: 'loaded', description: 'Loaded skill'),
                instructions: 'Loaded instructions',
                path: '',
            ),
        ];

        $availableSummaries = [
            'loaded' => ['name' => 'loaded', 'description' => 'Loaded skill'],
            'unloaded' => ['name' => 'unloaded', 'description' => 'Available but unloaded'],
        ];

        $result = $this->composer->composeWithDiscovery(
            'Base prompt',
            $loadedSkills,
            $availableSummaries
        );

        $this->assertStringContainsString('Base prompt', $result);
        $this->assertStringContainsString('Active Skills', $result);
        $this->assertStringContainsString('Loaded instructions', $result);
        $this->assertStringContainsString('Available Skills', $result);
        $this->assertStringContainsString('unloaded', $result);
    }

    public function test_build_skills_section(): void
    {
        $skills = [
            new Skill(
                metadata: new SkillMetadata(name: 'section-test', description: 'Test section'),
                instructions: 'Section instructions',
                path: '',
            ),
        ];

        $result = $this->composer->buildSkillsSection($skills);

        $this->assertStringContainsString('### Skill: section-test', $result);
        $this->assertStringContainsString('**Description:** Test section', $result);
        $this->assertStringContainsString('Section instructions', $result);
    }
}
