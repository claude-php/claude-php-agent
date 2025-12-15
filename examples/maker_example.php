<?php

declare(strict_types=1);

/**
 * MAKER Agent Example
 *
 * Demonstrates the MAKER (Maximal Agentic decomposition, first-to-ahead-by-K 
 * Error correction, and Red-flagging) framework for solving complex tasks with 
 * near-zero error rates.
 *
 * Based on: "Solving a Million-Step LLM Task with Zero Errors"
 * https://arxiv.org/html/2511.09030v1
 *
 * Key Features:
 * - Extreme task decomposition into minimal subtasks
 * - Multi-agent voting for error correction at each step
 * - Red-flagging to detect and retry unreliable responses
 * - Can handle tasks requiring millions of steps with high reliability
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
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

echo "=== MAKER Agent Demo ===\n\n";
echo "This demonstrates the MAKER framework for highly reliable task execution.\n";
echo "The agent uses extreme decomposition and voting to minimize errors.\n\n";

// Example 1: Multi-Step Mathematical Problem
echo "--- Example 1: Complex Mathematical Problem ---\n\n";

$mathTask = <<<TASK
Calculate the following step by step:
1. Start with 100
2. Multiply by 3
3. Add 50
4. Divide by 5
5. Subtract 20
6. Multiply by 2
7. What is the final result?

Show your work for each step.
TASK;

$makerAgent = new MakerAgent($client, [
    'name' => 'math_maker',
    'voting_k' => 2,              // First-to-ahead-by-2 voting
    'enable_red_flagging' => true, // Enable uncertainty detection
    'max_decomposition_depth' => 5,
]);

echo "Task: Multi-step calculation\n";
echo "Voting K: 2 (first to lead by 2 votes wins)\n";
echo "Red-flagging: Enabled\n\n";

$startTime = microtime(true);
$result = $makerAgent->run($mathTask);
$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    echo "✓ Success!\n\n";
    echo "Answer:\n{$result->getAnswer()}\n\n";
    
    $stats = $result->getMetadata()['execution_stats'] ?? [];
    echo "Execution Statistics:\n";
    echo "  Total Steps: {$stats['total_steps']}\n";
    echo "  Atomic Executions: {$stats['atomic_executions']}\n";
    echo "  Decompositions: {$stats['decompositions']}\n";
    echo "  Subtasks Created: {$stats['subtasks_created']}\n";
    echo "  Votes Cast: {$stats['votes_cast']}\n";
    echo "  Red Flags Detected: {$stats['red_flags_detected']}\n";
    echo "  Estimated Error Rate: " . ($result->getMetadata()['error_rate'] ?? 'N/A') . "\n";
    echo "  Duration: " . round($duration, 2) . "s\n";
} else {
    echo "✗ Failed: {$result->getError()}\n";
}

echo "\n" . str_repeat("-", 70) . "\n\n";

// Example 2: Logic Problem with Multiple Steps
echo "--- Example 2: Logic Problem ---\n\n";

$logicTask = <<<TASK
Three friends - Alice, Bob, and Charlie - are sitting in a row.
- Alice is not at either end
- Bob is to the left of Charlie
- Charlie is not next to Alice

Determine the order they are sitting in from left to right.
Explain your reasoning step by step.
TASK;

$logicMaker = new MakerAgent($client, [
    'name' => 'logic_maker',
    'voting_k' => 3,  // More stringent voting for logic problems
    'enable_red_flagging' => true,
]);

echo "Task: Logical reasoning problem\n";
echo "Voting K: 3 (higher threshold for logic)\n\n";

$startTime = microtime(true);
$result = $logicMaker->run($logicTask);
$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    echo "✓ Success!\n\n";
    echo "Answer:\n{$result->getAnswer()}\n\n";
    
    $stats = $result->getMetadata()['execution_stats'] ?? [];
    echo "Execution Statistics:\n";
    echo "  Total Steps: {$stats['total_steps']}\n";
    echo "  Votes Cast: {$stats['votes_cast']}\n";
    echo "  Red Flags: {$stats['red_flags_detected']}\n";
    echo "  Duration: " . round($duration, 2) . "s\n";
} else {
    echo "✗ Failed: {$result->getError()}\n";
}

echo "\n" . str_repeat("-", 70) . "\n\n";

// Example 3: Procedural Task (Recipe-like)
echo "--- Example 3: Multi-Step Procedure ---\n\n";

$procedureTask = <<<TASK
You need to prepare a workspace for painting:
1. Remove all furniture from the room
2. Cover the floor with drop cloths
3. Apply painter's tape to window frames and door frames
4. Fill any holes in the walls with spackle
5. Sand the spackled areas smooth
6. Prime the walls
7. Clean up and prepare for painting tomorrow

What is the correct sequence and what should you check at each step?
TASK;

$procedureMaker = new MakerAgent($client, [
    'name' => 'procedure_maker',
    'voting_k' => 2,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 3,
]);

echo "Task: Sequential procedure verification\n";
echo "Max Decomposition Depth: 3\n\n";

$startTime = microtime(true);
$result = $procedureMaker->run($procedureTask);
$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    echo "✓ Success!\n\n";
    echo "Answer:\n{$result->getAnswer()}\n\n";
    
    $stats = $result->getMetadata()['execution_stats'] ?? [];
    echo "Execution Statistics:\n";
    echo "  Decompositions: {$stats['decompositions']}\n";
    echo "  Subtasks: {$stats['subtasks_created']}\n";
    echo "  Red Flags: {$stats['red_flags_detected']}\n";
    echo "  Duration: " . round($duration, 2) . "s\n";
} else {
    echo "✗ Failed: {$result->getError()}\n";
}

echo "\n" . str_repeat("-", 70) . "\n\n";

// Example 4: Comparing with and without Red-Flagging
echo "--- Example 4: Impact of Red-Flagging ---\n\n";

$ambiguousTask = <<<TASK
If a train leaves Station A at 2 PM traveling at 60 mph, and another train 
leaves Station B at 3 PM traveling at 80 mph, and the stations are 300 miles 
apart and the trains are traveling toward each other, when will they meet?
TASK;

echo "Running TWICE - with and without red-flagging:\n\n";

// Without red-flagging
echo "1. WITHOUT Red-Flagging:\n";
$noRedFlagMaker = new MakerAgent($client, [
    'name' => 'no_redflag',
    'voting_k' => 2,
    'enable_red_flagging' => false,
]);

$startTime = microtime(true);
$result1 = $noRedFlagMaker->run($ambiguousTask);
$duration1 = microtime(true) - $startTime;

echo "   Duration: " . round($duration1, 2) . "s\n";
if ($result1->isSuccess()) {
    $stats1 = $result1->getMetadata()['execution_stats'] ?? [];
    echo "   Votes Cast: {$stats1['votes_cast']}\n";
    echo "   Answer: " . substr($result1->getAnswer(), 0, 100) . "...\n";
}
echo "\n";

// With red-flagging
echo "2. WITH Red-Flagging:\n";
$withRedFlagMaker = new MakerAgent($client, [
    'name' => 'with_redflag',
    'voting_k' => 2,
    'enable_red_flagging' => true,
]);

$startTime = microtime(true);
$result2 = $withRedFlagMaker->run($ambiguousTask);
$duration2 = microtime(true) - $startTime;

echo "   Duration: " . round($duration2, 2) . "s\n";
if ($result2->isSuccess()) {
    $stats2 = $result2->getMetadata()['execution_stats'] ?? [];
    echo "   Votes Cast: {$stats2['votes_cast']}\n";
    echo "   Red Flags Detected: {$stats2['red_flags_detected']}\n";
    echo "   Answer: " . substr($result2->getAnswer(), 0, 100) . "...\n";
}

echo "\n" . str_repeat("-", 70) . "\n\n";

// Summary
echo "=== MAKER Framework Summary ===\n\n";
echo "The MAKER agent demonstrates:\n\n";
echo "1. MAXIMAL DECOMPOSITION:\n";
echo "   - Tasks broken into minimal, atomic subtasks\n";
echo "   - Recursive decomposition to appropriate depth\n";
echo "   - Each subtask becomes independent execution unit\n\n";

echo "2. ERROR CORRECTION (First-to-ahead-by-K):\n";
echo "   - Multiple agents vote on each subtask\n";
echo "   - Winner determined by vote margin (K)\n";
echo "   - Ensures consensus before proceeding\n\n";

echo "3. RED-FLAGGING:\n";
echo "   - Detects uncertain or confused responses\n";
echo "   - Identifies circular reasoning patterns\n";
echo "   - Triggers retry for suspicious outputs\n\n";

echo "This approach enables:\n";
echo "  ✓ Solving tasks with millions of steps\n";
echo "  ✓ Near-zero error rates through voting\n";
echo "  ✓ Reliable execution without perfect base models\n";
echo "  ✓ Decorrelated errors via independent agents\n\n";

echo "Key Paper Insights:\n";
echo "  - Standard LLMs fail after ~100-300 steps (1% error rate)\n";
echo "  - MAKER solved 1M+ step tasks with ZERO errors\n";
echo "  - Smaller models with MAKER > larger models without\n";
echo "  - Cost scales sub-linearly with proper decomposition\n\n";

echo "For more details, see: https://arxiv.org/html/2511.09030v1\n";

