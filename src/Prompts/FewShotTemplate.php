<?php

declare(strict_types=1);

namespace ClaudeAgents\Prompts;

use ClaudeAgents\Exceptions\ValidationException;

/**
 * Template for few-shot learning with examples.
 *
 * Builds prompts with examples to guide the LLM's behavior through demonstration
 * rather than explicit instructions.
 */
class FewShotTemplate
{
    /**
     * @var array<array{input: string, output: string}> Examples
     */
    private array $examples = [];

    /**
     * @var string System instruction prefix
     */
    private string $prefix = '';

    /**
     * @var string Instruction suffix (after examples)
     */
    private string $suffix = '';

    /**
     * @var string Format for example input
     */
    private string $exampleInputPrefix = 'Input: ';

    /**
     * @var string Format for example output
     */
    private string $exampleOutputPrefix = 'Output: ';

    /**
     * @var string Separator between examples
     */
    private string $exampleSeparator = "\n\n";

    /**
     * @var array<string> Variables from prefix, suffix, and input template
     */
    private array $variables = [];

    /**
     * @var string|null Template for the input query
     */
    private ?string $inputTemplate = null;

    private function __construct()
    {
    }

    /**
     * Create a new few-shot template.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the prefix (instruction before examples).
     */
    public function withPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        $this->extractVariablesFromText($prefix);

        return $this;
    }

    /**
     * Set the suffix (instruction after examples).
     */
    public function withSuffix(string $suffix): self
    {
        $this->suffix = $suffix;
        $this->extractVariablesFromText($suffix);

        return $this;
    }

    /**
     * Add an example.
     *
     * @param string $input Example input
     * @param string $output Example output
     */
    public function addExample(string $input, string $output): self
    {
        $this->examples[] = ['input' => $input, 'output' => $output];

        return $this;
    }

    /**
     * Add multiple examples at once.
     *
     * @param array<array{input: string, output: string}> $examples
     */
    public function withExamples(array $examples): self
    {
        foreach ($examples as $example) {
            $this->addExample($example['input'], $example['output']);
        }

        return $this;
    }

    /**
     * Set the input template for formatting queries.
     */
    public function withInputTemplate(string $template): self
    {
        $this->inputTemplate = $template;
        $this->extractVariablesFromText($template);

        return $this;
    }

    /**
     * Set custom example formatting.
     *
     * @param string $inputPrefix Prefix for input (e.g., "Q: ", "Input: ")
     * @param string $outputPrefix Prefix for output (e.g., "A: ", "Output: ")
     */
    public function withExampleFormat(string $inputPrefix, string $outputPrefix): self
    {
        $this->exampleInputPrefix = $inputPrefix;
        $this->exampleOutputPrefix = $outputPrefix;

        return $this;
    }

    /**
     * Set the separator between examples.
     */
    public function withExampleSeparator(string $separator): self
    {
        $this->exampleSeparator = $separator;

        return $this;
    }

    /**
     * Format the complete prompt with examples and query.
     *
     * @param array<string, mixed> $values Variable values for templates
     * @return string Complete formatted prompt
     */
    public function format(array $values): string
    {
        $parts = [];

        // Add prefix if set
        if (! empty($this->prefix)) {
            $parts[] = $this->formatText($this->prefix, $values);
        }

        // Add examples
        if (! empty($this->examples)) {
            $exampleTexts = [];
            foreach ($this->examples as $i => $example) {
                $exampleText = $this->exampleInputPrefix . $example['input'] . "\n";
                $exampleText .= $this->exampleOutputPrefix . $example['output'];
                $exampleTexts[] = $exampleText;
            }
            $parts[] = implode($this->exampleSeparator, $exampleTexts);
        }

        // Add suffix if set
        if (! empty($this->suffix)) {
            $parts[] = $this->formatText($this->suffix, $values);
        }

        // Add input query if template is set
        if ($this->inputTemplate !== null) {
            $parts[] = $this->exampleInputPrefix . $this->formatText($this->inputTemplate, $values);
        }

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Format just the examples section.
     *
     * @return string Formatted examples
     */
    public function formatExamples(): string
    {
        if (empty($this->examples)) {
            return '';
        }

        $exampleTexts = [];
        foreach ($this->examples as $example) {
            $exampleText = $this->exampleInputPrefix . $example['input'] . "\n";
            $exampleText .= $this->exampleOutputPrefix . $example['output'];
            $exampleTexts[] = $exampleText;
        }

        return implode($this->exampleSeparator, $exampleTexts);
    }

    /**
     * Get all examples.
     *
     * @return array<array{input: string, output: string}>
     */
    public function getExamples(): array
    {
        return $this->examples;
    }

    /**
     * Get required variables.
     *
     * @return array<string>
     */
    public function getVariables(): array
    {
        return array_unique($this->variables);
    }

    /**
     * Validate that all variables are provided.
     *
     * @param array<string, mixed> $values
     * @throws ValidationException If required variables are missing
     */
    public function validate(array $values): void
    {
        $missing = [];
        foreach ($this->getVariables() as $var) {
            if (! isset($values[$var])) {
                $missing[] = $var;
            }
        }

        if (! empty($missing)) {
            throw new ValidationException(
                'Missing required variable(s): ' . implode(', ', $missing) . '. ' .
                'Available variables: ' . implode(', ', array_keys($values))
            );
        }
    }

    /**
     * Format text with variable substitution.
     *
     * @param string $text Text with {variable} placeholders
     * @param array<string, mixed> $values Variable values
     */
    private function formatText(string $text, array $values): string
    {
        foreach ($values as $key => $value) {
            $placeholder = '{' . $key . '}';
            $text = str_replace($placeholder, (string) $value, $text);
        }

        return $text;
    }

    /**
     * Extract variables from text.
     */
    private function extractVariablesFromText(string $text): void
    {
        if (preg_match_all('/\{(\w+)\}/', $text, $matches)) {
            $this->variables = array_merge($this->variables, $matches[1]);
        }
    }

    /**
     * Create a classification template.
     *
     * @param array<array{input: string, output: string}> $examples
     * @param array<string> $categories Available categories
     */
    public static function forClassification(array $examples, array $categories): self
    {
        return self::create()
            ->withPrefix(
                "Classify the input into one of these categories:\n" .
                implode(', ', $categories) . "\n\n" .
                'Examples:'
            )
            ->withExamples($examples)
            ->withSuffix('Now classify the following:')
            ->withInputTemplate('{input}')
            ->withExampleFormat('Input: ', 'Category: ');
    }

    /**
     * Create an extraction template.
     *
     * @param array<array{input: string, output: string}> $examples
     * @param string $extractionTarget What to extract (e.g., "entities", "keywords")
     */
    public static function forExtraction(array $examples, string $extractionTarget): self
    {
        return self::create()
            ->withPrefix("Extract {$extractionTarget} from the input.\n\nExamples:")
            ->withExamples($examples)
            ->withSuffix("Now extract {$extractionTarget} from:")
            ->withInputTemplate('{input}')
            ->withExampleFormat('Text: ', 'Extracted: ');
    }

    /**
     * Create a transformation template.
     *
     * @param array<array{input: string, output: string}> $examples
     * @param string $transformation Description of transformation
     */
    public static function forTransformation(array $examples, string $transformation): self
    {
        return self::create()
            ->withPrefix("Transform the input by {$transformation}.\n\nExamples:")
            ->withExamples($examples)
            ->withSuffix('Now transform:')
            ->withInputTemplate('{input}')
            ->withExampleFormat('Original: ', 'Transformed: ');
    }
}
