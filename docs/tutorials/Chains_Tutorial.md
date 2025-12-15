# Chain Composition Tutorial

Learn how to build powerful LLM workflows using the Chain Composition System. This tutorial takes you from basic concepts to advanced patterns.

## Prerequisites

- PHP 8.1 or higher
- Composer installed
- Anthropic API key
- Basic understanding of PHP and LLMs

## Setup

First, ensure you have the library installed:

```bash
composer require claude-agents/claude-php-agent
```

Set your API key in `.env`:

```bash
ANTHROPIC_API_KEY=your_key_here
```

## Part 1: Understanding Chains

### What is a Chain?

A chain is a composable unit that:
1. Takes input data
2. Processes it (calls LLM, transforms data, etc.)
3. Returns output data with metadata

Think of chains as building blocks you can connect together.

### The Chain Interface

Every chain implements this interface:

```php
interface ChainInterface
{
    // Execute with ChainInput/Output objects
    public function run(ChainInput $input): ChainOutput;
    
    // Convenience method with arrays
    public function invoke(array $input): array;
    
    // Schema and validation
    public function getInputSchema(): array;
    public function getOutputSchema(): array;
    public function validateInput(ChainInput $input): bool;
}
```

You'll typically use `invoke()` for simple cases and `run()` when you need access to metadata.

## Part 2: Your First Chain

### Example: Simple LLM Chain

```php
<?php

require_once 'vendor/autoload.php';

use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Prompts\PromptTemplate;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$client = new ClaudePhp(apiKey: $_ENV['ANTHROPIC_API_KEY']);

// Create a simple LLM chain
$chain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('What is {number} squared?'))
    ->withModel('claude-sonnet-4-5')
    ->withMaxTokens(100);

// Use it!
$result = $chain->invoke(['number' => '7']);
echo $result['result']; // "49" or "7 squared is 49"
```

**What's happening:**
1. Create an `LLMChain` with the Claude client
2. Set a prompt template with variables (`{number}`)
3. Configure model and parameters
4. Invoke with input data
5. Get results

### Example: Transform Chain

Not everything needs an LLM! Use `TransformChain` for data processing:

```php
use ClaudeAgents\Chains\TransformChain;

$transformer = TransformChain::create(function (array $input): array {
    $text = $input['text'] ?? '';
    
    return [
        'uppercase' => strtoupper($text),
        'word_count' => str_word_count($text),
        'reversed' => strrev($text),
    ];
});

$result = $transformer->invoke(['text' => 'Hello World']);
print_r($result);
// [
//     'uppercase' => 'HELLO WORLD',
//     'word_count' => 2,
//     'reversed' => 'dlroW olleH',
// ]
```

**Key Takeaway:** Use `TransformChain` for operations that don't need LLM intelligence.

## Part 3: Sequential Chains

Sequential chains execute multiple chains in order, passing data between them.

### Basic Sequential Chain

```php
use ClaudeAgents\Chains\SequentialChain;

// Step 1: Clean the input
$cleanChain = TransformChain::create(fn($i) => [
    'clean_text' => trim(strtolower($i['text'] ?? '')),
]);

// Step 2: Analyze with LLM
$analyzeChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Count the words in this text and respond with just the number: {clean_text}'
    ))
    ->withMaxTokens(50);

// Compose them
$pipeline = SequentialChain::create()
    ->addChain('clean', $cleanChain)
    ->addChain('analyze', $analyzeChain)
    ->mapOutput('clean', 'clean_text', 'analyze', 'clean_text');

// Execute
$result = $pipeline->invoke(['text' => '  HELLO WORLD  ']);
print_r($result);
// [
//     'clean' => ['clean_text' => 'hello world'],
//     'analyze' => ['result' => '2'],
// ]
```

**Understanding mapOutput:**

```php
->mapOutput('clean', 'clean_text', 'analyze', 'clean_text')
//          ^^^^^^   ^^^^^^^^^^^   ^^^^^^^^  ^^^^^^^^^^^
//          from     from          to        to
//          chain    key           chain     key
```

This tells the pipeline: "Take `clean_text` from the `clean` chain's output and make it available as `clean_text` to the `analyze` chain."

### Real-World Example: Text Analysis Pipeline

Let's build a complete text analysis system:

