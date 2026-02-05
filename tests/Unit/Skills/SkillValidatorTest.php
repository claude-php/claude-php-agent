<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\Skill;
use ClaudeAgents\Skills\SkillMetadata;
use ClaudeAgents\Skills\SkillValidator;
use PHPUnit\Framework\TestCase;

class SkillValidatorTest extends TestCase
{
    private SkillValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SkillValidator();
    }

    public function test_valid_skill_content(): void
    {
        $content = <<<MD
---
name: valid-skill
description: A valid skill description
---

# Valid Skill

Instructions here.
MD;

        $result = $this->validator->validate($content);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_missing_name(): void
    {
        $content = <<<MD
---
description: A skill without a name
---

Instructions.
MD;

        $result = $this->validator->validate($content);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('name', $result['errors'][0]);
    }

    public function test_missing_description(): void
    {
        $content = <<<MD
---
name: no-description
---

Instructions.
MD;

        $result = $this->validator->validate($content);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('description', $result['errors'][0]);
    }

    public function test_name_too_long(): void
    {
        $longName = str_repeat('a', 65);
        $content = "---\nname: {$longName}\ndescription: test\n---\nBody";

        $result = $this->validator->validate($content);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('64 characters', $result['errors'][0]);
    }

    public function test_description_too_long_is_warning(): void
    {
        $longDesc = str_repeat('a', 201);
        $content = "---\nname: long-desc\ndescription: {$longDesc}\n---\nBody";

        $result = $this->validator->validate($content);

        // Description length is a warning, not an error
        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_empty_body_is_warning(): void
    {
        $content = "---\nname: empty-body\ndescription: test\n---\n";

        $result = $this->validator->validate($content);

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('empty', $result['warnings'][0]);
    }

    public function test_invalid_frontmatter(): void
    {
        $result = $this->validator->validate('No frontmatter here');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_non_kebab_case_name_is_warning(): void
    {
        $content = "---\nname: InvalidName\ndescription: test\n---\nBody";

        $result = $this->validator->validate($content);

        // Non-kebab case is a warning, not an error
        $this->assertTrue($result['valid']);
    }

    public function test_validate_directory_valid(): void
    {
        $tmpDir = sys_get_temp_dir() . '/valid-skill-dir-' . uniqid();
        mkdir($tmpDir . '/scripts', 0755, true);
        mkdir($tmpDir . '/references', 0755, true);
        file_put_contents($tmpDir . '/SKILL.md', <<<MD
---
name: valid-dir-skill
description: A valid directory skill
---

Instructions.
MD
        );

        $result = $this->validator->validateDirectory($tmpDir);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // Cleanup
        unlink($tmpDir . '/SKILL.md');
        rmdir($tmpDir . '/scripts');
        rmdir($tmpDir . '/references');
        rmdir($tmpDir);
    }

    public function test_validate_directory_missing_skill_md(): void
    {
        $tmpDir = sys_get_temp_dir() . '/no-skill-md-' . uniqid();
        mkdir($tmpDir, 0755, true);

        $result = $this->validator->validateDirectory($tmpDir);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('SKILL.md not found', $result['errors'][0]);

        rmdir($tmpDir);
    }

    public function test_validate_directory_not_a_directory(): void
    {
        $result = $this->validator->validateDirectory('/nonexistent/path');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not a directory', $result['errors'][0]);
    }

    public function test_validate_directory_unknown_subdirectory_warns(): void
    {
        $tmpDir = sys_get_temp_dir() . '/unknown-subdir-' . uniqid();
        mkdir($tmpDir . '/custom-dir', 0755, true);
        file_put_contents($tmpDir . '/SKILL.md', "---\nname: test\ndescription: test\n---\nBody");

        $result = $this->validator->validateDirectory($tmpDir);

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('custom-dir', $result['warnings'][0]);

        // Cleanup
        unlink($tmpDir . '/SKILL.md');
        rmdir($tmpDir . '/custom-dir');
        rmdir($tmpDir);
    }

    public function test_validate_skill_object(): void
    {
        $skill = new Skill(
            metadata: new SkillMetadata(name: 'valid', description: 'test'),
            instructions: 'Instructions',
            path: '',
        );

        $result = $this->validator->validateSkill($skill);

        $this->assertTrue($result['valid']);
    }

    public function test_validate_skill_object_empty_name(): void
    {
        $skill = new Skill(
            metadata: new SkillMetadata(name: '', description: 'test'),
            instructions: 'Instructions',
            path: '',
        );

        $result = $this->validator->validateSkill($skill);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('name is empty', $result['errors'][0]);
    }

    public function test_validate_skill_object_empty_description(): void
    {
        $skill = new Skill(
            metadata: new SkillMetadata(name: 'test', description: ''),
            instructions: 'Instructions',
            path: '',
        );

        $result = $this->validator->validateSkill($skill);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('description is empty', $result['errors'][0]);
    }

    public function test_validate_skill_object_empty_instructions_is_warning(): void
    {
        $skill = new Skill(
            metadata: new SkillMetadata(name: 'test', description: 'test'),
            instructions: '',
            path: '',
        );

        $result = $this->validator->validateSkill($skill);

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
    }
}
