#!/usr/bin/env php
<?php
/**
 * Chain of Thought (CoT) Example
 *
 * Demonstrates reasoning patterns using Chain of Thought prompting.
 * Shows both zero-shot and few-shot CoT approaches for complex problem solving.
 * 
 * Usage: php examples/cot_example.php
 * 
 * Make sure ANTHROPIC_API_KEY is set in your .env file
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\ChainOfThoughtAgent;
use ClaudeAgents\Reasoning\CoTPrompts;
use ClaudePhp\ClaudePhp;

// Initialize Claude client
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "Error: ANTHROPIC_API_KEY not found in environment.\n";
    echo "Please set it as an environment variable:\n";
    echo "  export ANTHROPIC_API_KEY='your-api-key'\n";
    echo "Or run with: ANTHROPIC_API_KEY='your-api-key' php examples/cot_example.php\n";
    exit(1);
}

$client = new ClaudePhp(apiKey: $apiKey);

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         Chain-of-Thought (CoT) Reasoning Examples             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Example 1: Zero-shot CoT for Math
echo "━━━ Example 1: Zero-Shot CoT (Math Problem) ━━━\n\n";

$agent1 = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => CoTPrompts::zeroShotTrigger(),
    'name' => 'zero_shot_math',
]);

$task1 = "A bakery makes 24 cupcakes per batch. They sell them in boxes of 6. If each box costs \$12, how much money do they make per batch?";

echo "📝 Task: {$task1}\n\n";

$result1 = $agent1->run($task1);

if ($result1->isSuccess()) {
    echo "💡 Answer:\n{$result1->getAnswer()}\n\n";
    echo "📊 Metadata:\n";
    echo "   - Iterations: {$result1->getIterations()}\n";
    echo "   - Mode: {$result1->getMetadata()['reasoning_mode']}\n";
    echo "   - Tokens: {$result1->getMetadata()['tokens']['input']} in, {$result1->getMetadata()['tokens']['output']} out\n\n";
} else {
    echo "❌ Error: {$result1->getError()}\n\n";
}

// Example 2: Few-shot CoT with Math Examples
echo "━━━ Example 2: Few-Shot CoT (With Examples) ━━━\n\n";

$agent2 = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::mathExamples(),
    'name' => 'few_shot_math',
]);

$task2 = "If a store has a 30% discount and an item originally costs \$50, what is the sale price after adding 8% sales tax?";

echo "📝 Task: {$task2}\n\n";

$result2 = $agent2->run($task2);

if ($result2->isSuccess()) {
    echo "💡 Answer:\n{$result2->getAnswer()}\n\n";
    echo "📊 Metadata:\n";
    echo "   - Iterations: {$result2->getIterations()}\n";
    echo "   - Mode: {$result2->getMetadata()['reasoning_mode']}\n";
    echo "   - Tokens: {$result2->getMetadata()['tokens']['input']} in, {$result2->getMetadata()['tokens']['output']} out\n\n";
} else {
    echo "❌ Error: {$result2->getError()}\n\n";
}

// Example 3: Logic Problem with CoT
echo "━━━ Example 3: Zero-Shot CoT (Logic Problem) ━━━\n\n";

$agent3 = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => "Let's approach this logically.",
    'name' => 'zero_shot_logic',
]);

$task3 = "Five people (Alice, Bob, Carol, David, Eve) finish a race. Alice finishes before Bob but after Carol. David finishes after Bob. Eve finishes before Carol. Who won the race?";

echo "📝 Task: {$task3}\n\n";

$result3 = $agent3->run($task3);

if ($result3->isSuccess()) {
    echo "💡 Answer:\n{$result3->getAnswer()}\n\n";
    echo "📊 Metadata:\n";
    echo "   - Iterations: {$result3->getIterations()}\n";
    echo "   - Mode: {$result3->getMetadata()['reasoning_mode']}\n";
    echo "   - Tokens: {$result3->getMetadata()['tokens']['input']} in, {$result3->getMetadata()['tokens']['output']} out\n\n";
} else {
    echo "❌ Error: {$result3->getError()}\n\n";
}

// Example 4: Few-shot CoT with Logic Examples
echo "━━━ Example 4: Few-Shot CoT (Logic with Examples) ━━━\n\n";

$agent4 = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::logicExamples(),
    'name' => 'few_shot_logic',
]);

$task4 = "Three boxes are labeled 'Apples', 'Oranges', and 'Mixed'. All labels are incorrect. You can pick one fruit from one box. How do you correctly label all boxes?";

echo "📝 Task: {$task4}\n\n";

$result4 = $agent4->run($task4);

if ($result4->isSuccess()) {
    echo "💡 Answer:\n{$result4->getAnswer()}\n\n";
    echo "📊 Metadata:\n";
    echo "   - Iterations: {$result4->getIterations()}\n";
    echo "   - Mode: {$result4->getMetadata()['reasoning_mode']}\n";
    echo "   - Tokens: {$result4->getMetadata()['tokens']['input']} in, {$result4->getMetadata()['tokens']['output']} out\n\n";
} else {
    echo "❌ Error: {$result4->getError()}\n\n";
}

// Example 5: Decision Making with CoT
echo "━━━ Example 5: Few-Shot CoT (Decision Making) ━━━\n\n";

$agent5 = new ChainOfThoughtAgent($client, [
    'mode' => 'few_shot',
    'examples' => CoTPrompts::decisionExamples(),
    'name' => 'few_shot_decision',
]);

$task5 = "A small business needs to choose between hiring a full-time developer at \$80k/year or using freelancers at \$50/hour. They estimate needing 30 hours of work per week. Which option should they choose?";

echo "📝 Task: {$task5}\n\n";

$result5 = $agent5->run($task5);

if ($result5->isSuccess()) {
    echo "💡 Answer:\n{$result5->getAnswer()}\n\n";
    echo "📊 Metadata:\n";
    echo "   - Iterations: {$result5->getIterations()}\n";
    echo "   - Mode: {$result5->getMetadata()['reasoning_mode']}\n";
    echo "   - Tokens: {$result5->getMetadata()['tokens']['input']} in, {$result5->getMetadata()['tokens']['output']} out\n\n";
} else {
    echo "❌ Error: {$result5->getError()}\n\n";
}

// Example 6: Custom Trigger Phrase
echo "━━━ Example 6: Custom Trigger Phrase ━━━\n\n";

$agent6 = new ChainOfThoughtAgent($client, [
    'mode' => 'zero_shot',
    'trigger' => "Let's break this down systematically and show all our work.",
    'name' => 'custom_trigger',
]);

$task6 = "If I read 15 pages per day for 3 weeks, and then 20 pages per day for 2 weeks, how many pages total did I read?";

echo "📝 Task: {$task6}\n\n";

$result6 = $agent6->run($task6);

if ($result6->isSuccess()) {
    echo "💡 Answer:\n{$result6->getAnswer()}\n\n";
    echo "📊 Metadata:\n";
    echo "   - Iterations: {$result6->getIterations()}\n";
    echo "   - Mode: {$result6->getMetadata()['reasoning_mode']}\n";
    echo "   - Tokens: {$result6->getMetadata()['tokens']['input']} in, {$result6->getMetadata()['tokens']['output']} out\n\n";
} else {
    echo "❌ Error: {$result6->getError()}\n\n";
}

// Summary
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                          Summary                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$results = [$result1, $result2, $result3, $result4, $result5, $result6];
$successful = array_filter($results, fn($r) => $r->isSuccess());

echo "Total Examples: " . count($results) . "\n";
echo "Successful: " . count($successful) . "\n";
echo "Failed: " . (count($results) - count($successful)) . "\n\n";

$totalTokensIn = array_sum(array_map(fn($r) => $r->getMetadata()['tokens']['input'] ?? 0, $successful));
$totalTokensOut = array_sum(array_map(fn($r) => $r->getMetadata()['tokens']['output'] ?? 0, $successful));

echo "Total Tokens Used:\n";
echo "  - Input: {$totalTokensIn}\n";
echo "  - Output: {$totalTokensOut}\n";
echo "  - Total: " . ($totalTokensIn + $totalTokensOut) . "\n\n";

echo "✅ All examples completed!\n";