```php
// Step 1: Normalize input
$normalizeChain = TransformChain::create(function ($input) {
    return [
        'normalized' => trim($input['text'] ?? ''),
        'length' => strlen($input['text'] ?? ''),
    ];
});

// Step 2: Extract entities
$extractChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Extract all person names and locations from this text: {normalized}'
    ))
    ->withMaxTokens(200);

// Step 3: Analyze sentiment
$sentimentChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Rate sentiment as positive, negative, or neutral: {normalized}'
    ))
    ->withMaxTokens(50);

// Step 4: Generate summary
$summaryChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Summarize in one sentence: {normalized}'
    ))
    ->withMaxTokens(100);

// Build the pipeline
$analysisPipeline = SequentialChain::create()
    ->addChain('normalize', $normalizeChain)
    ->addChain('extract', $extractChain)
    ->addChain('sentiment', $sentimentChain)
    ->addChain('summary', $summaryChain)
    ->mapOutput('normalize', 'normalized', 'extract', 'normalized')
    ->mapOutput('normalize', 'normalized', 'sentiment', 'normalized')
    ->mapOutput('normalize', 'normalized', 'summary', 'normalized');

// Use it
$result = $analysisPipeline->invoke([
    'text' => 'John visited Paris last week. He had a wonderful time!',
]);
```

### Conditional Execution

Sometimes you want to skip steps based on conditions:

```php
$validateChain = TransformChain::create(function ($input) {
    $email = $input['email'] ?? '';
    return [
        'email' => $email,
        'is_valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
    ];
});

$processChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Extract the domain from: {email}'
    ));

$pipeline = SequentialChain::create()
    ->addChain('validate', $validateChain)
    ->addChain('process', $processChain)
    ->setCondition('process', function ($results) {
        // Only process if validation passed
        return $results['validate']['is_valid'] === true;
    })
    ->mapOutput('validate', 'email', 'process', 'email');

// Valid email - both chains execute
$result1 = $pipeline->invoke(['email' => 'user@example.com']);

// Invalid email - process chain is skipped
$result2 = $pipeline->invoke(['email' => 'not-an-email']);
```

## Part 4: Parallel Chains

Execute multiple chains simultaneously for better performance.

### Basic Parallel Execution

```php
use ClaudeAgents\Chains\ParallelChain;

// Create different analysis chains
$sentimentChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Sentiment: {text}'));

$topicsChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Main topics: {text}'));

$keywordsChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Keywords: {text}'));

// Run them all in parallel
$parallel = ParallelChain::create()
    ->addChain('sentiment', $sentimentChain)
    ->addChain('topics', $topicsChain)
    ->addChain('keywords', $keywordsChain)
    ->withAggregation('merge');

$result = $parallel->invoke(['text' => 'Sample review text...']);
```

### Aggregation Strategies

**1. Merge (Default)**: Combine all results into one array

```php
->withAggregation('merge')

// Output:
// [
//     'sentiment_result' => '...',
//     'topics_result' => '...',
//     'keywords_result' => '...',
// ]
```

**2. First**: Return first successful result

```php
->withAggregation('first')

// Output: (from whichever chain completes first)
// ['result' => '...']
```

**3. All**: Keep everything structured

```php
->withAggregation('all')

// Output:
// [
//     'results' => [
//         'sentiment' => ['result' => '...'],
//         'topics' => ['result' => '...'],
//     ],
//     'errors' => [],
// ]
```

### Real-World Example: Multi-Perspective Analysis

```php
// Analyze a product from different angles
$technicalChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Technical assessment (1 sentence): {product}'
    ));

$businessChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Business viability (1 sentence): {product}'
    ));

$uxChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'User experience assessment (1 sentence): {product}'
    ));

$analysis = ParallelChain::create()
    ->addChain('technical', $technicalChain)
    ->addChain('business', $businessChain)
    ->addChain('ux', $uxChain)
    ->withAggregation('all')
    ->withTimeout(60000);

$result = $analysis->invoke([
    'product' => 'A mobile app for tracking daily water intake',
]);

// Access results
foreach ($result['results'] as $perspective => $data) {
    echo "$perspective: " . $data['result'] . "\n";
}
```

## Part 5: Router Chains

Route inputs to different chains based on conditions.

### Basic Router

```php
use ClaudeAgents\Chains\RouterChain;

// Create specialized chains
$codeChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Review code: {content}'));

$questionChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Answer question: {content}'));

$textChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Summarize: {content}'));

// Create router
$router = RouterChain::create()
    ->addRoute(
        // Condition: Check if input contains code
        fn($input) => str_contains($input['content'], '<?php'),
        $codeChain
    )
    ->addRoute(
        // Condition: Check if input is a question
        fn($input) => str_ends_with($input['content'], '?'),
        $questionChain
    )
    ->setDefault($textChain); // Fallback for everything else

// Use it
$result1 = $router->invoke(['content' => '<?php echo "test";']);
// Routes to codeChain

$result2 = $router->invoke(['content' => 'What is PHP?']);
// Routes to questionChain

$result3 = $router->invoke(['content' => 'PHP is a language.']);
// Routes to textChain (default)
```

