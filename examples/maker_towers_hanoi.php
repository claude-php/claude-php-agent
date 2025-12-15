<?php

declare(strict_types=1);

/**
 * MAKER Agent: Towers of Hanoi Benchmark
 *
 * This example demonstrates the MAKER framework on the Towers of Hanoi problem,
 * the benchmark used in the paper "Solving a Million-Step LLM Task with Zero Errors".
 *
 * The N-disk Towers of Hanoi problem requires 2^N - 1 moves to solve.
 * For 20 disks, that's 1,048,575 moves - over one million steps!
 *
 * The paper showed that:
 * - Standard LLMs fail after 100-300 steps
 * - MAKER solved the 20-disk problem with ZERO errors
 *
 * This example demonstrates the approach on smaller instances.
 *
 * Reference: https://arxiv.org/html/2511.09030v1
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

echo "=== MAKER Agent: Towers of Hanoi Benchmark ===\n\n";
echo "This demonstrates MAKER on the benchmark from the paper.\n";
echo "Towers of Hanoi requires 2^N - 1 moves for N disks.\n\n";

/**
 * Validate a Towers of Hanoi solution.
 */
function validateTowersOfHanoiSolution(int $disks, string $solution): array
{
    // Parse moves from solution
    preg_match_all('/move.*?(\d+).*?from.*?([ABC]).*?to.*?([ABC])/i', $solution, $matches, PREG_SET_ORDER);
    
    $moves = [];
    foreach ($matches as $match) {
        $moves[] = [
            'disk' => (int)$match[1],
            'from' => strtoupper($match[2]),
            'to' => strtoupper($match[3]),
        ];
    }
    
    // Initialize pegs
    $pegs = [
        'A' => range($disks, 1),  // All disks start on peg A
        'B' => [],
        'C' => [],
    ];
    
    $errors = [];
    $moveCount = 0;
    
    foreach ($moves as $i => $move) {
        $moveCount++;
        $disk = $move['disk'];
        $from = $move['from'];
        $to = $move['to'];
        
        // Validate move
        if (empty($pegs[$from])) {
            $errors[] = "Move {$moveCount}: Cannot move from empty peg {$from}";
            break;
        }
        
        $topDisk = array_pop($pegs[$from]);
        
        if ($topDisk !== $disk) {
            $errors[] = "Move {$moveCount}: Expected disk {$topDisk} on {$from}, tried to move disk {$disk}";
            $pegs[$from][] = $topDisk;  // Put it back
            break;
        }
        
        if (!empty($pegs[$to]) && end($pegs[$to]) < $disk) {
            $errors[] = "Move {$moveCount}: Cannot place disk {$disk} on smaller disk " . end($pegs[$to]);
            $pegs[$from][] = $topDisk;  // Put it back
            break;
        }
        
        $pegs[$to][] = $disk;
    }
    
    // Check if solved
    $solved = empty($pegs['A']) && empty($pegs['B']) && count($pegs['C']) === $disks;
    $expectedMoves = pow(2, $disks) - 1;
    
    return [
        'valid' => empty($errors) && $solved,
        'solved' => $solved,
        'moves' => $moveCount,
        'expected_moves' => $expectedMoves,
        'errors' => $errors,
        'final_state' => $pegs,
    ];
}

// Example 1: 3-Disk Problem (7 moves)
echo "--- Example 1: 3-Disk Towers of Hanoi (7 moves required) ---\n\n";

$task3Disk = <<<TASK
Solve the Towers of Hanoi puzzle with 3 disks.

Rules:
- There are 3 pegs (A, B, C)
- All disks start on peg A, smallest on top
- Goal: Move all disks to peg C
- Only one disk can be moved at a time
- A larger disk cannot be placed on a smaller disk

Provide the complete sequence of moves in this format:
Move disk X from peg Y to peg Z

Start with the initial state and provide all moves.
TASK;

$maker3 = new MakerAgent($client, [
    'name' => 'hanoi_3disk',
    'voting_k' => 2,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 4,
]);

