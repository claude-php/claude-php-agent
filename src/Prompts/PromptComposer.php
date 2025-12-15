<?php

declare(strict_types=1);

namespace ClaudeAgents\Prompts;

use ClaudeAgents\Exceptions\ValidationException;

/**
 * Composer for combining and chaining multiple prompts.
 *
 * Allows building complex prompts by composing simpler templates together,
 * with support for conditional sections and dynamic composition.
 */
class PromptComposer
{
    /**
     * @var array<array{type: string, content: mixed, condition: callable|null}> Prompt sections
     */
    private array $sections = [];

    /**
     * @var string Separator between sections
     */
    private string $separator = "\n\n";

    /**
     * @var array<string> Collected variables from all sections
     */
    private array $variables = [];

    private function __construct()
    {
    }

    /**
     * Create a new prompt composer.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Add a text section.
     *
     * @param string $text Static or template text
     * @param callable|null $condition Optional condition function (receives $values, returns bool)
     */
    public function addText(string $text, ?callable $condition = null): self
    {
        $this->sections[] = [
            'type' => 'text',
            'content' => $text,
            'condition' => $condition,
        ];
        $this->extractVariables($text);

        return $this;
    }

    /**
     * Add a PromptTemplate section.
     *
     * @param PromptTemplate $template Template to include
     * @param callable|null $condition Optional condition function
     */
    public function addTemplate(PromptTemplate $template, ?callable $condition = null): self
    {
        $this->sections[] = [
            'type' => 'template',
            'content' => $template,
            'condition' => $condition,
        ];
        $this->variables = array_merge($this->variables, $template->getVariables());

        return $this;
    }

    /**
     * Add a conditional section that only appears if condition is met.
     *
     * @param string $text Text to include
     * @param callable $condition Function that returns bool
     */
    public function addConditional(string $text, callable $condition): self
    {
        return $this->addText($text, $condition);
    }

    /**
     * Add a section that only appears if a variable is set.
     *
     * @param string $variableName Variable to check
     * @param string $text Text to include (can use the variable)
     */
    public function addIfVariable(string $variableName, string $text): self
    {
        return $this->addText($text, function ($values) use ($variableName) {
            return isset($values[$variableName]) && ! empty($values[$variableName]);
        });
    }

    /**
     * Add a list of items with a template for each.
     *
     * @param string $itemsVariable Variable name containing array of items
     * @param string $itemTemplate Template for each item (use {item} for value)
     * @param string $listPrefix Optional prefix before the list
     */
    public function addList(
        string $itemsVariable,
        string $itemTemplate = '- {item}',
        string $listPrefix = ''
    ): self {
        $this->sections[] = [
            'type' => 'list',
            'content' => [
                'variable' => $itemsVariable,
                'template' => $itemTemplate,
                'prefix' => $listPrefix,
            ],
            'condition' => null,
        ];
        $this->variables[] = $itemsVariable;

        return $this;
    }

    /**
     * Add examples section with formatting.
     *
     * @param string $examplesVariable Variable containing examples array
     * @param string $inputPrefix Prefix for inputs (e.g., "Input: ")
     * @param string $outputPrefix Prefix for outputs (e.g., "Output: ")
     */
    public function addExamples(
        string $examplesVariable,
        string $inputPrefix = 'Input: ',
        string $outputPrefix = 'Output: '
    ): self {
        $this->sections[] = [
            'type' => 'examples',
            'content' => [
                'variable' => $examplesVariable,
                'inputPrefix' => $inputPrefix,
                'outputPrefix' => $outputPrefix,
            ],
            'condition' => null,
        ];
        $this->variables[] = $examplesVariable;

        return $this;
    }

