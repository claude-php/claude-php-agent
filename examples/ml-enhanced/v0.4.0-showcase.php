<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../load-env.php';

use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\ML\ActiveLearning;
use ClaudeAgents\ML\MetaLearning;
use ClaudeAgents\ML\TransferLearning;
use ClaudeAgents\Tools\CalculatorTool;
use ClaudePhp\ClaudePhp;
use Psr\Log\NullLogger;

// .env already loaded by load-env.php
$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    die('ANTHROPIC_API_KEY not found in .env file or environment');
}

$client = new ClaudePhp($apiKey);

$logger = new NullLogger(); // Use NullLogger for simplicity

echo <<<BANNER
╔══════════════════════════════════════════════════════════════════════╗
║                                                                      ║
║            🚀 ML Framework v0.4.0 - Complete Showcase 🚀           ║
║                                                                      ║
║  Transfer Learning  •  Active Learning  •  Meta-Learning           ║
║                                                                      ║
║              100% of ML Opportunities Implemented!                  ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝

BANNER;

echo "\n";

// ==================== Transfer Learning ====================
echo "### 1. Transfer Learning (Bootstrap New Agents) ###\n\n";

$transferLearning = new TransferLearning([
    'source_history_path' => __DIR__ . '/../../storage/react_history.json',
    'target_history_path' => __DIR__ . '/../../storage/new_agent_history.json',
    'client' => $client,
    'logger' => $logger,
]);

echo "Bootstrapping new agent from experienced ReactAgent...\n";

$result = $transferLearning->bootstrap(
    sourceAgentId: 'ReactSolver',
    targetAgentId: 'NewCalculatorAgent',
    options: [
        'min_quality' => 7.0,
        'similarity_threshold' => 0.5,
        'max_samples' => 30,
    ]
);

echo "Bootstrap Results:\n";
echo "  - Transferred: {$result['transferred']} samples\n";
echo "  - Skipped: {$result['skipped']} (already existed)\n";
echo "  - Adapted: {$result['adapted']} (domain adapted)\n";
echo "\n";

echo "Fine-tuning for new domain...\n";
$finetuneResult = $transferLearning->fineTune(
    task: "Calculate the compound interest for $1000 at 5% for 3 years",
    taskAnalysis: ['domain' => 'finance', 'requires_tools' => true]
);

echo "Fine-tune Results:\n";
echo "  - Recommendations: " . count($finetuneResult['recommendations']) . "\n";
echo "  - Confidence: " . round($finetuneResult['confidence'] * 100) . "%\n";
echo "  - Source Count: {$finetuneResult['source_count']}\n";
echo "\n";

echo "Measuring transfer effectiveness...\n";
$effectiveness = $transferLearning->measureTransferEffectiveness('NewCalculatorAgent');
echo "Transfer Effectiveness:\n";
echo "  - Cold-start Quality: {$effectiveness['cold_start_improvement']}/10\n";
echo "  - Quality Improvement: +{$effectiveness['quality_improvement']}\n";
echo "  - Learning Speed: {$effectiveness['learning_speed']}\n";
echo "------------------------------------------------------\n\n";

// ==================== Active Learning ====================
echo "### 2. Active Learning (Intelligent Feedback Requests) ###\n\n";

$activeLearning = new ActiveLearning([
    'client' => $client,
    'logger' => $logger,
    'sampling_strategy' => 'uncertainty', // uncertainty, diversity, error_reduction, committee
    'history_store_path' => __DIR__ . '/../../storage/active_learning_history.json',
]);

echo "Evaluating tasks for human feedback need...\n\n";

$testAgent = new ReactAgent($client, [
    'name' => 'TestAgent',
    'tools' => [CalculatorTool::create()],
]);

$tasks = [
    "What is 2 + 2?",  // Easy - low uncertainty
    "Calculate the derivative of x^3 + 2x^2 - 5",  // Complex - high uncertainty
    "What's the weather like?",  // Ambiguous - high uncertainty
];

foreach ($tasks as $i => $task) {
    echo "Task " . ($i + 1) . ": {$task}\n";
    
    // Simulate agent result with confidence
    $confidence = match($i) {
        0 => 0.95,  // Very confident
        1 => 0.4,   // Uncertain
        2 => 0.3,   // Very uncertain
    };
    
    $result = $testAgent->run($task);
    
    $queryDecision = $activeLearning->shouldQuery($task, $result, [
        'confidence' => $confidence,
    ]);
    
    echo "  Should Query: " . ($queryDecision['should_query'] ? 'YES' : 'NO') . "\n";
    echo "  Reason: {$queryDecision['reason']}\n";
    echo "  Priority: " . round($queryDecision['priority'] * 100) . "%\n";
    echo "  Uncertainty: " . round($queryDecision['uncertainty'] * 100) . "%\n";
    echo "\n";
}

echo "Query Queue (Highest Priority):\n";
$queue = $activeLearning->getQueryQueue(5);
echo "  - Pending Queries: " . count($queue) . "\n";
foreach (array_slice($queue, 0, 3) as $i => $item) {
    echo "  " . ($i + 1) . ". " . substr($item['task'], 0, 50) . "... (Priority: " . round($item['priority'], 2) . ")\n";
}
echo "\n";

