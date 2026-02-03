<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\MCP;

use ClaudeAgents\MCP\AgentRegistry;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;
use Mockery;

class AgentRegistryTest extends TestCase
{
    private ClaudePhp $client;
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Mockery::mock(ClaudePhp::class);
        $this->registry = new AgentRegistry($this->client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testRegistryInitialization(): void
    {
        $this->assertInstanceOf(AgentRegistry::class, $this->registry);
    }

    public function testGetAllAgents(): void
    {
        $agents = $this->registry->all();
        $this->assertIsArray($agents);
        $this->assertNotEmpty($agents);
    }

    public function testSearchAgents(): void
    {
        $results = $this->registry->search('react');
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        
        foreach ($results as $agent) {
            $this->assertArrayHasKey('name', $agent);
            $this->assertArrayHasKey('type', $agent);
        }
    }

    public function testSearchAgentsByType(): void
    {
        $results = $this->registry->search(null, 'react');
        $this->assertIsArray($results);
        
        foreach ($results as $agent) {
            $this->assertEquals('react', $agent['type']);
        }
    }

    public function testSearchAgentsByCapabilities(): void
    {
        $results = $this->registry->search(null, null, ['reasoning']);
        $this->assertIsArray($results);
        
        foreach ($results as $agent) {
            $this->assertArrayHasKey('capabilities', $agent);
            $this->assertContains('reasoning', $agent['capabilities']);
        }
    }

    public function testGetAgent(): void
    {
        $agent = $this->registry->getAgent('ReactAgent');
        
        $this->assertIsArray($agent);
        $this->assertEquals('ReactAgent', $agent['name']);
        $this->assertArrayHasKey('description', $agent);
        $this->assertArrayHasKey('capabilities', $agent);
    }

    public function testGetNonexistentAgent(): void
    {
        $agent = $this->registry->getAgent('NonexistentAgent');
        $this->assertNull($agent);
    }

    public function testGetTypes(): void
    {
        $types = $this->registry->getTypes();
        
        $this->assertIsArray($types);
        $this->assertNotEmpty($types);
        $this->assertContains('react', $types);
        $this->assertContains('rag', $types);
    }

    public function testCount(): void
    {
        $count = $this->registry->count();
        $this->assertGreaterThan(0, $count);
    }

    public function testCountByType(): void
    {
        $count = $this->registry->count('react');
        $this->assertGreaterThan(0, $count);
    }

    public function testGetFactory(): void
    {
        $factory = $this->registry->getFactory();
        $this->assertNotNull($factory);
    }
}
