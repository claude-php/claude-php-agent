<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Prompts;

use ClaudeAgents\Exceptions\ValidationException;
use ClaudeAgents\Prompts\FewShotTemplate;
use PHPUnit\Framework\TestCase;

class FewShotTemplateTest extends TestCase
{
    public function testCreateFewShotTemplate(): void
    {
        $template = FewShotTemplate::create();

        $this->assertInstanceOf(FewShotTemplate::class, $template);
    }

    public function testAddExample(): void
    {
        $template = FewShotTemplate::create()
            ->addExample('What is 2+2?', '4');

        $examples = $template->getExamples();

        $this->assertCount(1, $examples);
        $this->assertSame('What is 2+2?', $examples[0]['input']);
        $this->assertSame('4', $examples[0]['output']);
    }

    public function testAddMultipleExamples(): void
    {
        $template = FewShotTemplate::create()
            ->addExample('What is 2+2?', '4')
            ->addExample('What is 3+3?', '6');

        $examples = $template->getExamples();

        $this->assertCount(2, $examples);
    }

    public function testWithExamples(): void
    {
        $examples = [
            ['input' => 'Hello', 'output' => 'Hi'],
            ['input' => 'Goodbye', 'output' => 'Bye'],
        ];

        $template = FewShotTemplate::create()
            ->withExamples($examples);

        $this->assertCount(2, $template->getExamples());
    }

    public function testFormatWithExamplesOnly(): void
    {
        $template = FewShotTemplate::create()
            ->addExample('Question 1', 'Answer 1')
            ->addExample('Question 2', 'Answer 2');

        $result = $template->format([]);

        $this->assertStringContainsString('Input: Question 1', $result);
        $this->assertStringContainsString('Output: Answer 1', $result);
        $this->assertStringContainsString('Input: Question 2', $result);
        $this->assertStringContainsString('Output: Answer 2', $result);
    }

    public function testFormatWithPrefix(): void
    {
        $template = FewShotTemplate::create()
            ->withPrefix('Translate to Spanish:')
            ->addExample('Hello', 'Hola');

        $result = $template->format([]);

        $this->assertStringContainsString('Translate to Spanish:', $result);
    }

    public function testFormatWithSuffix(): void
    {
        $template = FewShotTemplate::create()
            ->addExample('cat', 'animal')
            ->withSuffix('Now classify:');

        $result = $template->format([]);

        $this->assertStringContainsString('Now classify:', $result);
    }

    public function testFormatWithInputTemplate(): void
    {
        $template = FewShotTemplate::create()
            ->addExample('2+2', '4')
            ->withInputTemplate('{problem}');

        $result = $template->format(['problem' => '5+5']);

        $this->assertStringContainsString('Input: 5+5', $result);
    }

    public function testCustomExampleFormat(): void
    {
        $template = FewShotTemplate::create()
            ->addExample('question', 'answer')
            ->withExampleFormat('Q: ', 'A: ');

        $result = $template->format([]);

        $this->assertStringContainsString('Q: question', $result);
        $this->assertStringContainsString('A: answer', $result);
    }

    public function testCustomExampleSeparator(): void
    {
        $template = FewShotTemplate::create()
            ->addExample('first', 'one')
            ->addExample('second', 'two')
            ->withExampleSeparator("\n---\n");

        $result = $template->format([]);

        $this->assertStringContainsString("\n---\n", $result);
    }

    public function testFormatExamplesOnly(): void
    {
        $template = FewShotTemplate::create()
            ->addExample('in', 'out')
            ->withExampleFormat('Input: ', 'Output: ');

        $examples = $template->formatExamples();

        $this->assertStringContainsString('Input: in', $examples);
        $this->assertStringContainsString('Output: out', $examples);
    }

    public function testGetVariablesFromPrefix(): void
    {
        $template = FewShotTemplate::create()
            ->withPrefix('Translate {text} to {language}');

        $variables = $template->getVariables();

        $this->assertContains('text', $variables);
        $this->assertContains('language', $variables);
    }

    public function testGetVariablesFromSuffix(): void
    {
        $template = FewShotTemplate::create()
            ->withSuffix('Now translate {input}');

        $variables = $template->getVariables();

        $this->assertContains('input', $variables);
    }

    public function testGetVariablesFromInputTemplate(): void
    {
        $template = FewShotTemplate::create()
            ->withInputTemplate('Process {data}');

        $variables = $template->getVariables();

        $this->assertContains('data', $variables);
    }

    public function testValidatePassesWithAllVariables(): void
    {
        $template = FewShotTemplate::create()
            ->withPrefix('Task: {task}')
            ->withInputTemplate('{input}');

        $this->expectNotToPerformAssertions();
        $template->validate(['task' => 'test', 'input' => 'data']);
    }

    public function testValidateThrowsOnMissingVariable(): void
    {
        $template = FewShotTemplate::create()
            ->withPrefix('Task: {task}')
            ->withInputTemplate('{input}');

        $this->expectException(ValidationException::class);
        $template->validate(['task' => 'test']);
    }

    public function testForClassification(): void
    {
        $examples = [
            ['input' => 'happy news', 'output' => 'positive'],
            ['input' => 'sad news', 'output' => 'negative'],
        ];

        $template = FewShotTemplate::forClassification(
            $examples,
            ['positive', 'negative', 'neutral']
        );

        $result = $template->format(['input' => 'great day']);

        $this->assertStringContainsString('positive, negative, neutral', $result);
        $this->assertStringContainsString('happy news', $result);
        $this->assertStringContainsString('great day', $result);
    }

    public function testForExtraction(): void
    {
        $examples = [
            ['input' => 'John lives in NYC', 'output' => 'John, NYC'],
        ];

        $template = FewShotTemplate::forExtraction($examples, 'entities');

        $result = $template->format(['input' => 'Mary works at Google']);

        $this->assertStringContainsString('Extract entities', $result);
        $this->assertStringContainsString('Mary works at Google', $result);
    }

    public function testForTransformation(): void
    {
        $examples = [
            ['input' => 'hello world', 'output' => 'HELLO WORLD'],
        ];

        $template = FewShotTemplate::forTransformation($examples, 'converting to uppercase');

        $result = $template->format(['input' => 'test string']);

        $this->assertStringContainsString('converting to uppercase', $result);
        $this->assertStringContainsString('test string', $result);
    }

    public function testCompleteWorkflow(): void
    {
        $template = FewShotTemplate::create()
            ->withPrefix('Sentiment Analysis Examples:')
            ->addExample('I love this!', 'positive')
            ->addExample('This is terrible', 'negative')
            ->addExample('It\'s okay', 'neutral')
            ->withSuffix('Now analyze:')
            ->withInputTemplate('{text}')
            ->withExampleFormat('Text: ', 'Sentiment: ');

        $result = $template->format(['text' => 'Amazing product!']);

        $this->assertStringContainsString('Sentiment Analysis Examples:', $result);
        $this->assertStringContainsString('Text: I love this!', $result);
        $this->assertStringContainsString('Sentiment: positive', $result);
        $this->assertStringContainsString('Now analyze:', $result);
        $this->assertStringContainsString('Text: Amazing product!', $result);
    }

    public function testEmptyTemplate(): void
    {
        $template = FewShotTemplate::create();

        $result = $template->format([]);

        $this->assertEmpty($result);
    }
}