// Simulate providing feedback
echo "Recording human feedback for high-priority tasks...\n";
if (!empty($queue)) {
    $highPriorityTask = $queue[0]['task'];
    $activeLearning->recordFeedback(
        task: $highPriorityTask,
        correctAnswer: "Expert-provided answer",
        quality: 9.5,
        metadata: ['reviewer' => 'expert', 'review_time' => 45]
    );
    echo "  ✓ Feedback recorded for: " . substr($highPriorityTask, 0, 50) . "...\n";
}

echo "\nActive Learning Statistics:\n";
$stats = $activeLearning->getStatistics();
echo "  - Total Queries: {$stats['total_queries']}\n";
echo "  - Feedback Received: {$stats['feedback_received']}\n";
echo "  - Pending: {$stats['pending_queries']}\n";
echo "  - Learning Efficiency: {$stats['efficiency']}\n";
echo "------------------------------------------------------\n\n";

// ==================== Meta-Learning ====================
echo "### 3. Meta-Learning (Learn How to Learn) ###\n\n";

$metaLearning = new MetaLearning([
    'client' => $client,
    'logger' => $logger,
    'default_learning_rate' => 0.01,
    'history_store_path' => __DIR__ . '/../../storage/meta_learning_history.json',
]);

echo "Few-shot learning: Adapting to new task with minimal examples...\n\n";

$fewShotExamples = [
    ['task' => 'Translate "hello" to Spanish', 'answer' => 'hola', 'quality' => 9.0],
    ['task' => 'Translate "goodbye" to Spanish', 'answer' => 'adiós', 'quality' => 9.5],
    ['task' => 'Translate "thank you" to Spanish', 'answer' => 'gracias', 'quality' => 9.0],
];

$newTask = 'Translate "good morning" to Spanish';

$adaptation = $metaLearning->fewShotAdapt(
    task: $newTask,
    fewShotExamples: $fewShotExamples
);

echo "Few-shot Adaptation Results:\n";
echo "  - Selected Strategy: {$adaptation['strategy']}\n";
echo "  - Confidence: " . round($adaptation['confidence'] * 100) . "%\n";
echo "  - Few-shot Count: {$adaptation['few_shot_count']}\n";
echo "  - Meta Features:\n";
foreach ($adaptation['meta_features'] as $key => $value) {
    echo "    • {$key}: " . (is_numeric($value) ? round($value, 2) : $value) . "\n";
}
echo "\n";

echo "Optimizing learning rate based on performance...\n";
$recentPerformance = [
    ['quality' => 6.5],
    ['quality' => 7.0],
    ['quality' => 7.8],
    ['quality' => 8.2],
    ['quality' => 8.5],
];

$optimizedLR = $metaLearning->optimizeLearningRate($recentPerformance);
echo "  - Optimized Learning Rate: {$optimizedLR}\n";
echo "\n";

echo "Selecting best learning algorithm for task type...\n";
$taskCharacteristics = [
    'complexity' => 0.6,
    'requires_reasoning' => 1,
    'domain' => 'translation',
    'sample_count' => 3,
];

$bestAlgorithm = $metaLearning->selectAlgorithm($taskCharacteristics);
echo "  - Best Algorithm: {$bestAlgorithm}\n";
echo "\n";

// Update meta-model with experience
echo "Updating meta-learning model with new experience...\n";
$metaLearning->updateMetaModel(
    strategy: 'metric_based',
    success: true,
    samplesUsed: 3,
    qualityAchieved: 8.5
);

echo "\nMeta-Learning Statistics:\n";
$metaStats = $metaLearning->getStatistics();
echo "  - Learning Efficiency: {$metaStats['learning_efficiency']}\n";
echo "  - Best Strategy: {$metaStats['best_strategy']}\n";
echo "  - Total Meta Experiences: {$metaStats['total_meta_experiences']}\n";
echo "  - Strategy Performance:\n";
foreach ($metaStats['strategies'] as $strategy => $metrics) {
    echo "    • {$strategy}:\n";
    echo "      - Success Rate: " . round($metrics['success_rate'] * 100) . "%\n";
    echo "      - Sample Efficiency: " . round($metrics['sample_efficiency'], 3) . "\n";
    echo "      - Used Count: {$metrics['used_count']}\n";
}
echo "------------------------------------------------------\n\n";

// ==================== Summary ====================
echo <<<SUMMARY
╔══════════════════════════════════════════════════════════════════════╗
║                                                                      ║
║                    🎉 Showcase Complete! 🎉                        ║
║                                                                      ║
║  ✅ Transfer Learning - Bootstrap & knowledge sharing               ║
║  ✅ Active Learning - Intelligent feedback requests                 ║
║  ✅ Meta-Learning - Learn how to learn effectively                  ║
║                                                                      ║
║  🎯 ML Framework: 100% COMPLETE (17/17 opportunities)              ║
║                                                                      ║
║  All agents can now:                                                ║
║  • Learn from minimal examples (few-shot)                           ║
║  • Bootstrap from experienced agents (transfer)                     ║
║  • Request feedback strategically (active)                          ║
║  • Optimize their own learning (meta)                               ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝

SUMMARY;

echo "\n";

