<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Exceptions\SkillInstallException;
use ClaudeAgents\Skills\Exceptions\SkillNotFoundException;
use ClaudeAgents\Skills\SkillInstaller;
use ClaudeAgents\Skills\SkillRegistry;
use PHPUnit\Framework\TestCase;

class SkillInstallerTest extends TestCase
{
    private string $targetDir;
    private string $sourceDir;
    private SkillInstaller $installer;

    protected function setUp(): void
    {
        $this->targetDir = sys_get_temp_dir() . '/installer-target-' . uniqid();
        $this->sourceDir = sys_get_temp_dir() . '/installer-source-' . uniqid();

        mkdir($this->targetDir, 0755, true);
        mkdir($this->sourceDir . '/scripts', 0755, true);

        file_put_contents($this->sourceDir . '/SKILL.md', <<<MD
---
name: installable-skill
description: A skill to install
---

# Installable Skill

Instructions here.
MD
        );
        file_put_contents($this->sourceDir . '/scripts/helper.php', '<?php // helper');

        $this->installer = new SkillInstaller($this->targetDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->targetDir);
        $this->removeDir($this->sourceDir);
    }

    public function test_install(): void
    {
        $skill = $this->installer->install($this->sourceDir);

        $this->assertEquals('installable-skill', $skill->getName());
        $this->assertTrue(file_exists($this->targetDir . '/installable-skill/SKILL.md'));
    }

    public function test_install_copies_resources(): void
    {
        $this->installer->install($this->sourceDir);

        $this->assertTrue(file_exists($this->targetDir . '/installable-skill/scripts/helper.php'));
    }

    public function test_install_registers_in_registry(): void
    {
        $registry = new SkillRegistry();

        $this->installer->install($this->sourceDir, $registry);

        $this->assertTrue($registry->has('installable-skill'));
    }

    public function test_install_throws_on_missing_skill_md(): void
    {
        $emptyDir = sys_get_temp_dir() . '/empty-source-' . uniqid();
        mkdir($emptyDir, 0755, true);

        $this->expectException(SkillNotFoundException::class);

        try {
            $this->installer->install($emptyDir);
        } finally {
            rmdir($emptyDir);
        }
    }

    public function test_install_throws_on_already_installed(): void
    {
        $this->installer->install($this->sourceDir);

        $this->expectException(SkillInstallException::class);
        $this->expectExceptionMessage('already installed');

        $this->installer->install($this->sourceDir);
    }

    public function test_uninstall(): void
    {
        $this->installer->install($this->sourceDir);
        $this->assertTrue($this->installer->isInstalled('installable-skill'));

        $this->installer->uninstall('installable-skill');
        $this->assertFalse($this->installer->isInstalled('installable-skill'));
    }

    public function test_uninstall_removes_from_registry(): void
    {
        $registry = new SkillRegistry();

        $this->installer->install($this->sourceDir, $registry);
        $this->assertTrue($registry->has('installable-skill'));

        $this->installer->uninstall('installable-skill', $registry);
        $this->assertFalse($registry->has('installable-skill'));
    }

    public function test_uninstall_throws_on_not_found(): void
    {
        $this->expectException(SkillNotFoundException::class);

        $this->installer->uninstall('nonexistent');
    }

    public function test_is_installed(): void
    {
        $this->assertFalse($this->installer->isInstalled('installable-skill'));

        $this->installer->install($this->sourceDir);

        $this->assertTrue($this->installer->isInstalled('installable-skill'));
    }

    public function test_list_installed(): void
    {
        $this->assertEmpty($this->installer->listInstalled());

        $this->installer->install($this->sourceDir);

        $installed = $this->installer->listInstalled();
        $this->assertContains('installable-skill', $installed);
    }

    public function test_install_validates_content(): void
    {
        $badDir = sys_get_temp_dir() . '/bad-skill-' . uniqid();
        mkdir($badDir, 0755, true);
        file_put_contents($badDir . '/SKILL.md', "---\ndescription: no name\n---\nBody");

        $this->expectException(SkillInstallException::class);
        $this->expectExceptionMessage('Validation failed');

        try {
            $this->installer->install($badDir);
        } finally {
            unlink($badDir . '/SKILL.md');
            rmdir($badDir);
        }
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
