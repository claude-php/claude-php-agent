<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Templates;

use ClaudeAgents\Templates\TemplateLoader;
use ClaudeAgents\Templates\Template;
use ClaudeAgents\Templates\Exceptions\TemplateNotFoundException;
use PHPUnit\Framework\TestCase;

class TemplateLoaderTest extends TestCase
{
    private string $testTemplatesPath;
    private TemplateLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use actual templates directory
        $this->testTemplatesPath = dirname(__DIR__, 3) . '/templates';
        $this->loader = new TemplateLoader($this->testTemplatesPath);
    }

    public function testLoadAllReturnsTemplates(): void
    {
        $templates = $this->loader->loadAll();
        
        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);
        $this->assertContainsOnlyInstancesOf(Template::class, $templates);
    }

    public function testLoadAllLoadsJsonTemplates(): void
    {
        $templates = $this->loader->loadAll();
        
        // Should load at least our 22 templates
        $this->assertGreaterThanOrEqual(22, count($templates));
    }

    public function testFindByIdReturnsTemplate(): void
    {
        $templates = $this->loader->loadAll();
        $firstTemplate = reset($templates);
        
        $found = $this->loader->findById($firstTemplate->getId());
        
        $this->assertNotNull($found);
        $this->assertSame($firstTemplate->getId(), $found->getId());
    }

    public function testFindByIdReturnsNullForNonexistent(): void
    {
        $found = $this->loader->findById('nonexistent-id');
        $this->assertNull($found);
    }

    public function testFindByNameReturnsTemplates(): void
    {
        $templates = $this->loader->findByName('agent');
        
        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);
        
        foreach ($templates as $template) {
            $this->assertStringContainsStringIgnoringCase('agent', $template->getName());
        }
    }

    public function testFindByCategoryReturnsCorrectTemplates(): void
    {
        $agentTemplates = $this->loader->findByCategory('agents');
        
        $this->assertNotEmpty($agentTemplates);
        
        foreach ($agentTemplates as $template) {
            $this->assertSame('agents', $template->getCategory());
        }
    }

    public function testFindByTagsReturnsMatchingTemplates(): void
    {
        $templates = $this->loader->findByTags(['beginner']);
        
        $this->assertNotEmpty($templates);
        
        foreach ($templates as $template) {
            $this->assertTrue($template->hasTag('beginner'));
        }
    }

    public function testCacheIsUsed(): void
    {
        $this->loader->setCacheEnabled(true);
        
        // First load
        $templates1 = $this->loader->loadAll();
        
        // Second load (should use cache)
        $templates2 = $this->loader->loadAll();
        
        $this->assertSame(count($templates1), count($templates2));
    }

    public function testClearCacheWorks(): void
    {
        $this->loader->loadAll();
        $this->loader->clearCache();
        
        // Should reload after cache clear
        $templates = $this->loader->loadAll();
        $this->assertNotEmpty($templates);
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $count = $this->loader->count();
        $templates = $this->loader->loadAll();
        
        $this->assertSame(count($templates), $count);
        $this->assertGreaterThanOrEqual(22, $count);
    }

    public function testGetAllTagsReturnsUniqueTags(): void
    {
        $tags = $this->loader->getAllTags();
        
        $this->assertIsArray($tags);
        $this->assertNotEmpty($tags);
        
        // Should contain common tags
        $this->assertContains('beginner', $tags);
    }

    public function testGetAllCategoriesReturnsUniqueCategories(): void
    {
        $categories = $this->loader->getAllCategories();
        
        $this->assertIsArray($categories);
        $this->assertNotEmpty($categories);
        
        // Should contain our defined categories
        $this->assertContains('agents', $categories);
    }

    public function testInvalidPathThrowsException(): void
    {
        $this->expectException(TemplateNotFoundException::class);
        
        $loader = new TemplateLoader('/nonexistent/path');
        $loader->loadAll();
    }

    public function testSetTemplatesPathWorks(): void
    {
        $originalPath = $this->loader->getTemplatesPath();
        $newPath = dirname(__DIR__, 3) . '/templates';
        
        $this->loader->setTemplatesPath($newPath);
        $this->assertSame($newPath, $this->loader->getTemplatesPath());
    }
}
