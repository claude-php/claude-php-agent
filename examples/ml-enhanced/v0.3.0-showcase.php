<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../load-env.php';

use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Agents\MakerAgent;
use ClaudeAgents\Agents\ReactAgent;
use ClaudeAgents\Debate\DebateAgent;
use ClaudeAgents\Debate\DebateSystem;
use ClaudeAgents\ML\EnsembleLearning;
use ClaudeAgents\ML\PromptOptimizer;
use ClaudeAgents\Tools\CalculatorTool;
use ClaudePhp\ClaudePhp;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

loadEnv(__DIR__ . '/../..'); // Load from project root

$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    die('ANTHROPIC_API_KEY not found in .env file or environment');
}

$client = new ClaudePhp($apiKey);

$logger = new Logger('MLV030Showcase');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

echo <<<BANNER
╔══════════════════════════════════════════════════════════════════╗
║                                                                  ║
║               🚀 ML-Enhanced Agents v0.3.0 Showcase 🚀         ║
║                                                                  ║
║  DialogAgent  •  DebateSystem  •  MakerAgent                   ║
║  PromptOptimizer  •  EnsembleLearning                          ║
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝

BANNER;

echo "\n";

// ==================== DialogAgent with ML ====================
echo "### 1. DialogAgent (ML for Context & Strategy Learning) ###\n\n";

$dialogAgent = new DialogAgent($client, [
    'name' => 'SmartDialog',
    'logger' => $logger,
    'enable_ml_optimization' => true,
    'ml_history_path' => __DIR__ . '/../../storage/dialog_ml_history.json',
    'context_window' => 5,
]);

$session = $dialogAgent->startConversation();
echo "Session started: {$session->getId()}\n\n";

$dialogTurns = [
    "Hi! Can you help me understand machine learning?",
    "What's the difference between supervised and unsupervised learning?",
    "Give me an example of supervised learning.",
    "How does reinforcement learning fit in?",
];

foreach ($dialogTurns as $turn) {
    echo "User: {$turn}\n";
    $response = $dialogAgent->turn($turn);
    echo "Agent: " . substr($response, 0, 150) . "...\n\n";
}

echo "Dialog completed. Context window and strategy learned from history.\n";
echo "------------------------------------------------------\n\n";

// ==================== DebateSystem with ML ====================
echo "### 2. DebateSystem (ML for Optimal Rounds & Consensus) ###\n\n";

$debateSystem = new DebateSystem($client, [
    'logger' => $logger,
    'enable_ml_optimization' => true,
    'ml_history_path' => __DIR__ . '/../../storage/debate_ml_history.json',
    'rounds' => 3,
    'enable_early_stopping' => true,
]);

// Create debate agents
$proAgent = new DebateAgent($client, [
    'name' => 'Optimist',
    'stance' => 'You are optimistic about AI progress and benefits.',
]);

$conAgent = new DebateAgent($client, [
    'name' => 'Realist',
    'stance' => 'You are realistic about AI challenges and risks.',
]);

$neutralAgent = new DebateAgent($client, [
    'name' => 'Analyst',
    'stance' => 'You provide balanced technical analysis.',
]);

$debateSystem
    ->addAgent('pro', $proAgent)
    ->addAgent('con', $conAgent)
    ->addAgent('neutral', $neutralAgent);

$debateTopic = "Will artificial general intelligence (AGI) be achieved by 2030?";
echo "Debate Topic: {$debateTopic}\n\n";

$debateResult = $debateSystem->debate($debateTopic);

echo "Debate Result:\n";
echo "- Rounds: " . count($debateResult->getRounds()) . "\n";
echo "- Agreement Score: " . round($debateResult->getAgreementScore() * 100) . "%\n";
echo "- Synthesis: " . substr($debateResult->getSynthesis(), 0, 200) . "...\n";
echo "------------------------------------------------------\n\n";

// ==================== MakerAgent with ML ====================
echo "### 3. MakerAgent (ML for Voting-K & Decomposition Optimization) ###\n\n";

$makerAgent = new MakerAgent($client, [
    'name' => 'OptimizedMAKER',
    'logger' => $logger,
    'enable_ml_optimization' => true,
    'ml_history_path' => __DIR__ . '/../../storage/maker_ml_history.json',
    'voting_k' => 3,
    'enable_red_flagging' => true,
    'max_decomposition_depth' => 8,
]);

$makerTasks = [
    "Calculate the sum of all prime numbers between 10 and 30.",
    "Solve the Tower of Hanoi puzzle for 3 disks and list all moves.",
];

