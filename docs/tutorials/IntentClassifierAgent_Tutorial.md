# IntentClassifierAgent Tutorial: Building NLU-Powered Applications

## Introduction

This comprehensive tutorial will guide you through building natural language understanding (NLU) applications using the IntentClassifierAgent. You'll learn how to classify user intents, extract entities, and build production-ready conversational AI systems similar to Rasa NLU or Dialogflow.

By the end of this tutorial, you'll be able to:
- Build intent classification systems for chatbots and voice assistants
- Extract structured data from unstructured text
- Implement confidence-based routing and escalation
- Create hierarchical intent classification systems
- Build complete NLU pipelines for real-world applications
- Handle multi-language intent classification
- Integrate with existing dialog systems

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and object-oriented programming
- Familiarity with conversational AI concepts (helpful but not required)

## Table of Contents

1. [Getting Started](#getting-started)
2. [Your First Intent Classifier](#your-first-intent-classifier)
3. [Understanding Intents and Entities](#understanding-intents-and-entities)
4. [Building a Customer Support Bot](#building-a-customer-support-bot)
5. [Entity Extraction](#entity-extraction)
6. [Confidence Thresholds and Fallbacks](#confidence-thresholds-and-fallbacks)
7. [Intent Routing](#intent-routing)
8. [Hierarchical Classification](#hierarchical-classification)
9. [Context-Aware Classification](#context-aware-classification)
10. [Production Best Practices](#production-best-practices)

## Getting Started

### Installation

First, ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to test the IntentClassifierAgent:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\IntentClassifierAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the intent classifier
$classifier = new IntentClassifierAgent($client, [
    'name' => 'tutorial_classifier',
]);

echo "Intent classifier ready!\n";
```

## Your First Intent Classifier

Let's start with a simple example - classifying greetings and farewells.

### Step 1: Define Your Intents

```php
// Add greeting intent
$classifier->addIntent('greeting', [
    'Hello',
    'Hi there',
    'Good morning',
    'Hey',
    'Greetings',
]);

// Add farewell intent
$classifier->addIntent('goodbye', [
    'Bye',
    'Goodbye',
    'See you later',
    'Take care',
    'Have a good day',
]);

echo "Intents configured!\n";
```

### Step 2: Classify User Input

```php
$userInput = 'Hi! How are you today?';

$result = $classifier->run($userInput);

if ($result->isSuccess()) {
    $data = $result->getMetadata();
    
    echo "User said: {$userInput}\n";
    echo "Intent: {$data['intent']}\n";
    echo "Confidence: " . ($data['confidence'] * 100) . "%\n";
}
```

**Output:**
```
User said: Hi! How are you today?
Intent: greeting
Confidence: 95%
```

### Step 3: Handle Different Intents

```php
function handleIntent(array $classification): string
{
    return match($classification['intent']) {
        'greeting' => 'Hello! How can I help you today?',
        'goodbye' => 'Goodbye! Have a great day!',
        default => 'I\'m not sure what you mean.',
    };
}

$result = $classifier->run('See you later!');
$data = $result->getMetadata();
$response = handleIntent($data);

echo "Bot: {$response}\n";
// Output: "Goodbye! Have a great day!"
```

## Understanding Intents and Entities

### What are Intents?

An **intent** represents the user's goal or purpose. For example:
- `greeting`: User wants to start a conversation
- `book_flight`: User wants to book a flight
- `check_weather`: User wants weather information

### What are Entities?

**Entities** are specific pieces of information extracted from the user's input:
- In "Book a flight to **Paris** on **March 15th**":
  - `destination`: Paris
  - `date`: March 15th

### Working with Intent Descriptions

Add descriptions to help the classifier understand intent meanings:

```php
$classifier->addIntent(
    intent: 'book_flight',
    examples: [
        'I want to book a flight',
        'Book me a ticket',
        'I need to fly somewhere',
    ],
    description: 'User wants to book an airline ticket'
);

$classifier->addIntent(
    intent: 'check_flight_status',
    examples: [
        'Where is my flight',
        'Flight status',
        'Is my flight on time',
    ],
    description: 'User wants to check the status of their flight'
);
```

## Building a Customer Support Bot

Let's build a complete customer support bot that handles multiple types of requests.

### Step 1: Define Support Intents

```php
class SupportBot
{
    private IntentClassifierAgent $classifier;
    
    public function __construct(ClaudePhp $client)
    {
        $this->classifier = new IntentClassifierAgent($client, [
            'name' => 'support_bot',
            'confidence_threshold' => 0.65,
            'fallback_intent' => 'need_human_agent',
        ]);
        
        $this->setupIntents();
    }
    
    private function setupIntents(): void
    {
        // Account issues
        $this->classifier->addIntent('account_issue', [
            'I can\'t log in',
            'My account is locked',
            'I forgot my password',
            'Can\'t access my account',
        ], 'User has problems accessing their account');
        
        // Billing questions
        $this->classifier->addIntent('billing_question', [
            'Why was I charged',
            'I have a billing question',
            'Refund request',
            'Cancel my subscription',
        ], 'User has questions about billing or payments');
        
        // Technical problems
        $this->classifier->addIntent('technical_problem', [
            'The app is crashing',
            'Feature not working',
            'I found a bug',
            'Error message',
        ], 'User is experiencing technical issues');
        
        // Feature requests
        $this->classifier->addIntent('feature_request', [
            'Can you add this feature',
            'I suggest implementing',
            'It would be great if',
            'Feature suggestion',
        ], 'User wants to suggest a new feature');
    }
}
```

### Step 2: Process Customer Messages

```php
class SupportBot
{
    // ... previous code ...
    
    public function handleMessage(string $message): array
    {
        $result = $this->classifier->run($message);
        
        if (!$result->isSuccess()) {
            return [
                'response' => 'I\'m having trouble understanding. Please try again.',
                'action' => 'error',
            ];
        }
        
        $data = $result->getMetadata();
        
        return [
            'intent' => $data['intent'],
            'confidence' => $data['confidence'],
            'response' => $this->generateResponse($data),
            'action' => $this->determineAction($data),
        ];
    }
    
    private function generateResponse(array $data): string
    {
        return match($data['intent']) {
            'account_issue' => 'I can help you with your account. Let me look into that.',
            'billing_question' => 'I\'ll help you with your billing question.',
            'technical_problem' => 'Let me help you resolve this technical issue.',
            'feature_request' => 'Thank you for your suggestion! I\'ll make note of it.',
            'need_human_agent' => 'Let me connect you with a human agent.',
            default => 'How can I help you today?',
        };
    }
    
    private function determineAction(array $data): string
    {
        $confidence = $data['confidence'];
        
        if ($confidence < 0.5) {
            return 'escalate_to_human';
        }
        
        return match($data['intent']) {
            'account_issue' => 'open_account_support_ticket',
            'billing_question' => 'route_to_billing',
            'technical_problem' => 'start_technical_troubleshooting',
            'feature_request' => 'log_feature_request',
            default => 'show_help_menu',
        };
    }
}
```

### Step 3: Test the Support Bot

```php
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$supportBot = new SupportBot($client);

$testMessages = [
    "I can't log into my account",
    "Why was I charged $99.99 last week?",
    "The export feature keeps crashing",
    "Can you add dark mode to the app?",
];

foreach ($testMessages as $message) {
    echo "\nCustomer: {$message}\n";
    
    $result = $supportBot->handleMessage($message);
    
    echo "Intent: {$result['intent']}\n";
    echo "Confidence: " . round($result['confidence'] * 100) . "%\n";
    echo "Bot: {$result['response']}\n";
    echo "Action: {$result['action']}\n";
}
```

## Entity Extraction

Entities allow you to extract structured data from unstructured text.

### Step 1: Define Entity Types

```php
$classifier = new IntentClassifierAgent($client);

// Add intents
$classifier->addIntent('book_hotel', [
    'Book a hotel',
    'I need a hotel room',
    'Reserve a hotel',
]);

// Add custom entity types
$classifier->addEntityType('city', 'City or location name');
$classifier->addEntityType('check_in_date', 'Check-in date');
$classifier->addEntityType('check_out_date', 'Check-out date');
$classifier->addEntityType('guests', 'Number of guests');
```

### Step 2: Extract Entities

```php
$userInput = 'I want to book a hotel in New York from June 1st to June 5th for 2 guests';

$result = $classifier->run($userInput);
$data = $result->getMetadata();

echo "Intent: {$data['intent']}\n";
echo "Entities:\n";

foreach ($data['entities'] as $entity) {
    echo "  - {$entity['type']}: {$entity['value']}\n";
}
```

**Output:**
```
Intent: book_hotel
Entities:
  - city: New York
  - check_in_date: June 1st
  - check_out_date: June 5th
  - guests: 2
```

### Step 3: Use Entities in Your Application

```php
class HotelBookingSystem
{
    private IntentClassifierAgent $classifier;
    
    public function processBooking(string $userInput): array
    {
        $result = $this->classifier->run($userInput);
        $data = $result->getMetadata();
        
        if ($data['intent'] !== 'book_hotel') {
            return ['error' => 'Not a booking request'];
        }
        
        $booking = [
            'city' => null,
            'check_in' => null,
            'check_out' => null,
            'guests' => 1,
        ];
        
        foreach ($data['entities'] as $entity) {
            switch ($entity['type']) {
                case 'city':
                    $booking['city'] = $entity['value'];
                    break;
                case 'check_in_date':
                    $booking['check_in'] = $entity['value'];
                    break;
                case 'check_out_date':
                    $booking['check_out'] = $entity['value'];
                    break;
                case 'guests':
                    $booking['guests'] = (int) $entity['value'];
                    break;
            }
        }
        
        // Validate required fields
        $missing = [];
        if (!$booking['city']) $missing[] = 'city';
        if (!$booking['check_in']) $missing[] = 'check-in date';
        if (!$booking['check_out']) $missing[] = 'check-out date';
        
        if (!empty($missing)) {
            return [
                'status' => 'incomplete',
                'missing' => $missing,
                'message' => 'Please provide: ' . implode(', ', $missing),
            ];
        }
        
        return [
            'status' => 'complete',
            'booking' => $booking,
            'message' => "Looking for hotels in {$booking['city']}...",
        ];
    }
}
```

## Confidence Thresholds and Fallbacks

Confidence scores help you determine how certain the classifier is about its prediction.

### Understanding Confidence Scores

```php
$classifier = new IntentClassifierAgent($client, [
    'confidence_threshold' => 0.7,
    'fallback_intent' => 'unclear',
]);

$result = $classifier->run('umm... maybe... I think...');
$data = $result->getMetadata();

echo "Intent: {$data['intent']}\n";
echo "Confidence: {$data['confidence']}\n";

if ($data['confidence'] < 0.7) {
    echo "Low confidence - using fallback\n";
    if (isset($data['original_intent'])) {
        echo "Original intent was: {$data['original_intent']}\n";
    }
}
```

### Implementing Escalation Logic

```php
class SmartRouter
{
    public function route(array $classification): string
    {
        $confidence = $classification['confidence'];
        $intent = $classification['intent'];
        
        // High confidence - handle automatically
        if ($confidence >= 0.85) {
            return $this->handleAutomatically($intent);
        }
        
        // Medium confidence - ask for confirmation
        if ($confidence >= 0.65) {
            return $this->askForConfirmation($intent);
        }
        
        // Low confidence - escalate to human
        return $this->escalateToHuman();
    }
    
    private function handleAutomatically(string $intent): string
    {
        return "Processing {$intent} automatically...";
    }
    
    private function askForConfirmation(string $intent): string
    {
        return "Did you want to {$intent}? (Yes/No)";
    }
    
    private function escalateToHuman(): string
    {
        return "Let me connect you with a human agent who can better help you.";
    }
}
```

### Confidence-Based Responses

```php
function generateResponse(array $classification): string
{
    $intent = $classification['intent'];
    $confidence = $classification['confidence'];
    
    if ($confidence >= 0.9) {
        // Very confident - direct response
        return match($intent) {
            'greeting' => 'Hello! How can I help you?',
            'help' => 'I can help with orders, returns, and product info.',
            default => 'How can I assist you?',
        };
    }
    
    if ($confidence >= 0.7) {
        // Somewhat confident - confirm first
        return "It sounds like you want to {$intent}. Is that correct?";
    }
    
    // Low confidence - ask for clarification
    return "I'm not quite sure what you need. Could you rephrase that?";
}
```

## Intent Routing

Build a flexible routing system that directs intents to appropriate handlers.

### Step 1: Create an Intent Router

```php
class IntentRouter
{
    private IntentClassifierAgent $classifier;
    private array $handlers = [];
    private array $middleware = [];
    
    public function __construct(IntentClassifierAgent $classifier)
    {
        $this->classifier = $classifier;
    }
    
    public function addHandler(string $intent, callable $handler): void
    {
        $this->handlers[$intent] = $handler;
    }
    
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }
    
    public function route(string $userInput): mixed
    {
        $result = $this->classifier->run($userInput);
        
        if (!$result->isSuccess()) {
            return $this->handleError($result->getError());
        }
        
        $classification = $result->getMetadata();
        
        // Apply middleware
        foreach ($this->middleware as $mw) {
            $classification = $mw($classification);
        }
        
        $intent = $classification['intent'];
        
        if (!isset($this->handlers[$intent])) {
            return $this->handleUnknown($classification);
        }
        
        return $this->handlers[$intent]($classification);
    }
    
    private function handleError(string $error): array
    {
        return ['error' => $error];
    }
    
    private function handleUnknown(array $classification): array
    {
        return [
            'intent' => $classification['intent'],
            'message' => 'No handler registered for this intent',
        ];
    }
}
```

### Step 2: Register Handlers

```php
$router = new IntentRouter($classifier);

// Register intent handlers
$router->addHandler('order_status', function($classification) {
    $orderNum = null;
    foreach ($classification['entities'] as $entity) {
        if ($entity['type'] === 'order_number') {
            $orderNum = $entity['value'];
            break;
        }
    }
    
    if ($orderNum) {
        return OrderService::checkStatus($orderNum);
    }
    
    return ['message' => 'Please provide your order number'];
});

$router->addHandler('cancel_order', function($classification) {
    return OrderService::initiateCancellation($classification);
});

$router->addHandler('product_inquiry', function($classification) {
    return ProductService::getInfo($classification);
});
```

### Step 3: Add Middleware

```php
// Logging middleware
$router->addMiddleware(function($classification) {
    error_log("Intent: {$classification['intent']}, Confidence: {$classification['confidence']}");
    return $classification;
});

// Analytics middleware
$router->addMiddleware(function($classification) {
    Analytics::track('intent_classified', [
        'intent' => $classification['intent'],
        'confidence' => $classification['confidence'],
    ]);
    return $classification;
});

// Confidence filter middleware
$router->addMiddleware(function($classification) {
    if ($classification['confidence'] < 0.5) {
        $classification['requires_verification'] = true;
    }
    return $classification;
});
```

## Hierarchical Classification

For complex systems, use hierarchical classification to improve accuracy.

### Two-Level Classification

```php
class HierarchicalClassifier
{
    private IntentClassifierAgent $domainClassifier;
    private array $specificClassifiers = [];
    
    public function __construct(ClaudePhp $client)
    {
        // Level 1: Domain classifier
        $this->domainClassifier = new IntentClassifierAgent($client, [
            'name' => 'domain_classifier',
        ]);
        
        $this->domainClassifier->addIntent('sales', [
            'buy', 'purchase', 'price', 'cost'
        ]);
        
        $this->domainClassifier->addIntent('support', [
            'help', 'problem', 'issue', 'broken'
        ]);
        
        $this->domainClassifier->addIntent('account', [
            'login', 'password', 'profile', 'settings'
        ]);
        
        // Level 2: Create domain-specific classifiers
        $this->setupSalesClassifier($client);
        $this->setupSupportClassifier($client);
        $this->setupAccountClassifier($client);
    }
    
    private function setupSalesClassifier(ClaudePhp $client): void
    {
        $classifier = new IntentClassifierAgent($client, [
            'name' => 'sales_classifier',
        ]);
        
        $classifier->addIntent('product_info', [
            'tell me about', 'features', 'what is'
        ]);
        
        $classifier->addIntent('pricing', [
            'how much', 'price', 'cost'
        ]);
        
        $classifier->addIntent('purchase', [
            'buy now', 'purchase', 'order'
        ]);
        
        $this->specificClassifiers['sales'] = $classifier;
    }
    
    private function setupSupportClassifier(ClaudePhp $client): void
    {
        $classifier = new IntentClassifierAgent($client, [
            'name' => 'support_classifier',
        ]);
        
        $classifier->addIntent('technical_issue', [
            'not working', 'error', 'crash', 'bug'
        ]);
        
        $classifier->addIntent('how_to', [
            'how do I', 'show me', 'tutorial'
        ]);
        
        $classifier->addIntent('complaint', [
            'unhappy', 'disappointed', 'terrible'
        ]);
        
        $this->specificClassifiers['support'] = $classifier;
    }
    
    private function setupAccountClassifier(ClaudePhp $client): void
    {
        $classifier = new IntentClassifierAgent($client, [
            'name' => 'account_classifier',
        ]);
        
        $classifier->addIntent('login_issue', [
            'can\'t login', 'forgot password'
        ]);
        
        $classifier->addIntent('update_profile', [
            'change email', 'update information'
        ]);
        
        $this->specificClassifiers['account'] = $classifier;
    }
    
    public function classify(string $userInput): array
    {
        // Level 1: Classify domain
        $domainResult = $this->domainClassifier->run($userInput);
        $domainData = $domainResult->getMetadata();
        $domain = $domainData['intent'];
        
        // Level 2: Classify specific intent within domain
        if (isset($this->specificClassifiers[$domain])) {
            $specificResult = $this->specificClassifiers[$domain]->run($userInput);
            $specificData = $specificResult->getMetadata();
            
            return [
                'domain' => $domain,
                'domain_confidence' => $domainData['confidence'],
                'specific_intent' => $specificData['intent'],
                'specific_confidence' => $specificData['confidence'],
                'entities' => $specificData['entities'],
                'route' => "{$domain}/{$specificData['intent']}",
            ];
        }
        
        return [
            'domain' => $domain,
            'domain_confidence' => $domainData['confidence'],
            'specific_intent' => null,
            'route' => $domain,
        ];
    }
}
```

### Using Hierarchical Classification

```php
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$hierarchical = new HierarchicalClassifier($client);

$testInputs = [
    'How much does the premium plan cost?',
    'My app keeps crashing',
    'I forgot my password',
];

foreach ($testInputs as $input) {
    echo "\nUser: {$input}\n";
    
    $classification = $hierarchical->classify($input);
    
    echo "Domain: {$classification['domain']} ";
    echo "({$classification['domain_confidence']})\n";
    
    if ($classification['specific_intent']) {
        echo "Specific: {$classification['specific_intent']} ";
        echo "({$classification['specific_confidence']})\n";
    }
    
    echo "Route to: {$classification['route']}\n";
}
```

## Context-Aware Classification

Improve classification by considering conversation context.

### Building a Context Manager

```php
class ConversationContext
{
    private array $history = [];
    private int $maxHistory = 10;
    private array $userProfile = [];
    
    public function addTurn(string $input, array $classification): void
    {
        $this->history[] = [
            'input' => $input,
            'intent' => $classification['intent'],
            'timestamp' => time(),
        ];
        
        // Keep only recent history
        if (count($this->history) > $this->maxHistory) {
            array_shift($this->history);
        }
    }
    
    public function getRecentIntents(int $count = 3): array
    {
        $recent = array_slice($this->history, -$count);
        return array_map(fn($turn) => $turn['intent'], $recent);
    }
    
    public function getLastIntent(): ?string
    {
        if (empty($this->history)) {
            return null;
        }
        
        return end($this->history)['intent'];
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
        $recentIntents = $this->getRecentIntents(3);
        
        if (empty($recentIntents)) {
            return 'New conversation';
        }
        
        return 'Recent: ' . implode(' → ', $recentIntents);
    }
}
```

### Context-Aware Classifier

```php
class ContextAwareBot
{
    private IntentClassifierAgent $classifier;
    private ConversationContext $context;
    
    public function __construct(ClaudePhp $client)
    {
        $this->classifier = new IntentClassifierAgent($client);
        $this->context = new ConversationContext();
        $this->setupIntents();
    }
    
    private function setupIntents(): void
    {
        $this->classifier->addIntent('yes', ['yes', 'yeah', 'sure', 'ok']);
        $this->classifier->addIntent('no', ['no', 'nope', 'not really']);
        $this->classifier->addIntent('order_status', ['where is my order']);
        $this->classifier->addIntent('cancel_order', ['cancel order']);
    }
    
    public function process(string $userInput): string
    {
        $result = $this->classifier->run($userInput);
        $classification = $result->getMetadata();
        
        // Use context to interpret ambiguous intents
        $intent = $this->interpretWithContext($classification);
        
        // Store in history
        $this->context->addTurn($userInput, $classification);
        
        return $this->generateResponse($intent);
    }
    
    private function interpretWithContext(array $classification): string
    {
        $intent = $classification['intent'];
        $lastIntent = $this->context->getLastIntent();
        
        // Handle yes/no based on previous question
        if ($intent === 'yes' && $lastIntent === 'cancel_order') {
            return 'confirm_cancel_order';
        }
        
        if ($intent === 'yes' && $lastIntent === 'order_status') {
            return 'track_order';
        }
        
        return $intent;
    }
    
    private function generateResponse(string $intent): string
    {
        return match($intent) {
            'order_status' => 'Would you like me to track your order?',
            'track_order' => 'Looking up your order...',
            'cancel_order' => 'Are you sure you want to cancel?',
            'confirm_cancel_order' => 'Your order has been cancelled.',
            default => 'How can I help you?',
        };
    }
}
```

## Production Best Practices

### 1. Error Handling

```php
class RobustClassifier
{
    private IntentClassifierAgent $classifier;
    private int $maxRetries = 3;
    
    public function classifyWithRetry(string $input): ?array
    {
        $attempt = 0;
        
        while ($attempt < $this->maxRetries) {
            try {
                $result = $this->classifier->run($input);
                
                if ($result->isSuccess()) {
                    return $result->getMetadata();
                }
                
                error_log("Classification failed: {$result->getError()}");
                
            } catch (\Exception $e) {
                error_log("Exception in classification: {$e->getMessage()}");
            }
            
            $attempt++;
            sleep(pow(2, $attempt)); // Exponential backoff
        }
        
        return null;
    }
}
```

### 2. Caching

```php
class CachedClassifier
{
    private IntentClassifierAgent $classifier;
    private array $cache = [];
    private int $cacheSize = 1000;
    
    public function classify(string $input): array
    {
        $key = md5(strtolower(trim($input)));
        
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        $result = $this->classifier->run($input);
        $data = $result->getMetadata();
        
        // Add to cache
        $this->cache[$key] = $data;
        
        // Prevent cache from growing too large
        if (count($this->cache) > $this->cacheSize) {
            array_shift($this->cache);
        }
        
        return $data;
    }
}
```

### 3. Monitoring and Analytics

```php
class MonitoredClassifier
{
    private IntentClassifierAgent $classifier;
    private array $stats = [];
    
    public function classify(string $input): array
    {
        $startTime = microtime(true);
        
        $result = $this->classifier->run($input);
        $data = $result->getMetadata();
        
        $duration = microtime(true) - $startTime;
        
        // Track statistics
        $this->trackStats($data, $duration);
        
        return $data;
    }
    
    private function trackStats(array $data, float $duration): void
    {
        $intent = $data['intent'];
        
        if (!isset($this->stats[$intent])) {
            $this->stats[$intent] = [
                'count' => 0,
                'total_confidence' => 0,
                'total_duration' => 0,
            ];
        }
        
        $this->stats[$intent]['count']++;
        $this->stats[$intent]['total_confidence'] += $data['confidence'];
        $this->stats[$intent]['total_duration'] += $duration;
    }
    
    public function getStats(): array
    {
        $result = [];
        
        foreach ($this->stats as $intent => $data) {
            $result[$intent] = [
                'count' => $data['count'],
                'avg_confidence' => $data['total_confidence'] / $data['count'],
                'avg_duration_ms' => ($data['total_duration'] / $data['count']) * 1000,
            ];
        }
        
        return $result;
    }
}
```

### 4. Testing and Validation

```php
class ClassifierTester
{
    private IntentClassifierAgent $classifier;
    
    public function runTests(array $testCases): array
    {
        $results = [
            'total' => count($testCases),
            'correct' => 0,
            'incorrect' => 0,
            'low_confidence' => 0,
            'failures' => [],
        ];
        
        foreach ($testCases as $test) {
            $result = $this->classifier->run($test['input']);
            
            if (!$result->isSuccess()) {
                $results['failures'][] = [
                    'input' => $test['input'],
                    'error' => $result->getError(),
                ];
                continue;
            }
            
            $data = $result->getMetadata();
            
            if ($data['intent'] === $test['expected_intent']) {
                $results['correct']++;
            } else {
                $results['incorrect']++;
                $results['failures'][] = [
                    'input' => $test['input'],
                    'expected' => $test['expected_intent'],
                    'actual' => $data['intent'],
                    'confidence' => $data['confidence'],
                ];
            }
            
            if ($data['confidence'] < 0.7) {
                $results['low_confidence']++;
            }
        }
        
        $results['accuracy'] = $results['correct'] / $results['total'];
        
        return $results;
    }
}

// Example test cases
$testCases = [
    ['input' => 'Hello there!', 'expected_intent' => 'greeting'],
    ['input' => 'Goodbye!', 'expected_intent' => 'goodbye'],
    ['input' => 'Where is my order?', 'expected_intent' => 'order_status'],
];

$tester = new ClassifierTester($classifier);
$results = $tester->runTests($testCases);

echo "Accuracy: " . ($results['accuracy'] * 100) . "%\n";
```

## Real-World Example: E-Commerce Chatbot

Let's build a complete e-commerce chatbot that combines everything we've learned.

```php
class EcommerceBot
{
    private IntentClassifierAgent $classifier;
    private ConversationContext $context;
    private IntentRouter $router;
    
    public function __construct(ClaudePhp $client)
    {
        $this->classifier = new IntentClassifierAgent($client, [
            'confidence_threshold' => 0.65,
        ]);
        
        $this->context = new ConversationContext();
        $this->router = new IntentRouter($this->classifier);
        
        $this->setupIntentsAndEntities();
        $this->setupRoutes();
    }
    
    private function setupIntentsAndEntities(): void
    {
        // Browse intents
        $this->classifier->addIntent('browse_products', [
            'show me products', 'what do you have', 'browse catalog'
        ]);
        
        $this->classifier->addIntent('search_product', [
            'find', 'search for', 'looking for'
        ]);
        
        // Cart intents
        $this->classifier->addIntent('add_to_cart', [
            'add to cart', 'I\'ll take it', 'add this'
        ]);
        
        $this->classifier->addIntent('view_cart', [
            'show cart', 'what\'s in my cart', 'cart'
        ]);
        
        $this->classifier->addIntent('remove_from_cart', [
            'remove from cart', 'delete this', 'take it out'
        ]);
        
        // Order intents
        $this->classifier->addIntent('checkout', [
            'checkout', 'complete purchase', 'buy now'
        ]);
        
        $this->classifier->addIntent('order_status', [
            'where is my order', 'track order', 'order status'
        ]);
        
        // Entity types
        $this->classifier->addEntityType('product_name', 'Name of product');
        $this->classifier->addEntityType('category', 'Product category');
        $this->classifier->addEntityType('quantity', 'Number of items');
        $this->classifier->addEntityType('order_number', 'Order ID');
    }
    
    private function setupRoutes(): void
    {
        $this->router->addHandler('browse_products', function($data) {
            $category = $this->extractEntity($data['entities'], 'category');
            return $this->browseProducts($category);
        });
        
        $this->router->addHandler('search_product', function($data) {
            $productName = $this->extractEntity($data['entities'], 'product_name');
            return $this->searchProduct($productName);
        });
        
        $this->router->addHandler('add_to_cart', function($data) {
            $productName = $this->extractEntity($data['entities'], 'product_name');
            $quantity = $this->extractEntity($data['entities'], 'quantity', '1');
            return $this->addToCart($productName, (int)$quantity);
        });
        
        $this->router->addHandler('checkout', function($data) {
            return $this->checkout();
        });
    }
    
    private function extractEntity(array $entities, string $type, ?string $default = null): ?string
    {
        foreach ($entities as $entity) {
            if ($entity['type'] === $type) {
                return $entity['value'];
            }
        }
        return $default;
    }
    
    public function chat(string $userMessage): string
    {
        // Route the message
        $response = $this->router->route($userMessage);
        
        return is_array($response) ? $response['message'] : $response;
    }
    
    private function browseProducts(?string $category): array
    {
        return [
            'message' => $category 
                ? "Here are our {$category} products..."
                : "Here's our complete catalog...",
            'action' => 'show_product_list',
            'category' => $category,
        ];
    }
    
    private function searchProduct(?string $productName): array
    {
        if (!$productName) {
            return ['message' => 'What product are you looking for?'];
        }
        
        return [
            'message' => "Searching for {$productName}...",
            'action' => 'search',
            'query' => $productName,
        ];
    }
    
    private function addToCart(?string $productName, int $quantity): array
    {
        if (!$productName) {
            return ['message' => 'Which product would you like to add?'];
        }
        
        return [
            'message' => "Added {$quantity} {$productName} to your cart!",
            'action' => 'add_to_cart',
            'product' => $productName,
            'quantity' => $quantity,
        ];
    }
    
    private function checkout(): array
    {
        return [
            'message' => "Let's complete your purchase...",
            'action' => 'start_checkout',
        ];
    }
}

// Usage
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$bot = new EcommerceBot($client);

$conversation = [
    "Show me your laptops",
    "I'll take the MacBook Pro",
    "Add the wireless mouse too",
    "Proceed to checkout",
];

foreach ($conversation as $message) {
    echo "User: {$message}\n";
    $response = $bot->chat($message);
    echo "Bot: {$response}\n\n";
}
```

## Conclusion

You now have a comprehensive understanding of the IntentClassifierAgent! You've learned:

- ✅ How to classify user intents with confidence scores
- ✅ How to extract entities from unstructured text
- ✅ How to build production-ready chatbots and NLU systems
- ✅ How to implement routing and escalation logic
- ✅ How to use hierarchical classification for complex domains
- ✅ How to build context-aware conversational systems
- ✅ Best practices for production deployment

### Next Steps

1. **Experiment**: Try the examples with different intents and entities
2. **Integrate**: Connect the classifier to your application
3. **Monitor**: Track accuracy and confidence in production
4. **Iterate**: Refine intents based on real user inputs
5. **Scale**: Implement caching and hierarchical classification as needed

### Additional Resources

- [IntentClassifierAgent Documentation](../IntentClassifierAgent.md)
- [Basic Example](../../examples/intent_classifier.php)
- [Advanced Example](../../examples/advanced_intent_classifier.php)
- [Agent Selection Guide](../agent-selection-guide.md)

Happy building! 🚀

