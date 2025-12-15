#!/usr/bin/env php
<?php
/**
 * Advanced Intent Classifier Agent Example
 *
 * Demonstrates advanced features including:
 * - Custom confidence thresholds
 * - Intent hierarchies and routing
 * - Dynamic intent management
 * - Context-aware classification
 * - Integration with conversation flows
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\IntentClassifierAgent;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Enhanced console logger with colors
class ColorLogger extends AbstractLogger
{
    private array $colors = [
        'error' => "\033[0;31m",
        'warning' => "\033[0;33m",
        'info' => "\033[0;36m",
        'debug' => "\033[0;37m",
        'reset' => "\033[0m",
    ];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        $color = $this->colors[$level] ?? $this->colors['reset'];
        $reset = $this->colors['reset'];
        $emoji = match ($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'debug' => '🔍',
            default => '📝',
        };
        echo "{$color}[{$timestamp}] {$emoji} [{$level}] {$message}{$reset}\n";
    }
}

// Intent Router - Routes intents to appropriate handlers
class IntentRouter
{
    private array $handlers = [];
    private array $stats = [];

    public function registerHandler(string $intent, callable $handler): void
    {
        $this->handlers[$intent] = $handler;
    }

    public function route(array $classification): array
    {
        $intent = $classification['intent'];
        
        // Track statistics
        if (!isset($this->stats[$intent])) {
            $this->stats[$intent] = ['count' => 0, 'total_confidence' => 0];
        }
        $this->stats[$intent]['count']++;
        $this->stats[$intent]['total_confidence'] += $classification['confidence'];

        if (isset($this->handlers[$intent])) {
            $response = call_user_func($this->handlers[$intent], $classification);
            return [
                'intent' => $intent,
                'response' => $response,
                'handled' => true,
            ];
        }

        return [
            'intent' => $intent,
            'response' => 'I\'m not sure how to handle that request.',
            'handled' => false,
        ];
    }

    public function getStats(): array
    {
        $stats = [];
        foreach ($this->stats as $intent => $data) {
            $stats[$intent] = [
                'count' => $data['count'],
                'avg_confidence' => $data['total_confidence'] / $data['count'],
            ];
        }
        return $stats;
    }
}

// Context Manager - Manages conversation context for better classification
class ClassificationContext
{
    private array $history = [];
    private int $maxHistory = 5;
    private array $userProfile = [];

    public function addClassification(array $classification): void
    {
        $this->history[] = $classification;
        if (count($this->history) > $this->maxHistory) {
            array_shift($this->history);
        }
    }

    public function getRecentIntents(): array
    {
        return array_map(fn($c) => $c['intent'], $this->history);
    }

    public function setUserPreference(string $key, mixed $value): void
    {
        $this->userProfile[$key] = $value;
    }

    public function getUserProfile(): array
    {
        return $this->userProfile;
    }

    public function getContextSummary(): string
    {
        $recentIntents = array_slice($this->getRecentIntents(), -3);
        return empty($recentIntents) 
            ? 'No prior context' 
            : 'Recent intents: ' . implode(' → ', $recentIntents);
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
echo "║                Advanced Intent Classifier Agent Example                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

$logger = new ColorLogger();

// Example 1: Dynamic Intent Management
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Dynamic Intent Management\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$classifier = new IntentClassifierAgent($client, [
    'name' => 'dynamic_classifier',
    'logger' => $logger,
    'confidence_threshold' => 0.7,
]);

// Start with basic intents
$classifier->addIntent('greeting', ['Hello', 'Hi'], 'Basic greeting');
$classifier->addIntent('help', ['Help me', 'I need help'], 'Help request');

echo "📝 Initial intents: " . implode(', ', array_keys($classifier->getIntents())) . "\n\n";

$result = $classifier->run('Hello there!');
$data = $result->getMetadata();
echo "💬 \"Hello there!\" → Intent: {$data['intent']} ({$data['confidence']})\n\n";

// Dynamically add more intents
echo "➕ Adding new intents dynamically...\n";
$classifier->addIntent('order_status', [
    'Where is my order',
    'Track my package',
    'Order status',
], 'Customer wants to check order status');

$classifier->addIntent('cancel_order', [
    'Cancel my order',
    'I want to cancel',
    'Stop my order',
], 'Customer wants to cancel an order');

echo "📝 Updated intents: " . implode(', ', array_keys($classifier->getIntents())) . "\n\n";

$result = $classifier->run('Where is my package?');
$data = $result->getMetadata();
echo "💬 \"Where is my package?\" → Intent: {$data['intent']} ({$data['confidence']})\n\n";

// Remove an intent
echo "➖ Removing 'greeting' intent...\n";
$classifier->removeIntent('greeting');
echo "📝 Final intents: " . implode(', ', array_keys($classifier->getIntents())) . "\n\n";

sleep(1);

// Example 2: Intent Routing System
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Intent Routing with Custom Handlers\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$routingClassifier = new IntentClassifierAgent($client, [
    'name' => 'routing_classifier',
    'confidence_threshold' => 0.65,
]);

$routingClassifier->addIntent('order_status', ['Where is my order', 'Track package']);
$routingClassifier->addIntent('return_request', ['Return item', 'I want to return']);
$routingClassifier->addIntent('product_inquiry', ['Tell me about', 'Product info']);
$routingClassifier->addIntent('complaint', ['I\'m unhappy', 'This is terrible']);

$routingClassifier->addEntityType('order_number', 'Order or tracking number');
$routingClassifier->addEntityType('product_name', 'Name of product');

$router = new IntentRouter();

// Register handlers for each intent
$router->registerHandler('order_status', function($classification) {
    $orderNum = null;
    foreach ($classification['entities'] as $entity) {
        if ($entity['type'] === 'order_number') {
            $orderNum = $entity['value'];
        }
    }
    
    if ($orderNum) {
        return "Looking up order {$orderNum}... Your order is out for delivery!";
    }
    return "Please provide your order number to check status.";
});

$router->registerHandler('return_request', function($classification) {
    return "I'll help you start a return. Please provide your order number.";
});

$router->registerHandler('product_inquiry', function($classification) {
    return "I'd be happy to provide product information. Which product are you interested in?";
});

$router->registerHandler('complaint', function($classification) {
    return "I'm sorry to hear you're having issues. Let me connect you with a supervisor.";
});

$customerMessages = [
    "Where is my order #12345?",
    "I want to return this item",
    "This product is terrible and doesn't work!",
    "Tell me about the wireless headphones",
];

foreach ($customerMessages as $message) {
    echo "💬 Customer: \"{$message}\"\n";
    
    $result = $routingClassifier->run($message);
    if ($result->isSuccess()) {
        $classification = $result->getMetadata();
        $routingResult = $router->route($classification);
        
        echo "   🎯 Classified as: {$routingResult['intent']}\n";
        echo "   💡 Response: {$routingResult['response']}\n";
        echo "   ✅ Handled: " . ($routingResult['handled'] ? 'Yes' : 'No') . "\n";
    }
    echo "\n";
}

echo "📊 Routing Statistics:\n";
foreach ($router->getStats() as $intent => $stats) {
    echo "   - {$intent}: {$stats['count']} times (avg confidence: " 
         . number_format($stats['avg_confidence'] * 100, 1) . "%)\n";
}
echo "\n";

sleep(1);

// Example 3: Context-Aware Classification
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Context-Aware Intent Classification\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$contextClassifier = new IntentClassifierAgent($client, [
    'name' => 'context_classifier',
    'confidence_threshold' => 0.6,
]);

$contextClassifier->addIntent('browse_products', ['Show me products', 'What do you have']);
$contextClassifier->addIntent('add_to_cart', ['Add to cart', 'I\'ll take it']);
$contextClassifier->addIntent('checkout', ['Checkout', 'Complete purchase']);
$contextClassifier->addIntent('modify_cart', ['Remove from cart', 'Change quantity']);

$context = new ClassificationContext();
$context->setUserPreference('location', 'US');
$context->setUserPreference('language', 'en');

$conversationFlow = [
    "Show me your laptops",
    "I'll take the MacBook Pro",
    "Actually, add the wireless mouse too",
    "Remove the mouse",
    "Proceed to checkout",
];

echo "🛒 E-commerce Conversation Flow:\n";
echo "User profile: " . json_encode($context->getUserProfile()) . "\n\n";

foreach ($conversationFlow as $i => $message) {
    echo "Turn " . ($i + 1) . ":\n";
    echo "💬 User: \"{$message}\"\n";
    echo "📍 Context: {$context->getContextSummary()}\n";
    
    $result = $contextClassifier->run($message);
    if ($result->isSuccess()) {
        $classification = $result->getMetadata();
        $context->addClassification($classification);
        
        echo "   🎯 Intent: {$classification['intent']}\n";
        echo "   📊 Confidence: " . number_format($classification['confidence'] * 100, 1) . "%\n";
        
        if (!empty($classification['entities'])) {
            echo "   📦 Entities: ";
            echo implode(', ', array_map(
                fn($e) => "{$e['type']}={$e['value']}", 
                $classification['entities']
            ));
            echo "\n";
        }
    }
    echo "\n";
}

sleep(1);

// Example 4: Multi-Level Intent Hierarchy
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Hierarchical Intent Classification\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Level 1: Domain classifier
$domainClassifier = new IntentClassifierAgent($client, [
    'name' => 'domain_classifier',
    'confidence_threshold' => 0.7,
]);

$domainClassifier->addIntent('sales', ['Buy', 'Purchase', 'Price'], 'Sales related');
$domainClassifier->addIntent('support', ['Help', 'Problem', 'Issue'], 'Support related');
$domainClassifier->addIntent('account', ['Login', 'Password', 'Profile'], 'Account related');

// Level 2: Sales-specific classifier
$salesClassifier = new IntentClassifierAgent($client, [
    'name' => 'sales_classifier',
]);

$salesClassifier->addIntent('product_info', ['Tell me about', 'Features of']);
$salesClassifier->addIntent('pricing', ['How much', 'Price', 'Cost']);
$salesClassifier->addIntent('purchase', ['Buy now', 'Order', 'Purchase']);

// Level 2: Support-specific classifier
$supportClassifier = new IntentClassifierAgent($client, [
    'name' => 'support_classifier',
]);

$supportClassifier->addIntent('technical', ['Not working', 'Error', 'Bug']);
$supportClassifier->addIntent('how_to', ['How do I', 'Show me', 'Tutorial']);
$supportClassifier->addIntent('complaint', ['Unhappy', 'Disappointed', 'Bad']);

$testMessages = [
    "How much does the premium plan cost?",
    "My app keeps crashing when I export files",
    "How do I reset my password?",
];

foreach ($testMessages as $message) {
    echo "💬 User: \"{$message}\"\n";
    
    // Level 1: Determine domain
    $domainResult = $domainClassifier->run($message);
    if ($domainResult->isSuccess()) {
        $domainData = $domainResult->getMetadata();
        $domain = $domainData['intent'];
        
        echo "   📂 Domain: {$domain} (confidence: " 
             . number_format($domainData['confidence'] * 100, 1) . "%)\n";
        
        // Level 2: Classify within domain
        $specificClassifier = match($domain) {
            'sales' => $salesClassifier,
            'support' => $supportClassifier,
            default => null,
        };
        
        if ($specificClassifier) {
            $specificResult = $specificClassifier->run($message);
            if ($specificResult->isSuccess()) {
                $specificData = $specificResult->getMetadata();
                echo "   🎯 Specific Intent: {$specificData['intent']} (confidence: " 
                     . number_format($specificData['confidence'] * 100, 1) . "%)\n";
                echo "   🔀 Route to: {$domain}/{$specificData['intent']} handler\n";
            }
        }
    }
    echo "\n";
}

sleep(1);

// Example 5: Confidence-Based Escalation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Confidence-Based Escalation Strategy\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$escalationClassifier = new IntentClassifierAgent($client, [
    'name' => 'escalation_classifier',
    'confidence_threshold' => 0.5,  // Lower threshold for fallback
]);

$escalationClassifier->addIntent('simple_greeting', ['Hello', 'Hi']);
$escalationClassifier->addIntent('simple_help', ['Help', 'Assist me']);

$ambiguousMessages = [
    "Hello!",
    "Hmm, maybe you can help?",
    "asdfghjkl",  // Complete gibberish
    "What about the thing with the stuff?",
];

foreach ($ambiguousMessages as $message) {
    echo "💬 User: \"{$message}\"\n";
    
    $result = $escalationClassifier->run($message);
    if ($result->isSuccess()) {
        $data = $result->getMetadata();
        $confidence = $data['confidence'];
        
        echo "   🎯 Intent: {$data['intent']}\n";
        echo "   📊 Confidence: " . number_format($confidence * 100, 1) . "%\n";
        
        // Escalation logic based on confidence
        if ($confidence >= 0.85) {
            echo "   ✅ Action: Handle automatically\n";
        } elseif ($confidence >= 0.65) {
            echo "   ⚠️  Action: Handle with clarification\n";
        } elseif ($confidence >= 0.5) {
            echo "   🤔 Action: Request more information\n";
        } else {
            echo "   🔀 Action: Escalate to human agent\n";
        }
    }
    echo "\n";
}

// Summary
echo str_repeat("═", 80) . "\n";
echo "✨ Advanced Intent Classifier example completed!\n\n";
echo "📊 Summary of Advanced Features:\n";
echo "   - Dynamic intent management (add/remove at runtime)\n";
echo "   - Intent routing with custom handlers\n";
echo "   - Context-aware classification with conversation history\n";
echo "   - Hierarchical intent classification (domain → specific)\n";
echo "   - Confidence-based escalation strategies\n";
echo "   - Statistics tracking and analytics\n";
echo "   - Real-world e-commerce conversation flow\n";
echo str_repeat("═", 80) . "\n";

