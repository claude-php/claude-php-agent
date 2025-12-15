#!/usr/bin/env php
<?php
/**
 * Intent Classifier Agent Basic Example
 *
 * Demonstrates basic usage of the IntentClassifierAgent for intent classification
 * and entity extraction in conversational AI applications.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\IntentClassifierAgent;
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
echo "║                  Intent Classifier Agent Basic Example                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create intent classifier with logger
$logger = new ConsoleLogger();
$classifier = new IntentClassifierAgent($client, [
    'name' => 'demo_classifier',
    'logger' => $logger,
    'confidence_threshold' => 0.6,
]);

echo "🤖 Intent Classifier initialized\n\n";

// Example 1: Basic Intent Classification
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Basic Intent Classification\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Define intents
$classifier->addIntent('greeting', [
    'Hello',
    'Hi there',
    'Good morning',
    'Hey',
], 'User wants to greet or start a conversation');

$classifier->addIntent('goodbye', [
    'Bye',
    'Goodbye',
    'See you later',
    'Have a good day',
], 'User wants to end the conversation');

$classifier->addIntent('help', [
    'I need help',
    'Can you help me',
    'Help please',
], 'User needs assistance');

$testInputs = [
    'Hello! How are you today?',
    'Goodbye, thanks for your help!',
    'I need some help with my account',
];

foreach ($testInputs as $input) {
    echo "💬 User input: \"{$input}\"\n";
    $result = $classifier->run($input);
    
    if ($result->isSuccess()) {
        $data = $result->getMetadata();
        echo "   🎯 Intent: {$data['intent']}\n";
        echo "   📊 Confidence: " . number_format($data['confidence'] * 100, 1) . "%\n";
        
        if (!empty($data['entities'])) {
            echo "   📦 Entities:\n";
            foreach ($data['entities'] as $entity) {
                echo "      - {$entity['type']}: {$entity['value']}\n";
            }
        }
    } else {
        echo "   ❌ Error: {$result->getError()}\n";
    }
    echo "\n";
}

sleep(1);

// Example 2: Entity Extraction
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Intent Classification with Entity Extraction\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$classifier->addIntent('book_flight', [
    'I want to book a flight',
    'Book me a flight to Paris',
    'I need to fly to London tomorrow',
], 'User wants to book a flight');

$classifier->addIntent('check_weather', [
    'What\'s the weather like',
    'Will it rain today',
    'Weather forecast for tomorrow',
], 'User wants to check weather information');

// Define custom entity types
$classifier->addEntityType('destination', 'City or country name');
$classifier->addEntityType('date', 'Travel date or time period');
$classifier->addEntityType('location', 'Geographic location');

$testInputs2 = [
    'I want to book a flight to Tokyo next Monday',
    'What\'s the weather like in New York today?',
    'Book me a ticket to London for December 25th',
];

foreach ($testInputs2 as $input) {
    echo "💬 User input: \"{$input}\"\n";
    $result = $classifier->run($input);
    
    if ($result->isSuccess()) {
        $data = $result->getMetadata();
        echo "   🎯 Intent: {$data['intent']}\n";
        echo "   📊 Confidence: " . number_format($data['confidence'] * 100, 1) . "%\n";
        
        if (!empty($data['entities'])) {
            echo "   📦 Entities extracted:\n";
            foreach ($data['entities'] as $entity) {
                echo "      - {$entity['type']}: \"{$entity['value']}\"\n";
            }
        } else {
            echo "   📦 No entities found\n";
        }
    }
    echo "\n";
}

sleep(1);

// Example 3: Chatbot Intent Recognition
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Customer Support Chatbot Intents\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create a new classifier for customer support
$supportClassifier = new IntentClassifierAgent($client, [
    'name' => 'support_classifier',
    'confidence_threshold' => 0.65,
    'fallback_intent' => 'need_human_agent',
]);

// Define support intents
$supportClassifier->addIntent('account_issue', [
    'I can\'t log in',
    'My account is locked',
    'I forgot my password',
], 'User has problems with their account');

$supportClassifier->addIntent('billing_question', [
    'Why was I charged',
    'I have a question about my bill',
    'Refund request',
], 'User has billing or payment questions');

$supportClassifier->addIntent('technical_problem', [
    'The app is crashing',
    'Feature not working',
    'I found a bug',
], 'User is experiencing technical issues');

$supportClassifier->addIntent('feature_request', [
    'Can you add this feature',
    'I suggest implementing',
    'It would be great if',
], 'User wants to request a new feature');

// Define support-specific entities
$supportClassifier->addEntityType('account_number', 'User account or order number');
$supportClassifier->addEntityType('amount', 'Monetary amount');
$supportClassifier->addEntityType('feature_name', 'Name of app feature or functionality');

$supportQueries = [
    "I can't log into my account #12345",
    "Why was I charged $99.99 last week?",
    "The export feature keeps crashing on my phone",
    "Can you add dark mode to the app?",
    "Something weird is happening but I'm not sure what",
];

foreach ($supportQueries as $query) {
    echo "💬 Customer: \"{$query}\"\n";
    $result = $supportClassifier->run($query);
    
    if ($result->isSuccess()) {
        $data = $result->getMetadata();
        $confidence = $data['confidence'];
        
        echo "   🎯 Intent: {$data['intent']}\n";
        echo "   📊 Confidence: " . number_format($confidence * 100, 1) . "%\n";
        
        if (!empty($data['entities'])) {
            echo "   📦 Extracted entities:\n";
            foreach ($data['entities'] as $entity) {
                echo "      - {$entity['type']}: \"{$entity['value']}\"\n";
            }
        }
        
        // Route based on intent
        if ($data['intent'] === 'need_human_agent') {
            echo "   🔀 Action: Transfer to human agent (low confidence)\n";
        } else {
            echo "   🔀 Action: Route to {$data['intent']} handler\n";
        }
    }
    echo "\n";
}

sleep(1);

// Example 4: Multi-Language Support
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Multi-Language Intent Classification\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$multiLangClassifier = new IntentClassifierAgent($client, [
    'name' => 'multilang_classifier',
]);

$multiLangClassifier->addIntent('greeting', [
    'Hello', 'Bonjour', 'Hola', 'Ciao', 'Hallo',
], 'Greeting in any language');

$multiLangClassifier->addIntent('help_request', [
    'Help me', 'Aidez-moi', 'Ayúdame', 'Aiutami', 'Hilf mir',
], 'Request for help in any language');

$multiLangInputs = [
    'Bonjour! Comment allez-vous?',  // French
    'Hola, necesito ayuda',          // Spanish
    'Ciao, come stai?',              // Italian
    'Hallo, ich brauche Hilfe',     // German
];

foreach ($multiLangInputs as $input) {
    echo "💬 Input: \"{$input}\"\n";
    $result = $multiLangClassifier->run($input);
    
    if ($result->isSuccess()) {
        $data = $result->getMetadata();
        echo "   🎯 Intent: {$data['intent']}\n";
        echo "   📊 Confidence: " . number_format($data['confidence'] * 100, 1) . "%\n";
    }
    echo "\n";
}

// Summary
echo str_repeat("═", 80) . "\n";
echo "✨ Intent Classifier example completed!\n\n";
echo "📊 Summary:\n";
echo "   - Classified various user intents with confidence scores\n";
echo "   - Extracted entities from user input (dates, locations, etc.)\n";
echo "   - Demonstrated customer support use case\n";
echo "   - Showed multi-language classification capabilities\n";
echo "   - Used confidence thresholds for fallback routing\n";
echo str_repeat("═", 80) . "\n";

