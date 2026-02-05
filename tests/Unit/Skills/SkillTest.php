<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillMetadata;
use PHPUnit\Framework\TestCase;

class SkillTest extends TestCase
{
    private Skill $skill;

    protected function setUp(): void
    {
        $this->skill = new Skill(
            metadata: new SkillMetadata(
                name: 'test-skill',
                description: 'A test skill for reviewing code',
                license: 'MIT',
                version: '1.0.0',
                metadata: ['author' => 'tester', 'tags' => ['php', 'code-review']],
            ),
            instructions: "# Test Skill\n\nInstructions here.",
            path: '/tmp/test-skill',
            scripts: ['script.php'],
            references: ['guide.md'],
            assets: ['template.json'],
        );
    }

    public function test_get_name(): void
    {
        $this->assertEquals('test-skill', $this->skill->getName());
    }

    public function test_get_description(): void
    {
        $this->assertEquals('A test skill for reviewing code', $this->skill->getDescription());
    }

    public function test_get_metadata(): void
    {
        $metadata = $this->skill->getMetadata();

        $this->assertInstanceOf(SkillMetadata::class, $metadata);
        $this->assertEquals('test-skill', $metadata->name);
        $this->assertEquals('MIT', $metadata->license);
    }

    public function test_get_instructions(): void
    {
        $this->assertStringContainsString('# Test Skill', $this->skill->getInstructions());
        $this->assertStringContainsString('Instructions here.', $this->skill->getInstructions());
    }

    public function test_get_path(): void
    {
        $this->assertEquals('/tmp/test-skill', $this->skill->getPath());
    }

    public function test_get_scripts(): void
    {
        $this->assertEquals(['script.php'], $this->skill->getScripts());
    }

    public function test_get_references(): void
    {
        $this->assertEquals(['guide.md'], $this->skill->getReferences());
    }

    public function test_get_assets(): void
    {
        $this->assertEquals(['template.json'], $this->skill->getAssets());
    }

    public function test_is_loaded_default(): void
    {
        $this->assertFalse($this->skill->isLoaded());
    }

    public function test_mark_loaded(): void
    {
        $this->skill->markLoaded();
        $this->assertTrue($this->skill->isLoaded());
    }

    public function test_is_auto_invocable_default(): void
    {
        $this->assertTrue($this->skill->isAutoInvocable());
    }

    public function test_is_auto_invocable_when_disabled(): void
    {
        $skill = new Skill(
            metadata: new SkillMetadata(
                name: 'manual',
                description: 'manual only',
                disableModelInvocation: true,
            ),
            instructions: 'test',
            path: '',
        );

        $this->assertFalse($skill->isAutoInvocable());
    }

    public function test_is_mode(): void
    {
        $skill = new Skill(
            metadata: new SkillMetadata(
                name: 'mode-test',
                description: 'a mode',
                mode: true,
            ),
            instructions: 'test',
            path: '',
        );

        $this->assertTrue($skill->isMode());
    }

    public function test_matches_query_by_name(): void
    {
        $this->assertTrue($this->skill->matchesQuery('test'));
        $this->assertTrue($this->skill->matchesQuery('skill'));
        $this->assertTrue($this->skill->matchesQuery('test-skill'));
    }

    public function test_matches_query_by_description(): void
    {
        $this->assertTrue($this->skill->matchesQuery('reviewing'));
        $this->assertTrue($this->skill->matchesQuery('code'));
    }

    public function test_matches_query_by_tags(): void
    {
        $this->assertTrue($this->skill->matchesQuery('php'));
        $this->assertTrue($this->skill->matchesQuery('code-review'));
    }

    public function test_does_not_match_unrelated_query(): void
    {
        $this->assertFalse($this->skill->matchesQuery('unrelated'));
        $this->assertFalse($this->skill->matchesQuery('database'));
    }

    public function test_relevance_score_high_for_name_match(): void
    {
        $score = $this->skill->relevanceScore('test-skill');

        $this->assertGreaterThan(0.5, $score);
    }

    public function test_relevance_score_medium_for_description_match(): void
    {
        $score = $this->skill->relevanceScore('reviewing');

        $this->assertGreaterThan(0.3, $score);
    }

    public function test_relevance_score_lower_for_tag_match(): void
    {
        $score = $this->skill->relevanceScore('php');
        // Should match via name or description (both contain relevance)
        $this->assertGreaterThan(0.0, $score);
    }