    /**
     * Set the separator between sections.
     */
    public function withSeparator(string $separator): self
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * Compose all sections into a single prompt.
     *
     * @param array<string, mixed> $values Variable values
     * @return string Complete composed prompt
     */
    public function compose(array $values): string
    {
        $parts = [];

        foreach ($this->sections as $section) {
            // Check condition if present
            if ($section['condition'] !== null) {
                if (! ($section['condition'])($values)) {
                    continue;
                }
            }

            // Process section based on type
            $part = match ($section['type']) {
                'text' => $this->processText($section['content'], $values),
                'template' => $this->processTemplate($section['content'], $values),
                'list' => $this->processList($section['content'], $values),
                'examples' => $this->processExamples($section['content'], $values),
                default => '',
            };

            if (! empty($part)) {
                $parts[] = $part;
            }
        }

        return implode($this->separator, $parts);
    }

    /**
     * Get all required variables.
     *
     * @return array<string>
     */
    public function getVariables(): array
    {
        return array_unique($this->variables);
    }

    /**
     * Validate that all required variables are provided.
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
                'Missing required variable(s) for composition: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Create a quick composition from multiple templates.
     *
     * @param array<PromptTemplate|string> $items Templates or text strings
     * @param string $separator Separator between items
     */
    public static function fromTemplates(array $items, string $separator = "\n\n"): self
    {
        $composer = self::create()->withSeparator($separator);

        foreach ($items as $item) {
            if ($item instanceof PromptTemplate) {
                $composer->addTemplate($item);
            } elseif (is_string($item)) {
                $composer->addText($item);
            }
        }

        return $composer;
    }

    /**
     * Process a text section.
     */
    private function processText(string $text, array $values): string
    {
        foreach ($values as $key => $value) {
            if (is_scalar($value)) {
                $placeholder = '{' . $key . '}';
                $text = str_replace($placeholder, (string) $value, $text);
            }
        }

        return $text;
    }

    /**
     * Process a template section.
     */
    private function processTemplate(PromptTemplate $template, array $values): string
    {
        return $template->format($values);
    }

    /**
     * Process a list section.
     *
     * @param array{variable: string, template: string, prefix: string} $config
     */
    private function processList(array $config, array $values): string
    {
        if (! isset($values[$config['variable']])) {
            return '';
        }

        $items = $values[$config['variable']];
        if (! is_array($items)) {
            return '';
        }

        $lines = [];
        if (! empty($config['prefix'])) {
            $lines[] = $config['prefix'];
        }

        foreach ($items as $item) {
            $line = str_replace('{item}', (string) $item, $config['template']);
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Process an examples section.
     *
     * @param array{variable: string, inputPrefix: string, outputPrefix: string} $config
     */
    private function processExamples(array $config, array $values): string
    {
        if (! isset($values[$config['variable']])) {
            return '';
        }

        $examples = $values[$config['variable']];
        if (! is_array($examples)) {
            return '';
        }

        $formatted = [];
        foreach ($examples as $example) {
            if (! isset($example['input']) || ! isset($example['output'])) {
                continue;
            }

            $formatted[] = $config['inputPrefix'] . $example['input'] . "\n" .
                          $config['outputPrefix'] . $example['output'];
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Extract variables from text.
     */
    private function extractVariables(string $text): void
    {
        if (preg_match_all('/\{(\w+)\}/', $text, $matches)) {
            $this->variables = array_merge($this->variables, $matches[1]);
        }
    }

    /**
     * Create a chain-of-thought composition.
     */
    public static function chainOfThought(): self
    {
        return self::create()
            ->addText('Problem: {problem}')
            ->addText("Let's solve this step by step:")
            ->addText('Step 1: Understand the problem')
            ->addText('Step 2: Break it down')
            ->addText('Step 3: Solve systematically')
            ->withSeparator("\n\n");
    }

    /**
     * Create a RAG (Retrieval Augmented Generation) composition.
     */
    public static function rag(): self
    {
        return self::create()
            ->addText('Context:')
            ->addText('{context}')
            ->addText('Question: {question}')
            ->addText('Based on the context above, provide a detailed answer:')
            ->withSeparator("\n\n");
    }
}