### Real-World Example: Support Ticket Router

```php
// Create priority-based routing
$urgentChain = TransformChain::create(fn($i) => [
    'priority' => 'URGENT',
    'sla' => '1 hour',
    'message' => 'Escalated to senior support',
]);

$highChain = TransformChain::create(fn($i) => [
    'priority' => 'HIGH',
    'sla' => '4 hours',
    'message' => 'Assigned to experienced agent',
]);

$normalChain = TransformChain::create(fn($i) => [
    'priority' => 'NORMAL',
    'sla' => '24 hours',
    'message' => 'Added to standard queue',
]);

$ticketRouter = RouterChain::create()
    ->addRoute(
        // Urgent: High severity AND premium customer
        fn($i) => ($i['severity'] ?? 0) >= 9 && ($i['tier'] ?? '') === 'premium',
        $urgentChain
    )
    ->addRoute(
        // High: Medium severity OR premium customer
        fn($i) => ($i['severity'] ?? 0) >= 5 || ($i['tier'] ?? '') === 'premium',
        $highChain
    )
    ->setDefault($normalChain);

// Test it
$ticket1 = [
    'severity' => 10,
    'tier' => 'premium',
    'issue' => 'Server down',
];
$result1 = $ticketRouter->invoke($ticket1);
// Priority: URGENT, SLA: 1 hour

$ticket2 = [
    'severity' => 3,
    'tier' => 'free',
    'issue' => 'Minor UI bug',
];
$result2 = $ticketRouter->invoke($ticket2);
// Priority: NORMAL, SLA: 24 hours
```

## Part 6: Advanced Patterns

### Pattern 1: Nested Chains

Chains can contain other chains:

```php
// Build a sub-pipeline for preprocessing
$preprocessPipeline = SequentialChain::create()
    ->addChain('clean', $cleanChain)
    ->addChain('validate', $validateChain);

// Use it in a router
$mainRouter = RouterChain::create()
    ->addRoute(
        fn($i) => $i['needs_preprocessing'] ?? false,
        $preprocessPipeline
    )
    ->setDefault($directProcessingChain);
```

### Pattern 2: Dynamic Routing with LLM

Use an LLM to make routing decisions:

```php
// Create a classifier chain
$classifierChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Classify this as "code", "question", or "text": {content}. Respond with just one word.'
    ))
    ->withMaxTokens(10);

// Build router that uses LLM classification
$intelligentRouter = RouterChain::create()
    ->addRoute(
        function ($input) use ($classifierChain) {
            $classification = $classifierChain->invoke(['content' => $input['content']]);
            return str_contains(strtolower($classification['result']), 'code');
        },
        $codeChain
    )
    ->addRoute(
        function ($input) use ($classifierChain) {
            $classification = $classifierChain->invoke(['content' => $input['content']]);
            return str_contains(strtolower($classification['result']), 'question');
        },
        $questionChain
    )
    ->setDefault($textChain);
```

### Pattern 3: Error Recovery with Parallel Chains

Use parallel chains with "first" strategy for resilience:

```php
// Create multiple chains that solve the same problem differently
$primaryChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Complex analysis: {text}'))
    ->withMaxTokens(500);

$fallbackChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Simple analysis: {text}'))
    ->withMaxTokens(200);

$fastChain = TransformChain::create(fn($i) => [
    'result' => 'Quick heuristic result',
]);

// Use first successful result
$resilientChain = ParallelChain::create()
    ->addChain('primary', $primaryChain)
    ->addChain('fallback', $fallbackChain)
    ->addChain('fast', $fastChain)
    ->withAggregation('first');
```

### Pattern 4: Monitoring and Callbacks

Add observability to your chains:

