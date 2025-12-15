# Prompts System

The Prompts system provides a comprehensive set of tools for building, managing, and composing prompts for LLM interactions. It offers templates, few-shot learning support, pre-built prompt libraries, and composition utilities.

## Table of Contents

- [Overview](#overview)
- [Core Concepts](#core-concepts)
- [Components](#components)
  - [PromptTemplate](#prompttemplate)
  - [ChatTemplate](#chattemplate)
  - [FewShotTemplate](#fewshottemplate)
  - [PromptLibrary](#promptlibrary)
  - [PromptComposer](#promptcomposer)
- [Usage Examples](#usage-examples)
- [Best Practices](#best-practices)
- [Advanced Patterns](#advanced-patterns)

## Overview

The Prompts system helps you:

- **Manage Variables**: Use `{variable}` placeholders for dynamic content
- **Build Conversations**: Create multi-turn chat templates
- **Few-Shot Learning**: Provide examples to guide LLM behavior
- **Compose Complex Prompts**: Combine multiple templates and sections
- **Reuse Common Patterns**: Access pre-built templates for common tasks
- **Validate Inputs**: Ensure all required variables are provided

## Core Concepts

### Variable Substitution

All templates support variable substitution using `{variable_name}` syntax:

```php
$template = PromptTemplate::create('Hello {name}, you are {age} years old');
$prompt = $template->format(['name' => 'Alice', 'age' => 30]);
// Result: "Hello Alice, you are 30 years old"
```

### Validation

Templates automatically extract and validate required variables:

```php
$template = PromptTemplate::create('Hello {name}');
$variables = $template->getVariables(); // ['name']
$template->validate(['name' => 'Bob']); // Passes
$template->validate([]); // Throws InvalidArgumentException with helpful message
```

### Fluent Interface

All classes use fluent interfaces for easy chaining:

```php
$template = ChatTemplate::create()
    ->system('You are helpful')
    ->user('Question: {question}')
    ->assistant('Let me help with that');
```

## Components

### PromptTemplate

Basic template with variable substitution. Perfect for single-turn prompts.

#### Creating Templates

```php
use ClaudeAgents\Prompts\PromptTemplate;

// Simple template
$template = PromptTemplate::create('Summarize: {text}');

// Or use constructor
$template = new PromptTemplate('Translate {text} to {language}');
```

#### Formatting

```php
$result = $template->format([
    'text' => 'Hello world',
    'language' => 'Spanish',
]);
```

#### Partial Templates

Create a new template with some variables pre-filled:

```php
$template = PromptTemplate::create('Hello {name} from {city}, {country}');
$partial = $template->partial(['country' => 'USA']);
// New template: "Hello {name} from {city}, USA"
```

#### API Reference

- `create(string $template): self` - Create new template
- `format(array $values): string` - Format with values
- `getVariables(): array` - Get required variables
- `validate(array $values): void` - Validate all variables present
- `getTemplate(): string` - Get raw template string
- `partial(array $values): self` - Create partial template

### ChatTemplate

Template for multi-turn conversations with role-based messages.

#### Creating Chat Templates

```php
use ClaudeAgents\Prompts\ChatTemplate;

$chat = ChatTemplate::create()
    ->system('You are a {role}')
    ->user('My name is {name}')
    ->assistant('Hello {name}!')
    ->user('Tell me about {topic}');
```

#### Formatting

```php
$messages = $chat->format([
    'role' => 'helpful assistant',
    'name' => 'Alice',
    'topic' => 'PHP',
]);

// Returns array of messages:
// [
//   ['role' => 'system', 'content' => 'You are a helpful assistant'],
//   ['role' => 'user', 'content' => 'My name is Alice'],
//   ['role' => 'assistant', 'content' => 'Hello Alice!'],
//   ['role' => 'user', 'content' => 'Tell me about PHP'],
// ]
```

#### API Reference

- `create(): self` - Create new chat template
- `system(string $content): self` - Add system message
- `user(string $content): self` - Add user message
- `assistant(string $content): self` - Add assistant message
- `message(string $role, string $content): self` - Add custom role message
- `format(array $values): array` - Format all messages
- `getMessages(): array` - Get raw unformatted messages
- `getVariables(): array` - Get all required variables
- `validate(array $values): void` - Validate variables

### FewShotTemplate

Template for few-shot learning with examples.

#### Creating Few-Shot Templates

```php
use ClaudeAgents\Prompts\FewShotTemplate;

$template = FewShotTemplate::create()
    ->withPrefix('Classify sentiment as positive, negative, or neutral.')
    ->addExample('I love this product!', 'positive')
    ->addExample('This is terrible', 'negative')
    ->addExample('It works fine', 'neutral')
    ->withSuffix('Now classify:')
    ->withInputTemplate('{text}');
```

#### Formatting

```php
$prompt = $template->format(['text' => 'Amazing service!']);
```

#### Built-in Factories

```php
// Classification
$template = FewShotTemplate::forClassification(
    $examples,
    ['positive', 'negative', 'neutral']
);

// Extraction
$template = FewShotTemplate::forExtraction(
    $examples,
    'named entities'
);

// Transformation
$template = FewShotTemplate::forTransformation(
    $examples,
    'converting to uppercase'
);
```

#### Custom Example Format

```php
$template = FewShotTemplate::create()
    ->withExampleFormat('Q: ', 'A: ')  // Instead of "Input: ", "Output: "
    ->withExampleSeparator("\n---\n"); // Custom separator
```

#### API Reference

- `create(): self` - Create new template
- `withPrefix(string $prefix): self` - Set prefix text
- `withSuffix(string $suffix): self` - Set suffix text
- `addExample(string $input, string $output): self` - Add single example
- `withExamples(array $examples): self` - Add multiple examples
- `withInputTemplate(string $template): self` - Set input template
- `withExampleFormat(string $inputPrefix, string $outputPrefix): self` - Customize format
- `withExampleSeparator(string $separator): self` - Set separator
- `format(array $values): string` - Format complete prompt
- `formatExamples(): string` - Format just examples
- `getExamples(): array` - Get all examples
- `getVariables(): array` - Get required variables
- `validate(array $values): void` - Validate variables

### PromptLibrary

Collection of pre-built templates for common tasks.

#### Available Templates

```php
use ClaudeAgents\Prompts\PromptLibrary;

// Summarization
$template = PromptLibrary::summarization();
$prompt = $template->format([
    'text' => 'Long text...',
    'length' => '3 sentences'
]);

// Classification
$template = PromptLibrary::classification(['spam', 'ham']);
$prompt = $template->format(['text' => 'Email content...']);

// Sentiment Analysis
$template = PromptLibrary::sentimentAnalysis();
$prompt = $template->format(['text' => 'Review text...']);

// Entity Extraction
$template = PromptLibrary::entityExtraction();
$prompt = $template->format(['text' => 'Article text...']);

// Question Answering
$template = PromptLibrary::questionAnswering();
$prompt = $template->format([
    'context' => 'Background information...',
    'question' => 'What is...?'
]);

// Translation
$template = PromptLibrary::translation();
$prompt = $template->format([
    'text' => 'Hello',
    'source_language' => 'English',
    'target_language' => 'Spanish'
]);

// Code Explanation
$template = PromptLibrary::codeExplanation();
$prompt = $template->format([
    'code' => 'function example() { ... }',
    'language' => 'PHP'
]);

// Code Review
$template = PromptLibrary::codeReview();
$prompt = $template->format([
    'code' => 'class Example { ... }',
    'language' => 'PHP'
]);
```

#### All Available Templates

**Text Processing:**
- `summarization()` - Summarize text
- `rewrite()` - Rewrite text in different style
- `translation()` - Translate between languages

**Classification & Analysis:**
- `classification(array $categories)` - Classify into categories
- `sentimentAnalysis()` - Analyze sentiment
- `entityExtraction()` - Extract named entities

**Question & Answers:**
- `questionAnswering()` - Answer based on context
- `factCheck()` - Fact-check statements

**Code Related:**
- `codeExplanation()` - Explain code
- `codeReview()` - Review code for issues
- `sqlGenerator()` - Generate SQL queries
- `apiDocumentation()` - Generate API docs

**Creative & Planning:**
- `brainstorm()` - Generate ideas
- `creativeWriting()` - Write stories
- `userStory()` - Generate user stories

**Structured Output:**
- `jsonOutput()` - Format as JSON
- `dataFormatting()` - Convert data formats

**Analysis & Decision:**
- `comparison()` - Compare two items
- `prosAndCons()` - List pros and cons
- `errorDiagnosis()` - Diagnose errors

**Communication:**
- `emailResponse()` - Generate email responses
- `meetingNotesSummary()` - Summarize meeting notes

**Chat Templates:**
- `conversational()` - Basic conversation
- `expertAdvisor()` - Expert advisor role
- `socraticTeaching()` - Socratic teaching method
- `debateOpponent()` - Debate opponent role

### PromptComposer

Compose complex prompts from multiple sections with conditional logic.

#### Basic Composition

```php
use ClaudeAgents\Prompts\PromptComposer;

$composer = PromptComposer::create()
    ->addText('System: You are a helpful assistant')
    ->addText('User: {question}')
    ->addText('Assistant: Let me help with that');

$prompt = $composer->compose(['question' => 'What is PHP?']);
```

#### Adding Templates

```php
$template1 = PromptTemplate::create('Analyze: {text}');
$template2 = PromptTemplate::create('Focus on: {aspect}');

$composer = PromptComposer::create()
    ->addTemplate($template1)
    ->addTemplate($template2);
```

#### Conditional Sections

```php
$composer = PromptComposer::create()
    ->addText('Process the following:')
    ->addConditional(
        'Additional context: {context}',
        fn($values) => isset($values['context']) && !empty($values['context'])
    )
    ->addText('Input: {input}');

// Context only appears if provided
$prompt = $composer->compose(['input' => 'data', 'context' => 'extra info']);
```

#### Variable-Based Conditions

```php
$composer = PromptComposer::create()
    ->addText('Main task: {task}')
    ->addIfVariable('examples', 'Examples: {examples}')
    ->addIfVariable('constraints', 'Constraints: {constraints}');
```

#### Lists

```php
$composer = PromptComposer::create()
    ->addText('Requirements:')
    ->addList('requirements', '- {item}', 'The system must:');

$prompt = $composer->compose([
    'requirements' => ['be fast', 'be secure', 'be scalable']
]);
```

#### Examples Section

```php
$composer = PromptComposer::create()
    ->addText('Task: Classify sentiment')
    ->addExamples('examples', 'Text: ', 'Sentiment: ')
    ->addText('Now classify: {input}');

$prompt = $composer->compose([
    'examples' => [
        ['input' => 'I love it', 'output' => 'positive'],
        ['input' => 'I hate it', 'output' => 'negative'],
    ],
    'input' => 'It was okay'
]);
```

#### Quick Composition

```php
$template1 = PromptTemplate::create('Part 1: {a}');
$template2 = PromptTemplate::create('Part 2: {b}');

$composer = PromptComposer::fromTemplates(
    [$template1, $template2],
    "\n---\n"  // custom separator
);
```

#### Built-in Patterns

```php
// Chain of Thought
$composer = PromptComposer::chainOfThought();
$prompt = $composer->compose(['problem' => '2+2=?']);

// RAG (Retrieval Augmented Generation)
$composer = PromptComposer::rag();
$prompt = $composer->compose([
    'context' => 'Background info...',
    'question' => 'What is...?'
]);
```

#### API Reference

- `create(): self` - Create new composer
- `addText(string $text, ?callable $condition): self` - Add text section
- `addTemplate(PromptTemplate $template, ?callable $condition): self` - Add template
- `addConditional(string $text, callable $condition): self` - Add conditional section
- `addIfVariable(string $variableName, string $text): self` - Add if variable exists
- `addList(string $itemsVariable, string $itemTemplate, string $listPrefix): self` - Add list
- `addExamples(string $examplesVariable, string $inputPrefix, string $outputPrefix): self` - Add examples
- `withSeparator(string $separator): self` - Set section separator
- `compose(array $values): string` - Compose final prompt
- `getVariables(): array` - Get all required variables
- `validate(array $values): void` - Validate variables
- `fromTemplates(array $items, string $separator): self` - Quick composition
- `chainOfThought(): self` - Chain of thought pattern
- `rag(): self` - RAG pattern

## Usage Examples

### Example 1: Simple Prompt

```php
use ClaudeAgents\Prompts\PromptTemplate;

$template = PromptTemplate::create(
    'Summarize the following text in {length}:\n\n{text}'
);

$prompt = $template->format([
    'text' => 'Long article content...',
    'length' => '3 sentences'
]);
```

### Example 2: Multi-Turn Conversation

```php
use ClaudeAgents\Prompts\ChatTemplate;

$chat = ChatTemplate::create()
    ->system('You are a {expertise} expert')
    ->user('I need help with {problem}')
    ->assistant('I\'d be happy to help. Let me {action}.')
    ->user('Also, can you {additional_request}?');

$messages = $chat->format([
    'expertise' => 'PHP',
    'problem' => 'optimization',
    'action' => 'analyze your code',
    'additional_request' => 'suggest improvements'
]);
```

### Example 3: Few-Shot Classification

```php
use ClaudeAgents\Prompts\FewShotTemplate;

$examples = [
    ['input' => 'Great product!', 'output' => 'positive'],
    ['input' => 'Terrible service', 'output' => 'negative'],
    ['input' => 'It works', 'output' => 'neutral'],
];

$template = FewShotTemplate::forClassification(
    $examples,
    ['positive', 'negative', 'neutral']
);

$prompt = $template->format(['input' => 'Amazing experience!']);
```

### Example 4: Using PromptLibrary

```php
use ClaudeAgents\Prompts\PromptLibrary;

// Sentiment analysis
$template = PromptLibrary::sentimentAnalysis();
$prompt = $template->format(['text' => 'Customer review...']);

// Code review
$template = PromptLibrary::codeReview();
$prompt = $template->format([
    'code' => 'function example() { ... }',
    'language' => 'PHP'
]);

// Question answering with context
$template = PromptLibrary::questionAnswering();
$prompt = $template->format([
    'context' => 'PHP is a scripting language...',
    'question' => 'What is PHP used for?'
]);
```

### Example 5: Complex Composition

```php
use ClaudeAgents\Prompts\PromptComposer;
use ClaudeAgents\Prompts\PromptLibrary;

$composer = PromptComposer::create()
    ->addText('Task: Analyze code quality')
    ->addIfVariable('context', 'Context: {context}')
    ->addText('Code:')
    ->addText('{code}')
    ->addConditional(
        'Previous issues:\n{previous_issues}',
        fn($v) => !empty($v['previous_issues'])
    )
    ->addList('focus_areas', '- {item}', 'Focus on:')
    ->addText('Provide detailed analysis:');

$prompt = $composer->compose([
    'code' => 'class Example { ... }',
    'context' => 'Legacy codebase',
    'focus_areas' => ['security', 'performance', 'maintainability']
]);
```

### Example 6: RAG Pattern

```php
use ClaudeAgents\Prompts\PromptComposer;

$composer = PromptComposer::rag();

$prompt = $composer->compose([
    'context' => $retrievedDocuments,
    'question' => 'What are the key features?'
]);
```

## Best Practices

### 1. Always Validate

```php
$template = PromptTemplate::create('Hello {name}');

// Validate before using
try {
    $template->validate($userInput);
    $prompt = $template->format($userInput);
} catch (\InvalidArgumentException $e) {
    // Handle missing variables
    echo $e->getMessage(); // Helpful error message
}
```

### 2. Use Type-Appropriate Templates

- **PromptTemplate**: Single-turn prompts
- **ChatTemplate**: Multi-turn conversations
- **FewShotTemplate**: When providing examples
- **PromptComposer**: Complex, conditional prompts

### 3. Leverage PromptLibrary

Don't recreate common patterns:

```php
// Instead of:
$template = PromptTemplate::create('Summarize: {text}');

// Use:
$template = PromptLibrary::summarization();
```

### 4. Use Descriptive Variable Names

```php
// Good
$template = PromptTemplate::create('Translate {source_text} from {source_lang} to {target_lang}');

// Less clear
$template = PromptTemplate::create('Translate {text} from {from} to {to}');
```

### 5. Compose for Flexibility

```php
$composer = PromptComposer::create()
    ->addText('Main task: {task}')
    ->addIfVariable('examples', 'Examples:\n{examples}')
    ->addIfVariable('constraints', 'Constraints:\n{constraints}');

// Works with or without optional fields
$prompt1 = $composer->compose(['task' => 'analyze']);
$prompt2 = $composer->compose(['task' => 'analyze', 'examples' => '...']);
```

## Advanced Patterns

### Pattern 1: Template Inheritance with Partials

```php
$baseTemplate = PromptTemplate::create(
    'You are a {role}. Task: {task}. Context: {context}'
);

// Create specialized versions
$developerTemplate = $baseTemplate->partial(['role' => 'senior developer']);
$analystTemplate = $baseTemplate->partial(['role' => 'data analyst']);

// Use with remaining variables
$prompt1 = $developerTemplate->format(['task' => 'code review', 'context' => '...']);
$prompt2 = $analystTemplate->format(['task' => 'data analysis', 'context' => '...']);
```

### Pattern 2: Dynamic Few-Shot Examples

```php
function createDynamicFewShot(array $userExamples): FewShotTemplate {
    $template = FewShotTemplate::create()
        ->withPrefix('Based on these examples:')
        ->withExamples($userExamples)
        ->withSuffix('Now process:')
        ->withInputTemplate('{input}');
    
    return $template;
}

// Use with different example sets
$examples1 = [/* sentiment examples */];
$examples2 = [/* classification examples */];

$template1 = createDynamicFewShot($examples1);
$template2 = createDynamicFewShot($examples2);
```

### Pattern 3: Conditional Chain of Thought

```php
$composer = PromptComposer::create()
    ->addText('Problem: {problem}')
    ->addConditional(
        "Let's think step by step:\n1. Analyze\n2. Break down\n3. Solve",
        fn($v) => $v['use_cot'] ?? false
    )
    ->addText('Solution:');

// Enable CoT when needed
$prompt = $composer->compose([
    'problem' => 'complex math',
    'use_cot' => true
]);
```

### Pattern 4: Multi-Language Support

```php
$templates = [
    'en' => PromptTemplate::create('Summarize: {text}'),
    'es' => PromptTemplate::create('Resumir: {text}'),
    'fr' => PromptTemplate::create('Résumer: {text}'),
];

function getPrompt(string $lang, array $values): string {
    global $templates;
    return $templates[$lang]->format($values);
}
```

### Pattern 5: Template Registry

```php
class PromptRegistry {
    private static array $templates = [];
    
    public static function register(string $name, PromptTemplate $template): void {
        self::$templates[$name] = $template;
    }
    
    public static function get(string $name): PromptTemplate {
        return self::$templates[$name] ?? throw new \RuntimeException("Template not found: $name");
    }
}

// Register
PromptRegistry::register('summarize', PromptLibrary::summarization());
PromptRegistry::register('sentiment', PromptLibrary::sentimentAnalysis());

// Use
$template = PromptRegistry::get('summarize');
$prompt = $template->format(['text' => '...', 'length' => '2 sentences']);
```

---

## Integration with Agents

The Prompts system integrates seamlessly with the agent system:

```php
use ClaudeAgents\Agent;
use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Prompts\PromptTemplate;

$template = PromptTemplate::create('Analyze: {text}');

$chain = LLMChain::create($client)
    ->withPromptTemplate($template)
    ->withModel('claude-sonnet-4-5');

$result = $chain->invoke(['text' => 'Content to analyze...']);
```

## See Also

- [Chains Documentation](Chains.md) - For using prompts in chains
- [LLMChain](Chains.md#llmchain) - Direct integration with prompts
- [Agent System](README.md) - Using prompts with agents

