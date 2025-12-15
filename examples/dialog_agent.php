#!/usr/bin/env php
<?php
/**
 * Dialog Agent Basic Example
 *
 * Demonstrates basic usage of the DialogAgent for multi-turn conversations
 * with context awareness and session management.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Conversation\Session;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Simple console logger
class ConsoleLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        $emoji = match ($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📝',
        };
        echo "[{$timestamp}] {$emoji} [{$level}] {$message}\n";
    }
}

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

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                        Dialog Agent Basic Example                         ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create dialog agent with logger
$logger = new ConsoleLogger();
$dialogAgent = new DialogAgent($client, [
    'name' => 'demo_dialog_agent',
    'logger' => $logger,
]);

echo "🤖 Dialog Agent initialized\n\n";

// Example 1: Simple Multi-Turn Conversation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Simple Multi-Turn Conversation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$session1 = $dialogAgent->startConversation();
echo "📝 Session started: {$session1->getId()}\n\n";

$conversation1 = [
    "Hi! My name is Alice and I'm a software developer.",
    "What programming languages should I learn?",
    "I'm particularly interested in web development.",
    "Thanks for the advice!",
];

foreach ($conversation1 as $i => $userInput) {
    echo "👤 User: {$userInput}\n";
    $response = $dialogAgent->turn($userInput, $session1->getId());
    echo "🤖 Agent: {$response}\n\n";
    
    if ($i < count($conversation1) - 1) {
        sleep(1); // Brief pause between turns
    }
}

echo "📊 Session info:\n";
echo "   - Turn count: {$session1->getTurnCount()}\n";
echo "   - Session ID: {$session1->getId()}\n\n";

sleep(1);

// Example 2: Conversation with State Management
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Conversation with State Management\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$session2 = $dialogAgent->startConversation();
echo "📝 Session started: {$session2->getId()}\n\n";

// Set some initial state
$session2->updateState('user_name', 'Bob');
$session2->updateState('user_preference', 'detailed_explanations');
$session2->updateState('topic_interest', 'artificial_intelligence');

echo "📌 Session state initialized:\n";
foreach ($session2->getState() as $key => $value) {
    echo "   - {$key}: {$value}\n";
}
echo "\n";

$conversation2 = [
    "Can you explain what machine learning is?",
    "How does it differ from traditional programming?",
    "What are some practical applications?",
];

foreach ($conversation2 as $i => $userInput) {
    echo "👤 User: {$userInput}\n";
    $response = $dialogAgent->turn($userInput, $session2->getId());
    echo "🤖 Agent: {$response}\n\n";
    
    if ($i < count($conversation2) - 1) {
        sleep(1);
    }
}

sleep(1);

// Example 3: Multiple Concurrent Sessions
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Multiple Concurrent Sessions\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$techSession = $dialogAgent->startConversation('tech_support');
$salesSession = $dialogAgent->startConversation('sales_inquiry');

echo "📝 Tech Support Session: {$techSession->getId()}\n";
echo "📝 Sales Session: {$salesSession->getId()}\n\n";

// Interleaved conversations
echo "💬 Tech Support Conversation:\n";
echo "👤 User: My application is running slow.\n";
$techResponse1 = $dialogAgent->turn('My application is running slow.', $techSession->getId());
echo "🤖 Agent: {$techResponse1}\n\n";

sleep(1);

echo "💬 Sales Conversation:\n";
echo "👤 User: What pricing plans do you offer?\n";
$salesResponse1 = $dialogAgent->turn('What pricing plans do you offer?', $salesSession->getId());
echo "🤖 Agent: {$salesResponse1}\n\n";

sleep(1);

echo "💬 Tech Support Conversation (continued):\n";
echo "👤 User: I've tried restarting but it's still slow.\n";
$techResponse2 = $dialogAgent->turn("I've tried restarting but it's still slow.", $techSession->getId());
echo "🤖 Agent: {$techResponse2}\n\n";

echo "✅ Both sessions maintained separate contexts!\n\n";

sleep(1);

// Example 4: Using the run() Method
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Single-Turn Execution with run()\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$result = $dialogAgent->run('What is the capital of France?');

if ($result->isSuccess()) {
    echo "✅ Task completed successfully!\n";
    echo "📝 Answer: {$result->getAnswer()}\n";
    echo "📊 Iterations: {$result->getIterations()}\n";
    
    $metadata = $result->getMetadata();
    echo "📋 Metadata:\n";
    echo "   - Session ID: {$metadata['session_id']}\n";
}

echo "\n";

// Example 5: Retrieving Session Information
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Session Information and Turn History\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$retrievedSession = $dialogAgent->getSession($session1->getId());

if ($retrievedSession) {
    echo "📋 Session Details:\n";
    echo "   - ID: {$retrievedSession->getId()}\n";
    echo "   - Turn Count: {$retrievedSession->getTurnCount()}\n";
    echo "   - Created: " . date('Y-m-d H:i:s', (int)$retrievedSession->getCreatedAt()) . "\n";
    echo "   - Last Activity: " . date('Y-m-d H:i:s', (int)$retrievedSession->getLastActivity()) . "\n\n";
    
    echo "📜 Turn History:\n";
    foreach ($retrievedSession->getTurns() as $i => $turn) {
        echo "   Turn " . ($i + 1) . ":\n";
        echo "      User: " . substr($turn->getUserInput(), 0, 50) . "...\n";
        echo "      Agent: " . substr($turn->getAgentResponse(), 0, 50) . "...\n";
        echo "      Time: " . date('H:i:s', (int)$turn->getTimestamp()) . "\n\n";
    }
}

// Summary
echo str_repeat("═", 80) . "\n";
echo "✨ Dialog Agent example completed!\n\n";
echo "📊 Summary:\n";
echo "   - Created multiple conversation sessions\n";
echo "   - Demonstrated context awareness across turns\n";
echo "   - Showed state management capabilities\n";
echo "   - Handled concurrent sessions independently\n";
echo "   - Used both turn() and run() methods\n";
echo str_repeat("═", 80) . "\n";

