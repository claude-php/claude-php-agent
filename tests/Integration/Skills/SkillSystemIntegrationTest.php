<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Skills;

use ClaudeAgents\Skills\FrontmatterParser;
use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillExporter;
use ClaudeAgents\Skills\SkillInstaller;
use ClaudeAgents\Skills\SkillLoader;
use ClaudeAgents\Skills\SkillManager;
use ClaudeAgents\Skills\SkillMetadata;
use ClaudeAgents\Skills\SkillPromptComposer;
use ClaudeAgents\Skills\SkillRegistry;
use ClaudeAgents\Skills\SkillResolver;
use ClaudeAgents\Skills\SkillValidator;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Agent Skills system.
 *
 * Tests the full lifecycle of skill discovery, loading, resolution,
 * validation, installation, export, and prompt composition.
 */
class SkillSystemIntegrationTest extends TestCase
{
    private string $skillsDir;

    protected function setUp(): void
    {
        SkillManager::resetInstance();

        $this->skillsDir = sys_get_temp_dir() . '/integration-skills-' . uniqid();
        mkdir($this->skillsDir, 0755, true);

        // Create comprehensive test skills
        $this->createSkill('code-review', [
            'name' => 'code-review',
            'description' => 'Review PHP code for quality, security, and best practices',
            'license' => 'MIT',
            'metadata' => ['author' => 'test', 'tags' => ['php', 'review', 'quality']],
        ], "# Code Review\n\n## Steps\n1. Check security\n2. Check quality\n3. Report findings");

        $this->createSkill('api-testing', [
            'name' => 'api-testing',
            'description' => 'Create and execute API test suites for REST endpoints',
            'metadata' => ['tags' => ['api', 'testing', 'rest']],
        ], "# API Testing\n\nTest REST API endpoints systematically.");

        $this->createSkill('data-analysis', [
            'name' => 'data-analysis',
            'description' => 'Analyze datasets and generate statistics and insights',
            'metadata' => ['tags' => ['data', 'analysis', 'statistics']],
        ], "# Data Analysis\n\nAnalyze CSV and JSON datasets.");

        $this->createSkill('debug-mode', [
            'name' => 'debug-mode',
            'description' => 'Enable verbose debug output mode',
            'mode' => true,
        ], "# Debug Mode\n\nEnable detailed logging and tracing.");

        $this->createSkill('manual-skill', [
            'name' => 'manual-skill',
            'description' => 'A manual-only skill for admin use',
            'disable-model-invocation' => true,
        ], "# Manual Skill\n\nOnly invoke manually.");
    }

    protected function tearDown(): void
    {
        SkillManager::resetInstance();
        $this->removeDir($this->skillsDir);
    }

    /**
     * Test: Full lifecycle - discover, load, resolve, compose prompt.
     */
    public function test_full_lifecycle(): void
    {
        $manager = new SkillManager($this->skillsDir);

        // 1. Discover all skills
        $skills = $manager->discover();
        $this->assertCount(5, $skills);

        // 2. Get summaries for progressive disclosure
        $summaries = $manager->summaries();
        $this->assertCount(5, $summaries);
        $this->assertArrayHasKey('code-review', $summaries);

        // 3. Resolve skills for a task
        $resolved = $manager->resolve('review code quality');
        $this->assertNotEmpty($resolved);
        $this->assertEquals('code-review', $resolved[0]->getName());

        // 4. Compose prompt with resolved skills
        $composer = new SkillPromptComposer();
        $prompt = $composer->compose('You are a helpful assistant.', $resolved);
        $this->assertStringContainsString('You are a helpful assistant.', $prompt);
        $this->assertStringContainsString('code-review', $prompt);
        $this->assertStringContainsString('Code Review', $prompt);
    }

