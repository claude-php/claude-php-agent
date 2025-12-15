#!/usr/bin/env php
<?php
/**
 * Basic Chains Example
 *
 * Demonstrates the fundamental Chain types:
 * - LLMChain: Call LLMs with prompts
 * - TransformChain: Transform data
 * - SequentialChain: Execute chains in sequence
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Chains\TransformChain;
use ClaudeAgents\Chains\SequentialChain;
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
echo "║                      Basic Chains Example                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Example 1: Simple LLMChain
// ============================================================================

echo "═══ Example 1: Simple LLM Chain ═══\n\n";

$simpleChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create('What is {number} + {number}?'))
    ->withModel('claude-sonnet-4-5')
    ->withMaxTokens(100);

$result = $simpleChain->invoke(['number' => '42']);

echo "Question: What is 42 + 42?\n";
echo "Answer: " . ($result['result'] ?? 'N/A') . "\n\n";

// ============================================================================
// Example 2: TransformChain for Data Processing
// ============================================================================

echo "═══ Example 2: Transform Chain ═══\n\n";

$transformChain = TransformChain::create(function (array $input): array {
    $text = $input['text'] ?? '';
    
    return [
        'uppercase' => strtoupper($text),
        'lowercase' => strtolower($text),
        'length' => strlen($text),
        'word_count' => str_word_count($text),
        'reversed' => strrev($text),
    ];
});

$transformResult = $transformChain->invoke(['text' => 'Hello World']);

echo "Input: Hello World\n";
echo "Uppercase: " . $transformResult['uppercase'] . "\n";
echo "Lowercase: " . $transformResult['lowercase'] . "\n";
echo "Length: " . $transformResult['length'] . " characters\n";
echo "Words: " . $transformResult['word_count'] . " words\n";
echo "Reversed: " . $transformResult['reversed'] . "\n\n";

// ============================================================================
// Example 3: Sequential Chain - Multi-step Processing
// ============================================================================

echo "═══ Example 3: Sequential Chain ═══\n\n";

// Step 1: Normalize the input
$normalizeChain = TransformChain::create(function (array $input): array {
    return [
        'normalized' => trim(strtolower($input['text'] ?? '')),
    ];
});

// Step 2: Analyze with LLM
$analyzeChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Analyze this text and identify the main topic in 1-2 words: {normalized}'
    ))
    ->withMaxTokens(50);

// Step 3: Format the output
$formatChain = TransformChain::create(function (array $input): array {
    return [
        'report' => "Topic: " . ($input['result'] ?? 'Unknown'),
        'processed_at' => date('Y-m-d H:i:s'),
    ];
});

// Compose into sequential chain
$pipeline = SequentialChain::create()
    ->addChain('normalize', $normalizeChain)
    ->addChain('analyze', $analyzeChain)
    ->addChain('format', $formatChain)
    ->mapOutput('normalize', 'normalized', 'analyze', 'normalized')
    ->mapOutput('analyze', 'result', 'format', 'result');

$pipelineResult = $pipeline->invoke([
    'text' => '  PHP is a popular server-side scripting language.  ',
]);

echo "Input: '  PHP is a popular server-side scripting language.  '\n\n";
echo "Pipeline Results:\n";
echo "- Normalized: " . ($pipelineResult['normalize']['normalized'] ?? 'N/A') . "\n";
echo "- Analysis: " . ($pipelineResult['analyze']['result'] ?? 'N/A') . "\n";
echo "- " . ($pipelineResult['format']['report'] ?? 'N/A') . "\n";
echo "- Processed: " . ($pipelineResult['format']['processed_at'] ?? 'N/A') . "\n\n";

// ============================================================================
// Example 4: Sequential Chain with Conditional Execution
// ============================================================================

echo "═══ Example 4: Conditional Sequential Chain ═══\n\n";

$validateChain = TransformChain::create(function (array $input): array {
    $email = $input['email'] ?? '';
    $isValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    
    return [
        'email' => $email,
        'is_valid' => $isValid,
    ];
});

$processChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Extract the domain from this email: {email}. Return only the domain name.'
    ))
    ->withMaxTokens(50);

$conditionalPipeline = SequentialChain::create()
    ->addChain('validate', $validateChain)
    ->addChain('process', $processChain)
    ->setCondition('process', function (array $results): bool {
        // Only process if email is valid
        return $results['validate']['is_valid'] ?? false;
    })
    ->mapOutput('validate', 'email', 'process', 'email');

// Test with valid email
$result1 = $conditionalPipeline->invoke(['email' => 'user@example.com']);
echo "Valid Email: user@example.com\n";
echo "Validation: " . ($result1['validate']['is_valid'] ? 'PASS' : 'FAIL') . "\n";
echo "Processing: " . (isset($result1['process']) ? 'Executed' : 'Skipped') . "\n";
if (isset($result1['process']['result'])) {
    echo "Domain: " . $result1['process']['result'] . "\n";
}
echo "\n";

// Test with invalid email
$result2 = $conditionalPipeline->invoke(['email' => 'invalid-email']);
echo "Invalid Email: invalid-email\n";
echo "Validation: " . ($result2['validate']['is_valid'] ? 'PASS' : 'FAIL') . "\n";
echo "Processing: " . (isset($result2['process']) ? 'Executed' : 'Skipped') . "\n\n";

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                         Examples Complete                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";

