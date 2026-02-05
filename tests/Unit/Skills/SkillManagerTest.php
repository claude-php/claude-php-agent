<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;
use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\SkillMetadata;
use PHPUnit\Framework\TestCase;

class SkillManagerTest extends TestCase
{
    private string $skillsDir;
    private SkillManager $manager;

    protected function setUp(): void
    {
        SkillManager::resetInstance();

        $this->skillsDir = sys_get_temp_dir() . '/test-skills-manager-' . uniqid();
        mkdir($this->skillsDir, 0755, true);

        // Create test skills
        $this->createSkillDir('review-tool', 'Review code for quality', 'Review instructions');
        $this->createSkillDir('test-runner', 'Run automated tests', 'Test runner instructions');
        $this->createSkillDir('debug-helper', 'Help debug issues', 'Debug instructions');

        $this->manager = new SkillManager($this->skillsDir);
    }

    protected function tearDown(): void
    {
        SkillManager::resetInstance();
        $this->removeDir($this->skillsDir);
    }

    public function test_discover_loads_all_skills(): void
    {
        $skills = $this->manager->discover();

        $this->assertCount(3, $skills);
        $this->assertArrayHasKey('review-tool', $skills);
        $this->assertArrayHasKey('test-runner', $skills);
        $this->assertArrayHasKey('debug-helper', $skills);
    }

    public function test_get_by_name(): void
    {
        $this->manager->discover();

        $skill = $this->manager->get('review-tool');

        $this->assertEquals('review-tool', $skill->getName());
        $this->assertEquals('Review code for quality', $skill->getDescription());
    }

    public function test_get_loads_on_demand(): void
    {
        // Don't call discover() - should load on demand
        $skill = $this->manager->get('review-tool');

        $this->assertEquals('review-tool', $skill->getName());
    }

    public function test_get_throws_on_not_found(): void
    {
        $this->expectException(SkillNotFoundException::class);

        $this->manager->get('nonexistent');
    }

    public function test_resolve_finds_relevant_skills(): void
    {
        $this->manager->discover();

        $results = $this->manager->resolve('review code');

        $this->assertNotEmpty($results);
        $names = array_map(fn($s) => $s->getName(), $results);
        $this->assertContains('review-tool', $names);
    }

    public function test_resolve_one(): void
    {
        $this->manager->discover();

        $result = $this->manager->resolveOne('automated tests');

        $this->assertNotNull($result);
        $this->assertEquals('test-runner', $result->getName());
    }

    public function test_search(): void
    {
        $this->manager->discover();

        $results = $this->manager->search('debug');

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('debug-helper', $results);
    }

    public function test_all(): void
    {
        $this->manager->discover();

        $all = $this->manager->all();

        $this->assertCount(3, $all);
    }

    public function test_summaries(): void
    {
        $this->manager->discover();

        $summaries = $this->manager->summaries();

        $this->assertCount(3, $summaries);
        $this->assertArrayHasKey('review-tool', $summaries);
        $this->assertEquals('Review code for quality', $summaries['review-tool']['description']);
    }

    public function test_register_programmatically(): void
    {
        $skill = new Skill(
            metadata: new SkillMetadata(name: 'custom', description: 'Custom skill'),
            instructions: 'Custom instructions',
            path: '',
        );

        $this->manager->register($skill);

        $retrieved = $this->manager->get('custom');
        $this->assertEquals('custom', $retrieved->getName());
    }

    public function test_register_from_markdown(): void
    {
        $content = <<<MD
---
name: md-skill
description: From markdown
---

Markdown skill instructions.
MD;

        $skill = $this->manager->registerFromMarkdown($content);

        $this->assertEquals('md-skill', $skill->getName());
        $this->assertEquals('md-skill', $this->manager->get('md-skill')->getName());
    }

    public function test_validate_content(): void
    {
        $content = "---\nname: valid\ndescription: test\n---\nBody";

        $result = $this->manager->validate($content);

        $this->assertTrue($result['valid']);
    }

    public function test_validate_invalid_content(): void
    {
        $result = $this->manager->validate("No frontmatter");

        $this->assertFalse($result['valid']);
    }