    /**
     * Test: Skill installation and uninstallation.
     */
    public function test_install_uninstall_lifecycle(): void
    {
        $targetDir = sys_get_temp_dir() . '/install-lifecycle-' . uniqid();
        mkdir($targetDir, 0755, true);

        // Create a source skill
        $sourceDir = sys_get_temp_dir() . '/install-source-lifecycle-' . uniqid();
        mkdir($sourceDir . '/scripts', 0755, true);
        file_put_contents($sourceDir . '/SKILL.md', <<<MD
---
name: new-skill
description: A brand new skill
license: MIT
---

# New Skill

Instructions for the new skill.
MD
        );
        file_put_contents($sourceDir . '/scripts/helper.php', '<?php // helper');

        $installer = new SkillInstaller($targetDir);

        // Install
        $skill = $installer->install($sourceDir);
        $this->assertEquals('new-skill', $skill->getName());
        $this->assertTrue($installer->isInstalled('new-skill'));
        $this->assertTrue(file_exists($targetDir . '/new-skill/SKILL.md'));
        $this->assertTrue(file_exists($targetDir . '/new-skill/scripts/helper.php'));

        // List installed
        $installed = $installer->listInstalled();
        $this->assertContains('new-skill', $installed);

        // Uninstall
        $installer->uninstall('new-skill');
        $this->assertFalse($installer->isInstalled('new-skill'));
        $this->assertFalse(is_dir($targetDir . '/new-skill'));

        $this->removeDir($sourceDir);
        $this->removeDir($targetDir);
    }

    /**
     * Test: Export and re-import a skill.
     */
    public function test_export_and_reimport(): void
    {
        $manager = new SkillManager($this->skillsDir);
        $manager->discover();

        // Export
        $exportDir = sys_get_temp_dir() . '/export-reimport-' . uniqid();
        mkdir($exportDir, 0755, true);
        $exportPath = $manager->export('code-review', $exportDir);

        // Verify exported
        $this->assertTrue(file_exists($exportPath . '/SKILL.md'));

        // Re-import via loader
        $loader = new SkillLoader($exportDir);
        $reimported = $loader->loadFromPath($exportPath);

        $this->assertEquals('code-review', $reimported->getName());
        $this->assertEquals(
            $manager->get('code-review')->getDescription(),
            $reimported->getDescription()
        );

        $this->removeDir($exportDir);
    }

    /**
     * Test: Validation across the system.
     */
    public function test_validation_integration(): void
    {
        $validator = new SkillValidator();

        // Validate each skill directory
        $items = scandir($this->skillsDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $result = $validator->validateDirectory($this->skillsDir . '/' . $item);
            $this->assertTrue($result['valid'], "Skill '{$item}' validation failed: " . implode(', ', $result['errors']));
        }
    }

    /**
     * Test: Resolver correctly prioritizes skills.
     */
    public function test_resolver_prioritization(): void
    {
        $manager = new SkillManager($this->skillsDir);
        $manager->discover();

        // Test specific queries match expected skills
        $queries = [
            'review code' => 'code-review',
            'test API endpoints' => 'api-testing',
            'analyze data statistics' => 'data-analysis',
        ];

        foreach ($queries as $query => $expectedName) {
            $result = $manager->resolveOne($query);
            $this->assertNotNull($result, "No skill resolved for query: '{$query}'");
            $this->assertEquals($expectedName, $result->getName(), "Wrong skill for query: '{$query}'");
        }
    }

    /**
     * Test: Mode skills are correctly identified.
     */
    public function test_mode_skills(): void
    {
        $manager = new SkillManager($this->skillsDir);
        $manager->discover();

        $registry = $manager->getRegistry();
        $modes = $registry->getModes();

        $this->assertCount(1, $modes);
        $this->assertArrayHasKey('debug-mode', $modes);
    }

    /**
     * Test: Manual skills are excluded from auto-resolution.
     */
    public function test_manual_skills_excluded(): void
    {
        $manager = new SkillManager($this->skillsDir);
        $manager->discover();

        $results = $manager->resolve('manual admin skill');

        $names = array_map(fn($s) => $s->getName(), $results);
        $this->assertNotContains('manual-skill', $names);
    }

    /**
     * Test: Prompt generation with progressive disclosure.
     */
    public function test_progressive_disclosure_prompt(): void
    {
        $manager = new SkillManager($this->skillsDir);
        $manager->discover();

        // Generate the skills prompt (summaries only)
        $skillsPrompt = $manager->generateSkillsPrompt();

        // Should contain all skill names and descriptions
        $this->assertStringContainsString('code-review', $skillsPrompt);
        $this->assertStringContainsString('api-testing', $skillsPrompt);
        $this->assertStringContainsString('data-analysis', $skillsPrompt);

        // Mode skills should be in separate section
        $this->assertStringContainsString('Mode Commands', $skillsPrompt);
        $this->assertStringContainsString('debug-mode', $skillsPrompt);
    }