echo "Solving 3-disk problem...\n";
$startTime = microtime(true);
$result = $maker3->run($task3Disk);
$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    echo "✓ MAKER completed!\n\n";
    echo "Solution:\n";
    echo str_repeat("-", 50) . "\n";
    echo $result->getAnswer() . "\n";
    echo str_repeat("-", 50) . "\n\n";
    
    // Validate solution
    $validation = validateTowersOfHanoiSolution(3, $result->getAnswer());
    
    echo "Validation:\n";
    if ($validation['valid']) {
        echo "  ✓ Solution is CORRECT!\n";
    } else {
        echo "  ✗ Solution has errors:\n";
        foreach ($validation['errors'] as $error) {
            echo "    - {$error}\n";
        }
    }
    echo "  Moves made: {$validation['moves']}\n";
    echo "  Expected moves: {$validation['expected_moves']}\n";
    echo "  Solved: " . ($validation['solved'] ? "Yes" : "No") . "\n\n";
    
    $stats = $result->getMetadata()['execution_stats'] ?? [];
    echo "MAKER Statistics:\n";
    echo "  Total Steps: {$stats['total_steps']}\n";
    echo "  Decompositions: {$stats['decompositions']}\n";
    echo "  Votes Cast: {$stats['votes_cast']}\n";
    echo "  Red Flags: {$stats['red_flags_detected']}\n";
    echo "  Duration: " . round($duration, 2) . "s\n";
} else {
    echo "✗ Failed: {$result->getError()}\n";
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// Example 2: 4-Disk Problem (15 moves)
echo "--- Example 2: 4-Disk Towers of Hanoi (15 moves required) ---\n\n";

$task4Disk = <<<TASK
Solve the Towers of Hanoi puzzle with 4 disks.

Rules:
- There are 3 pegs (A, B, C)
- All disks start on peg A (disk 1 is smallest, disk 4 is largest)
- Goal: Move all disks to peg C
- Only one disk can be moved at a time
- A larger disk cannot be placed on a smaller disk

Provide the complete sequence of moves. Each move should be:
"Move disk X from peg Y to peg Z"

Think carefully about the optimal sequence.
TASK;

$maker4 = new MakerAgent($client, [
    'name' => 'hanoi_4disk',
    'voting_k' => 3,  // More stringent for longer sequence
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 5,
]);

echo "Solving 4-disk problem...\n";
$startTime = microtime(true);
$result = $maker4->run($task4Disk);
$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    echo "✓ MAKER completed!\n\n";
    
    // Validate solution
    $validation = validateTowersOfHanoiSolution(4, $result->getAnswer());
    
    echo "Validation Results:\n";
    if ($validation['valid']) {
        echo "  ✓✓✓ Solution is CORRECT! ✓✓✓\n";
        echo "  Successfully moved all 4 disks!\n";
    } else {
        echo "  ✗ Solution has errors:\n";
        foreach ($validation['errors'] as $error) {
            echo "    - {$error}\n";
        }
    }
    echo "  Moves made: {$validation['moves']}\n";
    echo "  Expected moves: {$validation['expected_moves']}\n";
    
    if ($validation['moves'] === $validation['expected_moves'] && $validation['valid']) {
        echo "  ✓ Optimal solution!\n";
    }
    echo "\n";
    
    $stats = $result->getMetadata()['execution_stats'] ?? [];
    echo "MAKER Statistics:\n";
    echo "  Total Steps: {$stats['total_steps']}\n";
    echo "  Atomic Executions: {$stats['atomic_executions']}\n";
    echo "  Votes Cast: {$stats['votes_cast']}\n";
    echo "  Red Flags Detected: {$stats['red_flags_detected']}\n";
    echo "  Estimated Error Rate: " . ($result->getMetadata()['error_rate'] ?? 'N/A') . "\n";
    echo "  Duration: " . round($duration, 2) . "s\n\n";
    
    // Show first few moves
    preg_match_all('/(Move.*?)(?=Move|$)/s', $result->getAnswer(), $moveMatches);
    $moves = array_slice($moveMatches[1], 0, 5);
    echo "First 5 moves:\n";
    foreach ($moves as $i => $move) {
        echo "  " . ($i + 1) . ". " . trim($move) . "\n";
    }
    if ($validation['moves'] > 5) {
        echo "  ... (" . ($validation['moves'] - 5) . " more moves)\n";
    }
} else {
    echo "✗ Failed: {$result->getError()}\n";
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// Paper Comparison
echo "=== Comparison with Paper Results ===\n\n";

echo "Paper Findings (from arXiv:2511.09030v1):\n\n";

echo "Standard LLMs (without MAKER):\n";
echo "  - GPT-4: ~100-200 consecutive error-free steps\n";
echo "  - Claude Sonnet: ~150-300 consecutive error-free steps\n";
echo "  - Error rate: ~0.5-1% per step\n";
echo "  - Result: FAIL on 20-disk problem (1M+ steps)\n\n";

echo "MAKER Framework:\n";
echo "  - 20-disk problem: 1,048,575 moves\n";
echo "  - Result: ZERO ERRORS\n";
echo "  - Used: Non-reasoning models suffice\n";
echo "  - Key: Decomposition + Voting + Red-flagging\n\n";

echo "Scaling Properties:\n";
echo "  - Probability of success with k votes: Θ(ln(s)) where s = steps\n";
echo "  - Cost scales sub-linearly with proper decomposition\n";
echo "  - Decorrelated errors crucial for voting effectiveness\n\n";

echo "Key Insight:\n";
echo "  Instead of relying on ever-more-intelligent base LLMs,\n";
echo "  MAKER uses MDAP (Massively Decomposed Agentic Processes)\n";
echo "  to achieve organization-level task execution reliability.\n\n";

echo "This PHP implementation demonstrates the same principles\n";
echo "on smaller problem instances.\n\n";

echo "To scale to larger problems (5+ disks), consider:\n";
echo "  1. Implementing persistent state management\n";
echo "  2. Caching intermediate results\n";
echo "  3. Parallel voting execution\n";
echo "  4. Specialized micro-agents for Hanoi moves\n";
echo "  5. Adaptive voting K based on confidence\n\n";

echo "For the full million-step solution, see the paper:\n";
echo "https://arxiv.org/html/2511.09030v1\n";

