<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Templates;

use ClaudeAgents\Templates\Template;
use ClaudeAgents\Templates\Exceptions\TemplateValidationException;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    public function testCreateTemplateFromArray(): void
    {
        $data = [
            'id' => 'test-001',
            'name' => 'Test Template',
            'description' => 'A test template',
            'category' => 'agents',
            'tags' => ['test', 'demo'],
            'version' => '1.0.0',
            'config' => ['agent_type' => 'Agent']
        ];

        $template = Template::fromArray($data);

        $this->assertSame('test-001', $template->getId());
        $this->assertSame('Test Template', $template->getName());
        $this->assertSame('A test template', $template->getDescription());
        $this->assertSame('agents', $template->getCategory());
        $this->assertSame(['test', 'demo'], $template->getTags());
    }

    public function testCreateTemplateFromJson(): void
    {
        $json = json_encode([
            'id' => 'test-002',
            'name' => 'JSON Template',
            'description' => 'Created from JSON',
            'category' => 'chatbots',
            'config' => ['agent_type' => 'DialogAgent']
        ]);

        $template = Template::fromJson($json);

        $this->assertSame('test-002', $template->getId());
        $this->assertSame('JSON Template', $template->getName());
    }

    public function testInvalidJsonThrowsException(): void
    {
        $this->expectException(TemplateValidationException::class);
        Template::fromJson('{invalid json}');
    }

    public function testTemplateValidation(): void
    {
        $template = Template::fromArray([
            'name' => 'Valid Template',
            'description' => 'Valid description',
            'category' => 'agents',
            'config' => ['agent_type' => 'Agent']
        ]);

        $this->assertTrue($template->isValid());
        $this->assertEmpty($template->getErrors());
    }

    public function testMissingNameFailsValidation(): void
    {
        $template = Template::fromArray([
            'description' => 'Valid description',
            'category' => 'agents',
            'config' => ['agent_type' => 'Agent']
        ]);

        $this->assertFalse($template->isValid());
        $this->assertContains('Name is required', $template->getErrors());
    }

    public function testMissingDescriptionFailsValidation(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'category' => 'agents',
            'config' => ['agent_type' => 'Agent']
        ]);

        $this->assertFalse($template->isValid());
        $this->assertContains('Description is required', $template->getErrors());
    }

    public function testInvalidCategoryFailsValidation(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Test description',
            'category' => 'invalid_category',
            'config' => ['agent_type' => 'Agent']
        ]);

        $this->assertFalse($template->isValid());
        $errors = $template->getErrors();
        $this->assertNotEmpty(array_filter($errors, fn($e) => str_contains($e, 'Invalid category')));
    }

    public function testMissingAgentTypeFailsValidation(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Test description',
            'category' => 'agents',
            'config' => []
        ]);

        $this->assertFalse($template->isValid());
        $this->assertContains('Config must include agent_type', $template->getErrors());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $data = [
            'id' => 'test-003',
            'name' => 'Test',
            'description' => 'Description',
            'category' => 'agents',
            'tags' => ['tag1'],
            'version' => '1.0.0',
            'config' => ['agent_type' => 'Agent']
        ];

        $template = Template::fromArray($data);
        $result = $template->toArray();

        $this->assertSame($data['id'], $result['id']);
        $this->assertSame($data['name'], $result['name']);
        $this->assertArrayHasKey('metadata', $result);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Description',
            'category' => 'agents',
            'config' => ['agent_type' => 'Agent']
        ]);

        $json = $template->toJson();
        $this->assertJson($json);
        
        $decoded = json_decode($json, true);
        $this->assertSame('Test', $decoded['name']);
    }

    public function testToPhpReturnsValidPhp(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Description',
            'category' => 'agents',
            'config' => ['agent_type' => 'Agent']
        ]);

        $php = $template->toPhp();
        $this->assertStringStartsWith('<?php', $php);
        $this->assertStringContainsString('return', $php);
    }

    public function testGetAndSetMetadata(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Description',
            'category' => 'agents',
            'config' => ['agent_type' => 'Agent'],
            'metadata' => ['icon' => '🤖']
        ]);

        $this->assertSame('🤖', $template->getMetadata('icon'));
        $this->assertNull($template->getMetadata('nonexistent'));

        $template->setMetadata('difficulty', 'beginner');
        $this->assertSame('beginner', $template->getMetadata('difficulty'));
    }

    public function testTagManagement(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Description',
            'category' => 'agents',
            'tags' => ['tag1'],
            'config' => ['agent_type' => 'Agent']
        ]);

        $this->assertTrue($template->hasTag('tag1'));
        $this->assertFalse($template->hasTag('tag2'));

        $template->addTag('tag2');
        $this->assertTrue($template->hasTag('tag2'));
        $this->assertCount(2, $template->getTags());

        // Adding duplicate should not increase count
        $template->addTag('tag1');
        $this->assertCount(2, $template->getTags());

        $template->removeTag('tag1');
        $this->assertFalse($template->hasTag('tag1'));
        $this->assertCount(1, $template->getTags());
    }

    public function testMatchesQuery(): void
    {
        $template = Template::fromArray([
            'name' => 'ReAct Agent',
            'description' => 'Reasoning and acting agent',
            'category' => 'agents',
            'config' => ['agent_type' => 'ReactAgent']
        ]);

        $this->assertTrue($template->matchesQuery('react'));
        $this->assertTrue($template->matchesQuery('Reasoning'));
        $this->assertTrue($template->matchesQuery('agent'));
        $this->assertFalse($template->matchesQuery('chatbot'));
        $this->assertTrue($template->matchesQuery(null)); // null matches all
    }

    public function testMatchesTags(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Description',
            'category' => 'agents',
            'tags' => ['advanced', 'reasoning'],
            'config' => ['agent_type' => 'Agent']
        ]);

        $this->assertTrue($template->matchesTags(['advanced']));
        $this->assertTrue($template->matchesTags(['reasoning']));
        $this->assertTrue($template->matchesTags(['advanced', 'other'])); // OR logic
        $this->assertFalse($template->matchesTags(['beginner']));
        $this->assertTrue($template->matchesTags(null)); // null matches all
        $this->assertTrue($template->matchesTags([])); // empty matches all
    }

    public function testMatchesCategory(): void
    {
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Description',
            'category' => 'chatbots',
            'config' => ['agent_type' => 'Agent']
        ]);

        $this->assertTrue($template->matchesCategory('chatbots'));
        $this->assertFalse($template->matchesCategory('agents'));
        $this->assertTrue($template->matchesCategory(null)); // null matches all
    }

    public function testGenerateId(): void
    {
        $template1 = Template::fromArray([
            'name' => 'Test 1',
            'description' => 'Description',
            'category' => 'agents',
            'config' => ['agent_type' => 'Agent']
        ]);

        $template2 = Template::fromArray([
            'name' => 'Test 2',
            'description' => 'Description',
            'category' => 'agents',
            'config' => ['agent_type' => 'Agent']
        ]);

        // Auto-generated IDs should be different
        $this->assertNotSame($template1->getId(), $template2->getId());
        
        // Should be UUID format
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $template1->getId());
    }
}