    /**
     * Test: Compose with both loaded and available skills.
     */
    public function test_compose_with_discovery(): void
    {
        $manager = new SkillManager($this->skillsDir);
        $manager->discover();

        $loadedSkills = [$manager->get('code-review')];
        $summaries = $manager->summaries();

        $composer = new SkillPromptComposer();
        $result = $composer->composeWithDiscovery(
            'You are a helpful assistant.',
            $loadedSkills,
            $summaries
        );

        // Should contain loaded skill's full instructions
        $this->assertStringContainsString('Code Review', $result);
        $this->assertStringContainsString('Check security', $result);

        // Should contain summaries of unloaded skills
        $this->assertStringContainsString('api-testing', $result);
        $this->assertStringContainsString('data-analysis', $result);
    }

    /**
     * Test: Create skill from data and export.
     */
    public function test_create_and_export(): void
    {
        $manager = new SkillManager($this->skillsDir);

        $skill = $manager->create([
            'name' => 'dynamic-skill',
            'description' => 'A dynamically created skill',
            'instructions' => '# Dynamic Skill\n\nCreated at runtime.',
            'version' => '1.0.0',
        ]);

        $this->assertEquals('dynamic-skill', $skill->getName());

        // Register and verify
        $manager->register($skill);
        $this->assertEquals('dynamic-skill', $manager->get('dynamic-skill')->getName());
    }

    /**
     * Test: Register from markdown string.
     */
    public function test_register_from_markdown_integration(): void
    {
        $manager = new SkillManager($this->skillsDir);

        $md = <<<MD
---
name: inline-skill
description: Created inline from markdown
---

# Inline Skill

These are inline instructions.
MD;

        $skill = $manager->registerFromMarkdown($md);

        $this->assertEquals('inline-skill', $skill->getName());
        $this->assertEquals('inline-skill', $manager->get('inline-skill')->getName());

        // Should be discoverable in search
        $results = $manager->search('inline');
        $this->assertArrayHasKey('inline-skill', $results);
    }

    /**
     * Test: Additional paths integration.
     */
    public function test_additional_paths(): void
    {
        $extraDir = sys_get_temp_dir() . '/extra-integration-' . uniqid();
        $this->createSkill('extra-skill', [
            'name' => 'extra-skill',
            'description' => 'Skill from extra path',
        ], 'Extra instructions', $extraDir);

        $manager = new SkillManager($this->skillsDir);
        $manager->addPath($extraDir);

        $all = $manager->all();
        $this->assertArrayHasKey('extra-skill', $all);
        $this->assertArrayHasKey('code-review', $all);

        $this->removeDir($extraDir);
    }

    /**
     * Test: Real-world skills from the bundled skills directory.
     */
    public function test_bundled_skills_valid(): void
    {
        $bundledDir = dirname(__DIR__, 3) . '/skills';
        if (!is_dir($bundledDir)) {
            $this->markTestSkipped('Bundled skills directory not found');
        }

        $validator = new SkillValidator();
        $loader = new SkillLoader($bundledDir);
        $skills = $loader->loadAll();

        $this->assertNotEmpty($skills, 'No bundled skills found');

        foreach ($skills as $name => $skill) {
            $result = $validator->validateSkill($skill);
            $this->assertTrue(
                $result['valid'],
                "Bundled skill '{$name}' validation failed: " . implode(', ', $result['errors'])
            );
        }
    }

    /**
     * Test: Frontmatter round-trip integrity.
     */
    public function test_frontmatter_roundtrip(): void
    {
        $original = <<<MD
---
name: roundtrip
description: Test roundtrip integrity
license: MIT
metadata:
  author: test
  version: "2.0.0"
---

# Roundtrip

Instructions preserved.
MD;

        $parsed = FrontmatterParser::parse($original);
        $generated = FrontmatterParser::generate($parsed['frontmatter']);
        $rebuilt = $generated . "\n" . $parsed['body'] . "\n";

        $reparsed = FrontmatterParser::parse($rebuilt);

        $this->assertEquals(
            $parsed['frontmatter']['name'],
            $reparsed['frontmatter']['name']
        );
        $this->assertEquals(
            $parsed['frontmatter']['description'],
            $reparsed['frontmatter']['description']
        );
        $this->assertStringContainsString(
            'Instructions preserved.',
            $reparsed['body']
        );
    }

    private function createSkill(
        string $dirName,
        array $frontmatter,
        string $body,
        ?string $baseDir = null,
    ): void {
        $baseDir = $baseDir ?? $this->skillsDir;
        $dir = $baseDir . '/' . $dirName;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $yaml = FrontmatterParser::generate($frontmatter);
        $content = $yaml . "\n" . $body . "\n";
        file_put_contents($dir . '/SKILL.md', $content);
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
