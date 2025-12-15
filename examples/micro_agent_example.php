<?php

declare(strict_types=1);

/**
 * MicroAgent Example
 *
 * Demonstrates the MicroAgent - a lightweight, focused agent designed for 
 * single-purpose tasks as part of the MAKER framework.
 *
 * MicroAgents are the atomic units of complex agent systems. Each micro-agent
 * has a specific role and executes independently with high consistency.
 *
 * Key Features:
 * - Specialized roles (decomposer, executor, composer, validator, discriminator)
 * - Low temperature (0.1) for consistent, deterministic outputs
 * - Built-in retry logic with exponential backoff
 * - Minimal overhead - fast and efficient
 * - Designed for parallel execution
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\MicroAgent;
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

echo "=== MicroAgent Demo ===\n\n";
echo "MicroAgents are specialized, lightweight agents designed for single-purpose tasks.\n";
echo "They are the building blocks of complex multi-agent systems like MAKER.\n\n";

// Example 1: Executor Role - Direct Task Execution
echo "--- Example 1: Executor (Default Role) ---\n\n";

$executor = new MicroAgent($client, [
    'role' => 'executor',
    'temperature' => 0.1,  // Very low for consistency
]);

echo "Task: Calculate 15% tip on \$67.43\n";
$result = $executor->execute("Calculate a 15% tip on a bill of \$67.43. Provide just the tip amount.");

echo "Result: {$result}\n\n";
echo str_repeat("-", 70) . "\n\n";

// Example 2: Decomposer Role - Break Down Complex Tasks
echo "--- Example 2: Decomposer (Task Breakdown) ---\n\n";

$decomposer = new MicroAgent($client, [
    'role' => 'decomposer',
]);

$complexTask = "Plan a surprise birthday party for 20 people with a \$500 budget";
echo "Task: {$complexTask}\n\n";

$subtasks = $decomposer->execute("Break this task into clear, minimal subtasks:\n{$complexTask}");

echo "Decomposed Subtasks:\n{$subtasks}\n\n";
echo str_repeat("-", 70) . "\n\n";

// Example 3: Validator Role - Check Correctness
echo "--- Example 3: Validator (Result Verification) ---\n\n";

$validator = new MicroAgent($client, [
    'role' => 'validator',
]);

$calculationToCheck = "15% of \$67.43 = \$10.11";
echo "Checking: {$calculationToCheck}\n";

$validation = $validator->execute(
    "Validate this calculation and respond with 'VALID' or 'INVALID' with brief explanation: {$calculationToCheck}"
);

echo "Validation Result: {$validation}\n\n";
echo str_repeat("-", 70) . "\n\n";

// Example 4: Composer Role - Combine Results
echo "--- Example 4: Composer (Result Synthesis) ---\n\n";

$composer = new MicroAgent($client, [
    'role' => 'composer',
]);

$subtaskResults = [
    "Venue booked at community center - \$100",
    "Catering ordered for 20 people - \$300",
    "Decorations purchased - \$50",
    "Birthday cake ordered - \$50",
];

echo "Subtask Results to Combine:\n";
foreach ($subtaskResults as $i => $result) {
    echo "  " . ($i + 1) . ". {$result}\n";
}
echo "\n";

$composedResult = $composer->execute(
    "Synthesize these party planning results into a coherent summary:\n" . 
    implode("\n", $subtaskResults)
);

echo "Composed Summary:\n{$composedResult}\n\n";
echo str_repeat("-", 70) . "\n\n";

// Example 5: Discriminator Role - Choose Best Option
echo "--- Example 5: Discriminator (Option Selection) ---\n\n";

$discriminator = new MicroAgent($client, [
    'role' => 'discriminator',
]);

$options = [
    "Option A: Host party on Saturday afternoon at 2 PM (good weather, most people available)",
    "Option B: Host party on Friday evening at 7 PM (some people working, but more intimate)",
    "Option C: Host party on Sunday morning at 11 AM (brunch style, but some may have commitments)",
];

echo "Options to Evaluate:\n";
foreach ($options as $option) {
    echo "  {$option}\n";
}
echo "\n";

$bestOption = $discriminator->execute(
    "Choose the best option for a surprise birthday party and explain why:\n" . 
    implode("\n", $options)
);

echo "Best Option: {$bestOption}\n\n";
echo str_repeat("-", 70) . "\n\n";

// Example 6: Custom System Prompt
echo "--- Example 6: Custom System Prompt ---\n\n";

$customAgent = new MicroAgent($client, [
    'role' => 'executor',
]);

$customAgent->setSystemPrompt(
    "You are a pirate expert. Answer all questions in pirate speak but remain factually accurate."
);

echo "Task: Explain what PHP is\n";
$pirateResult = $customAgent->execute("Explain what PHP is in one sentence.");

echo "Result (in pirate speak): {$pirateResult}\n\n";
echo str_repeat("-", 70) . "\n\n";

// Example 7: Retry Logic
echo "--- Example 7: Retry with Exponential Backoff ---\n\n";

$reliableAgent = new MicroAgent($client, [
    'role' => 'executor',
]);

echo "Task: Complex calculation with retry protection\n";
$task = "If compound interest is calculated annually at 5% on \$1000 for 3 years, what's the final amount?";

$startTime = microtime(true);
$result = $reliableAgent->executeWithRetry($task, maxRetries: 3);
$duration = microtime(true) - $startTime;

echo "Result: {$result}\n";
echo "Execution Time: " . round($duration, 2) . "s\n\n";
echo str_repeat("-", 70) . "\n\n";

// Example 8: Parallel Micro-Agents (Simulated)
echo "--- Example 8: Parallel Execution Pattern ---\n\n";

echo "In production, you would execute these in parallel for speed.\n";
echo "Here we demonstrate the pattern sequentially:\n\n";

$tasks = [
    "Calculate 18% tip on \$45.67",
    "Calculate 18% tip on \$123.45",
    "Calculate 18% tip on \$89.01",
];

$results = [];
$totalStartTime = microtime(true);

foreach ($tasks as $i => $task) {
    $agent = new MicroAgent($client, ['role' => 'executor']);
    $results[] = $agent->execute($task);
}

$totalDuration = microtime(true) - $totalStartTime;

echo "Processed " . count($tasks) . " tasks:\n";
foreach ($results as $i => $result) {
    echo "  Task " . ($i + 1) . ": {$result}\n";
}
echo "\nTotal Time: " . round($totalDuration, 2) . "s\n";
echo "Note: With async execution, this would be much faster!\n\n";
echo str_repeat("-", 70) . "\n\n";

// Summary
echo "=== MicroAgent Design Principles ===\n\n";

echo "1. SINGLE RESPONSIBILITY:\n";
echo "   - Each micro-agent has one specific role\n";
echo "   - Focused on doing one thing well\n";
echo "   - No complex state or dependencies\n\n";

echo "2. CONSISTENCY:\n";
echo "   - Low temperature (0.1) by default\n";
echo "   - Deterministic outputs for same inputs\n";
echo "   - Minimal variance in responses\n\n";

echo "3. RELIABILITY:\n";
echo "   - Built-in retry logic\n";
echo "   - Exponential backoff on failures\n";
echo "   - Graceful error handling\n\n";

echo "4. EFFICIENCY:\n";
echo "   - Minimal overhead\n";
echo "   - Fast execution\n";
echo "   - Designed for parallel processing\n\n";

echo "5. COMPOSABILITY:\n";
echo "   - Can be combined into complex systems\n";
echo "   - Building blocks for MAKER framework\n";
echo "   - Independent and loosely coupled\n\n";

echo "=== Use Cases ===\n\n";

echo "MicroAgents are perfect for:\n\n";

echo "  ✓ Task decomposition in multi-step processes\n";
echo "  ✓ Parallel processing of independent subtasks\n";
echo "  ✓ Voting mechanisms in error correction systems\n";
echo "  ✓ Result validation and verification\n";
echo "  ✓ Option discrimination and selection\n";
echo "  ✓ Atomic operations in complex workflows\n\n";

echo "For complex multi-step tasks, see MakerAgent which orchestrates\n";
echo "multiple MicroAgents with voting and error correction.\n\n";

echo "=== Configuration Options ===\n\n";

echo "Available options:\n";
echo "  - role: 'decomposer', 'executor', 'composer', 'validator', 'discriminator'\n";
echo "  - model: Claude model to use (default: claude-sonnet-4-5)\n";
echo "  - max_tokens: Maximum tokens per response (default: 2048)\n";
echo "  - temperature: Sampling temperature (default: 0.1 for consistency)\n";
echo "  - logger: PSR-3 logger for monitoring\n\n";

echo "For more information, see:\n";
echo "  - MakerAgent example: ./maker_example.php\n";
echo "  - MAKER framework: https://arxiv.org/html/2511.09030v1\n";

