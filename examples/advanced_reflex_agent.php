#!/usr/bin/env php
<?php
/**
 * Advanced Reflex Agent Example
 *
 * This example demonstrates advanced features of the ReflexAgent including:
 * - Complex pattern matching with regex
 * - Callable conditions and actions
 * - Rule management (adding, removing, clearing)
 * - Priority-based rule matching
 * - Context-aware responses
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
echo "║                  Advanced Reflex Agent Example                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// =============================================================================
// Example 1: Smart Calculator Agent
// =============================================================================
echo "Example 1: Smart Calculator with Pattern Matching\n";
echo "──────────────────────────────────────────────────\n\n";

$calculator = new ReflexAgent($client, [
    'name' => 'smart_calculator',
    'use_llm_fallback' => false, // Strict rule-based only
]);

// Add calculator rules with different priorities
$calculator->addRule(
    name: 'multiplication',
    condition: '/(\d+\.?\d*)\s*[×x*]\s*(\d+\.?\d*)/',
    action: function(string $input) {
        preg_match('/(\d+\.?\d*)\s*[×x*]\s*(\d+\.?\d*)/', $input, $matches);
        $result = (float)$matches[1] * (float)$matches[2];
        return "Calculation: {$matches[1]} × {$matches[2]} = {$result}";
    },
    priority: 10
);

$calculator->addRule(
    name: 'addition',
    condition: '/(\d+\.?\d*)\s*\+\s*(\d+\.?\d*)/',
    action: function(string $input) {
        preg_match('/(\d+\.?\d*)\s*\+\s*(\d+\.?\d*)/', $input, $matches);
        $result = (float)$matches[1] + (float)$matches[2];
        return "Calculation: {$matches[1]} + {$matches[2]} = {$result}";
    },
    priority: 9
);

$calculator->addRule(
    name: 'subtraction',
    condition: '/(\d+\.?\d*)\s*-\s*(\d+\.?\d*)/',
    action: function(string $input) {
        preg_match('/(\d+\.?\d*)\s*-\s*(\d+\.?\d*)/', $input, $matches);
        $result = (float)$matches[1] - (float)$matches[2];
        return "Calculation: {$matches[1]} - {$matches[2]} = {$result}";
    },
    priority: 9
);

$calculator->addRule(
    name: 'division',
    condition: '/(\d+\.?\d*)\s*[÷\/]\s*(\d+\.?\d*)/',
    action: function(string $input) {
        preg_match('/(\d+\.?\d*)\s*[÷\/]\s*(\d+\.?\d*)/', $input, $matches);
        $divisor = (float)$matches[2];
        if ($divisor == 0) {
            return "Error: Cannot divide by zero!";
        }
        $result = (float)$matches[1] / $divisor;
        return "Calculation: {$matches[1]} ÷ {$matches[2]} = {$result}";
    },
    priority: 10
);

$calcTests = [
    'What is 25 * 4?',
    'Calculate 100 + 50',
    'What is 75 - 32?',
    'Divide 100 / 4',
    '3.14 * 2',
];

foreach ($calcTests as $test) {
    $result = $calculator->run($test);
    echo "Input: {$test}\n";
    echo "Output: " . ($result->isSuccess() ? $result->getAnswer() : $result->getError()) . "\n\n";
}

// =============================================================================
// Example 2: Email Validator Agent
// =============================================================================
echo "\nExample 2: Email Validator with Complex Patterns\n";
echo "──────────────────────────────────────────────────\n\n";

$emailAgent = new ReflexAgent($client, [
    'name' => 'email_validator',
    'use_llm_fallback' => false,
]);

$emailAgent->addRule(
    name: 'valid_email',
    condition: '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
    action: function(string $input) {
        $input = trim($input);
        $parts = explode('@', $input);
        $domain = $parts[1] ?? '';
        return "✅ Valid email address!\n" .
               "   Domain: {$domain}\n" .
               "   Username: {$parts[0]}";
    },
    priority: 10
);

$emailAgent->addRule(
    name: 'missing_at',
    condition: fn($input) => !str_contains($input, '@'),
    action: '❌ Invalid: Email must contain @ symbol',
    priority: 5
);

$emailAgent->addRule(
    name: 'missing_domain',
    condition: fn($input) => str_contains($input, '@') && !str_contains($input, '.'),
    action: '❌ Invalid: Email must have a domain extension (.com, .org, etc.)',
    priority: 6
);

$emailTests = [
    'john.doe@example.com',
    'invalid-email',
    'missing-domain@example',
    'user@company.co.uk',
];

foreach ($emailTests as $test) {
    $result = $emailAgent->run($test);
    echo "Email: {$test}\n";
    echo $result->getAnswer() . "\n\n";
}

// =============================================================================
// Example 3: Dynamic Rule Management
// =============================================================================
echo "\nExample 3: Dynamic Rule Management\n";
echo "──────────────────────────────────\n\n";

$dynamicAgent = new ReflexAgent($client, [
    'name' => 'dynamic_agent',
    'use_llm_fallback' => false,
]);

echo "Adding initial rules...\n";
$dynamicAgent->addRules([
    ['name' => 'rule1', 'condition' => 'test1', 'action' => 'Response 1', 'priority' => 5],
    ['name' => 'rule2', 'condition' => 'test2', 'action' => 'Response 2', 'priority' => 10],
    ['name' => 'rule3', 'condition' => 'test3', 'action' => 'Response 3', 'priority' => 1],
]);

echo "Total rules: " . count($dynamicAgent->getRules()) . "\n\n";

// Show rules in priority order
echo "Rules (in priority order):\n";
foreach ($dynamicAgent->getRules() as $rule) {
    echo "  - {$rule['name']} (priority: {$rule['priority']})\n";
}
echo "\n";

// Remove a rule
echo "Removing 'rule2'...\n";
$removed = $dynamicAgent->removeRule('rule2');
echo ($removed ? "✓" : "✗") . " Rule removed\n";
echo "Remaining rules: " . count($dynamicAgent->getRules()) . "\n\n";

// Clear all rules
echo "Clearing all rules...\n";
$dynamicAgent->clearRules();
echo "Rules after clear: " . count($dynamicAgent->getRules()) . "\n\n";

// =============================================================================
// Example 4: Context-Aware Agent with State
// =============================================================================
echo "\nExample 4: Context-Aware Conversation Agent\n";
echo "────────────────────────────────────────────\n\n";

// Using closures to maintain state
$conversationState = [
    'user_name' => null,
    'user_age' => null,
    'conversation_stage' => 'greeting',
];

$contextAgent = new ReflexAgent($client, [
    'name' => 'context_aware_bot',
    'use_llm_fallback' => false,
]);

$contextAgent->addRule(
    name: 'greeting',
    condition: fn($input) => $conversationState['conversation_stage'] === 'greeting' && 
                              preg_match('/hello|hi|hey/i', $input),
    action: function($input) use (&$conversationState) {
        $conversationState['conversation_stage'] = 'ask_name';
        return "Hello! Welcome! What's your name?";
    },
    priority: 10
);

$contextAgent->addRule(
    name: 'capture_name',
    condition: fn($input) => $conversationState['conversation_stage'] === 'ask_name' && 
                              preg_match('/my name is (\w+)|i am (\w+)|(\w+)/i', $input),
    action: function($input) use (&$conversationState) {
        preg_match('/my name is (\w+)|i am (\w+)|call me (\w+)|^(\w+)$/i', $input, $matches);
        $name = $matches[1] ?? $matches[2] ?? $matches[3] ?? $matches[4] ?? 'there';
        $conversationState['user_name'] = $name;
        $conversationState['conversation_stage'] = 'ask_age';
        return "Nice to meet you, {$name}! How old are you?";
    },
    priority: 10
);

$contextAgent->addRule(
    name: 'capture_age',
    condition: fn($input) => $conversationState['conversation_stage'] === 'ask_age' && 
                              preg_match('/\d+/', $input),
    action: function($input) use (&$conversationState) {
        preg_match('/(\d+)/', $input, $matches);
        $age = $matches[1];
        $conversationState['user_age'] = $age;
        $conversationState['conversation_stage'] = 'complete';
        $name = $conversationState['user_name'];
        return "Great! So you're {$name}, {$age} years old. Nice to meet you! " .
               "I've got all the information I need. How can I help you today?";
    },
    priority: 10
);

$conversation = [
    'Hello!',
    'My name is Alice',
    'I am 25 years old',
];

foreach ($conversation as $message) {
    echo "User: {$message}\n";
    $result = $contextAgent->run($message);
    if ($result->isSuccess()) {
        echo "Bot: {$result->getAnswer()}\n\n";
    }
}

// =============================================================================
// Example 5: Priority-Based Matching
// =============================================================================
echo "\nExample 5: Priority-Based Rule Matching\n";
echo "────────────────────────────────────────\n\n";

$priorityAgent = new ReflexAgent($client, [
    'name' => 'priority_demo',
    'use_llm_fallback' => false,
]);

// Add multiple rules that could match the same input
$priorityAgent->addRule('generic', 'test', '📋 Generic match (low priority)', priority: 1);
$priorityAgent->addRule('medium', 'test', '📊 Medium priority match', priority: 5);
$priorityAgent->addRule('high', 'test', '⭐ High priority match', priority: 10);
$priorityAgent->addRule('highest', 'test', '🏆 Highest priority match!', priority: 15);

echo "Testing with input 'test' (all rules match):\n";
$result = $priorityAgent->run('test');
echo "Result: {$result->getAnswer()}\n";
$metadata = $result->getMetadata();
echo "Matched rule: {$metadata['rule_matched']} (priority: {$metadata['priority']})\n\n";

echo "This demonstrates that the highest priority rule wins when multiple rules match!\n\n";

// =============================================================================
// Summary
// =============================================================================
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                           Summary                                          ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "Advanced Features Demonstrated:\n";
echo "1. ✅ Complex regex patterns for pattern matching\n";
echo "2. ✅ Callable conditions and actions with custom logic\n";
echo "3. ✅ Dynamic rule management (add, remove, clear)\n";
echo "4. ✅ Priority-based rule matching\n";
echo "5. ✅ Stateful context-aware agents using closures\n";
echo "6. ✅ Domain-specific agents (calculator, validator)\n\n";

echo "Use Cases:\n";
echo "• Input validation and parsing\n";
echo "• Simple calculators and transformers\n";
echo "• Rule-based routing and classification\n";
echo "• FAQ bots and customer service automation\n";
echo "• Context-aware conversational flows\n";

