<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Skills;

use ClaudeAgents\Skills\SkillMetadata;
use PHPUnit\Framework\TestCase;

class SkillMetadataTest extends TestCase
{
    public function test_creates_from_array(): void
    {
        $data = [
            'name' => 'test-skill',
            'description' => 'A test skill',
            'license' => 'MIT',
            'version' => '1.0.0',
            'metadata' => ['author' => 'tester', 'tags' => ['php', 'test']],
            'dependencies' => ['phpunit/phpunit'],
            'compatibility' => ['product' => 'claude-code'],
        ];

        $metadata = SkillMetadata::fromArray($data);

        $this->assertEquals('test-skill', $metadata->name);
        $this->assertEquals('A test skill', $metadata->description);
        $this->assertEquals('MIT', $metadata->license);
        $this->assertEquals('1.0.0', $metadata->version);
        $this->assertEquals('tester', $metadata->getAuthor());
        $this->assertEquals(['php', 'test'], $metadata->getTags());
        $this->assertEquals(['phpunit/phpunit'], $metadata->dependencies);
        $this->assertEquals(['product' => 'claude-code'], $metadata->compatibility);
        $this->assertFalse($metadata->disableModelInvocation);
        $this->assertFalse($metadata->mode);
    }

    public function test_creates_with_defaults(): void
    {
        $metadata = SkillMetadata::fromArray([
            'name' => 'minimal',
            'description' => 'Minimal skill',
        ]);

        $this->assertEquals('minimal', $metadata->name);
        $this->assertEquals('Minimal skill', $metadata->description);
        $this->assertNull($metadata->license);
        $this->assertNull($metadata->version);
        $this->assertEmpty($metadata->metadata);
        $this->assertEmpty($metadata->dependencies);
        $this->assertEmpty($metadata->compatibility);
        $this->assertFalse($metadata->disableModelInvocation);
        $this->assertFalse($metadata->mode);
    }

    public function test_creates_with_disable_model_invocation(): void
    {
        $metadata = SkillMetadata::fromArray([
            'name' => 'manual-skill',
            'description' => 'Manual only',
            'disable-model-invocation' => true,
        ]);

        $this->assertTrue($metadata->disableModelInvocation);
    }

    public function test_creates_with_mode_flag(): void
    {
        $metadata = SkillMetadata::fromArray([
            'name' => 'mode-skill',
            'description' => 'A mode',
            'mode' => true,
        ]);

        $this->assertTrue($metadata->mode);
    }

    public function test_converts_to_array(): void
    {
        $metadata = new SkillMetadata(
            name: 'export-test',
            description: 'For export',
            license: 'MIT',
            version: '2.0.0',
            metadata: ['author' => 'tester'],
        );

        $array = $metadata->toArray();

        $this->assertEquals('export-test', $array['name']);
        $this->assertEquals('For export', $array['description']);
        $this->assertEquals('MIT', $array['license']);
        $this->assertEquals('2.0.0', $array['version']);
        $this->assertEquals(['author' => 'tester'], $array['metadata']);
    }

    public function test_to_array_excludes_empty_optional_fields(): void
    {
        $metadata = new SkillMetadata(
            name: 'minimal',
            description: 'test',
        );

        $array = $metadata->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayNotHasKey('license', $array);
        $this->assertArrayNotHasKey('version', $array);
        $this->assertArrayNotHasKey('metadata', $array);
        $this->assertArrayNotHasKey('dependencies', $array);
        $this->assertArrayNotHasKey('compatibility', $array);
        $this->assertArrayNotHasKey('disable-model-invocation', $array);
        $this->assertArrayNotHasKey('mode', $array);
    }

    public function test_to_array_includes_boolean_flags_when_true(): void
    {
        $metadata = new SkillMetadata(
            name: 'flags',
            description: 'test',
            disableModelInvocation: true,
            mode: true,
        );

        $array = $metadata->toArray();

        $this->assertTrue($array['disable-model-invocation']);
        $this->assertTrue($array['mode']);
    }

    public function test_get_author_returns_null_when_missing(): void
    {
        $metadata = new SkillMetadata(
            name: 'no-author',
            description: 'test',
        );

        $this->assertNull($metadata->getAuthor());
    }

    public function test_get_tags_returns_empty_when_missing(): void
    {
        $metadata = new SkillMetadata(
            name: 'no-tags',
            description: 'test',
        );

        $this->assertEmpty($metadata->getTags());
    }

    public function test_handles_empty_array(): void
    {
        $metadata = SkillMetadata::fromArray([]);

        $this->assertEquals('', $metadata->name);
        $this->assertEquals('', $metadata->description);
    }

    public function test_readonly_properties(): void
    {
        $metadata = new SkillMetadata(
            name: 'readonly-test',
            description: 'testing readonly',
        );

        $this->assertEquals('readonly-test', $metadata->name);
        $this->assertEquals('testing readonly', $metadata->description);
    }
}
