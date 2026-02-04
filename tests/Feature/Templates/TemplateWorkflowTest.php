<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Feature\Templates;

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Templates\Template;
use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

/**
 * Feature tests for complete template workflows
 */
class TemplateWorkflowTest extends TestCase
{
    private TemplateManager $manager;
    private string $testTemplatesPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testTemplatesPath = dirname(__DIR__, 3) . '/templates';
        $this->manager = new TemplateManager($this->testTemplatesPath);
    }

    public function testCompleteSearchAndFilterWorkflow(): void
    {
        // Search by category
        $agentTemplates = $this->manager->search(category: 'agents');
        $this->assertNotEmpty($agentTemplates);
        
        // Search by tags
        $beginnerTemplates = $this->manager->search(tags: ['beginner']);
        $this->assertNotEmpty($beginnerTemplates);
        
        // Combined search
        $results = $this->manager->search(
            category: 'agents',
            tags: ['beginner']
        );
        $this->assertNotEmpty($results);
        
        // Verify all results match criteria
        foreach ($results as $template) {
            $this->assertSame('agents', $template->getCategory());
            $this->assertTrue($template->hasTag('beginner'));
        }
    }

    public function testBrowseByMultipleCriteria(): void
    {
        // Get all categories
        $categories = $this->manager->getCategories();
        $this->assertGreaterThanOrEqual(5, count($categories));
        
        // Browse each category
        foreach ($categories as $category) {
            $templates = $this->manager->getByCategory($category);
            $this->assertIsArray($templates);
            
            foreach ($templates as $template) {
                $this->assertSame($category, $template->getCategory());
            }
        }
        
        // Get all tags
        $tags = $this->manager->getAllTags();
        $this->assertNotEmpty($tags);
    }

    public function testTemplateMetadataAccessWorkflow(): void
    {
        $template = $this->manager->getByName('Basic Agent');
        
        // Verify basic metadata
        $this->assertNotEmpty($template->getName());
        $this->assertNotEmpty($template->getDescription());
        $this->assertNotEmpty($template->getCategory());
        $this->assertNotEmpty($template->getTags());
        
        // Verify custom metadata
        $icon = $template->getMetadata('icon');
        $this->assertNotEmpty($icon);
        
        $difficulty = $template->getMetadata('difficulty');
        $this->assertContains($difficulty, ['beginner', 'intermediate', 'advanced']);
        
        // Verify requirements
        $requirements = $template->getRequirements();
        $this->assertArrayHasKey('php', $requirements);
        $this->assertArrayHasKey('packages', $requirements);
    }

    public function testTemplateValidationWorkflow(): void
    {
        $templates = $this->manager->loadAll();
        
        $validCount = 0;
        $invalidCount = 0;
        
        foreach ($templates as $template) {
            if ($template->isValid()) {
                $validCount++;
            } else {
                $invalidCount++;
            }
        }
        
        // All shipped templates should be valid
        $this->assertSame(count($templates), $validCount);
        $this->assertSame(0, $invalidCount);
    }

    public function testSearchWithFieldSelectionWorkflow(): void
    {
        // Get only specific fields
        $results = $this->manager->search(
            query: 'agent',
            fields: ['name', 'description', 'category']
        );
        
        $this->assertNotEmpty($results);
        
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('description', $result);
            $this->assertArrayHasKey('category', $result);
            
            // Should not include fields we didn't request
            $this->assertArrayNotHasKey('config', $result);
            $this->assertArrayNotHasKey('requirements', $result);
        }
    }

    public function testExportAndLoadWorkflow(): void
    {
        if (!getenv('ANTHROPIC_API_KEY')) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }
        
        // Create a simple agent
        $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
        $agent = Agent::create($client)
            ->withModel('claude-sonnet-4-5')
            ->withSystemPrompt('Test agent');
        
        // Export as template
        $template = $this->manager->exportAgent($agent, [
            'name' => 'Test Export Agent',
            'description' => 'Created for testing',
            'category' => 'custom',
            'tags' => ['test']
        ]);
        
        $this->assertInstanceOf(Template::class, $template);
        $this->assertSame('Test Export Agent', $template->getName());
        $this->assertTrue($template->isValid());
        
        // Verify we can convert to different formats
        $json = $template->toJson();
        $this->assertJson($json);
        
        $php = $template->toPhp();
        $this->assertStringStartsWith('<?php', $php);
        
        $array = $template->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('config', $array);
    }

    public function testCategoryDistribution(): void
    {
        $templates = $this->manager->loadAll();
        $categories = [];
        
        foreach ($templates as $template) {
            $category = $template->getCategory();
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }
        
        // Verify we have templates in multiple categories
        $this->assertGreaterThanOrEqual(5, count($categories));
        
        // Verify each category has templates
        foreach ($categories as $category => $count) {
            $this->assertGreaterThan(0, $count);
        }
    }

    public function testDifficultyDistribution(): void
    {
        $templates = $this->manager->loadAll();
        $difficulties = [];
        
        foreach ($templates as $template) {
            $difficulty = $template->getMetadata('difficulty');
            if ($difficulty) {
                $difficulties[$difficulty] = ($difficulties[$difficulty] ?? 0) + 1;
            }
        }
        
        // Should have templates at different difficulty levels
        $this->assertArrayHasKey('beginner', $difficulties);
        $this->assertArrayHasKey('intermediate', $difficulties);
        $this->assertArrayHasKey('advanced', $difficulties);
    }
}
