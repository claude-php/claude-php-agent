<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;
use ClaudeAgents\Skills\SkillLoader;
use PHPUnit\Framework\TestCase;

class SkillLoaderTest extends TestCase
{
    private string $skillsDir;

    protected function setUp(): void
    {
        $this->skillsDir = sys_get_temp_dir() . '/test-skills-' . uniqid();
        mkdir($this->skillsDir, 0755, true);

        // Create test skill 1
        $skill1Dir = $this->skillsDir . '/test-skill-1';
        mkdir($skill1Dir . '/scripts', 0755, true);
        mkdir($skill1Dir . '/references', 0755, true);
        file_put_contents($skill1Dir . '/SKILL.md', <<<MD
---
name: test-skill-1
description: First test skill
license: MIT
---

# Test Skill 1

Instructions for skill 1.
MD
        );
        file_put_contents($skill1Dir . '/scripts/helper.php', '<?php // helper');
        file_put_contents($skill1Dir . '/references/ref.md', '# Reference');

        // Create test skill 2
        $skill2Dir = $this->skillsDir . '/test-skill-2';
        mkdir($skill2Dir, 0755, true);
        file_put_contents($skill2Dir . '/SKILL.md', <<<MD
---
name: test-skill-2
description: Second test skill
---

# Test Skill 2

Instructions for skill 2.
MD
        );

        // Create a non-skill directory
        mkdir($this->skillsDir . '/not-a-skill', 0755, true);
        file_put_contents($this->skillsDir . '/not-a-skill/README.md', '# Not a skill');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->skillsDir);
    }

    public function test_load_all(): void
    {
        $loader = new SkillLoader($this->skillsDir);
        $skills = $loader->loadAll();

        $this->assertCount(2, $skills);
        $this->assertArrayHasKey('test-skill-1', $skills);
        $this->assertArrayHasKey('test-skill-2', $skills);
    }

    public function test_load_by_name(): void
    {
        $loader = new SkillLoader($this->skillsDir);
        $skill = $loader->load('test-skill-1');

        $this->assertEquals('test-skill-1', $skill->getName());
        $this->assertEquals('First test skill', $skill->getDescription());
        $this->assertStringContainsString('Instructions for skill 1', $skill->getInstructions());
    }

    public function test_load_discovers_resources(): void
    {
        $loader = new SkillLoader($this->skillsDir);
        $skill = $loader->load('test-skill-1');

        $this->assertContains('helper.php', $skill->getScripts());
        $this->assertContains('ref.md', $skill->getReferences());
    }

    public function test_load_throws_on_not_found(): void
    {
        $loader = new SkillLoader($this->skillsDir);

        $this->expectException(SkillNotFoundException::class);

        $loader->load('nonexistent-skill');
    }

    public function test_exists(): void
    {
        $loader = new SkillLoader($this->skillsDir);

        $this->assertTrue($loader->exists('test-skill-1'));
        $this->assertTrue($loader->exists('test-skill-2'));
        $this->assertFalse($loader->exists('nonexistent'));
        $this->assertFalse($loader->exists('not-a-skill'));
    }

    public function test_load_from_path(): void
    {
        $loader = new SkillLoader($this->skillsDir);
        $skill = $loader->loadFromPath($this->skillsDir . '/test-skill-1');

        $this->assertEquals('test-skill-1', $skill->getName());
    }

    public function test_load_from_path_throws_on_missing_skill_md(): void
    {
        $loader = new SkillLoader($this->skillsDir);

        $this->expectException(SkillNotFoundException::class);

        $loader->loadFromPath($this->skillsDir . '/not-a-skill');
    }

    public function test_caching(): void
    {
        $loader = new SkillLoader($this->skillsDir);

        $skill1 = $loader->load('test-skill-1');
        $skill2 = $loader->load('test-skill-1');

        $this->assertSame($skill1, $skill2);
    }

    public function test_disable_caching(): void
    {
        $loader = new SkillLoader($this->skillsDir);
        $loader->setCacheEnabled(false);

        $skill1 = $loader->load('test-skill-1');
        $skill2 = $loader->load('test-skill-1');

        // Different instances when caching disabled
        $this->assertNotSame($skill1, $skill2);
        $this->assertEquals($skill1->getName(), $skill2->getName());
    }

    public function test_clear_cache(): void
    {
        $loader = new SkillLoader($this->skillsDir);

        $skill1 = $loader->load('test-skill-1');
        $loader->clearCache();
        $skill2 = $loader->load('test-skill-1');

        $this->assertNotSame($skill1, $skill2);
    }

    public function test_add_additional_path(): void
    {
        $extraDir = sys_get_temp_dir() . '/extra-skills-' . uniqid();
        mkdir($extraDir . '/extra-skill', 0755, true);
        file_put_contents($extraDir . '/extra-skill/SKILL.md', <<<MD
---
name: extra-skill
description: Extra skill from additional path
---

Extra skill instructions.
MD
        );

        $loader = new SkillLoader($this->skillsDir);
        $loader->addPath($extraDir);

        $skills = $loader->loadAll();
        $this->assertArrayHasKey('extra-skill', $skills);

        $this->assertTrue($loader->exists('extra-skill'));

        // Cleanup
        $this->removeDir($extraDir);
    }

    public function test_load_all_skips_non_directories(): void
    {
        file_put_contents($this->skillsDir . '/readme.txt', 'just a file');

        $loader = new SkillLoader($this->skillsDir);
        $skills = $loader->loadAll();

        // Should only find the two test skills
        $this->assertCount(2, $skills);

        unlink($this->skillsDir . '/readme.txt');
    }

    public function test_get_skills_path(): void
    {
        $loader = new SkillLoader($this->skillsDir);

        $this->assertEquals($this->skillsDir, $loader->getSkillsPath());
    }

    public function test_load_all_handles_nonexistent_path(): void
    {
        $loader = new SkillLoader('/nonexistent/path');
        $skills = $loader->loadAll();

        $this->assertEmpty($skills);
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