    public function test_relevance_score_zero_for_no_match(): void
    {
        $score = $this->skill->relevanceScore('unrelated query');

        $this->assertEquals(0.0, $score);
    }

    public function test_to_array(): void
    {
        $array = $this->skill->toArray();

        $this->assertEquals('test-skill', $array['name']);
        $this->assertEquals('A test skill for reviewing code', $array['description']);
        $this->assertEquals('/tmp/test-skill', $array['path']);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('instructions', $array);
        $this->assertEquals(['script.php'], $array['scripts']);
        $this->assertEquals(['guide.md'], $array['references']);
        $this->assertEquals(['template.json'], $array['assets']);
        $this->assertFalse($array['loaded']);
    }

    public function test_get_summary(): void
    {
        $summary = $this->skill->getSummary();

        $this->assertEquals('test-skill', $summary['name']);
        $this->assertEquals('A test skill for reviewing code', $summary['description']);
        $this->assertCount(2, $summary);
    }

    public function test_from_markdown(): void
    {
        $content = <<<MD
---
name: from-md
description: Created from markdown
license: MIT
---

# From Markdown

Instructions.
MD;

        $skill = Skill::fromMarkdown($content, '/tmp/from-md');

        $this->assertEquals('from-md', $skill->getName());
        $this->assertEquals('Created from markdown', $skill->getDescription());
        $this->assertStringContainsString('# From Markdown', $skill->getInstructions());
        $this->assertEquals('/tmp/from-md', $skill->getPath());
    }

    public function test_create_with_resource_discovery(): void
    {
        // Create a temporary skill directory
        $tmpDir = sys_get_temp_dir() . '/test-skill-discovery-' . uniqid();
        mkdir($tmpDir . '/scripts', 0755, true);
        mkdir($tmpDir . '/references', 0755, true);
        mkdir($tmpDir . '/assets', 0755, true);
        file_put_contents($tmpDir . '/scripts/test.php', '<?php echo "test";');
        file_put_contents($tmpDir . '/references/guide.md', '# Guide');
        file_put_contents($tmpDir . '/assets/data.json', '{}');

        $metadata = new SkillMetadata(
            name: 'discovery-test',
            description: 'test discovery',
        );

        $skill = Skill::create($tmpDir, $metadata, 'Instructions');

        $this->assertContains('test.php', $skill->getScripts());
        $this->assertContains('guide.md', $skill->getReferences());
        $this->assertContains('data.json', $skill->getAssets());

        // Cleanup
        unlink($tmpDir . '/scripts/test.php');
        unlink($tmpDir . '/references/guide.md');
        unlink($tmpDir . '/assets/data.json');
        rmdir($tmpDir . '/scripts');
        rmdir($tmpDir . '/references');
        rmdir($tmpDir . '/assets');
        rmdir($tmpDir);
    }

    public function test_get_reference_returns_content(): void
    {
        $tmpDir = sys_get_temp_dir() . '/test-skill-ref-' . uniqid();
        mkdir($tmpDir . '/references', 0755, true);
        file_put_contents($tmpDir . '/references/test.md', '# Test Reference');

        $skill = new Skill(
            metadata: new SkillMetadata(name: 'ref-test', description: 'test'),
            instructions: 'test',
            path: $tmpDir,
            references: ['test.md'],
        );

        $content = $skill->getReference('test.md');
        $this->assertEquals('# Test Reference', $content);

        // Missing reference
        $this->assertNull($skill->getReference('missing.md'));

        // Cleanup
        unlink($tmpDir . '/references/test.md');
        rmdir($tmpDir . '/references');
        rmdir($tmpDir);
    }

    public function test_get_script_returns_content(): void
    {
        $tmpDir = sys_get_temp_dir() . '/test-skill-script-' . uniqid();
        mkdir($tmpDir . '/scripts', 0755, true);
        file_put_contents($tmpDir . '/scripts/test.php', '<?php echo "hello";');

        $skill = new Skill(
            metadata: new SkillMetadata(name: 'script-test', description: 'test'),
            instructions: 'test',
            path: $tmpDir,
            scripts: ['test.php'],
        );

        $content = $skill->getScript('test.php');
        $this->assertStringContainsString('echo "hello"', $content);

        $this->assertNull($skill->getScript('missing.php'));

        // Cleanup
        unlink($tmpDir . '/scripts/test.php');
        rmdir($tmpDir . '/scripts');
        rmdir($tmpDir);
    }
}
