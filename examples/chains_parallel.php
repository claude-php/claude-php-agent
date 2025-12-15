#!/usr/bin/env php
<?php
/**
 * Parallel Chains Example
 *
 * Demonstrates how to execute multiple chains in parallel
 * with different aggregation strategies.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Chains\ParallelChain;
use ClaudeAgents\Chains\TransformChain;
use ClaudeAgents\Prompts\PromptTemplate;
use ClaudePhp\ClaudePhp;

// Load environment
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? throw new RuntimeException('ANTHROPIC_API_KEY not set');
$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    Parallel Chains Example                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

$sampleText = "I love this product! It's amazing and works perfectly. The customer service was excellent too.";

// ============================================================================
// Example 1: Parallel Analysis with Merge Strategy
// ============================================================================

echo "═══ Example 1: Parallel Analysis (Merge) ═══\n\n";

// Create multiple analysis chains
$sentimentChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Rate the sentiment of this text as positive, negative, or neutral (one word only): {text}'
    ))
    ->withMaxTokens(50);

$lengthChain = TransformChain::create(function (array $input): array {
    $text = $input['text'] ?? '';
    return [
        'char_count' => strlen($text),
        'word_count' => str_word_count($text),
    ];
});

$keywordsChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Extract 3 main keywords from this text (comma-separated): {text}'
    ))
    ->withMaxTokens(50);

// Execute all chains in parallel with merge aggregation
$parallelMerge = ParallelChain::create()
    ->addChain('sentiment', $sentimentChain)
    ->addChain('length', $lengthChain)
    ->addChain('keywords', $keywordsChain)
    ->withAggregation('merge');

echo "Text: \"$sampleText\"\n\n";
echo "Running parallel analysis...\n\n";

$mergeResult = $parallelMerge->invoke(['text' => $sampleText]);

echo "Results (Merged):\n";
foreach ($mergeResult as $key => $value) {
    if ($key === 'metadata') continue; // Skip metadata
    if (is_array($value)) {
        echo "- $key: " . json_encode($value) . "\n";
    } else {
        echo "- $key: $value\n";
    }
}
echo "\n";

// ============================================================================
// Example 2: First Success Strategy
// ============================================================================

echo "═══ Example 2: First Success Strategy ═══\n\n";

$fastChain = TransformChain::create(function (array $input): array {
    return ['method' => 'fast', 'result' => 'Quick answer'];
});

$slowChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('Analyze deeply: {text}'))
    ->withMaxTokens(100);

$parallelFirst = ParallelChain::create()
    ->addChain('fast', $fastChain)
    ->addChain('slow', $slowChain)
    ->withAggregation('first');

$firstResult = $parallelFirst->invoke(['text' => 'Sample text']);

echo "Using 'first' aggregation - returns first successful result:\n";
echo "Result: " . json_encode($firstResult) . "\n\n";

// ============================================================================
// Example 3: All Results Strategy
// ============================================================================

echo "═══ Example 3: All Results Strategy ═══\n\n";

$chain1 = TransformChain::create(fn($input) => ['analysis' => 'Type A']);
$chain2 = TransformChain::create(fn($input) => ['analysis' => 'Type B']);
$chain3 = TransformChain::create(fn($input) => ['analysis' => 'Type C']);

$parallelAll = ParallelChain::create()
    ->addChain('typeA', $chain1)
    ->addChain('typeB', $chain2)
    ->addChain('typeC', $chain3)
    ->withAggregation('all');

$allResult = $parallelAll->invoke(['text' => 'Sample']);

echo "Using 'all' aggregation - returns structured results:\n";
echo "Results:\n";
if (isset($allResult['results'])) {
    foreach ($allResult['results'] as $name => $result) {
        echo "  - $name: " . json_encode($result) . "\n";
    }
}
echo "\n";

// ============================================================================
// Example 4: Error Handling in Parallel Chains
// ============================================================================

echo "═══ Example 4: Error Handling ═══\n\n";

$successChain = TransformChain::create(function (array $input): array {
    return ['status' => 'success', 'data' => 'Good result'];
});

$errorChain = TransformChain::create(function (array $input): array {
    throw new RuntimeException('Intentional error for demo');
});

$parallelWithError = ParallelChain::create()
    ->addChain('success', $successChain)
    ->addChain('error', $errorChain)
    ->withAggregation('merge');

echo "Running chains with one that will fail...\n\n";

$errorResult = $parallelWithError->invoke(['test' => 'value']);

echo "Results (other chains still succeeded):\n";
foreach ($errorResult as $key => $value) {
    if ($key === 'metadata') {
        if (isset($value['errors'])) {
            echo "Errors detected:\n";
            foreach ($value['errors'] as $chainName => $error) {
                echo "  - $chainName: $error\n";
            }
        }
    } else if (is_array($value)) {
        echo "- $key: " . json_encode($value) . "\n";
    } else {
        echo "- $key: $value\n";
    }
}
echo "\n";

// ============================================================================
// Example 5: Real-world Use Case - Multi-perspective Analysis
// ============================================================================

echo "═══ Example 5: Multi-perspective Analysis ═══\n\n";

$technicalChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Analyze from a technical perspective (1 sentence): {text}'
    ))
    ->withMaxTokens(100);

$businessChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Analyze from a business perspective (1 sentence): {text}'
    ))
    ->withMaxTokens(100);

$userChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Analyze from a user experience perspective (1 sentence): {text}'
    ))
    ->withMaxTokens(100);

$multiPerspective = ParallelChain::create()
    ->addChain('technical', $technicalChain)
    ->addChain('business', $businessChain)
    ->addChain('user', $userChain)
    ->withAggregation('all')
    ->withTimeout(60000);

$perspectives = $multiPerspective->invoke([
    'text' => 'A new mobile app that helps users track their daily water intake.',
]);

echo "Product: Mobile app for tracking water intake\n\n";
echo "Multi-perspective Analysis:\n";
if (isset($perspectives['results'])) {
    foreach ($perspectives['results'] as $perspective => $result) {
        echo "  $perspective: " . ($result['result'] ?? 'N/A') . "\n";
    }
}
echo "\n";

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                         Examples Complete                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";