foreach ($makerTasks as $task) {
    echo "\nMAKER Task: {$task}\n";
    $result = $makerAgent->run($task);

    if ($result->isSuccess()) {
        echo "Answer: {$result->getAnswer()}\n";
        echo "Execution Stats:\n";
        $stats = $result->getMetadata()['execution_stats'];
        echo "  - Total Steps: {$stats['total_steps']}\n";
        echo "  - Atomic Executions: {$stats['atomic_executions']}\n";
        echo "  - Decompositions: {$stats['decompositions']}\n";
        echo "  - Votes Cast: {$stats['votes_cast']}\n";
        echo "  - Error Rate: " . ($result->getMetadata()['error_rate'] * 100) . "%\n";
        echo "  - Learned voting_k: {$result->getMetadata()['voting_k']}\n";
    } else {
        echo "Error: {$result->getError()}\n";
    }
    echo "------------------------------------------------------\n";
}

// ==================== PromptOptimizer ====================
echo "\n### 4. PromptOptimizer (ML for Prompt Improvement) ###\n\n";

$promptOptimizer = new PromptOptimizer($client, [
    'logger' => $logger,
    'history_store_path' => __DIR__ . '/../../storage/prompt_optimizer_history.json',
]);

$originalPrompt = "Explain machine learning.";
$taskContext = "Educational content for beginners, should be clear and concise.";

echo "Original Prompt: {$originalPrompt}\n";
echo "Task Context: {$taskContext}\n\n";

// First, let's record some historical performance (simulated)
echo "Recording historical prompt performance...\n";
$promptOptimizer->recordPerformance(
    prompt: "Explain machine learning in simple terms with examples.",
    taskContext: $taskContext,
    qualityScore: 8.5,
    tokenUsage: 450,
    success: true,
    duration: 2.3
);

$promptOptimizer->recordPerformance(
    prompt: "Describe what machine learning is and how it works.",
    taskContext: $taskContext,
    qualityScore: 7.2,
    tokenUsage: 380,
    success: true,
    duration: 1.8
);

// Now optimize
echo "Optimizing prompt based on history...\n";
$optimization = $promptOptimizer->optimize($originalPrompt, $taskContext);

echo "Optimized Prompt: {$optimization['optimized_prompt']}\n";
echo "Confidence: " . round($optimization['confidence'] * 100) . "%\n";
echo "Improvements:\n";
foreach ($optimization['improvements'] as $i => $improvement) {
    echo "  " . ($i + 1) . ". {$improvement}\n";
}
echo "------------------------------------------------------\n\n";

// ==================== EnsembleLearning ====================
echo "### 5. EnsembleLearning (Multiple Strategies) ###\n\n";

$ensemble = new EnsembleLearning([
    'logger' => $logger,
    'client' => $client,
    'history_store_path' => __DIR__ . '/../../storage/ensemble_history.json',
]);

// Create diverse agents for ensemble
$agents = [
    'react' => new ReactAgent($client, [
        'name' => 'ReactSolver',
        'tools' => [CalculatorTool::create()],
    ]),
    'quick' => new ReactAgent($client, [
        'name' => 'QuickAgent',
        'max_iterations' => 3,
    ]),
    'thorough' => new ReactAgent($client, [
        'name' => 'ThoroughAgent',
        'max_iterations' => 10,
    ]),
];

$ensembleTask = "What is 123 * 456 + 789?";

// Try different ensemble strategies
$strategies = ['voting', 'weighted_voting', 'best_of_n'];

foreach ($strategies as $strategy) {
    echo "\nEnsemble Strategy: {$strategy}\n";
    echo "Task: {$ensembleTask}\n";
    
    $result = $ensemble->combine($ensembleTask, $agents, ['strategy' => $strategy]);
    
    if ($result->isSuccess()) {
        echo "Answer: {$result->getAnswer()}\n";
        $metadata = $result->getMetadata();
        echo "Confidence: " . ($metadata['confidence'] ?? 'N/A') . "\n";
        echo "Selected: " . ($metadata['selected_agent'] ?? $metadata['voting_agents'][0] ?? 'N/A') . "\n";
    } else {
        echo "Error: {$result->getError()}\n";
    }
    echo "---\n";
}

echo "------------------------------------------------------\n\n";

// ==================== Summary ====================
echo <<<SUMMARY
╔══════════════════════════════════════════════════════════════════╗
║                                                                  ║
║                    🎉 Showcase Complete! 🎉                    ║
║                                                                  ║
║  ✅ DialogAgent - Context & strategy learning                  ║
║  ✅ DebateSystem - Optimal rounds & early stopping             ║
║  ✅ MakerAgent - Voting-K & decomposition optimization          ║
║  ✅ PromptOptimizer - Historical prompt improvement             ║
║  ✅ EnsembleLearning - Multiple voting strategies               ║
║                                                                  ║
║  All agents learn and improve from historical performance!     ║
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝

SUMMARY;

echo "\n";

