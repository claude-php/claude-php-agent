<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Integration\Templates;

use ClaudeAgents\Templates\TemplateManager;
use ClaudeAgents\Agent;
use ClaudeAgents\Tools\Tool;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests using real Anthropic API
 * 
 * @group integration
 */
class TemplateIntegrationTest extends TestCase
{
    private TemplateManager $manager;
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->apiKey = getenv('ANTHROPIC_API_KEY');
        if (!$this->apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY environment variable not set');
        }
        
        $templatesPath = dirname(__DIR__, 3) . '/templates';
        $this->manager = new TemplateManager($templatesPath);
    }

    public function testInstantiateAndRunBasicAgent(): void
    {
        $calculator = $this->createCalculatorTool();
        
        $agent = $this->manager->instantiate('Basic Agent', [
            'api_key' => $this->apiKey,
            'tools' => [$calculator]
        ]);
        
        $this->assertInstanceOf(Agent::class, $agent);
        
        $result = $agent->run('What is 15 + 27?');
        
        $this->assertNotEmpty($result->getAnswer());
        $this->assertStringContainsString('42', $result->getAnswer());
    }

    public function testInstantiateAndRunReActAgent(): void
    {
        $calculator = $this->createCalculatorTool();
        
        $agent = $this->manager->instantiate('ReAct Agent', [
            'api_key' => $this->apiKey,
            'tools' => [$calculator]
        ]);
        
        $this->assertInstanceOf(\ClaudeAgents\Agents\ReactAgent::class, $agent);
        
        $result = $agent->run('Calculate 10 * 5');
        
        $this->assertNotEmpty($result->getAnswer());
        $this->assertStringContainsString('50', $result->getAnswer());
    }

    public function testInstantiateDialogAgent(): void
    {
        $agent = $this->manager->instantiate('Dialog Agent', [
            'api_key' => $this->apiKey
        ]);
        
        $this->assertInstanceOf(\ClaudeAgents\Agents\DialogAgent::class, $agent);
        
        $result = $agent->run('Hello, my name is Alice.');
        
        $this->assertNotEmpty($result->getAnswer());
        $this->assertStringContainsStringIgnoringCase('alice', $result->getAnswer());
    }

    public function testInstantiateChainOfThoughtAgent(): void
    {
        $agent = $this->manager->instantiate('Chain-of-Thought Agent', [
            'api_key' => $this->apiKey
        ]);
        
        $this->assertInstanceOf(\ClaudeAgents\Agents\ChainOfThoughtAgent::class, $agent);
        
        $result = $agent->run('If it takes 2 hours to dry 2 shirts outside, how long does it take to dry 4 shirts?');
        
        $this->assertNotEmpty($result->getAnswer());
    }

    public function testInstantiateReflectionAgent(): void
    {
        $agent = $this->manager->instantiate('Reflection Agent', [
            'api_key' => $this->apiKey
        ]);
        
        $this->assertInstanceOf(\ClaudeAgents\Agents\ReflectionAgent::class, $agent);
        
        $result = $agent->run('Write a haiku about coding.');
        
        $this->assertNotEmpty($result->getAnswer());
    }

    public function testTemplateConfigurationOverrides(): void
    {
        $calculator = $this->createCalculatorTool();
        
        // Instantiate with custom configuration overrides
        $agent = $this->manager->instantiate('ReAct Agent', [
            'api_key' => $this->apiKey,
            'model' => 'claude-sonnet-4-5', // Override model
            'max_iterations' => 3, // Override iterations
            'temperature' => 0.5, // Override temperature
            'tools' => [$calculator]
        ]);
        
        $this->assertInstanceOf(\ClaudeAgents\Agents\ReactAgent::class, $agent);
        
        $result = $agent->run('What is 100 / 4?');
        
        $this->assertNotEmpty($result->getAnswer());
        $this->assertStringContainsString('25', $result->getAnswer());
    }

    public function testMultipleTemplateInstantiations(): void
    {
        $calculator = $this->createCalculatorTool();
        
        // Instantiate multiple agents from different templates
        $basicAgent = $this->manager->instantiate('Basic Agent', [
            'api_key' => $this->apiKey,
            'tools' => [$calculator]
        ]);
        
        $reactAgent = $this->manager->instantiate('ReAct Agent', [
            'api_key' => $this->apiKey,
            'tools' => [$calculator]
        ]);
        
        $this->assertInstanceOf(Agent::class, $basicAgent);
        $this->assertInstanceOf(\ClaudeAgents\Agents\ReactAgent::class, $reactAgent);
        
        // Run both agents
        $result1 = $basicAgent->run('Calculate 5 + 5');
        $result2 = $reactAgent->run('Calculate 10 - 3');
        
        $this->assertStringContainsString('10', $result1->getAnswer());
        $this->assertStringContainsString('7', $result2->getAnswer());
    }

    public function testAllBeginnerTemplatesAreInstantiable(): void
    {
        $templates = $this->manager->loadAll();
        $beginnerTemplates = array_filter($templates, function($template) {
            return $template->getMetadata('difficulty') === 'beginner';
        });
        
        $this->assertNotEmpty($beginnerTemplates);
        
        foreach ($beginnerTemplates as $template) {
            try {
                $agent = $this->manager->instantiateFromTemplate($template, [
                    'api_key' => $this->apiKey
                ]);
                
                $this->assertNotNull($agent);
            } catch (\Exception $e) {
                $this->fail("Failed to instantiate beginner template '{$template->getName()}': " . $e->getMessage());
            }
        }
    }

    private function createCalculatorTool(): Tool
    {
        return Tool::create('calculate')
            ->description('Perform mathematical calculations')
            ->parameter('expression', 'string', 'Math expression to evaluate')
            ->required('expression')
            ->handler(function (array $input): string {
                $expression = $input['expression'];
                // Safe evaluation for basic math
                if (preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $expression)) {
                    try {
                        return (string) eval("return {$expression};");
                    } catch (\Throwable $e) {
                        return "Error: " . $e->getMessage();
                    }
                }
                return "Invalid expression";
            });
    }
}
