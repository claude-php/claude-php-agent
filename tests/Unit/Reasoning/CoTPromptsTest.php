<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Reasoning;

use ClaudeAgents\Reasoning\CoTPrompts;
use PHPUnit\Framework\TestCase;

class CoTPromptsTest extends TestCase
{
    public function testZeroShotTrigger(): void
    {
        $trigger = CoTPrompts::zeroShotTrigger();

        $this->assertIsString($trigger);
        $this->assertNotEmpty($trigger);
        $this->assertSame("Let's think step by step.", $trigger);
    }

    public function testZeroShotTriggers(): void
    {
        $triggers = CoTPrompts::zeroShotTriggers();

        $this->assertIsArray($triggers);
        $this->assertNotEmpty($triggers);
        $this->assertGreaterThanOrEqual(3, count($triggers));

        foreach ($triggers as $trigger) {
            $this->assertIsString($trigger);
            $this->assertNotEmpty($trigger);
        }

        // Check that the default trigger is in the list
        $this->assertContains("Let's think step by step.", $triggers);
    }

    public function testMathExamples(): void
    {
        $examples = CoTPrompts::mathExamples();

        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);

        foreach ($examples as $example) {
            $this->assertArrayHasKey('question', $example);
            $this->assertArrayHasKey('answer', $example);
            $this->assertIsString($example['question']);
            $this->assertIsString($example['answer']);
            $this->assertNotEmpty($example['question']);
            $this->assertNotEmpty($example['answer']);

            // Check that answer contains reasoning steps
            $this->assertStringContainsString('Step', $example['answer']);
            $this->assertStringContainsString('Answer', $example['answer']);
        }
    }

    public function testLogicExamples(): void
    {
        $examples = CoTPrompts::logicExamples();

        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);

        foreach ($examples as $example) {
            $this->assertArrayHasKey('question', $example);
            $this->assertArrayHasKey('answer', $example);
            $this->assertIsString($example['question']);
            $this->assertIsString($example['answer']);
            $this->assertNotEmpty($example['question']);
            $this->assertNotEmpty($example['answer']);

            // Logic examples should contain reasoning structure
            $this->assertMatchesRegularExpression('/Constraint|Answer|Possibilit/i', $example['answer']);
        }
    }

    public function testDecisionExamples(): void
    {
        $examples = CoTPrompts::decisionExamples();

        $this->assertIsArray($examples);
        $this->assertNotEmpty($examples);

        foreach ($examples as $example) {
            $this->assertArrayHasKey('question', $example);
            $this->assertArrayHasKey('answer', $example);
            $this->assertIsString($example['question']);
            $this->assertIsString($example['answer']);
            $this->assertNotEmpty($example['question']);
            $this->assertNotEmpty($example['answer']);

            // Decision examples should contain analysis
            $this->assertMatchesRegularExpression('/factor|Analysis|Recommendation/i', $example['answer']);
        }
    }

    public function testFewShotSystemWithEmptyExamples(): void
    {
        $system = CoTPrompts::fewShotSystem([]);

        $this->assertIsString($system);
        $this->assertStringContainsString('expert problem solver', $system);
        $this->assertStringContainsString('step by step', $system);
    }

    public function testFewShotSystemWithMathExamples(): void
    {
        $examples = CoTPrompts::mathExamples();
        $system = CoTPrompts::fewShotSystem($examples);

        $this->assertIsString($system);
        $this->assertStringContainsString('expert problem solver', $system);
        $this->assertStringContainsString('Example', $system);

        // Check that examples are included in the system prompt
        foreach ($examples as $example) {
            $this->assertStringContainsString($example['question'], $system);
            $this->assertStringContainsString($example['answer'], $system);
        }
    }

    public function testFewShotSystemWithCustomExamples(): void
    {
        $customExamples = [
            [
                'question' => 'What is 2 + 2?',
                'answer' => 'Step 1: Add 2 + 2 = 4',
            ],
            [
                'question' => 'What is 3 * 3?',
                'answer' => 'Step 1: Multiply 3 * 3 = 9',
            ],
        ];

        $system = CoTPrompts::fewShotSystem($customExamples);

        $this->assertIsString($system);
        $this->assertStringContainsString('Example 1', $system);
        $this->assertStringContainsString('Example 2', $system);
        $this->assertStringContainsString('What is 2 + 2?', $system);
        $this->assertStringContainsString('What is 3 * 3?', $system);
        $this->assertStringContainsString('Q:', $system);
        $this->assertStringContainsString('A:', $system);
    }

    public function testFewShotSystemPromptStructure(): void
    {
        $examples = [
            [
                'question' => 'Test question',
                'answer' => 'Test answer',
            ],
        ];

        $system = CoTPrompts::fewShotSystem($examples);

        // Check structure components
        $this->assertStringContainsString('You are an expert problem solver', $system);
        $this->assertStringContainsString('Example 1:', $system);
        $this->assertStringContainsString('Q: Test question', $system);
        $this->assertStringContainsString('A: Test answer', $system);
        $this->assertStringContainsString('solve new problems', $system);
        $this->assertStringContainsString('step-by-step format', $system);
        $this->assertStringContainsString('show your reasoning', $system);
    }

    public function testMathExamplesContainNumericCalculations(): void
    {
        $examples = CoTPrompts::mathExamples();

        foreach ($examples as $example) {
            // Math examples should contain numbers or calculations
            $this->assertMatchesRegularExpression('/\d+|\$/', $example['question']);
            $this->assertMatchesRegularExpression('/\d+|\$/', $example['answer']);
        }
    }

    public function testAllExampleMethodsReturnUniqueContent(): void
    {
        $mathExamples = CoTPrompts::mathExamples();
        $logicExamples = CoTPrompts::logicExamples();
        $decisionExamples = CoTPrompts::decisionExamples();

        // Each method should return different examples
        $this->assertNotEquals($mathExamples, $logicExamples);
        $this->assertNotEquals($mathExamples, $decisionExamples);
        $this->assertNotEquals($logicExamples, $decisionExamples);
    }

    public function testExampleQuestionsAreDistinctFromAnswers(): void
    {
        $allExamples = array_merge(
            CoTPrompts::mathExamples(),
            CoTPrompts::logicExamples(),
            CoTPrompts::decisionExamples()
        );

        foreach ($allExamples as $example) {
            // Questions should be shorter and not contain detailed reasoning
            $this->assertLessThan(strlen($example['answer']), strlen($example['question']));

            // Answers should contain more detail
            $answerWords = str_word_count($example['answer']);
            $questionWords = str_word_count($example['question']);
            $this->assertGreaterThan($questionWords, $answerWords);
        }
    }
}
