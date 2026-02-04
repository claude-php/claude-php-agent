<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Templates;

use ClaudeAgents\Templates\TemplateInstantiator;
use ClaudeAgents\Templates\Template;
use ClaudeAgents\Templates\Exceptions\TemplateInstantiationException;
use ClaudeAgents\Agent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class TemplateInstantiatorTest extends TestCase
{
    private TemplateInstantiator $instantiator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instantiator = new TemplateInstantiator();
    }

    public function testInstantiateRequiresValidTemplate(): void
    {
        $this->expectException(TemplateInstantiationException::class);
        
        $template = Template::fromArray([
            'name' => 'Invalid',
            // Missing required fields
            'config' => []
        ]);
        
        $this->instantiator->instantiate($template);
    }

    public function testInstantiateRequiresApiKey(): void
    {
        $this->expectException(TemplateInstantiationException::class);
        $this->expectExceptionMessage('api_key');
        
        // Temporarily unset API key if it exists
        $originalKey = getenv('ANTHROPIC_API_KEY');
        putenv('ANTHROPIC_API_KEY');
        
        try {
            $template = Template::fromArray([
                'name' => 'Test',
                'description' => 'Test',
                'category' => 'agents',
                'config' => [
                    'agent_type' => 'Agent'
                ]
            ]);
            
            $this->instantiator->instantiate($template, []);
        } finally {
            // Restore API key
            if ($originalKey !== false) {
                putenv("ANTHROPIC_API_KEY={$originalKey}");
            }
        }
    }

    public function testInstantiateWithClientOverride(): void
    {
        if (!getenv('ANTHROPIC_API_KEY')) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }
        
        $client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
        
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Test',
            'category' => 'agents',
            'config' => [
                'agent_type' => 'Agent',
                'model' => 'claude-sonnet-4-5'
            ]
        ]);
        
        $agent = $this->instantiator->instantiate($template, [
            'client' => $client
        ]);
        
        $this->assertInstanceOf(Agent::class, $agent);
    }

    public function testGetRegisteredAgentTypesReturnsArray(): void
    {
        $types = $this->instantiator->getRegisteredAgentTypes();
        
        $this->assertIsArray($types);
        $this->assertContains('Agent', $types);
        $this->assertContains('ReactAgent', $types);
        $this->assertContains('ReflectionAgent', $types);
    }

    public function testHasAgentTypeWorks(): void
    {
        $this->assertTrue($this->instantiator->hasAgentType('Agent'));
        $this->assertTrue($this->instantiator->hasAgentType('ReactAgent'));
        $this->assertFalse($this->instantiator->hasAgentType('NonexistentAgent'));
    }

    public function testRegisterCustomAgentType(): void
    {
        $this->instantiator->registerAgentType('CustomAgent', Agent::class);
        
        $this->assertTrue($this->instantiator->hasAgentType('CustomAgent'));
        $this->assertContains('CustomAgent', $this->instantiator->getRegisteredAgentTypes());
    }

    public function testRegisterInvalidClassThrowsException(): void
    {
        $this->expectException(TemplateInstantiationException::class);
        
        $this->instantiator->registerAgentType('Invalid', 'NonexistentClass');
    }

    public function testInstantiateWithInvalidAgentTypeThrowsException(): void
    {
        if (!getenv('ANTHROPIC_API_KEY')) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }
        
        $this->expectException(TemplateInstantiationException::class);
        
        $template = Template::fromArray([
            'name' => 'Test',
            'description' => 'Test',
            'category' => 'agents',
            'config' => [
                'agent_type' => 'NonexistentAgentType'
            ]
        ]);
        
        $this->instantiator->instantiate($template, [
            'api_key' => getenv('ANTHROPIC_API_KEY')
        ]);
    }
}
