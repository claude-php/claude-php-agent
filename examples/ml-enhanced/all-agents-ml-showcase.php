<?php

/**
 * Comprehensive ML-Enhanced Agents Showcase
 *
 * Demonstrates all ML-enhanced agents working together with learned optimization.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../load-env.php';

use ClaudeAgents\Agents\CoordinatorAgent;
use ClaudeAgents\Agents\TreeOfThoughtsAgent;
use ClaudeAgents\Agents\ReflectionAgent;
use ClaudeAgents\Agents\RAGAgent;
use ClaudePhp\ClaudePhp;

// Create client
$client = ClaudePhp::make(apiKey: getenv('ANTHROPIC_API_KEY'));

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   ML-Enhanced Agents Comprehensive Showcase             ║\n";
echo "║   All agents learn and improve automatically!           ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// 1. ML-Enhanced CoordinatorAgent
echo "1. CoordinatorAgent with ML Worker Selection\n";
echo str_repeat("=", 60) . "\n";

$coordinator = new CoordinatorAgent($client, [
    'name' => 'ml_coordinator',
    'enable_ml_selection' => true,
    'ml_history_path' => __DIR__ . '/../../storage/showcase_coordinator.json',
]);

echo "✓ Created with ML-based worker selection\n";
echo "  Learns which workers perform best on which tasks\n\n";

// 2. ML-Enhanced TreeOfThoughtsAgent
echo "2. TreeOfThoughtsAgent with Strategy Learning\n";
echo str_repeat("=", 60) . "\n";

$totAgent = new TreeOfThoughtsAgent($client, [
    'name' => 'ml_tot',
    'enable_ml_optimization' => true,
    'ml_history_path' => __DIR__ . '/../../storage/showcase_tot.json',
    'branch_count' => 3,
    'max_depth' => 3, // Reduced for demo
]);

echo "✓ Created with ML optimization\n";
echo "  Learns optimal: search strategy, branch count, tree depth\n";
echo "  Strategies: best_first, breadth_first, depth_first\n\n";

// 3. ML-Enhanced ReflectionAgent
echo "3. ReflectionAgent with Adaptive Refinement\n";
echo str_repeat("=", 60) . "\n";

$reflectionAgent = new ReflectionAgent($client, [
    'name' => 'ml_reflection',
    'enable_ml_optimization' => true,
    'ml_history_path' => __DIR__ . '/../../storage/showcase_reflection.json',
]);

echo "✓ Created with ML-based adaptive refinement\n";
echo "  Learns optimal refinement count and quality threshold\n";
echo "  Detects diminishing returns automatically\n\n";

// 4. ML-Enhanced RAGAgent
echo "4. RAGAgent with Retrieval Optimization\n";
echo str_repeat("=", 60) . "\n";

$ragAgent = new RAGAgent($client, [
    'name' => 'ml_rag',
    'enable_ml_optimization' => true,
    'ml_history_path' => __DIR__ . '/../../storage/showcase_rag.json',
    'top_k' => 3,
]);

// Add some sample documents
$ragAgent->addDocuments([
    [
        'title' => 'ML Basics',
        'content' => 'Machine learning is a subset of artificial intelligence that enables systems to learn and improve from experience without being explicitly programmed.',
    ],
    [
        'title' => 'k-NN Algorithm',
        'content' => 'k-Nearest Neighbors (k-NN) is a simple, supervised machine learning algorithm that can be used for classification and regression tasks.',
    ],
    [
        'title' => 'Agent Architecture',
        'content' => 'Intelligent agents can be designed with various architectures including reactive, deliberative, and hybrid approaches.',
    ],
]);

echo "✓ Created with ML-based retrieval optimization\n";
echo "  Learns optimal topK (number of sources) per query type\n";
echo "  Added 3 sample documents to knowledge base\n\n";

// Demo each agent
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   Running Demonstrations                                 ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Demo 1: TreeOfThoughtsAgent
echo "Demo 1: TreeOfThoughts with Strategy Learning\n";
echo str_repeat("-", 60) . "\n";
$task1 = "Find three creative ways to reduce energy consumption";
echo "Task: {$task1}\n\n";

try {
    $result1 = $totAgent->run($task1);
    if ($result1->isSuccess()) {
        $metadata = $result1->getMetadata();
        echo "✓ Success!\n";
        echo "  Strategy used: " . ($metadata['strategy'] ?? 'unknown') . "\n";
        echo "  Branch count: " . ($metadata['branch_count'] ?? 'unknown') . "\n";
        echo "  Total nodes explored: " . ($metadata['total_nodes'] ?? 'unknown') . "\n";
        echo "  ML enabled: " . ($metadata['ml_enabled'] ? 'Yes' : 'No') . "\n";
        
        if (method_exists($totAgent, 'getStrategyPerformance')) {
            $stratPerf = $totAgent->getStrategyPerformance();
            if (!empty($stratPerf)) {
                echo "\n  Strategy Performance History:\n";
                foreach ($stratPerf as $strat => $perf) {
                    echo "    {$strat}: {$perf['attempts']} attempts, " .
                         round($perf['success_rate'] * 100, 1) . "% success\n";
                }
            }
        }
    }
} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Demo 2: ReflectionAgent
echo "Demo 2: Reflection with Adaptive Refinement\n";
echo str_repeat("-", 60) . "\n";
$task2 = "Write a haiku about artificial intelligence";
echo "Task: {$task2}\n\n";

try {
    $result2 = $reflectionAgent->run($task2);
    if ($result2->isSuccess()) {
        $metadata = $result2->getMetadata();
        echo "✓ Success!\n";
        echo "  Answer: " . substr($result2->getAnswer(), 0, 100) . "...\n";
        echo "  Refinements: " . count($metadata['reflections'] ?? []) . "\n";
        echo "  Final score: " . ($metadata['final_score'] ?? 'unknown') . "/10\n";
        echo "  ML enabled: " . ($metadata['ml_enabled'] ? 'Yes' : 'No') . "\n";
    }
} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Demo 3: RAGAgent
echo "Demo 3: RAG with Retrieval Optimization\n";
echo str_repeat("-", 60) . "\n";
$task3 = "What is k-NN and how does it work?";
echo "Task: {$task3}\n\n";

try {
    $result3 = $ragAgent->run($task3);
    if ($result3->isSuccess()) {
        $metadata = $result3->getMetadata();
        echo "✓ Success!\n";
        echo "  Answer: " . substr($result3->getAnswer(), 0, 150) . "...\n";
        echo "  Sources retrieved: " . count($metadata['sources'] ?? []) . "\n";
        echo "  TopK used: " . ($metadata['top_k_used'] ?? 'unknown') . "\n";
        echo "  ML enabled: " . ($metadata['ml_enabled'] ? 'Yes' : 'No') . "\n";
    }
} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n\n";

// Show combined learning statistics
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   Learning Statistics Summary                            ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

$agents = [
    'TreeOfThoughts' => $totAgent,
    'Reflection' => $reflectionAgent,
    'RAG' => $ragAgent,
];

foreach ($agents as $name => $agent) {
    if (method_exists($agent, 'getLearningStats')) {
        $stats = $agent->getLearningStats();
        if (isset($stats['learning_enabled']) && $stats['learning_enabled']) {
            echo "{$name} Agent:\n";
            echo "  Total learning records: " . ($stats['total_records'] ?? 0) . "\n";
            echo "  Success rate: " . round(($stats['success_rate'] ?? 0) * 100, 1) . "%\n";
            echo "  Avg quality score: " . round($stats['avg_quality'] ?? 0, 2) . "/10\n\n";
        }
    }
}

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║   Showcase Complete!                                     ║\n";
echo "║                                                          ║\n";
echo "║   All agents are now learning and will improve with     ║\n";
echo "║   each execution. Run this example multiple times to    ║\n";
echo "║   see the learning in action!                           ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";

