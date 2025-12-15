<?php

declare(strict_types=1);

namespace ClaudeAgents\Prompts;

/**
 * Fluent builder for constructing prompts.
 *
 * Provides a clean API for building complex prompts with context, examples, and formatting.
 *
 * @example
 * ```php
 * $prompt = PromptBuilder::create()
 *     ->addContext('You are a helpful assistant')
 *     ->addTask('Solve this problem')
 *     ->addExample('Input: 1+1', 'Output: 2')
 *     ->addConstraint('Use step-by-step reasoning')
 *     ->build();
 * ```
 */
class PromptBuilder
{
    /**
     * @var array<string>
     */
    private array $sections = [];

    /**
     * Create a new prompt builder.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Add context/background information.
     */
    public function addContext(string $context): self
    {
        $this->sections[] = $context;

        return $this;
    }

    /**
     * Add the main task description.
     */
    public function addTask(string $task): self
    {
        $this->sections[] = "Task: {$task}";

        return $this;
    }

    /**
     * Add an example.
     */
    public function addExample(string $input, string $output): self
    {
        $this->sections[] = "Example:\nInput: {$input}\nOutput: {$output}";

        return $this;
    }

    /**
     * Add multiple examples.
     *
     * @param array<array{input: string, output: string}> $examples
     */
    public function addExamples(array $examples): self
    {
        foreach ($examples as $example) {
            $this->addExample($example['input'], $example['output']);
        }

        return $this;
    }

    /**
     * Add a constraint or requirement.
     */
    public function addConstraint(string $constraint): self
    {
        $this->sections[] = "Requirement: {$constraint}";

        return $this;
    }

    /**
     * Add instructions.
     */
    public function addInstructions(string $instructions): self
    {
        $this->sections[] = "Instructions:\n{$instructions}";

        return $this;
    }

    /**
     * Add a custom section.
     */
    public function addSection(string $title, string $content): self
    {
        $this->sections[] = "{$title}:\n{$content}";

        return $this;
    }

    /**
     * Add raw text.
     */
    public function addRaw(string $text): self
    {
        $this->sections[] = $text;

        return $this;
    }

    /**
     * Add a separator line.
     */
    public function addSeparator(): self
    {
        $this->sections[] = '---';

        return $this;
    }

    /**
     * Build the final prompt.
     */
    public function build(): string
    {
        return implode("\n\n", $this->sections);
    }

    /**
     * Clear all sections.
     */
    public function clear(): self
    {
        $this->sections = [];

        return $this;
    }
}
