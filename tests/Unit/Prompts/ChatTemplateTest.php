<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Prompts;

use ClaudeAgents\Exceptions\ValidationException;
use ClaudeAgents\Prompts\ChatTemplate;
use PHPUnit\Framework\TestCase;

class ChatTemplateTest extends TestCase
{
    public function testCreateChatTemplate(): void
    {
        $template = ChatTemplate::create();

        $this->assertInstanceOf(ChatTemplate::class, $template);
    }

    public function testAddSystemMessage(): void
    {
        $template = ChatTemplate::create()
            ->system('You are a helpful assistant');

        $messages = $template->getMessages();

        $this->assertCount(1, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('You are a helpful assistant', $messages[0]['content']);
    }

    public function testAddUserMessage(): void
    {
        $template = ChatTemplate::create()
            ->user('Hello, how are you?');

        $messages = $template->getMessages();

        $this->assertCount(1, $messages);
        $this->assertSame('user', $messages[0]['role']);
    }

    public function testAddAssistantMessage(): void
    {
        $template = ChatTemplate::create()
            ->assistant('I am doing well, thank you!');

        $messages = $template->getMessages();

        $this->assertCount(1, $messages);
        $this->assertSame('assistant', $messages[0]['role']);
    }

    public function testAddGenericMessage(): void
    {
        $template = ChatTemplate::create()
            ->message('custom_role', 'Custom content');

        $messages = $template->getMessages();

        $this->assertCount(1, $messages);
        $this->assertSame('custom_role', $messages[0]['role']);
        $this->assertSame('Custom content', $messages[0]['content']);
    }

    public function testChainMultipleMessages(): void
    {
        $template = ChatTemplate::create()
            ->system('You are helpful')
            ->user('What is 2+2?')
            ->assistant('4');

        $messages = $template->getMessages();

        $this->assertCount(3, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertSame('assistant', $messages[2]['role']);
    }

    public function testFormatWithVariables(): void
    {
        $template = ChatTemplate::create()
            ->system('You are a {role}')
            ->user('My name is {name}');

        $formatted = $template->format([
            'role' => 'helpful assistant',
            'name' => 'Alice',
        ]);

        $this->assertCount(2, $formatted);
        $this->assertSame('You are a helpful assistant', $formatted[0]['content']);
        $this->assertSame('My name is Alice', $formatted[1]['content']);
    }

    public function testGetVariablesFromMultipleMessages(): void
    {
        $template = ChatTemplate::create()
            ->system('You are a {role}')
            ->user('Hello {name}')
            ->assistant('Nice to meet you, {name}');

        $variables = $template->getVariables();

        $this->assertCount(2, $variables);
        $this->assertContains('role', $variables);
        $this->assertContains('name', $variables);
    }

    public function testValidateWithAllVariables(): void
    {
        $template = ChatTemplate::create()
            ->user('Hello {name}');

        $this->expectNotToPerformAssertions();
        $template->validate(['name' => 'Bob']);
    }

    public function testValidateThrowsOnMissingVariable(): void
    {
        $template = ChatTemplate::create()
            ->user('Hello {name}, you have {points} points');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing required variable(s): points');

        $template->validate(['name' => 'Bob']);
    }

    public function testEmptyTemplate(): void
    {
        $template = ChatTemplate::create();

        $messages = $template->getMessages();
        $variables = $template->getVariables();

        $this->assertEmpty($messages);
        $this->assertEmpty($variables);
    }

    public function testFormatPreservesRoles(): void
    {
        $template = ChatTemplate::create()
            ->system('System {var}')
            ->user('User {var}')
            ->assistant('Assistant {var}');

        $formatted = $template->format(['var' => 'test']);

        $this->assertSame('system', $formatted[0]['role']);
        $this->assertSame('user', $formatted[1]['role']);
        $this->assertSame('assistant', $formatted[2]['role']);
    }

    public function testFormatWithNoVariables(): void
    {
        $template = ChatTemplate::create()
            ->user('Static message');

        $formatted = $template->format([]);

        $this->assertCount(1, $formatted);
        $this->assertSame('Static message', $formatted[0]['content']);
    }

    public function testMultipleVariablesInSingleMessage(): void
    {
        $template = ChatTemplate::create()
            ->user('Hello {first_name} {last_name}, you are {age} years old');

        $formatted = $template->format([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'age' => 30,
        ]);

        $this->assertSame('Hello John Doe, you are 30 years old', $formatted[0]['content']);
    }

    public function testConversationFlow(): void
    {
        $template = ChatTemplate::create()
            ->system('You are a math tutor')
            ->user('What is {problem}?')
            ->assistant('Let me solve {problem} step by step')
            ->user('Can you also solve {problem2}?');

        $formatted = $template->format([
            'problem' => '5 + 7',
            'problem2' => '10 * 3',
        ]);

        $this->assertCount(4, $formatted);
        $this->assertStringContainsString('5 + 7', $formatted[1]['content']);
        $this->assertStringContainsString('10 * 3', $formatted[3]['content']);
    }

    public function testFormatIgnoresExtraVariables(): void
    {
        $template = ChatTemplate::create()
            ->user('Hello {name}');

        $formatted = $template->format([
            'name' => 'Alice',
            'extra' => 'ignored',
            'another' => 'also ignored',
        ]);

        $this->assertStringContainsString('Alice', $formatted[0]['content']);
        $this->assertStringNotContainsString('ignored', $formatted[0]['content']);
    }

    public function testNumericValuesInChat(): void
    {
        $template = ChatTemplate::create()
            ->user('Calculate {x} + {y}');

        $formatted = $template->format(['x' => 5, 'y' => 10]);

        $this->assertSame('Calculate 5 + 10', $formatted[0]['content']);
    }

    public function testComplexConversationTemplate(): void
    {
        $template = ChatTemplate::create()
            ->system('You are a {profession} specializing in {specialty}')
            ->user('I need help with {task}')
            ->assistant('I\'d be happy to help with {task}. Let me {action}.')
            ->user('Also, can you {request}?');

        $formatted = $template->format([
            'profession' => 'developer',
            'specialty' => 'PHP',
            'task' => 'debugging',
            'action' => 'review the code',
            'request' => 'add tests',
        ]);

        $this->assertCount(4, $formatted);
        $this->assertStringContainsString('developer', $formatted[0]['content']);
        $this->assertStringContainsString('PHP', $formatted[0]['content']);
        $this->assertStringContainsString('debugging', $formatted[1]['content']);
        $this->assertStringContainsString('add tests', $formatted[3]['content']);
    }

    public function testGetMessagesReturnsOriginalUnformatted(): void
    {
        $template = ChatTemplate::create()
            ->user('Hello {name}');

        $messages = $template->getMessages();

        $this->assertSame('Hello {name}', $messages[0]['content']);
    }

    public function testMultilineMessagesWithVariables(): void
    {
        $template = ChatTemplate::create()
            ->user("Line 1: {var1}\nLine 2: {var2}\nLine 3: {var3}");

        $formatted = $template->format([
            'var1' => 'First',
            'var2' => 'Second',
            'var3' => 'Third',
        ]);

        $this->assertStringContainsString('Line 1: First', $formatted[0]['content']);
        $this->assertStringContainsString('Line 2: Second', $formatted[0]['content']);
        $this->assertStringContainsString('Line 3: Third', $formatted[0]['content']);
    }
}
