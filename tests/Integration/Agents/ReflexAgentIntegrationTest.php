<?php

declare(strict_types=1);

namespace Tests\Integration\Agents;

use ClaudeAgents\Agents\ReflexAgent;
use ClaudePhp\ClaudePhp;
use PHPUnit\Framework\TestCase;

class ReflexAgentIntegrationTest extends TestCase
{
    private ClaudePhp $client;

    protected function setUp(): void
    {
        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY');

        if (! $apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set');
        }

        $this->client = new ClaudePhp(apiKey: $apiKey);
    }

    public function testReflexAgentWithRealAPI(): void
    {
        $agent = new ReflexAgent($this->client, [
            'name' => 'test_reflex',
            'use_llm_fallback' => true,
        ]);

        // Add some rules
        $agent->addRule('greeting', 'hello', 'Hello! How can I help you?', priority: 10);
        $agent->addRule('farewell', 'goodbye', 'Goodbye! Have a great day!', priority: 9);
        $agent->addRule('help', 'help', 'I can respond to greetings and farewells.', priority: 8);

        // Test rule matching
        $result1 = $agent->run('hello there');
        $this->assertTrue($result1->isSuccess());
        $this->assertEquals('Hello! How can I help you?', $result1->getAnswer());

        // Test LLM fallback for unmatched input
        $result2 = $agent->run('What is the capital of France?');
        $this->assertTrue($result2->isSuccess());
        $this->assertNotEmpty($result2->getAnswer());

        $metadata = $result2->getMetadata();
        $this->assertTrue($metadata['used_llm_fallback'] ?? false);
    }

    public function testReflexAgentWithComplexRules(): void
    {
        $agent = new ReflexAgent($this->client, [
            'use_llm_fallback' => false,
        ]);

        // Add complex pattern matching rules
        $agent->addRule(
            'email_check',
            fn ($input) => preg_match('/\b[\w\.-]+@[\w\.-]+\.\w{2,}\b/', $input),
            function ($input) {
                preg_match('/\b([\w\.-]+@[\w\.-]+\.\w{2,})\b/', $input, $matches);

                return "I found an email address: {$matches[1]}";
            },
            priority: 10
        );

        $agent->addRule(
            'url_check',
            '/https?:\/\/[^\s]+/',
            'I found a URL in your message!',
            priority: 9
        );

        // Test email detection
        $result1 = $agent->run('Contact me at john@example.com');
        $this->assertTrue($result1->isSuccess());
        $this->assertStringContainsString('john@example.com', $result1->getAnswer());

        // Test URL detection
        $result2 = $agent->run('Visit https://example.com for more info');
        $this->assertTrue($result2->isSuccess());
        $this->assertStringContainsString('URL', $result2->getAnswer());
    }

    public function testReflexAgentWithDynamicActions(): void
    {
        $agent = new ReflexAgent($this->client, [
            'use_llm_fallback' => false,
        ]);

        // Add rules with dynamic actions
        $agent->addRule(
            'calculator',
            '/calculate|compute|what is/',
            function ($input) {
                // Simple calculator for addition
                if (preg_match('/(\d+)\s*\+\s*(\d+)/', $input, $matches)) {
                    $result = (int)$matches[1] + (int)$matches[2];

                    return "The answer is: {$result}";
                }

                return "I can calculate simple additions like '5 + 3'";
            },
            priority: 10
        );

        $agent->addRule(
            'uppercase',
            'uppercase',
            fn ($input) => strtoupper(str_replace('uppercase', '', $input)),
            priority: 5
        );

        // Test calculator
        $result1 = $agent->run('what is 15 + 27');
        $this->assertTrue($result1->isSuccess());
        $this->assertStringContainsString('42', $result1->getAnswer());

        // Test uppercase transformer
        $result2 = $agent->run('uppercase hello world');
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals('UPPERCASE HELLO WORLD', $result2->getAnswer());
    }

    public function testReflexAgentPriorityOrdering(): void
    {
        $agent = new ReflexAgent($this->client, [
            'use_llm_fallback' => false,
        ]);

        // Add overlapping rules with different priorities
        $agent->addRule('generic', 'test', 'Generic match', priority: 1);
        $agent->addRule('specific', 'test', 'Specific match', priority: 10);

        $result = $agent->run('test input');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Specific match', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertEquals('specific', $metadata['rule_matched']);
    }

    public function testReflexAgentMultipleRulesBatch(): void
    {
        $agent = new ReflexAgent($this->client, [
            'use_llm_fallback' => false,
        ]);

        $rules = [
            ['name' => 'morning', 'condition' => 'good morning', 'action' => 'Good morning!', 'priority' => 10],
            ['name' => 'afternoon', 'condition' => 'good afternoon', 'action' => 'Good afternoon!', 'priority' => 9],
            ['name' => 'evening', 'condition' => 'good evening', 'action' => 'Good evening!', 'priority' => 8],
            ['name' => 'night', 'condition' => 'good night', 'action' => 'Good night! Sweet dreams!', 'priority' => 7],
        ];

        $agent->addRules($rules);

        // Test each rule
        $this->assertTrue($agent->run('good morning')->isSuccess());
        $this->assertTrue($agent->run('good afternoon')->isSuccess());
        $this->assertTrue($agent->run('good evening')->isSuccess());
        $this->assertTrue($agent->run('good night')->isSuccess());
    }
}
