<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../load-env.php';

use ClaudeAgents\ML\PromptOptimizer;
use ClaudePhp\ClaudePhp;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

loadEnv(__DIR__ . '/../..');

$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    die('ANTHROPIC_API_KEY not found');
}

$client = new ClaudePhp($apiKey);
$logger = new \Psr\Log\NullLogger();

echo "=== PromptOptimizer - Learn from History ===\n\n";

$optimizer = new PromptOptimizer($client, [
    'logger' => $logger,
    'history_store_path' => __DIR__ . '/../../storage/prompt_optimizer_history.json',
]);

// Scenario: Building a code explanation prompt
echo "Scenario: Optimizing a code explanation prompt\n\n";

// Step 1: Record historical performance
echo "Step 1: Recording historical prompt performance...\n";

$historyData = [
    [
        'prompt' => "Explain this code.",
        'context' => "PHP function for user authentication",
        'quality' => 4.5,
        'tokens' => 300,
        'success' => true,
        'duration' => 1.2,
    ],
    [
        'prompt' => "Explain this PHP code in detail, including what each line does.",
        'context' => "PHP function for user authentication",
        'quality' => 7.8,
        'tokens' => 520,
        'success' => true,
        'duration' => 2.1,
    ],
    [
        'prompt' => "Provide a line-by-line explanation of the following PHP code, including purpose, inputs, outputs, and any security considerations.",
        'context' => "PHP function for user authentication",
        'quality' => 9.2,
        'tokens' => 680,
        'success' => true,
        'duration' => 2.8,
    ],
];

foreach ($historyData as $data) {
    $optimizer->recordPerformance(
        prompt: $data['prompt'],
        taskContext: $data['context'],
        qualityScore: $data['quality'],
        tokenUsage: $data['tokens'],
        success: $data['success'],
        duration: $data['duration']
    );
    echo "  ✓ Recorded: Quality {$data['quality']}/10\n";
}

echo "\nStep 2: Optimizing a new prompt...\n\n";

$originalPrompt = "Explain what this code does.";
$taskContext = "PHP function for user authentication, need detailed explanation for junior developers";

echo "Original Prompt:\n  \"{$originalPrompt}\"\n\n";
echo "Task Context:\n  {$taskContext}\n\n";

$optimization = $optimizer->optimize($originalPrompt, $taskContext);

echo "Optimized Prompt:\n  \"{$optimization['optimized_prompt']}\"\n\n";
echo "Confidence: " . round($optimization['confidence'] * 100) . "%\n\n";
echo "Improvements Made:\n";
foreach ($optimization['improvements'] as $i => $improvement) {
    echo "  " . ($i + 1) . ". {$improvement}\n";
}

echo "\n\nStep 3: A/B Testing multiple prompt variations...\n\n";

$promptVariations = [
    "Explain the code.",
    "Provide a detailed explanation of this code.",
    "Explain this code step-by-step with examples.",
];

echo "Testing variations:\n";
foreach ($promptVariations as $i => $prompt) {
    echo "  " . ($i + 1) . ". \"{$prompt}\"\n";
}

$comparison = $optimizer->comparePrompts($promptVariations, $taskContext);

echo "\nWinner (Variation #" . ($comparison['winner_index'] + 1) . "):\n";
echo "  \"{$comparison['winner']}\"\n";
echo "  Confidence: " . round($comparison['confidence'] * 100) . "%\n";

echo "\n--- Prompt Optimization Complete ---\n";
echo "The PromptOptimizer learned from historical data to suggest better prompts!\n";

