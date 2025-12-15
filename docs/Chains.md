# Chain Composition System

The Chain Composition System provides a powerful framework for building complex LLM workflows by composing simple, reusable chain components. Chains can be connected sequentially, executed in parallel, or routed conditionally to create sophisticated processing pipelines.

## Table of Contents

- [Overview](#overview)
- [Core Concepts](#core-concepts)
- [Chain Types](#chain-types)
- [Usage Examples](#usage-examples)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)

## Overview

Chains are composable units that transform inputs to outputs. Each chain:
- Accepts a `ChainInput` object
- Returns a `ChainOutput` object with data and metadata
- Can be composed with other chains
- Supports callbacks for monitoring and error handling

### Why Use Chains?

- **Modularity**: Break complex workflows into simple, testable components
- **Reusability**: Create chains once, use them in multiple workflows
- **Composability**: Combine chains in different ways for different use cases
- **Observability**: Built-in support for logging, callbacks, and metadata tracking
- **Type Safety**: Strong typing with validation support

## Core Concepts

### ChainInput

Represents input data passed to a chain:

```php
$input = ChainInput::create([
    'text' => 'Hello World',
    'language' => 'en',
]);

$value = $input->get('text'); // 'Hello World'
$nested = $input->getDot('user.name'); // Dot notation for nested values
```

### ChainOutput

Represents output data from a chain, including metadata:

```php
$output = ChainOutput::create(
    ['result' => 'Processed text'],
    ['tokens' => 100, 'model' => 'claude-sonnet-4-5']
);

$result = $output->get('result');
$metadata = $output->getMetadata();
```

### Chain Interface

All chains implement the `ChainInterface`:

```php
interface ChainInterface
{
    public function run(ChainInput $input): ChainOutput;
    public function invoke(array $input): array;
    public function getInputSchema(): array;
    public function getOutputSchema(): array;
    public function validateInput(ChainInput $input): bool;
}
```

## Chain Types

### LLMChain

Wraps a single LLM call with prompt templating and output parsing.

```php
use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Prompts\PromptTemplate;

$chain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Summarize: {text}'))
    ->withModel('claude-sonnet-4-5')
    ->withMaxTokens(256)
    ->withTemperature(0.7);

$result = $chain->invoke(['text' => 'Long document...']);
```

**Features:**
- Prompt templating with variable substitution
- JSON output parsing
- Custom output parsers
- Configurable model parameters
- Token usage tracking

### TransformChain

Transforms input data without calling an LLM.

```php
use ClaudeAgents\Chains\TransformChain;

$chain = TransformChain::create(function (array $input): array {
    return [
        'uppercase' => strtoupper($input['text'] ?? ''),
        'length' => strlen($input['text'] ?? ''),
    ];
});

$result = $chain->invoke(['text' => 'hello']);
// ['uppercase' => 'HELLO', 'length' => 5]
```

**Use Cases:**
- Data normalization
- Format conversion
- Validation
- Preprocessing/postprocessing

### SequentialChain

Executes chains in sequence, passing outputs to subsequent chains.

```php
use ClaudeAgents\Chains\SequentialChain;

$pipeline = SequentialChain::create()
    ->addChain('step1', $extractChain)
    ->addChain('step2', $analyzeChain)
    ->addChain('step3', $formatChain)
    ->mapOutput('step1', 'entities', 'step2', 'input')
    ->mapOutput('step2', 'analysis', 'step3', 'data');

$result = $pipeline->invoke(['text' => 'Input...']);
```

**Features:**
- Output mapping between chains
- Conditional execution
- Accumulated context
- Step-by-step metadata

**Output Structure:**
```php
[
    'step1' => ['entities' => [...]],
    'step2' => ['analysis' => '...'],
    'step3' => ['formatted' => '...'],
]
```

### ParallelChain

Executes multiple chains concurrently (or simulated parallel).

```php
use ClaudeAgents\Chains\ParallelChain;

$parallel = ParallelChain::create()
    ->addChain('sentiment', $sentimentChain)
    ->addChain('topics', $topicsChain)
    ->addChain('summary', $summaryChain)
    ->withAggregation('merge') // 'merge', 'first', or 'all'
    ->withTimeout(30000);

$result = $parallel->invoke(['text' => 'Review...']);
```

**Aggregation Strategies:**

- **merge**: Merge all results into a single flat array
  ```php
  ['sentiment_result' => '...', 'topics_result' => '...']
  ```

- **first**: Return only the first successful result
  ```php
  ['result' => 'First chain output']
  ```

- **all**: Return structured results with error tracking
  ```php
  [
      'results' => [
          'sentiment' => ['result' => '...'],
          'topics' => ['result' => '...'],
      ],
      'errors' => ['failed_chain' => 'Error message'],
  ]
  ```

**Error Handling:**
- Individual chain failures don't stop other chains
- Errors are tracked in metadata
- Throws exception only if all chains fail

### RouterChain

Routes inputs to different chains based on conditions.

```php
use ClaudeAgents\Chains\RouterChain;

$router = RouterChain::create()
    ->addRoute(
        fn($input) => str_contains($input['content'], '<?php'),
        $codeReviewChain
    )
    ->addRoute(
        fn($input) => str_ends_with($input['content'], '?'),
        $questionChain
    )
    ->setDefault($generalChain);

$result = $router->invoke(['content' => 'Some text']);
```

**Features:**
- Multiple conditional routes
- First-match-wins evaluation
- Fallback to default chain
- Route metadata in output

## Usage Examples

### Example 1: Text Processing Pipeline

```php
// Step 1: Clean and normalize text
$normalizeChain = TransformChain::create(fn($i) => [
    'text' => trim(strtolower($i['text'] ?? '')),
]);

// Step 2: Extract entities with LLM
$extractChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Extract named entities from: {text}'
    ));

// Step 3: Format as JSON
$formatChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Format as JSON: {entities}'
    ))
    ->withJsonParser();

// Compose pipeline
$pipeline = SequentialChain::create()
    ->addChain('normalize', $normalizeChain)
    ->addChain('extract', $extractChain)
    ->addChain('format', $formatChain)
    ->mapOutput('normalize', 'text', 'extract', 'text')
    ->mapOutput('extract', 'result', 'format', 'entities');

$result = $pipeline->invoke(['text' => '  JOHN lives in NYC  ']);
```

### Example 2: Multi-Perspective Analysis

```php
// Create analysis chains for different perspectives
$technicalChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Technical analysis: {product}'
    ));

$businessChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Business analysis: {product}'
    ));

$userChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'User experience analysis: {product}'
    ));

// Execute all in parallel
$analysis = ParallelChain::create()
    ->addChain('technical', $technicalChain)
    ->addChain('business', $businessChain)
    ->addChain('ux', $userChain)
    ->withAggregation('all');

$result = $analysis->invoke(['product' => 'New mobile app']);
```

### Example 3: Smart Content Router

```php
// Create specialized chains
$codeChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Review code: {content}'));

$docChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Explain docs: {content}'));

$qaChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Answer: {content}'));

// Route based on content type
$router = RouterChain::create()
    ->addRoute(
        fn($i) => preg_match('/^```|function |class /', $i['content']),
        $codeChain
    )
    ->addRoute(
        fn($i) => str_contains($i['content'], 'documentation'),
        $docChain
    )
    ->addRoute(
        fn($i) => str_ends_with($i['content'], '?'),
        $qaChain
    )
    ->setDefault($codeChain);

$result = $router->invoke(['content' => $userInput]);
```

### Example 4: Conditional Processing

```php
$validateChain = TransformChain::create(function ($input) {
    return [
        'email' => $input['email'],
        'valid' => filter_var($input['email'], FILTER_VALIDATE_EMAIL) !== false,
    ];
});

$processChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Extract domain: {email}'));

$pipeline = SequentialChain::create()
    ->addChain('validate', $validateChain)
    ->addChain('process', $processChain)
    ->setCondition('process', fn($results) => $results['validate']['valid'])
    ->mapOutput('validate', 'email', 'process', 'email');

$result = $pipeline->invoke(['email' => 'user@example.com']);
```

## Callbacks and Monitoring

All chains support lifecycle callbacks:

```php
$chain = LLMChain::create($client)
    ->onBefore(function (ChainInput $input) {
        echo "Starting with: " . json_encode($input->all());
    })
    ->onAfter(function (ChainInput $input, ChainOutput $output) {
        echo "Completed with {$output->getMetadataValue('tokens')} tokens";
    })
    ->onError(function (ChainInput $input, \Throwable $error) {
        echo "Failed: " . $error->getMessage();
    });
```

## Error Handling

Chains throw specific exceptions for different error types:

```php
use ClaudeAgents\Chains\Exceptions\ChainExecutionException;
use ClaudeAgents\Chains\Exceptions\ChainValidationException;

try {
    $result = $chain->invoke(['input' => 'data']);
} catch (ChainValidationException $e) {
    // Input validation failed
    echo "Invalid input: " . $e->getMessage();
} catch (ChainExecutionException $e) {
    // Execution failed
    echo "Execution error: " . $e->getMessage();
}
```

## Best Practices

### 1. Design for Reusability

Create small, focused chains that do one thing well:

```php
// Good: Focused, reusable
$extractDatesChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Extract dates: {text}'));

// Avoid: Too specific, not reusable
$extractDatesFromEmailsChain = ...
```

### 2. Use Transform Chains for Non-LLM Operations

Don't waste LLM calls on simple transformations:

```php
// Good: Use TransformChain for simple operations
$normalizeChain = TransformChain::create(fn($i) => [
    'text' => trim(strtolower($i['text'])),
]);

// Avoid: Wasting LLM call
$normalizeChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Lowercase this: {text}'));
```

### 3. Map Outputs Explicitly

Use `mapOutput()` to make data flow clear:

```php
$pipeline = SequentialChain::create()
    ->addChain('step1', $chain1)
    ->addChain('step2', $chain2)
    ->mapOutput('step1', 'result', 'step2', 'input'); // Clear mapping
```

### 4. Handle Errors Gracefully

Use ParallelChain for resilience:

```php
$parallel = ParallelChain::create()
    ->addChain('primary', $primaryChain)
    ->addChain('fallback', $fallbackChain)
    ->withAggregation('first'); // Use first successful result
```

### 5. Monitor with Callbacks

Add monitoring for production use:

```php
$chain->onAfter(function ($input, $output) {
    $this->metrics->recordTokens($output->getMetadataValue('tokens'));
    $this->logger->info('Chain completed', [
        'duration' => $output->getMetadataValue('duration'),
    ]);
});
```

## API Reference

### Chain Base Class

```php
abstract class Chain implements ChainInterface
{
    public function __construct(?LoggerInterface $logger = null);
    
    public function run(ChainInput $input): ChainOutput;
    public function invoke(array $input): array;
    
    public function onBefore(callable $callback): self;
    public function onAfter(callable $callback): self;
    public function onError(callable $callback): self;
    public function withLogger(LoggerInterface $logger): self;
    
    public function getInputSchema(): array;
    public function getOutputSchema(): array;
    public function validateInput(ChainInput $input): bool;
    
    abstract protected function execute(ChainInput $input): ChainOutput;
}
```

### LLMChain

```php
class LLMChain extends Chain
{
    public static function create(ClaudePhp $client, ?LoggerInterface $logger = null): self;
    
    public function withPromptTemplate(PromptTemplate $template): self;
    public function withOutputParser(callable $parser): self;
    public function withJsonParser(): self;
    public function withModel(string $model): self;
    public function withMaxTokens(int $maxTokens): self;
    public function withTemperature(float $temperature): self;
}
```

### TransformChain

```php
class TransformChain extends Chain
{
    public function __construct(callable $transformer, ?LoggerInterface $logger = null);
    public static function create(callable $transformer, ?LoggerInterface $logger = null): self;
}
```

### SequentialChain

```php
class SequentialChain extends Chain
{
    public static function create(?LoggerInterface $logger = null): self;
    
    public function addChain(string $name, ChainInterface $chain): self;
    public function mapOutput(string $fromChain, string $fromKey, string $toChain, string $toKey): self;
    public function setCondition(string $chainName, callable $condition): self;
}
```

### ParallelChain

```php
class ParallelChain extends Chain
{
    public static function create(?LoggerInterface $logger = null): self;
    
    public function addChain(string $name, ChainInterface $chain): self;
    public function withAggregation(string $strategy): self; // 'merge', 'first', 'all'
    public function withTimeout(int $milliseconds): self;
}
```

### RouterChain

```php
class RouterChain extends Chain
{
    public static function create(?LoggerInterface $logger = null): self;
    
    public function addRoute(callable $condition, ChainInterface $chain): self;
    public function setDefault(ChainInterface $chain): self;
}
```

## See Also

- [Chain Tutorial](tutorials/Chains_Tutorial.md) - Step-by-step guide
- [Examples](../examples/) - Working code examples
- [Tests](../tests/Integration/Chains/) - Test examples and usage patterns

