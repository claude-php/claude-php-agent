<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../load-env.php';

use ClaudeAgents\Agents\DialogAgent;
use ClaudePhp\ClaudePhp;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

loadEnv(__DIR__ . '/../..');

$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    die('ANTHROPIC_API_KEY not found');
}

$client = new ClaudePhp($apiKey);
$logger = new Logger('DialogML');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

echo "=== DialogAgent with ML Optimization ===\n\n";

// Create ML-enhanced DialogAgent
$dialog = new DialogAgent($client, [
    'name' => 'LearningDialog',
    'logger' => $logger,
    'enable_ml_optimization' => true,
    'ml_history_path' => __DIR__ . '/../../storage/dialog_ml_history.json',
    'context_window' => 5,
]);

// Start a conversation session
$session = $dialog->startConversation();
echo "📝 Conversation Session: {$session->getId()}\n\n";

// Multi-turn conversation
$turns = [
    "Hello! I'm interested in learning about PHP.",
    "What makes PHP different from Python?",
    "Can you explain what dependency injection is?",
    "How would I implement that in PHP?",
    "Thanks! Can you summarize what we discussed?",
];

foreach ($turns as $i => $userInput) {
    $turnNum = $i + 1;
    echo "Turn {$turnNum}:\n";
    echo "  User: {$userInput}\n";
    
    $response = $dialog->turn($userInput);
    
    echo "  Agent: " . substr($response, 0, 200) . "...\n";
    echo "\n";
}

echo "\n--- Conversation Complete ---\n";
echo "The DialogAgent learned optimal context window and conversation strategies!\n";