```php
$chain = LLMChain::create($client)
    ->withPromptTemplate($template)
    ->onBefore(function ($input) {
        // Log start
        error_log("Starting chain with input: " . json_encode($input->all()));
        // Start timer
        $GLOBALS['chain_start'] = microtime(true);
    })
    ->onAfter(function ($input, $output) {
        // Log completion
        $duration = microtime(true) - $GLOBALS['chain_start'];
        error_log(sprintf(
            "Chain completed in %.2fs, used %d tokens",
            $duration,
            $output->getMetadataValue('input_tokens', 0) + 
            $output->getMetadataValue('output_tokens', 0)
        ));
    })
    ->onError(function ($input, $error) {
        // Log error
        error_log("Chain failed: " . $error->getMessage());
        // Send alert
        mail('admin@example.com', 'Chain Failed', $error->getMessage());
    });
```

## Part 7: Best Practices

### 1. Keep Chains Small and Focused

```php
// Good: Small, reusable chains
$extractDatesChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Extract dates: {text}'));

$extractLocationsChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Extract locations: {text}'));

// Compose them
$pipeline = SequentialChain::create()
    ->addChain('dates', $extractDatesChain)
    ->addChain('locations', $extractLocationsChain);

// Avoid: One big chain that does everything
$extractEverythingChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Extract dates, locations, people, events, and analyze sentiment: {text}'
    ));
```

### 2. Use Transform Chains for Simple Operations

```php
// Good: Transform chain for data manipulation
$formatChain = TransformChain::create(fn($i) => [
    'formatted' => strtoupper($i['text']),
]);

// Avoid: LLM for simple formatting
$formatChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Make this uppercase: {text}'));
```

### 3. Map Outputs Explicitly

```php
// Good: Clear data flow
$pipeline = SequentialChain::create()
    ->addChain('step1', $chain1)
    ->addChain('step2', $chain2)
    ->mapOutput('step1', 'result', 'step2', 'input');

// Avoid: Relying on implicit data passing
```

### 4. Handle Errors Appropriately

```php
try {
    $result = $chain->invoke($input);
} catch (ChainValidationException $e) {
    // Handle validation errors (bad input)
    return ['error' => 'Invalid input: ' . $e->getMessage()];
} catch (ChainExecutionException $e) {
    // Handle execution errors (API failures, etc.)
    error_log("Chain failed: " . $e->getMessage());
    return ['error' => 'Processing failed, please try again'];
}
```

### 5. Test Your Chains

```php
// Unit test individual chains
public function testExtractDatesChain(): void
{
    $chain = $this->createExtractDatesChain();
    
    $result = $chain->invoke([
        'text' => 'Meeting on January 15th, 2024',
    ]);
    
    $this->assertStringContainsString('2024', $result['result']);
}

// Integration test pipelines
public function testAnalysisPipeline(): void
{
    $pipeline = $this->createAnalysisPipeline();
    
    $result = $pipeline->invoke([
        'text' => 'Sample input',
    ]);
    
    $this->assertArrayHasKey('extract', $result);
    $this->assertArrayHasKey('analyze', $result);
}
```

## Next Steps

Now that you understand chains, you can:

1. **Build Complex Workflows**: Combine chains to create sophisticated processing pipelines
2. **Optimize Performance**: Use parallel chains to reduce latency
3. **Add Intelligence**: Use routers to create adaptive systems
4. **Monitor Production**: Add callbacks and error handling for production use

### Additional Resources

- [Chain API Documentation](../Chains.md)
- [Working Examples](../../examples/)
- [Test Suite](../../tests/Integration/Chains/)

### Example Projects to Build

Try building these to practice:

1. **Content Classifier**: Router that categorizes different types of content
2. **Multi-Stage Analyzer**: Sequential pipeline that processes documents
3. **Resilient Processor**: Parallel chain with fallback strategies
4. **Smart Assistant**: Combined system using all chain types

## Troubleshooting

### Common Issues

**Q: My sequential chain isn't passing data between steps**

A: Make sure you're using `mapOutput()`:
```php
->mapOutput('step1', 'output_key', 'step2', 'input_key')
```

**Q: Parallel chain is slow**

A: Currently uses simulated parallelism. For true async, consider using with async libraries or run in separate processes.

**Q: Router isn't matching my condition**

A: Check that your condition function returns a boolean:
```php
->addRoute(
    fn($input) => $input['type'] === 'code', // Returns bool
    $codeChain
)
```

**Q: Getting validation errors**

A: Check that required template variables are present:
```php
$template = PromptTemplate::create('Hello {name}');
// Must provide 'name' in input
$chain->invoke(['name' => 'World']);
```

## Conclusion

You now have the knowledge to build sophisticated LLM workflows using chains! Remember:

- Start simple with single chains
- Compose them into pipelines
- Add parallelism for performance
- Use routing for intelligence
- Monitor and handle errors

Happy chain building! 🔗

