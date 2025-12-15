<?php

declare(strict_types=1);

/**
 * Solution Discriminator Agent Example
 *
 * Demonstrates the SolutionDiscriminatorAgent for evaluating and selecting
 * the best solution from multiple candidates using LLM-based voting and scoring.
 *
 * This agent is particularly useful for:
 * - Comparing multiple algorithm implementations
 * - Evaluating different design approaches
 * - Selecting the best solution from generated alternatives
 * - Quality assurance through automated evaluation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\SolutionDiscriminatorAgent;
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

echo "=== Solution Discriminator Agent Demo ===\n\n";
echo "This agent evaluates multiple solutions and selects the best one\n";
echo "based on criteria like correctness, completeness, and quality.\n\n";

// Example 1: Comparing Algorithm Implementations
echo "--- Example 1: Comparing Sorting Algorithm Implementations ---\n\n";

$sortingSolutions = [
    [
        'id' => 'bubble_sort',
        'name' => 'Bubble Sort',
        'description' => 'Simple comparison-based sorting with O(n²) time complexity',
        'code' => <<<'PHP'
function bubbleSort($arr) {
    $n = count($arr);
    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = 0; $j < $n - $i - 1; $j++) {
            if ($arr[$j] > $arr[$j + 1]) {
                $temp = $arr[$j];
                $arr[$j] = $arr[$j + 1];
                $arr[$j + 1] = $temp;
            }
        }
    }
    return $arr;
}
PHP,
        'complexity' => 'O(n²)',
        'pros' => 'Simple, stable, in-place',
        'cons' => 'Very slow for large datasets',
    ],
    [
        'id' => 'quick_sort',
        'name' => 'Quick Sort',
        'description' => 'Divide and conquer sorting with O(n log n) average time',
        'code' => <<<'PHP'
function quickSort($arr) {
    if (count($arr) < 2) return $arr;
    $pivot = $arr[0];
    $left = $right = [];
    for ($i = 1; $i < count($arr); $i++) {
        if ($arr[$i] < $pivot) $left[] = $arr[$i];
        else $right[] = $arr[$i];
    }
    return array_merge(quickSort($left), [$pivot], quickSort($right));
}
PHP,
        'complexity' => 'O(n log n) average',
        'pros' => 'Fast, efficient for large datasets',
        'cons' => 'Not stable, O(n²) worst case',
    ],
    [
        'id' => 'merge_sort',
        'name' => 'Merge Sort',
        'description' => 'Stable divide and conquer with guaranteed O(n log n)',
        'code' => <<<'PHP'
function mergeSort($arr) {
    if (count($arr) <= 1) return $arr;
    $mid = count($arr) / 2;
    $left = mergeSort(array_slice($arr, 0, $mid));
    $right = mergeSort(array_slice($arr, $mid));
    return merge($left, $right);
}
PHP,
        'complexity' => 'O(n log n)',
        'pros' => 'Stable, predictable performance',
        'cons' => 'Requires O(n) extra space',
    ],
];

$discriminator = new SolutionDiscriminatorAgent($client, [
    'name' => 'sorting_evaluator',
    'criteria' => ['efficiency', 'correctness', 'code_quality'],
]);

echo "Evaluating " . count($sortingSolutions) . " sorting algorithms...\n";
$startTime = microtime(true);

$task = json_encode($sortingSolutions);
$result = $discriminator->run($task);

$duration = microtime(true) - $startTime;

if ($result->isSuccess()) {
    echo "✓ Evaluation complete!\n\n";
    echo "Result: {$result->getAnswer()}\n\n";
    
    $metadata = $result->getMetadata();
    
    echo "Detailed Evaluations:\n";
    echo str_repeat("-", 70) . "\n";
    
    foreach ($metadata['evaluations'] as $eval) {
        echo "\n{$eval['solution_id']}:\n";
        echo "  Total Score: " . number_format($eval['total_score'], 3) . "\n";
        echo "  Criteria Scores:\n";
        foreach ($eval['scores'] as $criterion => $score) {
            echo "    - {$criterion}: " . number_format($score, 3) . "\n";
        }
    }
    
    echo "\n" . str_repeat("-", 70) . "\n";
    echo "Best Solution: {$metadata['best_solution']['solution_id']}\n";
    echo "Best Score: " . number_format($metadata['best_solution']['total_score'], 3) . "\n";
    echo "Duration: " . round($duration, 2) . "s\n";
} else {
    echo "✗ Failed: {$result->getError()}\n";
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// Example 2: Evaluating Design Approaches
echo "--- Example 2: Comparing Database Design Approaches ---\n\n";

$databaseDesigns = [
    [
        'id' => 'normalized',
        'approach' => 'Fully Normalized (3NF)',
        'description' => 'Separate tables for users, orders, products, order_items',
        'tables' => 4,
        'pros' => 'No data duplication, easy to maintain, good for writes',
        'cons' => 'Requires joins, complex queries, slower reads',
    ],
    [
        'id' => 'denormalized',
        'approach' => 'Denormalized',
        'description' => 'Single table with all order info including duplicated product data',
        'tables' => 1,
        'pros' => 'Fast reads, simple queries, no joins',
        'cons' => 'Data duplication, harder to update, potential inconsistencies',
    ],
    [
        'id' => 'hybrid',
        'approach' => 'Hybrid Approach',
        'description' => 'Normalized base tables with materialized views for common queries',
        'tables' => 4,
        'pros' => 'Balance of normalization benefits and read performance',
        'cons' => 'More complex, requires view maintenance',
    ],
];

$designEvaluator = new SolutionDiscriminatorAgent($client, [
    'name' => 'design_evaluator',
    'criteria' => ['scalability', 'maintainability', 'performance'],
]);

echo "Evaluating " . count($databaseDesigns) . " database designs...\n";
$startTime = microtime(true);

$evaluations = $designEvaluator->evaluateSolutions(
    $databaseDesigns,
    'E-commerce system with high read volume, moderate writes'
);

$duration = microtime(true) - $startTime;

echo "✓ Evaluation complete!\n\n";

// Sort by total score descending
usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

echo "Rankings:\n";
echo str_repeat("-", 70) . "\n";

foreach ($evaluations as $i => $eval) {
    $rank = $i + 1;
    $medal = match($rank) {
        1 => '🥇',
        2 => '🥈',
        3 => '🥉',
        default => "#{$rank}",
    };
    
    echo "\n{$medal} {$eval['solution_id']} - Score: " . number_format($eval['total_score'], 3) . "\n";
    foreach ($eval['scores'] as $criterion => $score) {
        $bar = str_repeat('█', (int)($score * 20));
        echo "   {$criterion}: {$bar} " . number_format($score, 2) . "\n";
    }
}

echo "\n" . str_repeat("-", 70) . "\n";
echo "Duration: " . round($duration, 2) . "s\n";

echo "\n" . str_repeat("=", 70) . "\n\n";

// Example 3: Code Review - Comparing Refactoring Options
echo "--- Example 3: Comparing Code Refactoring Options ---\n\n";

$refactoringOptions = [
    [
        'id' => 'extract_method',
        'technique' => 'Extract Method',
        'description' => 'Break large method into smaller, focused methods',
        'before_lines' => 150,
        'after_lines' => 45,
        'maintainability' => 'High',
        'testability' => 'Easy',
        'risk' => 'Low',
    ],
    [
        'id' => 'introduce_polymorphism',
        'technique' => 'Replace Conditionals with Polymorphism',
        'description' => 'Use inheritance and polymorphism instead of switch statements',
        'before_lines' => 80,
        'after_lines' => 120,
        'maintainability' => 'Very High',
        'testability' => 'Very Easy',
        'risk' => 'Medium',
    ],
    [
        'id' => 'simplify_conditionals',
        'technique' => 'Simplify Conditionals',
        'description' => 'Simplify complex boolean logic with guard clauses',
        'before_lines' => 80,
        'after_lines' => 65,
        'maintainability' => 'Medium',
        'testability' => 'Moderate',
        'risk' => 'Very Low',
    ],
];

$codeReviewer = new SolutionDiscriminatorAgent($client, [
    'name' => 'code_reviewer',
    'criteria' => ['maintainability', 'testability', 'risk_level'],
]);

echo "Evaluating " . count($refactoringOptions) . " refactoring approaches...\n";
$startTime = microtime(true);

$evaluations = $codeReviewer->evaluateSolutions(
    $refactoringOptions,
    'Legacy codebase with limited test coverage, team has 2 weeks for refactoring'
);

$duration = microtime(true) - $startTime;

echo "✓ Evaluation complete!\n\n";

// Find best
usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
$best = $evaluations[0];

echo "Recommended Approach: {$best['solution_id']}\n";
echo "Overall Score: " . number_format($best['total_score'], 3) . "\n";
echo "\nScore Breakdown:\n";
foreach ($best['scores'] as $criterion => $score) {
    echo "  - " . ucwords(str_replace('_', ' ', $criterion)) . ": ";
    echo number_format($score * 100, 1) . "%\n";
}

echo "\nAll Options Comparison:\n";
foreach ($evaluations as $eval) {
    $stars = str_repeat('⭐', (int)($eval['total_score'] * 5));
    echo "  {$eval['solution_id']}: {$stars} (" . number_format($eval['total_score'], 2) . ")\n";
}

echo "\nDuration: " . round($duration, 2) . "s\n";

echo "\n" . str_repeat("=", 70) . "\n\n";

// Example 4: Custom Criteria Evaluation
echo "--- Example 4: Using Custom Evaluation Criteria ---\n\n";

$apiDesigns = [
    [
        'id' => 'rest',
        'type' => 'REST API',
        'description' => 'Traditional REST with CRUD endpoints',
    ],
    [
        'id' => 'graphql',
        'type' => 'GraphQL API',
        'description' => 'Single endpoint with flexible queries',
    ],
    [
        'id' => 'grpc',
        'type' => 'gRPC',
        'description' => 'Binary protocol with Protocol Buffers',
    ],
];

$customEvaluator = new SolutionDiscriminatorAgent($client, [
    'name' => 'api_evaluator',
    'criteria' => [
        'developer_experience',
        'performance',
        'ecosystem_maturity',
        'mobile_friendliness',
    ],
]);

echo "Evaluating API designs with custom criteria...\n";
$evaluations = $customEvaluator->evaluateSolutions(
    $apiDesigns,
    'Mobile app backend with complex data requirements'
);

echo "✓ Complete!\n\n";

usort($evaluations, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

echo "Results:\n";
foreach ($evaluations as $i => $eval) {
    echo "\n" . ($i + 1) . ". {$eval['solution_id']} (Score: " . 
         number_format($eval['total_score'], 3) . ")\n";
    echo "   Strengths:\n";
    
    $sortedScores = $eval['scores'];
    arsort($sortedScores);
    
    foreach (array_slice($sortedScores, 0, 2) as $criterion => $score) {
        echo "   ✓ " . ucwords(str_replace('_', ' ', $criterion)) . 
             " (" . number_format($score, 2) . ")\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// Summary
echo "=== Solution Discriminator Summary ===\n\n";
echo "The SolutionDiscriminatorAgent helps you:\n\n";
echo "1. OBJECTIVE EVALUATION:\n";
echo "   - Uses LLM to evaluate solutions against specific criteria\n";
echo "   - Provides numerical scores for comparison\n";
echo "   - Reduces human bias in solution selection\n\n";

echo "2. MULTI-CRITERIA ANALYSIS:\n";
echo "   - Define custom evaluation criteria\n";
echo "   - Get per-criterion scores\n";
echo "   - Understand strengths and weaknesses\n\n";

echo "3. AUTOMATED SELECTION:\n";
echo "   - Automatically identifies best solution\n";
echo "   - Provides detailed justification\n";
echo "   - Enables data-driven decisions\n\n";

echo "4. COMMON USE CASES:\n";
echo "   ✓ Algorithm selection and optimization\n";
echo "   ✓ Design pattern evaluation\n";
echo "   ✓ Code review and refactoring decisions\n";
echo "   ✓ Architecture comparison\n";
echo "   ✓ Technology stack selection\n";
echo "   ✓ A/B testing analysis\n\n";

echo "5. INTEGRATION WITH MAKER:\n";
echo "   - Can be used as voting mechanism in MAKER agent\n";
echo "   - Extends MAKER's error correction capabilities\n";
echo "   - Enables sophisticated solution comparison\n\n";

echo "For more information, see:\n";
echo "  - Documentation: docs/SolutionDiscriminatorAgent.md\n";
echo "  - Tutorial: docs/tutorials/SolutionDiscriminatorAgent_Tutorial.md\n\n";

