<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Templates;

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Templates\Template;
use ClaudeAgents\Templates\Exceptions\TemplateNotFoundException;
use PHPUnit\Framework\TestCase;

class TemplateManagerTest extends TestCase
{
    private TemplateManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $templatesPath = dirname(__DIR__, 3) . '/templates';
        $this->manager = new TemplateManager($templatesPath);
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $instance1 = TemplateManager::getInstance();
        $instance2 = TemplateManager::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    public function testSearchReturnsAllWhenNoFilters(): void
    {
        $results = $this->manager->search();
        
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertContainsOnlyInstancesOf(Template::class, $results);
    }

    public function testSearchByQueryFiltersResults(): void
    {
        $results = $this->manager->search(query: 'ReAct');
        
        $this->assertNotEmpty($results);
        
        foreach ($results as $template) {
            $matches = str_contains(strtolower($template->getName()), 'react') ||
                      str_contains(strtolower($template->getDescription()), 'react');
            $this->assertTrue($matches);
        }
    }

    public function testSearchByCategoryFiltersResults(): void
    {
        $results = $this->manager->search(category: 'agents');
        
        $this->assertNotEmpty($results);
        
        foreach ($results as $template) {
            $this->assertSame('agents', $template->getCategory());
        }
    }

    public function testSearchByTagsFiltersResults(): void
    {
        $results = $this->manager->search(tags: ['beginner']);
        
        $this->assertNotEmpty($results);
        
        foreach ($results as $template) {
            $this->assertTrue($template->hasTag('beginner'));
        }
    }

    public function testSearchWithFieldsReturnsArrays(): void
    {
        $results = $this->manager->search(
            query: 'agent',
            fields: ['name', 'description']
        );
        
        $this->assertNotEmpty($results);
        
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('description', $result);
            $this->assertArrayNotHasKey('config', $result); // Not in fields list
        }
    }

    public function testGetByIdReturnsTemplate(): void
    {
        $templates = $this->manager->loadAll();
        $firstTemplate = reset($templates);
        
        $found = $this->manager->getById($firstTemplate->getId());
        
        $this->assertInstanceOf(Template::class, $found);
        $this->assertSame($firstTemplate->getId(), $found->getId());
    }

    public function testGetByIdThrowsForNonexistent(): void
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->manager->getById('nonexistent-id');
    }

    public function testGetByNameReturnsTemplate(): void
    {
        $template = $this->manager->getByName('Basic Agent');
        
        $this->assertInstanceOf(Template::class, $template);
        $this->assertSame('Basic Agent', $template->getName());
    }

    public function testGetByNameThrowsForNonexistent(): void
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->manager->getByName('Nonexistent Template');
    }

    public function testGetByCategoryReturnsTemplates(): void
    {
        $templates = $this->manager->getByCategory('chatbots');
        
        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);
        
        foreach ($templates as $template) {
            $this->assertSame('chatbots', $template->getCategory());
        }
    }

    public function testGetByTagsReturnsTemplates(): void
    {
        $templates = $this->manager->getByTags(['conversation']);
        
        $this->assertIsArray($templates);
        
        foreach ($templates as $template) {
            $this->assertTrue($template->hasTag('conversation'));
        }
    }

    public function testGetCategoriesReturnsAllCategories(): void
    {
        $categories = $this->manager->getCategories();
        
        $this->assertIsArray($categories);
        $this->assertNotEmpty($categories);
        $this->assertContains('agents', $categories);
        $this->assertContains('chatbots', $categories);
    }

    public function testGetAllTagsReturnsAllTags(): void
    {
        $tags = $this->manager->getAllTags();
        
        $this->assertIsArray($tags);
        $this->assertNotEmpty($tags);
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $count = $this->manager->count();
        $templates = $this->manager->loadAll();
        
        $this->assertSame(count($templates), $count);
        $this->assertGreaterThanOrEqual(22, $count);
    }

    public function testLoadAllReturnsTemplates(): void
    {
        $templates = $this->manager->loadAll();
        
        $this->assertIsArray($templates);
        $this->assertGreaterThanOrEqual(22, count($templates));
        $this->assertContainsOnlyInstancesOf(Template::class, $templates);
    }

    public function testClearCacheWorks(): void
    {
        $this->manager->loadAll();
        $result = $this->manager->clearCache();
        
        $this->assertSame($this->manager, $result); // Should return self for chaining
    }

    public function testGetLoaderReturnsLoader(): void
    {
        $loader = $this->manager->getLoader();
        $this->assertInstanceOf(\ClaudeAgents\Templates\TemplateLoader::class, $loader);
    }

    public function testGetInstantiatorReturnsInstantiator(): void
    {
        $instantiator = $this->manager->getInstantiator();
        $this->assertInstanceOf(\ClaudeAgents\Templates\TemplateInstantiator::class, $instantiator);
    }

    public function testGetExporterReturnsExporter(): void
    {
        $exporter = $this->manager->getExporter();
        $this->assertInstanceOf(\ClaudeAgents\Templates\TemplateExporter::class, $exporter);
    }

    public function testStaticSearchWorks(): void
    {
        $results = TemplateManager::searchTemplates(query: 'agent');
        
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }
}
