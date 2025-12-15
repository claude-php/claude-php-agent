# Prompts System Tutorial

A step-by-step guide to mastering the Prompts system in Claude PHP Agent framework.

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started with PromptTemplate](#getting-started-with-prompttemplate)
3. [Building Conversations with ChatTemplate](#building-conversations-with-chattemplate)
4. [Few-Shot Learning](#few-shot-learning)
5. [Using the PromptLibrary](#using-the-promptlibrary)
6. [Advanced Composition](#advanced-composition)
7. [Best Practices](#best-practices)
8. [Real-World Examples](#real-world-examples)

## Introduction

The Prompts system helps you create reusable, validated, and composable prompts for your LLM applications. Instead of building prompts with string concatenation, you use structured templates that:

- **Validate inputs** - Ensure all required variables are provided
- **Enable reuse** - Create once, use many times
- **Compose easily** - Combine simple templates into complex prompts
- **Maintain clarity** - Separate prompt structure from data

## Getting Started with PromptTemplate

### Step 1: Your First Template

Let's create a simple template for summarization:

```php
use ClaudeAgents\Prompts\PromptTemplate;

$template = PromptTemplate::create(
    'Summarize the following text in {length}:\n\n{text}'
);
```

The `{length}` and `{text}` are variables that will be replaced when you format the template.

### Step 2: Format the Template

Now provide the values and get your prompt:

```php
$prompt = $template->format([
    'text' => 'Claude is a large language model created by Anthropic...',
    'length' => '2 sentences',
]);

echo $prompt;
// Output:
// Summarize the following text in 2 sentences:
//
// Claude is a large language model created by Anthropic...
```

### Step 3: Inspect Variables

See what variables your template needs:

```php
$variables = $template->getVariables();
// Returns: ['length', 'text']
```

### Step 4: Validate Inputs

Ensure all required variables are provided:

```php
try {
    $template->validate(['text' => 'Some text']);
    // Missing 'length' - will throw ValidationException
} catch (\ClaudeAgents\Exceptions\ValidationException $e) {
    echo $e->getMessage();
    // Helpful error message with what's missing and what's available
}
```

### Exercise: Create a Translation Template

Try creating your own template:

```php
$translator = PromptTemplate::create(
    'Translate "{text}" from {source_language} to {target_language}'
);

$prompt = $translator->format([
    'text' => 'Hello world',
    'source_language' => 'English',
    'target_language' => 'Spanish',
]);
```

## Building Conversations with ChatTemplate

### Step 1: Create a Chat Flow

ChatTemplate is perfect for multi-turn conversations:

```php
use ClaudeAgents\Prompts\ChatTemplate;

$chat = ChatTemplate::create()
    ->system('You are a {expertise} expert')
    ->user('I need help with {problem}')
    ->assistant('I understand. Let me help you with {problem}.')
    ->user('Specifically, I want to {specific_request}');
```

### Step 2: Format as Messages

Get an array of messages ready for the API:

```php
$messages = $chat->format([
    'expertise' => 'PHP',
    'problem' => 'performance optimization',
    'specific_request' => 'reduce database queries',
]);

// Returns array of message objects:
// [
//   ['role' => 'system', 'content' => 'You are a PHP expert'],
//   ['role' => 'user', 'content' => 'I need help with performance optimization'],
//   ...
// ]
```

### Step 3: Use with LLM

Send to Claude:

```php
$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-5',
    'messages' => $messages,
    'max_tokens' => 1024,
]);
```

### Exercise: Build a Support Bot

Create a support conversation template:

```php
$support = ChatTemplate::create()
    ->system('You are a {company} support agent. Be {tone} and helpful.')
    ->user('I have an issue: {issue}')
    ->assistant('I apologize for the inconvenience. Let me help you with {issue}.')
    ->user('My account is: {account_id}');

$messages = $support->format([
    'company' => 'Acme Inc',
    'tone' => 'friendly',
    'issue' => 'login problems',
    'account_id' => '12345',
]);
```

## Few-Shot Learning

Few-shot learning teaches the LLM by example. The FewShotTemplate makes this easy.

### Step 1: Prepare Examples

Collect input-output pairs:

```php
$examples = [
    ['input' => 'I love this product!', 'output' => 'positive'],
    ['input' => 'Terrible experience', 'output' => 'negative'],
    ['input' => 'It works fine', 'output' => 'neutral'],
];
```

### Step 2: Create the Template

Use the classification factory:

```php
use ClaudeAgents\Prompts\FewShotTemplate;

$template = FewShotTemplate::forClassification(
    $examples,
    ['positive', 'negative', 'neutral']
);
```

### Step 3: Format with New Input

```php
$prompt = $template->format([
    'input' => 'Best purchase ever!'
]);

// The prompt includes all examples followed by the new input
```

### Step 4: Custom Few-Shot Templates

Build your own from scratch:

```php
$custom = FewShotTemplate::create()
    ->withPrefix('Extract named entities from text.')
    ->addExample('John works at Google', 'Person: John, Company: Google')
    ->addExample('Paris is in France', 'City: Paris, Country: France')
    ->withSuffix('Now extract from:')
    ->withInputTemplate('{text}')
    ->withExampleFormat('Text: ', 'Entities: ');

$prompt = $custom->format(['text' => 'Alice lives in Tokyo']);
```

### Exercise: Build a Code Style Converter

Create a few-shot template that converts code style:

```php
$styleConverter = FewShotTemplate::create()
    ->withPrefix('Convert code to follow PSR-12 style guide')
    ->addExample(
        'function test(){return true;}',
        'function test()\n{\n    return true;\n}'
    )
    ->addExample(
        'class foo{private $bar;}',
        'class Foo\n{\n    private $bar;\n}'
    )
    ->withInputTemplate('{code}');
```

## Using the PromptLibrary

Don't reinvent the wheel! Use pre-built templates.

### Common Templates

```php
use ClaudeAgents\Prompts\PromptLibrary;

// Summarization
$summary = PromptLibrary::summarization();
$prompt = $summary->format([
    'text' => 'Long article...',
    'length' => '3 sentences'
]);

// Sentiment analysis
$sentiment = PromptLibrary::sentimentAnalysis();
$prompt = $sentiment->format(['text' => 'Customer review...']);

// Question answering
$qa = PromptLibrary::questionAnswering();
$prompt = $qa->format([
    'context' => 'Background information...',
    'question' => 'What is the main point?'
]);

// Code review
$review = PromptLibrary::codeReview();
$prompt = $review->format([
    'code' => 'class Example { ... }',
    'language' => 'PHP'
]);
```

### Available Categories

- **Text Processing**: summarization, rewrite, translation
- **Classification**: classification, sentiment analysis, entity extraction
- **Q&A**: question answering, fact checking
- **Code**: code explanation, code review, SQL generation, API docs
- **Creative**: brainstorming, creative writing
- **Analysis**: comparison, pros/cons, error diagnosis
- **Communication**: email responses, meeting notes

### Exercise: Combine Library Templates

Create a content analysis pipeline:

```php
// Step 1: Extract entities
$extraction = PromptLibrary::entityExtraction();
$entities = $extraction->format(['text' => $article]);

// Step 2: Analyze sentiment
$sentiment = PromptLibrary::sentimentAnalysis();
$feeling = $sentiment->format(['text' => $article]);

// Step 3: Summarize
$summary = PromptLibrary::summarization();
$brief = $summary->format(['text' => $article, 'length' => '2 sentences']);
```

## Advanced Composition

For complex prompts, use PromptComposer.

### Step 1: Basic Composition

Combine multiple sections:

```php
use ClaudeAgents\Prompts\PromptComposer;

$composer = PromptComposer::create()
    ->addText('Task: {task}')
    ->addText('Context: {context}')
    ->addText('Requirements:')
    ->addList('requirements', '- {item}')
    ->addText('Please provide detailed output.')
    ->withSeparator("\n\n");

$prompt = $composer->compose([
    'task' => 'Build a login system',
    'context' => 'PHP 8.1, MySQL database',
    'requirements' => ['Secure password hashing', 'Email verification', 'Rate limiting'],
]);
```

### Step 2: Conditional Sections

Add sections only when needed:

```php
$composer = PromptComposer::create()
    ->addText('Analyze: {code}')
    ->addIfVariable('context', 'Context: {context}')
    ->addConditional(
        'This is a HIGH PRIORITY review',
        fn($values) => ($values['priority'] ?? '') === 'high'
    );

// Context appears only if provided
// Priority notice appears only if priority is 'high'
$prompt = $composer->compose([
    'code' => 'function example() { }',
    'context' => 'Legacy code',
    'priority' => 'high',
]);
```

### Step 3: Dynamic Lists

Add formatted lists:

```php
$composer = PromptComposer::create()
    ->addText('Project Plan')
    ->addList('tasks', '✓ {item}', 'Tasks:')
    ->addList('blockers', '⚠ {item}', 'Blockers:');

$prompt = $composer->compose([
    'tasks' => ['Design database', 'Implement API', 'Write tests'],
    'blockers' => ['Waiting for API keys', 'Server not provisioned'],
]);
```

### Step 4: Examples in Composition

Combine composition with few-shot learning:

```php
$composer = PromptComposer::create()
    ->addText('Task: Classify sentiment')
    ->addExamples('training_examples', 'Review: ', 'Sentiment: ')
    ->addText('Now classify: {input}');

$prompt = $composer->compose([
    'training_examples' => [
        ['input' => 'Love it!', 'output' => 'positive'],
        ['input' => 'Hate it', 'output' => 'negative'],
    ],
    'input' => 'Pretty good',
]);
```

### Exercise: Build a Code Review System

Create a comprehensive code review prompt:

```php
$codeReview = PromptComposer::create()
    ->addText('=== CODE REVIEW ===')
    ->addText('Language: {language}')
    ->addIfVariable('project_context', 'Project: {project_context}')
    ->addText('Code:\n```{language}\n{code}\n```')
    ->addList('focus_areas', '• {item}', 'Review Focus:')
    ->addIfVariable('previous_issues', 'Previous Issues:\n{previous_issues}')
    ->addText('Provide detailed analysis with:')
    ->addList('output_sections', '{item}', '')
    ->withSeparator("\n\n");

$prompt = $codeReview->compose([
    'language' => 'PHP',
    'project_context' => 'Payment processing',
    'code' => 'function processPayment($amount) { ... }',
    'focus_areas' => ['Security', 'Error handling', 'Input validation'],
    'output_sections' => ['1. Security issues', '2. Code quality', '3. Recommendations'],
]);
```

## Best Practices

### 1. Validate Early

Always validate before using templates in production:

```php
$template = PromptTemplate::create('Hello {name}');

if (isset($_POST['name'])) {
    try {
        $template->validate($_POST);
        $prompt = $template->format($_POST);
        // Safe to use
    } catch (\ClaudeAgents\Exceptions\ValidationException $e) {
        // Handle missing variables
        logError($e->getMessage());
    }
}
```

### 2. Use Descriptive Variable Names

```php
// Good
$template = PromptTemplate::create(
    'Translate {source_text} from {source_language} to {target_language}'
);

// Less clear
$template = PromptTemplate::create(
    'Translate {text} from {lang1} to {lang2}'
);
```

### 3. Leverage the Library

```php
// Instead of recreating:
$template = PromptTemplate::create('Analyze sentiment of: {text}');

// Use the library:
$template = PromptLibrary::sentimentAnalysis();
```

### 4. Compose for Flexibility

```php
// Rigid
$prompt = "Task: $task\nContext: $context\nRequirements:\n- $req1\n- $req2";

// Flexible
$composer = PromptComposer::create()
    ->addText('Task: {task}')
    ->addIfVariable('context', 'Context: {context}')
    ->addList('requirements', '- {item}', 'Requirements:');

// Works with or without optional fields
```

### 5. Create Reusable Templates

```php
// Define once
class MyTemplates {
    public static function productReview(): FewShotTemplate {
        return FewShotTemplate::forClassification(
            self::getReviewExamples(),
            ['positive', 'negative', 'neutral']
        );
    }
    
    private static function getReviewExamples(): array {
        return [
            ['input' => 'Great product!', 'output' => 'positive'],
            // ... more examples
        ];
    }
}

// Use everywhere
$template = MyTemplates::productReview();
$prompt = $template->format(['input' => $userReview]);
```

## Real-World Examples

### Example 1: Customer Support Bot

```php
use ClaudeAgents\Prompts\ChatTemplate;
use ClaudeAgents\Prompts\PromptComposer;

// Conversation template
$support = ChatTemplate::create()
    ->system('You are a {company} support agent. Tone: {tone}. Priority: {priority}')
    ->user('Issue: {issue}\nAccount: {account_id}')
    ->assistant('Let me help you with this {priority} priority issue.');

// Dynamic knowledge base injection
$composer = PromptComposer::create()
    ->addText('Relevant documentation:')
    ->addList('docs', '- {item}')
    ->addIfVariable('similar_cases', 'Similar resolved cases:\n{similar_cases}');

// Combine them
$messages = $support->format([
    'company' => 'TechCorp',
    'tone' => 'empathetic',
    'priority' => 'high',
    'issue' => 'Cannot login',
    'account_id' => 'USR-12345',
]);

$context = $composer->compose([
    'docs' => ['Password reset guide', 'Account recovery process'],
    'similar_cases' => 'Case #5423: Reset password via email',
]);
```

### Example 2: Code Generator with Examples

```php
use ClaudeAgents\Prompts\FewShotTemplate;

$codeGen = FewShotTemplate::create()
    ->withPrefix('Generate {language} code based on description')
    ->addExample(
        'Create a function that adds two numbers',
        'function add($a, $b) {\n    return $a + $b;\n}'
    )
    ->addExample(
        'Create a class with a private property and getter',
        'class User {\n    private $name;\n    \n    public function getName() {\n        return $this->name;\n    }\n}'
    )
    ->withSuffix('Now generate code for:')
    ->withInputTemplate('{description}')
    ->withExampleFormat('Description: ', 'Code:\n```{language}\n');

$prompt = $codeGen->format([
    'language' => 'PHP',
    'description' => 'Create a function that validates an email address',
]);
```

### Example 3: Multi-Step Analysis Pipeline

```php
use ClaudeAgents\Prompts\PromptLibrary;
use ClaudeAgents\Chains\SequentialChain;
use ClaudeAgents\Chains\LLMChain;

// Step 1: Extract entities
$extractionChain = LLMChain::create($client)
    ->withPromptTemplate(PromptLibrary::entityExtraction());

// Step 2: Sentiment analysis
$sentimentChain = LLMChain::create($client)
    ->withPromptTemplate(PromptLibrary::sentimentAnalysis());

// Step 3: Summarize
$summaryChain = LLMChain::create($client)
    ->withPromptTemplate(PromptLibrary::summarization());

// Compose into pipeline
$pipeline = SequentialChain::create()
    ->addChain('extract', $extractionChain)
    ->addChain('sentiment', $sentimentChain)
    ->addChain('summary', $summaryChain);

$result = $pipeline->invoke([
    'text' => $articleContent,
    'length' => '3 sentences',
]);
```

---

## Next Steps

- Explore the [full Prompts documentation](../Prompts.md)
- Check out the [complete demo](../../examples/prompts_demo.php)
- Learn about [Chains](Chains_Tutorial.md) for prompt composition
- Study the [source code](../../src/Prompts/) for implementation details

## Quick Reference

```php
// PromptTemplate
$t = PromptTemplate::create('Hello {name}');
$prompt = $t->format(['name' => 'Alice']);

// ChatTemplate
$c = ChatTemplate::create()
    ->system('You are {role}')
    ->user('{message}');
$messages = $c->format(['role' => 'helper', 'message' => 'Hi']);

// FewShotTemplate
$f = FewShotTemplate::forClassification($examples, $categories);
$prompt = $f->format(['input' => 'test']);

// PromptLibrary
$template = PromptLibrary::summarization();
$prompt = $template->format(['text' => '...', 'length' => '2 sentences']);

// PromptComposer
$composer = PromptComposer::create()
    ->addText('Task: {task}')
    ->addList('items', '- {item}')
    ->addIfVariable('context', 'Context: {context}');
$prompt = $composer->compose($values);
```

