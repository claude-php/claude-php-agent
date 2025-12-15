#!/usr/bin/env php
<?php
/**
 * Chain Composition System - Comprehensive Usage Examples
 *
 * Demonstrates 10+ patterns for building complex LLM workflows using
 * the Chain Composition System. Shows sequential, parallel, routing,
 * and transformation patterns with real-world use cases.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agent;
use ClaudeAgents\Chains\ChainInput;
use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Chains\ParallelChain;
use ClaudeAgents\Chains\RouterChain;
use ClaudeAgents\Chains\SequentialChain;
use ClaudeAgents\Chains\TransformChain;
use ClaudeAgents\Prompts\PromptTemplate;
use ClaudeAgents\Tools\Tool;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client (in real code, use environment variable)
$client = new ClaudePhp(
    apiKey: $_ENV['ANTHROPIC_API_KEY'] ?? '',
);

// ============================================================================
// EXAMPLE 1: Simple LLM Chain
// ============================================================================

echo "=== Example 1: Simple LLM Chain ===\n\n";

$simpleLLMChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Summarize this in 2 sentences: {text}'))
    ->withModel('claude-sonnet-4-5')
    ->withMaxTokens(256);

// In a real scenario, you would invoke this:
// $result = $simpleLLMChain->invoke(['text' => 'Long document...']);
// echo "Summary: " . json_encode($result) . "\n\n";

// ============================================================================
// EXAMPLE 2: Sequential Chain - Multi-step Pipeline
// ============================================================================

echo "=== Example 2: Sequential Chain ===\n\n";

// Create individual chains for each step
$extractionChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Extract all named entities from this text: {text}'
    ));

$analysisChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Analyze the sentiment and tone based on these entities: {entities}'
    ));

$formatChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Format this analysis as JSON: {analysis}'
    ))
    ->withJsonParser();

// Compose them into a sequential pipeline
$sequentialPipeline = SequentialChain::create()
    ->addChain('extract', $extractionChain)
    ->addChain('analyze', $analysisChain)
    ->addChain('format', $formatChain)
    ->mapOutput('extract', 'result', 'analyze', 'entities')
    ->mapOutput('analyze', 'result', 'format', 'analysis')
    ->onBefore(function (ChainInput $input) {
        echo "Starting multi-step analysis...\n";
    })
    ->onAfter(function (ChainInput $input, $output) {
        echo "Analysis complete!\n";
    });

// Usage:
// $result = $sequentialPipeline->invoke(['text' => 'Customer review...']);

// ============================================================================
// EXAMPLE 3: Parallel Chain - Concurrent Analysis
// ============================================================================

echo "=== Example 3: Parallel Chain ===\n\n";

$sentimentChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Rate the sentiment (positive/negative/neutral): {text}'
    ));

$topicsChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Identify the main topics: {text}'
    ));

$summaryChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Provide a brief summary: {text}'
    ));

$parallelAnalysis = ParallelChain::create()
    ->addChain('sentiment', $sentimentChain)
    ->addChain('topics', $topicsChain)
    ->addChain('summary', $summaryChain)
    ->withAggregation('merge')  // Merge all results into one array
    ->withTimeout(30000);

// Usage:
// $result = $parallelAnalysis->invoke(['text' => 'Product review...']);

// ============================================================================
// EXAMPLE 4: Router Chain - Conditional Routing
// ============================================================================

echo "=== Example 4: Router Chain ===\n\n";

$codeReviewChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Review this code for issues:\n{content}'
    ));

$documentationChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Explain this documentation:\n{content}'
    ));

$questionsChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Answer this question:\n{content}'
    ));

$generalChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Process this request:\n{content}'
    ));

$router = RouterChain::create()
    ->addRoute(
        fn($input) => str_contains($input['content'], '<?php') ||
            str_contains($input['content'], 'function '),
        $codeReviewChain
    )
    ->addRoute(
        fn($input) => str_contains($input['content'], '/**') ||
            str_contains($input['content'], 'documentation'),
        $documentationChain
    )
    ->addRoute(
        fn($input) => str_ends_with($input['content'], '?'),
        $questionsChain
    )
    ->setDefault($generalChain);

// Usage:
// $result = $router->invoke(['content' => 'Code snippet or question']);

// ============================================================================
// EXAMPLE 5: Transform Chain - Data Transformation
// ============================================================================

echo "=== Example 5: Transform Chain ===\n\n";

$normalizeChain = TransformChain::create(
    function (array $input): array {
        return [
            'normalized_text' => strtolower($input['text'] ?? ''),
            'length' => strlen($input['text'] ?? ''),
            'word_count' => str_word_count($input['text'] ?? ''),
        ];
    }
);

// Usage:
// $result = $normalizeChain->invoke(['text' => 'Sample TEXT']);

// ============================================================================
// EXAMPLE 6: Complex Nested Chain
// ============================================================================

echo "=== Example 6: Complex Nested Chain ===\n\n";

// First, route the input to the appropriate chain
$inputRouter = RouterChain::create()
    ->addRoute(
        fn($input) => isset($input['analyze_sentiment']),
        $sentimentChain
    )
    ->addRoute(
        fn($input) => isset($input['extract_entities']),
        $extractionChain
    )
    ->setDefault($summaryChain);

// Then, apply transformations to the output
$postProcessor = TransformChain::create(
    function (array $input): array {
        // Add timestamp and metadata
        return array_merge($input, [
            'processed_at' => date('Y-m-d H:i:s'),
            'version' => '1.0',
        ]);
    }
);

// Combine routing and post-processing
$complexChain = SequentialChain::create()
    ->addChain('router', $inputRouter)
    ->addChain('postprocess', $postProcessor)
    ->mapOutput('router', 'result', 'postprocess', 'result');

// Usage:
// $result = $complexChain->invoke(['text' => '...', 'analyze_sentiment' => true]);

// ============================================================================
// EXAMPLE 7: Chain as Tool for Agents
// ============================================================================

echo "=== Example 7: Chain as Tool ===\n\n";

// Convert a chain to a tool that can be used by agents
$analysisTool = Tool::fromChain(
    $sequentialPipeline,
    'analyze_text',
    'Performs multi-step analysis on text including extraction, analysis, and formatting'
);

// Use the tool with an agent
$agent = Agent::create($client)
    ->withTool($analysisTool)
    ->withSystemPrompt('You are a text analysis assistant.')
    ->maxIterations(5);

// In a real scenario:
// $result = $agent->run('Please analyze this customer feedback: ...');

// ============================================================================
// EXAMPLE 8: Chain with Callbacks for Monitoring
// ============================================================================

echo "=== Example 8: Chain with Callbacks ===\n\n";

$monitoredChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Analyze: {input}'))
    ->onBefore(function (ChainInput $input) {
        echo "[BEFORE] Processing input: " . json_encode($input->all()) . "\n";
    })
    ->onAfter(function (ChainInput $input, $output) {
        echo "[AFTER] Got output with metadata: " . json_encode($output->getMetadata()) . "\n";
    })
    ->onError(function (ChainInput $input, \Throwable $error) {
        echo "[ERROR] Failed: " . $error->getMessage() . "\n";
    });

// ============================================================================
// EXAMPLE 9: Conditional Sequential Execution
// ============================================================================

echo "=== Example 9: Conditional Sequential Execution ===\n\n";

$conditionalPipeline = SequentialChain::create()
    ->addChain('initial_processing', $normalizeChain)
    ->addChain('analysis', $analysisChain)
    ->addChain('enrichment', $formatChain)
    ->setCondition('analysis', function (array $results) {
        // Only run analysis if normalization produced meaningful text
        return ($results['initial_processing']['word_count'] ?? 0) > 5;
    })
    ->setCondition('enrichment', function (array $results) {
        // Only run enrichment if analysis succeeded
        return isset($results['analysis']['result']);
    });

// ============================================================================
// EXAMPLE 10: Error Handling and Recovery
// ============================================================================

echo "=== Example 10: Error Handling ===\n\n";

try {
    // Try to execute a chain
    $result = $parallelAnalysis->invoke(['text' => 'Sample text']);
} catch (\ClaudeAgents\Chains\Exceptions\ChainExecutionException $e) {
    echo "Chain execution failed: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";
} catch (\ClaudeAgents\Chains\Exceptions\ChainValidationException $e) {
    echo "Chain validation failed: " . $e->getMessage() . "\n";
}

echo "\n=== All Examples Complete ===\n";
