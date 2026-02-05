<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillExporter;
use ClaudeAgents\Skills\SkillMetadata;
use PHPUnit\Framework\TestCase;

class SkillExporterTest extends TestCase
{
    private SkillExporter $exporter;
    private string $targetDir;

    protected function setUp(): void
    {
        $this->exporter = new SkillExporter();
        $this->targetDir = sys_get_temp_dir() . '/export-test-' . uniqid();
        mkdir($this->targetDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->targetDir);
    }

    public function test_export_creates_directory(): void
    {
        $skill = $this->createTestSkill();

        $path = $this->exporter->export($skill, $this->targetDir);

        $this->assertTrue(is_dir($path));
        $this->assertTrue(file_exists($path . '/SKILL.md'));
    }

    public function test_export_generates_valid_skill_md(): void
    {
        $skill = $this->createTestSkill();

        $path = $this->exporter->export($skill, $this->targetDir);
        $content = file_get_contents($path . '/SKILL.md');

        $this->assertStringContainsString('---', $content);
        $this->assertStringContainsString('name: export-test', $content);
        $this->assertStringContainsString('description: Test export skill', $content);
        $this->assertStringContainsString('Export instructions', $content);
    }

    public function test_export_creates_subdirectories(): void
    {
        $skill = $this->createTestSkill();

        $path = $this->exporter->export($skill, $this->targetDir);

        $this->assertTrue(is_dir($path . '/scripts'));
        $this->assertTrue(is_dir($path . '/references'));
        $this->assertTrue(is_dir($path . '/assets'));
    }

    public function test_export_copies_resources(): void
    {
        // Create a skill with actual resources
        $sourceDir = sys_get_temp_dir() . '/export-source-' . uniqid();
        mkdir($sourceDir . '/scripts', 0755, true);
        mkdir($sourceDir . '/references', 0755, true);
        file_put_contents($sourceDir . '/scripts/helper.php', '<?php // helper');
        file_put_contents($sourceDir . '/references/guide.md', '# Guide');

        $skill = new Skill(
            metadata: new SkillMetadata(name: 'resource-skill', description: 'Has resources'),
            instructions: 'Instructions',
            path: $sourceDir,
            scripts: ['helper.php'],
            references: ['guide.md'],
        );

        $path = $this->exporter->export($skill, $this->targetDir);

        $this->assertTrue(file_exists($path . '/scripts/helper.php'));
        $this->assertTrue(file_exists($path . '/references/guide.md'));

        $this->removeDir($sourceDir);
    }

    public function test_generate_skill_md(): void
    {
        $skill = $this->createTestSkill();

        $content = $this->exporter->generateSkillMd($skill);

        $this->assertStringStartsWith('---', $content);
        $this->assertStringContainsString('name: export-test', $content);
        $this->assertStringContainsString('Export instructions', $content);
    }

    public function test_generate_template(): void
    {
        $content = $this->exporter->generateTemplate('my-skill', 'A description');

        $this->assertStringContainsString('name: my-skill', $content);
        $this->assertStringContainsString('description: A description', $content);
        $this->assertStringContainsString('# my-skill', $content);
        $this->assertStringContainsString('Insert your skill instructions', $content);
    }

    public function test_create_skill(): void
    {
        $skill = $this->exporter->createSkill([
            'name' => 'created',
            'description' => 'Created skill',
            'instructions' => 'Do things',
            'version' => '2.0.0',
        ]);

        $this->assertEquals('created', $skill->getName());
        $this->assertEquals('Created skill', $skill->getDescription());
        $this->assertStringContainsString('Do things', $skill->getInstructions());
    }

    public function test_export_many(): void
    {
        $skills = [
            $this->createTestSkill('skill-a', 'First skill'),
            $this->createTestSkill('skill-b', 'Second skill'),
        ];

        $paths = $this->exporter->exportMany($skills, $this->targetDir);

        $this->assertCount(2, $paths);
        $this->assertTrue(file_exists($paths[0] . '/SKILL.md'));
        $this->assertTrue(file_exists($paths[1] . '/SKILL.md'));
    }

    private function createTestSkill(string $name = 'export-test', string $description = 'Test export skill'): Skill
    {
        return new Skill(
            metadata: new SkillMetadata(
                name: $name,
                description: $description,
                license: 'MIT',
                version: '1.0.0',
            ),
            instructions: 'Export instructions for ' . $name,
            path: '',
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
