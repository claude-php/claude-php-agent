<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use ClaudeAgents\Agents\ReflexAgent;
use ClaudePhp\ClaudePhp;
use ClaudePhp\Resources\Messages\Messages;
use ClaudePhp\Types\Message;
use ClaudePhp\Types\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReflexAgentTest extends TestCase
{
    private ClaudePhp $client;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClaudePhp::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testConstructorWithDefaults(): void
    {
        $agent = new ReflexAgent($this->client);

        $this->assertEquals('reflex_agent', $agent->getName());
        $this->assertEmpty($agent->getRules());
    }

    public function testConstructorWithCustomOptions(): void
    {
        $agent = new ReflexAgent($this->client, [
            'name' => 'custom_reflex',
            'use_llm_fallback' => false,
            'logger' => $this->logger,
        ]);

        $this->assertEquals('custom_reflex', $agent->getName());
    }

    public function testAddRuleWithStringCondition(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule(
            'greeting',
            'hello',
            'Hi there!',
            priority: 10
        );

        $rules = $agent->getRules();
        $this->assertCount(1, $rules);
        $this->assertEquals('greeting', $rules[0]['name']);
        $this->assertEquals(10, $rules[0]['priority']);
    }

    public function testAddRuleWithCallableCondition(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule(
            'length_check',
            fn (string $input) => strlen($input) > 10,
            'Input is long',
            priority: 5
        );

        $rules = $agent->getRules();
        $this->assertCount(1, $rules);
        $this->assertIsCallable($rules[0]['condition']);
    }

    public function testAddRuleWithCallableAction(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule(
            'uppercase',
            'test',
            fn (string $input) => strtoupper($input)
        );

        $result = $agent->run('test input');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('TEST INPUT', $result->getAnswer());
    }

    public function testRulePrioritySorting(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        // Add rules in random order
        $agent->addRule('low', 'test', 'Low priority', priority: 1);
        $agent->addRule('high', 'test', 'High priority', priority: 10);
        $agent->addRule('medium', 'test', 'Medium priority', priority: 5);

        $rules = $agent->getRules();

        // Should be sorted by priority descending
        $this->assertEquals('high', $rules[0]['name']);
        $this->assertEquals('medium', $rules[1]['name']);
        $this->assertEquals('low', $rules[2]['name']);
    }

    public function testAddMultipleRules(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $rules = [
            [
                'name' => 'rule1',
                'condition' => 'hello',
                'action' => 'Hello!',
                'priority' => 10,
            ],
            [
                'name' => 'rule2',
                'condition' => 'bye',
                'action' => 'Goodbye!',
                'priority' => 5,
            ],
        ];

        $agent->addRules($rules);

        $this->assertCount(2, $agent->getRules());
    }

    public function testRunWithMatchingStringCondition(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule('greeting', 'hello', 'Hi there!');

        $result = $agent->run('hello world');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hi there!', $result->getAnswer());
        $this->assertEquals(1, $result->getIterations());

        $metadata = $result->getMetadata();
        $this->assertEquals('greeting', $metadata['rule_matched']);
    }

    public function testRunWithRegexCondition(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule('email', '/\b[\w\.-]+@[\w\.-]+\.\w{2,}\b/', 'Email detected!');

        $result = $agent->run('Contact me at test@example.com');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Email detected!', $result->getAnswer());
    }

    public function testRunWithCallableCondition(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule(
            'long_input',
            fn (string $input) => strlen($input) > 20,
            'That\'s a long message!'
        );

        $result = $agent->run('This is a very long input message that exceeds twenty characters');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('That\'s a long message!', $result->getAnswer());
    }

    public function testRunWithCallableAction(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule(
            'echo',
            'echo',
            fn (string $input) => "You said: {$input}"
        );

        $result = $agent->run('echo hello');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('You said: echo hello', $result->getAnswer());
    }

    public function testRunWithNoMatchingRuleAndNoFallback(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule('greeting', 'hello', 'Hi!');

        $result = $agent->run('goodbye world');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No matching rule found', $result->getError());

        $metadata = $result->getMetadata();
        $this->assertEquals(1, $metadata['rules_checked']);
    }

    public function testRunWithLLMFallback(): void
    {
        $usage = new Usage(input_tokens: 50, output_tokens: 25);

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'LLM response to unmatched input'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(Messages::class);
        $mockMessages->expects($this->once())
            ->method('create')
            ->willReturn($mockResponse);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($mockMessages);

        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => true]);
        $agent->addRule('greeting', 'hello', 'Hi!');

        $result = $agent->run('something unexpected');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('LLM response to unmatched input', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertTrue($metadata['used_llm_fallback']);
    }

    public function testRemoveRule(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule('rule1', 'test1', 'Action1');
        $agent->addRule('rule2', 'test2', 'Action2');

        $this->assertCount(2, $agent->getRules());

        $removed = $agent->removeRule('rule1');

        $this->assertTrue($removed);

        $rules = $agent->getRules();
        $this->assertCount(1, $rules);

        // Get the first rule from the values (array_filter can leave gaps in keys)
        $remainingRule = array_values($rules)[0];
        $this->assertEquals('rule2', $remainingRule['name']);
    }

    public function testRemoveNonExistentRule(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule('rule1', 'test', 'Action');

        $removed = $agent->removeRule('nonexistent');

        $this->assertFalse($removed);
        $this->assertCount(1, $agent->getRules());
    }

    public function testClearRules(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule('rule1', 'test1', 'Action1');
        $agent->addRule('rule2', 'test2', 'Action2');
        $agent->addRule('rule3', 'test3', 'Action3');

        $this->assertCount(3, $agent->getRules());

        $agent->clearRules();

        $this->assertEmpty($agent->getRules());
    }

    public function testClearRulesReturnsAgent(): void
    {
        $agent = new ReflexAgent($this->client);

        $result = $agent->clearRules();

        $this->assertSame($agent, $result);
    }

    public function testCaseInsensitiveStringMatching(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule('greeting', 'hello', 'Hi!');

        $result1 = $agent->run('HELLO world');
        $result2 = $agent->run('Hello World');
        $result3 = $agent->run('hello WORLD');

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
        $this->assertTrue($result3->isSuccess());
    }

    public function testHigherPriorityRuleMatchesFirst(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        // Both rules match "test", but high priority should win
        $agent->addRule('low', 'test', 'Low priority match', priority: 1);
        $agent->addRule('high', 'test', 'High priority match', priority: 10);

        $result = $agent->run('test input');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('High priority match', $result->getAnswer());

        $metadata = $result->getMetadata();
        $this->assertEquals('high', $metadata['rule_matched']);
        $this->assertEquals(10, $metadata['priority']);
    }

    public function testMultipleTextBlocksInLLMResponse(): void
    {
        $usage = new Usage(input_tokens: 50, output_tokens: 25);

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [
                ['type' => 'text', 'text' => 'First part'],
                ['type' => 'text', 'text' => 'Second part'],
            ],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(Messages::class);
        $mockMessages->expects($this->once())
            ->method('create')
            ->willReturn($mockResponse);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($mockMessages);

        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => true]);

        $result = $agent->run('unmatched input');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals("First part\nSecond part", $result->getAnswer());
    }

    public function testExceptionHandlingInRuleAction(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        $agent->addRule(
            'throwing',
            'error',
            fn () => throw new \RuntimeException('Action failed')
        );

        $result = $agent->run('error trigger');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Action failed', $result->getError());
    }

    public function testLLMFallbackWithException(): void
    {
        $mockMessages = $this->createMock(Messages::class);

        $mockMessages->expects($this->once())
            ->method('create')
            ->willThrowException(new \RuntimeException('API error'));

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($mockMessages);

        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => true]);

        $result = $agent->run('unmatched input');

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('LLM fallback failed', $result->getError());
    }

    public function testGetRulesReturnsCorrectFormat(): void
    {
        $agent = new ReflexAgent($this->client);

        $agent->addRule('test_rule', 'condition', 'action', 5);

        $rules = $agent->getRules();

        $this->assertIsArray($rules);
        $this->assertCount(1, $rules);
        $this->assertArrayHasKey('name', $rules[0]);
        $this->assertArrayHasKey('condition', $rules[0]);
        $this->assertArrayHasKey('action', $rules[0]);
        $this->assertArrayHasKey('priority', $rules[0]);
    }

    public function testFluentInterface(): void
    {
        $agent = new ReflexAgent($this->client);

        $result = $agent
            ->addRule('rule1', 'test1', 'action1')
            ->addRule('rule2', 'test2', 'action2')
            ->clearRules();

        $this->assertInstanceOf(ReflexAgent::class, $result);
    }

    public function testEmptyRulesListInLLMPrompt(): void
    {
        $usage = new Usage(input_tokens: 50, output_tokens: 25);

        $mockResponse = new Message(
            id: 'msg_test',
            type: 'message',
            role: 'assistant',
            content: [['type' => 'text', 'text' => 'Response']],
            model: 'claude-sonnet-4-5',
            stop_reason: 'end_turn',
            stop_sequence: null,
            usage: $usage
        );

        $mockMessages = $this->createMock(Messages::class);
        $mockMessages->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) {
                $this->assertStringContainsString('None defined', $params['system']);

                return true;
            }))
            ->willReturn($mockResponse);

        $this->client->expects($this->once())
            ->method('messages')
            ->willReturn($mockMessages);

        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => true]);

        $agent->run('test');
    }

    public function testSubstringMatchingFallback(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        // Not a valid regex, should fall back to substring match
        $agent->addRule('partial', 'world', 'Found world!');

        $result = $agent->run('hello world today');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Found world!', $result->getAnswer());
    }

    public function testComplexRegexPattern(): void
    {
        $agent = new ReflexAgent($this->client, ['use_llm_fallback' => false]);

        // Match numbers
        $agent->addRule('number', '/\d+/', 'Number detected!');

        $result = $agent->run('I have 42 apples');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Number detected!', $result->getAnswer());
    }
}
