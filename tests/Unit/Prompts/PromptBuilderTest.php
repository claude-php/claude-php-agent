<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Prompts;

use ClaudeAgents\Prompts\PromptBuilder;
use PHPUnit\Framework\TestCase;

class PromptBuilderTest extends TestCase
{
    public function test_create(): void
    {
        $builder = PromptBuilder::create();

        $this->assertInstanceOf(PromptBuilder::class, $builder);
    }

    public function test_add_context(): void
    {
        $prompt = PromptBuilder::create()
            ->addContext('You are helpful')
            ->build();

        $this->assertStringContainsString('You are helpful', $prompt);
    }

    public function test_add_task(): void
    {
        $prompt = PromptBuilder::create()
            ->addTask('Solve this')
            ->build();

        $this->assertStringContainsString('Task: Solve this', $prompt);
    }

    public function test_add_example(): void
    {
        $prompt = PromptBuilder::create()
            ->addExample('1+1', '2')
            ->build();

        $this->assertStringContainsString('Input: 1+1', $prompt);
        $this->assertStringContainsString('Output: 2', $prompt);
    }

    public function test_add_examples(): void
    {
        $prompt = PromptBuilder::create()
            ->addExamples([
                ['input' => '1+1', 'output' => '2'],
                ['input' => '2+2', 'output' => '4'],
            ])
            ->build();

        $this->assertStringContainsString('1+1', $prompt);
        $this->assertStringContainsString('2+2', $prompt);
    }

    public function test_add_constraint(): void
    {
        $prompt = PromptBuilder::create()
            ->addConstraint('Be concise')
            ->build();

        $this->assertStringContainsString('Requirement: Be concise', $prompt);
    }

    public function test_add_instructions(): void
    {
        $prompt = PromptBuilder::create()
            ->addInstructions('Step 1\nStep 2')
            ->build();

        $this->assertStringContainsString('Instructions:', $prompt);
        $this->assertStringContainsString('Step 1', $prompt);
    }

    public function test_add_section(): void
    {
        $prompt = PromptBuilder::create()
            ->addSection('Custom', 'Content')
            ->build();

        $this->assertStringContainsString('Custom:', $prompt);
        $this->assertStringContainsString('Content', $prompt);
    }

    public function test_add_raw(): void
    {
        $prompt = PromptBuilder::create()
            ->addRaw('Raw text')
            ->build();

        $this->assertStringContainsString('Raw text', $prompt);
    }

    public function test_add_separator(): void
    {
        $prompt = PromptBuilder::create()
            ->addContext('Before')
            ->addSeparator()
            ->addContext('After')
            ->build();

        $this->assertStringContainsString('---', $prompt);
    }

    public function test_fluent_chaining(): void
    {
        $prompt = PromptBuilder::create()
            ->addContext('Context')
            ->addTask('Task')
            ->addExample('in', 'out')
            ->addConstraint('Constraint')
            ->build();

        $this->assertStringContainsString('Context', $prompt);
        $this->assertStringContainsString('Task', $prompt);
        $this->assertStringContainsString('in', $prompt);
        $this->assertStringContainsString('Constraint', $prompt);
    }

    public function test_clear(): void
    {
        $builder = PromptBuilder::create()
            ->addContext('Text')
            ->clear();

        $prompt = $builder->build();

        $this->assertEmpty($prompt);
    }
}
