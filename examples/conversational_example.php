<?php

/**
 * Conversational Agents Example
 * 
 * Demonstrates DialogAgent, ConversationManager, and IntentClassifierAgent.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Conversation\ConversationManager;
use ClaudeAgents\Agents\IntentClassifierAgent;
use ClaudePhp\ClaudePhp;

if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    $_ENV['ANTHROPIC_API_KEY'] = $env['ANTHROPIC_API_KEY'] ?? '';
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
if (empty($apiKey)) {
    die("Error: ANTHROPIC_API_KEY not set\n");
}

$client = new ClaudePhp($apiKey);

echo "Conversational Agents Demo\n";
echo "==========================\n\n";

// 1. Dialog Agent (Multi-Turn Conversation)
echo "1. Dialog Agent\n";
echo "---------------\n";

$dialog = new DialogAgent($client);
$session = $dialog->startConversation();

echo "Starting conversation (Session: {$session->getId()})\n\n";

$turns = [
    "Hi, I need help with my order",
    "It's order #12345",
    "I want to change the shipping address",
    "Thank you for your help!",
];

foreach ($turns as $i => $userInput) {
    $response = $dialog->turn($userInput);
    echo "Turn " . ($i + 1) . ":\n";
    echo "User: {$userInput}\n";
    echo "Agent: {$response}\n\n";
}

// 2. Conversation Manager (Multi-Session)
echo "2. Conversation Manager\n";
echo "-----------------------\n";

$convManager = new ConversationManager([
    'max_sessions' => 100,
    'session_timeout' => 3600,
]);

$session1 = $convManager->createSession('user_123');
$session2 = $convManager->createSession('user_456');

echo "Created session for user_123: {$session1->getId()}\n";
echo "Created session for user_456: {$session2->getId()}\n";

$userSessions = $convManager->getSessionsByUser('user_123');
echo "Sessions for user_123: " . count($userSessions) . "\n\n";

// 3. Intent Classifier Agent (Rasa-style)
echo "3. Intent Classifier Agent\n";
echo "--------------------------\n";

$classifier = new IntentClassifierAgent($client);

// Add intents
$classifier->addIntent('book_flight', ['I want to book a flight', 'Book me a ticket']);
$classifier->addIntent('check_status', ['Where is my order', 'Track my package']);
$classifier->addIntent('cancel_order', ['Cancel my order', 'I want a refund']);

$inputs = [
    "I'd like to book a flight to Paris",
    "Where is my package #789?",
    "Please cancel order #456",
];

foreach ($inputs as $input) {
    $result = $classifier->run($input);
    $classification = $result->getMetadata();
    
    echo "Input: {$input}\n";
    echo "Intent: {$classification['intent']} (confidence: {$classification['confidence']})\n";
    if (!empty($classification['entities'])) {
        echo "Entities: " . json_encode($classification['entities']) . "\n";
    }
    echo "\n";
}

echo "Conversational Demo Complete!\n";

