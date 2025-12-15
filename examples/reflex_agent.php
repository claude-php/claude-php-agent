#!/usr/bin/env php
<?php
/**
 * Reflex Agent Example - Basic rule-based agent demonstration
 *
 * This example shows how to create a simple reflex agent that responds
 * to user input using predefined rules with optional LLM fallback.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\ReflexAgent;
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

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    Reflex Agent - Basic Example                           ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create a reflex agent with LLM fallback enabled
$agent = new ReflexAgent($client, [
    'name' => 'customer_service_bot',
    'use_llm_fallback' => true, // Use LLM when no rule matches
]);

echo "Setting up customer service bot rules...\n\n";

// Add greeting rules
$agent->addRule(
    name: 'morning_greeting',
    condition: '/good morning|morning|gm/i',
    action: '🌅 Good morning! How can I help you today?',
    priority: 10
);

$agent->addRule(
    name: 'general_greeting',
    condition: 'hello',
    action: '👋 Hello! Welcome to our support. How can I assist you?',
    priority: 9
);

// Add help and support rules
$agent->addRule(
    name: 'help_request',
    condition: 'help',
    action: "I can help you with:\n" .
            "- Order status (say 'order status')\n" .
            "- Returns (say 'return')\n" .
            "- Technical support (say 'tech support')\n" .
            "- Billing questions (say 'billing')",
    priority: 8
);

$agent->addRule(
    name: 'order_status',
    condition: '/order status|track.*order|where.*order/i',
    action: '📦 I can help you track your order. Please provide your order number (format: ORD-12345)',
    priority: 10
);

$agent->addRule(
    name: 'order_number',
    condition: '/ORD-\d{5}/',
    action: function(string $input) {
        preg_match('/ORD-\d{5}/', $input, $matches);
        $orderNum = $matches[0];
        return "✅ Order {$orderNum} is currently being processed and will ship within 2 business days.";
    },
    priority: 11
);

$agent->addRule(
    name: 'returns',
    condition: '/return|refund/i',
    action: '🔄 To start a return, please visit our returns portal at returns.example.com ' .
            'or reply with your order number and I\'ll generate a return label.',
    priority: 10
);

$agent->addRule(
    name: 'tech_support',
    condition: '/tech support|technical|bug|error|not working/i',
    action: '🔧 I\'ll connect you with our technical support team. ' .
            'Please describe the issue you\'re experiencing.',
    priority: 10
);

$agent->addRule(
    name: 'billing',
    condition: '/billing|payment|charge|invoice/i',
    action: '💳 For billing inquiries, please contact our billing department at billing@example.com ' .
            'or call 1-800-BILLING',
    priority: 10
);

// Add farewell rules
$agent->addRule(
    name: 'thanks',
    condition: '/thank|thanks/i',
    action: '😊 You\'re welcome! Is there anything else I can help you with?',
    priority: 7
);

$agent->addRule(
    name: 'goodbye',
    condition: '/bye|goodbye|see you/i',
    action: '👋 Goodbye! Have a great day! Feel free to come back if you need anything.',
    priority: 8
);

// Test cases
$testInputs = [
    'Good morning!',
    'hello there',
    'I need help',
    'What is my order status?',
    'My order number is ORD-12345',
    'I want to return an item',
    'I have a billing question',
    'thank you',
    'What is the weather today?', // This will trigger LLM fallback
    'bye',
];

foreach ($testInputs as $input) {
    echo "─────────────────────────────────────────────────────────────────────────────\n";
    echo "User: {$input}\n\n";
    
    $result = $agent->run($input);
    
    if ($result->isSuccess()) {
        echo "Bot: {$result->getAnswer()}\n";
        
        $metadata = $result->getMetadata();
        if (isset($metadata['rule_matched'])) {
            echo "\n[✓ Matched rule: {$metadata['rule_matched']} (priority: {$metadata['priority']})]\n";
        } elseif (isset($metadata['used_llm_fallback']) && $metadata['used_llm_fallback']) {
            echo "\n[⚡ Used LLM fallback - no matching rule found]\n";
        }
    } else {
        echo "Bot: ❌ Error: {$result->getError()}\n";
    }
    
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

// Show statistics
echo "Agent Statistics:\n";
echo "- Total rules defined: " . count($agent->getRules()) . "\n";
echo "- Agent name: {$agent->getName()}\n\n";

echo "✨ Example complete!\n\n";

echo "Key Takeaways:\n";
echo "1. Reflex agents use simple condition → action rules\n";
echo "2. Rules can be strings, regex patterns, or callable functions\n";
echo "3. Higher priority rules are checked first\n";
echo "4. LLM fallback handles cases not covered by rules\n";
echo "5. Perfect for FAQ bots, simple automation, and rule-based logic\n";