    public function test_validate_directory(): void
    {
        $skillDir = $this->skillsDir . '/review-tool';

        $result = $this->manager->validateDirectory($skillDir);

        $this->assertTrue($result['valid']);
    }

    public function test_count(): void
    {
        $this->manager->discover();

        $this->assertEquals(3, $this->manager->count());
    }

    public function test_add_path(): void
    {
        $extraDir = sys_get_temp_dir() . '/extra-skills-mgr-' . uniqid();
        $this->createSkillDir('extra-skill', 'Extra skill', 'Extra instructions', $extraDir);

        $this->manager->addPath($extraDir);
        $all = $this->manager->all();

        $this->assertArrayHasKey('extra-skill', $all);

        $this->removeDir($extraDir);
    }

    public function test_create(): void
    {
        $skill = $this->manager->create([
            'name' => 'created-skill',
            'description' => 'A created skill',
            'instructions' => 'Instructions',
            'version' => '1.0.0',
        ]);

        $this->assertEquals('created-skill', $skill->getName());
        $this->assertEquals('A created skill', $skill->getDescription());
    }

    public function test_generate_skills_prompt(): void
    {
        $this->manager->discover();

        $prompt = $this->manager->generateSkillsPrompt();

        $this->assertStringContainsString('Available Skills', $prompt);
        $this->assertStringContainsString('review-tool', $prompt);
        $this->assertStringContainsString('test-runner', $prompt);
        $this->assertStringContainsString('debug-helper', $prompt);
    }

    public function test_generate_skills_prompt_empty_when_no_skills(): void
    {
        $emptyManager = new SkillManager(sys_get_temp_dir() . '/nonexistent-' . uniqid());

        $prompt = $emptyManager->generateSkillsPrompt();

        $this->assertEquals('', $prompt);
    }

    public function test_singleton(): void
    {
        SkillManager::resetInstance();

        $instance1 = SkillManager::getInstance($this->skillsDir);
        $instance2 = SkillManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_registry(): void
    {
        $registry = $this->manager->getRegistry();

        $this->assertNotNull($registry);
    }

    public function test_get_loader(): void
    {
        $loader = $this->manager->getLoader();

        $this->assertNotNull($loader);
        $this->assertEquals($this->skillsDir, $loader->getSkillsPath());
    }

    public function test_get_validator(): void
    {
        $this->assertNotNull($this->manager->getValidator());
    }

    public function test_get_resolver(): void
    {
        $this->assertNotNull($this->manager->getResolver());
    }

    public function test_install_and_uninstall(): void
    {
        $sourceDir = sys_get_temp_dir() . '/install-source-' . uniqid();
        mkdir($sourceDir, 0755, true);
        file_put_contents($sourceDir . '/SKILL.md', "---\nname: installable\ndescription: test\n---\nBody");

        $targetDir = sys_get_temp_dir() . '/install-target-' . uniqid();
        mkdir($targetDir, 0755, true);
        $manager = new SkillManager($targetDir);

        $skill = $manager->install($sourceDir);
        $this->assertEquals('installable', $skill->getName());
        $this->assertTrue(file_exists($targetDir . '/installable/SKILL.md'));

        $manager->uninstall('installable');
        $this->assertFalse(is_dir($targetDir . '/installable'));

        $this->removeDir($sourceDir);
        $this->removeDir($targetDir);
    }

    public function test_export(): void
    {
        $this->manager->discover();

        $targetDir = sys_get_temp_dir() . '/export-target-' . uniqid();
        mkdir($targetDir, 0755, true);

        $exportPath = $this->manager->export('review-tool', $targetDir);

        $this->assertTrue(file_exists($exportPath . '/SKILL.md'));

        $this->removeDir($targetDir);
    }

    private function createSkillDir(
        string $name,
        string $description,
        string $instructions,
        ?string $baseDir = null,
    ): void {
        $baseDir = $baseDir ?? $this->skillsDir;
        $dir = $baseDir . '/' . $name;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: {$name}\ndescription: {$description}\n---\n\n{$instructions}\n"
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
