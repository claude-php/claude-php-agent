<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Prompts;

use ClaudeAgents\Exceptions\ValidationException;
use ClaudeAgents\Prompts\PromptTemplate;
use PHPUnit\Framework\TestCase;

class PromptTemplateTest extends TestCase
{
    public function testCreateTemplate(): void
    {
        $template = PromptTemplate::create('Hello {name}');

        $this->assertInstanceOf(PromptTemplate::class, $template);
    }

    public function testFormatWithSingleVariable(): void
    {
        $template = PromptTemplate::create('Hello {name}');
        $result = $template->format(['name' => 'Alice']);

        $this->assertSame('Hello Alice', $result);
    }

    public function testFormatWithMultipleVariables(): void
    {
        $template = PromptTemplate::create('Hello {name}, you are {age} years old');
        $result = $template->format(['name' => 'Bob', 'age' => 30]);

        $this->assertSame('Hello Bob, you are 30 years old', $result);
    }

    public function testGetVariablesExtractsCorrectly(): void
    {
        $template = PromptTemplate::create('User {name} has {points} points');
        $variables = $template->getVariables();

        $this->assertCount(2, $variables);
        $this->assertContains('name', $variables);
        $this->assertContains('points', $variables);
    }

    public function testGetVariablesHandlesDuplicates(): void
    {
        $template = PromptTemplate::create('Hello {name}, goodbye {name}');
        $variables = $template->getVariables();

        $this->assertCount(1, $variables);
        $this->assertSame(['name'], $variables);
    }

    public function testGetVariablesWithNoVariables(): void
    {
        $template = PromptTemplate::create('Static text with no variables');
        $variables = $template->getVariables();

        $this->assertEmpty($variables);
    }

    public function testValidatePassesWithAllVariables(): void
    {
        $template = PromptTemplate::create('Hello {name}');

        $this->expectNotToPerformAssertions();
        $template->validate(['name' => 'Alice']);
    }

    public function testValidateThrowsOnMissingVariable(): void
    {
        $template = PromptTemplate::create('Hello {name}, you have {points} points');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing required variable(s): points');

        $template->validate(['name' => 'Alice']);
    }

    public function testGetTemplate(): void
    {
        $templateStr = 'Hello {name}';
        $template = PromptTemplate::create($templateStr);

        $this->assertSame($templateStr, $template->getTemplate());
    }

    public function testPartialTemplateSubstitution(): void
    {
        $template = PromptTemplate::create('Hello {name}, you are in {city}, {country}');
        $partial = $template->partial(['country' => 'USA']);

        $this->assertInstanceOf(PromptTemplate::class, $partial);
        $this->assertStringContainsString('USA', $partial->getTemplate());
    }

    public function testFormatWithNumericValues(): void
    {
        $template = PromptTemplate::create('The answer is {number}');
        $result = $template->format(['number' => 42]);

        $this->assertSame('The answer is 42', $result);
    }

    public function testFormatWithFloatValues(): void
    {
        $template = PromptTemplate::create('Pi is approximately {pi}');
        $result = $template->format(['pi' => 3.14159]);

        $this->assertSame('Pi is approximately 3.14159', $result);
    }

    public function testFormatWithBooleanValues(): void
    {
        $template = PromptTemplate::create('The statement is {value}');

        $resultTrue = $template->format(['value' => true]);
        $resultFalse = $template->format(['value' => false]);

        $this->assertSame('The statement is 1', $resultTrue);
        $this->assertSame('The statement is ', $resultFalse);
    }

    public function testFormatIgnoresExtraVariables(): void
    {
        $template = PromptTemplate::create('Hello {name}');
        $result = $template->format(['name' => 'Alice', 'extra' => 'ignored']);

        $this->assertSame('Hello Alice', $result);
    }

    public function testFormatPreservesUnmatchedPlaceholders(): void
    {
        $template = PromptTemplate::create('Hello {name}, welcome to {place}');
        $result = $template->format(['name' => 'Alice']);

        $this->assertSame('Hello Alice, welcome to {place}', $result);
    }

    public function testFormatWithEmptyString(): void
    {
        $template = PromptTemplate::create('Hello {name}');
        $result = $template->format(['name' => '']);

        $this->assertSame('Hello ', $result);
    }

    public function testComplexTemplate(): void
    {
        $template = PromptTemplate::create(
            "Analyze the following text:\n\n{text}\n\n" .
            "Focus on: {focus}\n" .
            'Output format: {format}'
        );

        $result = $template->format([
            'text' => 'Sample text here',
            'focus' => 'sentiment',
            'format' => 'JSON',
        ]);

        $this->assertStringContainsString('Sample text here', $result);
        $this->assertStringContainsString('sentiment', $result);
        $this->assertStringContainsString('JSON', $result);
    }

    public function testVariableNamesAreAlphanumeric(): void
    {
        $template = PromptTemplate::create('Hello {name123} and {user_id}');
        $variables = $template->getVariables();

        $this->assertCount(2, $variables);
        $this->assertContains('name123', $variables);
        $this->assertContains('user_id', $variables);
    }

    public function testDoesNotExtractInvalidPlaceholders(): void
    {
        $template = PromptTemplate::create('Test {name} but not { invalid } or {-dash}');
        $variables = $template->getVariables();

        $this->assertCount(1, $variables);
        $this->assertSame(['name'], $variables);
    }

    public function testConstructorDirectUsage(): void
    {
        $template = new PromptTemplate('Direct {construction}');
        $result = $template->format(['construction' => 'works']);

        $this->assertSame('Direct works', $result);
    }

    public function testMultilineTemplate(): void
    {
        $template = PromptTemplate::create(
            "Line 1: {var1}\n" .
            "Line 2: {var2}\n" .
            'Line 3: {var3}'
        );

        $result = $template->format([
            'var1' => 'First',
            'var2' => 'Second',
            'var3' => 'Third',
        ]);

        $this->assertStringContainsString('Line 1: First', $result);
        $this->assertStringContainsString('Line 2: Second', $result);
        $this->assertStringContainsString('Line 3: Third', $result);
    }
}
