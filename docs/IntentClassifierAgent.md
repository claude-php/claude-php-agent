# IntentClassifierAgent Documentation

## Overview

The `IntentClassifierAgent` is an NLU (Natural Language Understanding) agent that classifies user intents and extracts entities from text input. Similar to systems like Rasa NLU or Dialogflow, it analyzes user input to determine what the user wants to accomplish and extracts relevant information.

## Features

- 🎯 **Intent Classification**: Identifies user intentions with confidence scores
- 📦 **Entity Extraction**: Extracts structured data from unstructured text
- 🔧 **Dynamic Management**: Add/remove intents and entity types at runtime
- 💯 **Confidence Thresholds**: Configurable thresholds for fallback handling
- 📚 **Training Examples**: Support for example-based intent definitions
- 🌍 **Multi-Language**: Works with text in multiple languages
- 🔄 **Rasa-Style**: Familiar API for Rasa NLU users
- 🎨 **Custom Entities**: Define application-specific entity types

## Installation

The IntentClassifierAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\IntentClassifierAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$classifier = new IntentClassifierAgent($client);

// Define intents
$classifier->addIntent('greeting', [
    'Hello',
    'Hi there',
    'Good morning'
]);

$classifier->addIntent('goodbye', [
    'Bye',
    'See you later',
    'Goodbye'
]);

// Classify user input
$result = $classifier->run('Hi! How are you?');

$data = $result->getMetadata();
echo "Intent: {$data['intent']}\n";          // "greeting"
echo "Confidence: {$data['confidence']}\n";  // 0.95
```

## Configuration

The IntentClassifierAgent accepts configuration options in its constructor:

```php
$classifier = new IntentClassifierAgent($client, [
    'name' => 'my_classifier',
    'intents' => [
        'greeting' => [
            'examples' => ['Hello', 'Hi'],
            'description' => 'User greeting',
        ],
    ],
    'entity_types' => [
        'product_name' => 'Name of a product',
        'order_number' => 'Customer order number',
    ],
    'confidence_threshold' => 0.7,
    'fallback_intent' => 'unknown',
    'logger' => $logger,
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'intent_classifier'` | Unique name for the agent |
| `intents` | array | `[]` | Initial intent definitions |
| `entity_types` | array | `[]` | Custom entity types to extract |
| `confidence_threshold` | float | `0.5` | Minimum confidence for classification |
| `fallback_intent` | string | `'unknown'` | Intent when confidence is too low |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Core Concepts

### Intents

An **intent** represents what the user wants to accomplish. Each intent can have:
- A unique name
- Training examples
- A description

```php
$classifier->addIntent(
    'book_flight',
    examples: [
        'I want to book a flight',
        'Book me a ticket to Paris',
        'I need to fly to London',
    ],
    description: 'User wants to book a flight'
);
```

### Entities

**Entities** are structured pieces of information extracted from user input:

```php
// Add custom entity types
$classifier->addEntityType('destination', 'City or country name');
$classifier->addEntityType('date', 'Travel date');

$result = $classifier->run('I want to fly to Tokyo on March 15th');

$data = $result->getMetadata();
// Entities: [
//   ['type' => 'destination', 'value' => 'Tokyo'],
//   ['type' => 'date', 'value' => 'March 15th']
// ]
```

Standard entity types extracted automatically:
- Person names
- Dates and times
- Locations
- Numbers
- Email addresses
- Phone numbers

### Confidence Scores

Each classification includes a confidence score (0.0 to 1.0):

```php
$classifier = new IntentClassifierAgent($client, [
    'confidence_threshold' => 0.7,
    'fallback_intent' => 'unclear',
]);

$result = $classifier->run('Hmm...');
$data = $result->getMetadata();

if ($data['confidence'] < 0.7) {
    // Falls back to 'unclear' intent
    // Original intent available in $data['original_intent']
}
```

## Using IntentClassifierAgent

### Adding Intents

```php
// Simple intent
$classifier->addIntent('greeting', ['Hello', 'Hi']);

// Intent with description
$classifier->addIntent(
    'help',
    ['I need help', 'Can you help me'],
    'User requests assistance'
);

// Intent without examples (rely on name and description)
$classifier->addIntent(
    'cancel_subscription',
    [],
    'User wants to cancel their subscription'
);
```

### Managing Intents

```php
// Get all intents
$intents = $classifier->getIntents();

// Remove an intent
$classifier->removeIntent('greeting');

// Check if intent exists
if (isset($classifier->getIntents()['greeting'])) {
    // Intent exists
}
```

### Entity Extraction

```php
// Define custom entities
$classifier->addEntityType('product_name', 'Name of product');
$classifier->addEntityType('quantity', 'Number of items');
$classifier->addEntityType('color', 'Color preference');

$result = $classifier->run('I want to buy 3 red t-shirts');
$data = $result->getMetadata();

foreach ($data['entities'] as $entity) {
    echo "{$entity['type']}: {$entity['value']}\n";
}
// Output:
// quantity: 3
// color: red
// product_name: t-shirts
```

### Classification Results

```php
$result = $classifier->run('Book a flight to Paris');

if ($result->isSuccess()) {
    $data = $result->getMetadata();
    
    echo "Intent: {$data['intent']}\n";
    echo "Confidence: {$data['confidence']}\n";
    
    foreach ($data['entities'] as $entity) {
        echo "Entity: {$entity['type']} = {$entity['value']}\n";
    }
} else {
    echo "Error: {$result->getError()}\n";
}
```

The result metadata includes:
- `intent`: Classified intent name
- `confidence`: Confidence score (0.0-1.0)
- `entities`: Array of extracted entities
- `original_intent`: (if fallback applied) Original intent before threshold

## Advanced Usage

### Building a Chatbot

```php
class SimpleChatbot
{
    private IntentClassifierAgent $classifier;
    private array $handlers;
    
    public function __construct(IntentClassifierAgent $classifier)
    {
        $this->classifier = $classifier;
        $this->setupIntents();
        $this->setupHandlers();
    }
    
    private function setupIntents(): void
    {
        $this->classifier->addIntent('greeting', [
            'Hello', 'Hi', 'Hey'
        ]);
        
        $this->classifier->addIntent('help', [
            'Help', 'I need help', 'Can you help'
        ]);
        
        $this->classifier->addIntent('order_status', [
            'Where is my order', 'Track my package'
        ]);
        
        $this->classifier->addEntityType('order_number', 'Order number');
    }
    
    private function setupHandlers(): void
    {
        $this->handlers = [
            'greeting' => fn() => 'Hello! How can I help you today?',
            'help' => fn() => 'I can help you with orders, returns, and product info.',
            'order_status' => fn($entities) => $this->handleOrderStatus($entities),
        ];
    }
    
    private function handleOrderStatus(array $entities): string
    {
        foreach ($entities as $entity) {
            if ($entity['type'] === 'order_number') {
                return "Looking up order {$entity['value']}...";
            }
        }
        return "Please provide your order number.";
    }
    
    public function respond(string $userInput): string
    {
        $result = $this->classifier->run($userInput);
        
        if (!$result->isSuccess()) {
            return "I'm having trouble understanding. Can you rephrase that?";
        }
        
        $data = $result->getMetadata();
        $intent = $data['intent'];
        
        if (isset($this->handlers[$intent])) {
            return $this->handlers[$intent]($data['entities']);
        }
        
        return "I'm not sure how to help with that.";
    }
}

// Usage
$chatbot = new SimpleChatbot($classifier);
echo $chatbot->respond("Hello!");
echo $chatbot->respond("Where is order #12345?");
```

### Intent Routing

```php
class IntentRouter
{
    private IntentClassifierAgent $classifier;
    private array $routes = [];
    
    public function addRoute(string $intent, callable $handler): void
    {
        $this->routes[$intent] = $handler;
    }
    
    public function route(string $userInput): mixed
    {
        $result = $this->classifier->run($userInput);
        $data = $result->getMetadata();
        
        $handler = $this->routes[$data['intent']] ?? null;
        
        if ($handler) {
            return $handler($data);
        }
        
        return $this->handleUnknown($data);
    }
    
    private function handleUnknown(array $data): string
    {
        return "I couldn't understand that (confidence: {$data['confidence']})";
    }
}

// Usage
$router = new IntentRouter($classifier);

$router->addRoute('order_status', function($data) {
    return OrderService::checkStatus($data['entities']);
});

$router->addRoute('cancel_order', function($data) {
    return OrderService::cancel($data['entities']);
});

$response = $router->route($userInput);
```

### Hierarchical Classification

```php
// Level 1: Domain classifier
$domainClassifier = new IntentClassifierAgent($client);
$domainClassifier->addIntent('sales', ['buy', 'purchase', 'price']);
$domainClassifier->addIntent('support', ['help', 'problem', 'issue']);

// Level 2: Domain-specific classifiers
$salesClassifier = new IntentClassifierAgent($client);
$salesClassifier->addIntent('product_info', ['tell me about', 'features']);
$salesClassifier->addIntent('pricing', ['how much', 'cost']);

$supportClassifier = new IntentClassifierAgent($client);
$supportClassifier->addIntent('technical', ['not working', 'error']);
$supportClassifier->addIntent('account', ['login', 'password']);

// Classify in two stages
function classifyHierarchical(string $input): array
{
    global $domainClassifier, $salesClassifier, $supportClassifier;
    
    $domainResult = $domainClassifier->run($input);
    $domain = $domainResult->getMetadata()['intent'];
    
    $specificClassifier = match($domain) {
        'sales' => $salesClassifier,
        'support' => $supportClassifier,
        default => null,
    };
    
    if ($specificClassifier) {
        $specificResult = $specificClassifier->run($input);
        return [
            'domain' => $domain,
            'specific_intent' => $specificResult->getMetadata()['intent'],
        ];
    }
    
    return ['domain' => $domain, 'specific_intent' => null];
}
```

### Context-Aware Classification

```php
class ContextualClassifier
{
    private IntentClassifierAgent $classifier;
    private array $conversationHistory = [];
    
    public function classify(string $input): array
    {
        $result = $this->classifier->run($input);
        $data = $result->getMetadata();
        
        // Enhance with context
        $data['context'] = [
            'previous_intents' => $this->getPreviousIntents(),
            'conversation_length' => count($this->conversationHistory),
        ];
        
        $this->conversationHistory[] = $data;
        
        return $data;
    }
    
    private function getPreviousIntents(): array
    {
        return array_map(
            fn($h) => $h['intent'],
            array_slice($this->conversationHistory, -3)
        );
    }
}
```

### Multi-Language Support

```php
$classifier = new IntentClassifierAgent($client);

// Add examples in multiple languages
$classifier->addIntent('greeting', [
    'Hello', 'Hi',           // English
    'Bonjour', 'Salut',      // French
    'Hola', 'Buenos días',   // Spanish
    'Ciao', 'Buongiorno',    // Italian
]);

$result = $classifier->run('Bonjour! Comment allez-vous?');
$data = $result->getMetadata();
echo $data['intent']; // "greeting"
```

### Confidence-Based Escalation

```php
function handleUserInput(string $input): string
{
    global $classifier;
    
    $result = $classifier->run($input);
    $data = $result->getMetadata();
    $confidence = $data['confidence'];
    
    if ($confidence >= 0.85) {
        // High confidence - handle automatically
        return handleIntent($data['intent'], $data['entities']);
    } elseif ($confidence >= 0.65) {
        // Medium confidence - ask for clarification
        return "Did you mean to {$data['intent']}? (Yes/No)";
    } else {
        // Low confidence - escalate to human
        return "Let me connect you with a human agent...";
    }
}
```

## Integration Examples

### Customer Support System

```php
class SupportSystem
{
    private IntentClassifierAgent $classifier;
    
    public function __construct(ClaudePhp $client)
    {
        $this->classifier = new IntentClassifierAgent($client, [
            'confidence_threshold' => 0.65,
        ]);
        
        $this->setupSupportIntents();
    }
    
    private function setupSupportIntents(): void
    {
        $this->classifier->addIntent('account_issue', [
            'can\'t login', 'locked account', 'forgot password'
        ]);
        
        $this->classifier->addIntent('billing_question', [
            'billing', 'charged', 'refund', 'payment'
        ]);
        
        $this->classifier->addIntent('technical_problem', [
            'not working', 'error', 'crash', 'bug'
        ]);
        
        $this->classifier->addEntityType('account_number');
        $this->classifier->addEntityType('error_code');
    }
    
    public function routeTicket(string $customerMessage): array
    {
        $result = $this->classifier->run($customerMessage);
        $data = $result->getMetadata();
        
        return [
            'department' => $this->getDepartment($data['intent']),
            'priority' => $this->getPriority($data),
            'extracted_data' => $data['entities'],
        ];
    }
    
    private function getDepartment(string $intent): string
    {
        return match($intent) {
            'account_issue' => 'account_services',
            'billing_question' => 'billing',
            'technical_problem' => 'tech_support',
            default => 'general',
        };
    }
    
    private function getPriority(array $data): string
    {
        if ($data['confidence'] < 0.5) {
            return 'high'; // Unclear requests need attention
        }
        
        if (in_array($data['intent'], ['account_issue', 'billing_question'])) {
            return 'high';
        }
        
        return 'normal';
    }
}
```

### Voice Assistant

```php
class VoiceAssistant
{
    private IntentClassifierAgent $classifier;
    
    public function __construct(ClaudePhp $client)
    {
        $this->classifier = new IntentClassifierAgent($client);
        $this->setupVoiceIntents();
    }
    
    private function setupVoiceIntents(): void
    {
        $this->classifier->addIntent('play_music', [
            'play', 'play music', 'play song'
        ]);
        
        $this->classifier->addIntent('set_alarm', [
            'set alarm', 'wake me up', 'alarm for'
        ]);
        
        $this->classifier->addIntent('weather_query', [
            'weather', 'temperature', 'forecast'
        ]);
        
        $this->classifier->addEntityType('song_name');
        $this->classifier->addEntityType('artist');
        $this->classifier->addEntityType('time');
        $this->classifier->addEntityType('location');
    }
    
    public function processVoiceCommand(string $transcribedText): string
    {
        $result = $this->classifier->run($transcribedText);
        
        if (!$result->isSuccess()) {
            return "I didn't understand that.";
        }
        
        $data = $result->getMetadata();
        
        return match($data['intent']) {
            'play_music' => $this->playMusic($data['entities']),
            'set_alarm' => $this->setAlarm($data['entities']),
            'weather_query' => $this->getWeather($data['entities']),
            default => "I can't do that yet.",
        };
    }
}
```

## Best Practices

### 1. Provide Quality Training Examples

```php
// Good: Diverse, natural examples
$classifier->addIntent('book_appointment', [
    'I need to book an appointment',
    'Schedule a meeting for me',
    'Can I see the doctor tomorrow',
    'Book me in for next week',
]);

// Bad: Repetitive or too similar
$classifier->addIntent('book_appointment', [
    'book appointment',
    'book an appointment',
    'book appointments',
]);
```

### 2. Use Meaningful Intent Names

```php
// Good: Clear and specific
$classifier->addIntent('cancel_subscription');
$classifier->addIntent('check_order_status');

// Bad: Vague or too general
$classifier->addIntent('action1');
$classifier->addIntent('query');
```

### 3. Set Appropriate Confidence Thresholds

```php
// For critical actions: higher threshold
$financialClassifier = new IntentClassifierAgent($client, [
    'confidence_threshold' => 0.85,
]);

// For general chat: lower threshold
$casualChatClassifier = new IntentClassifierAgent($client, [
    'confidence_threshold' => 0.6,
]);
```

### 4. Handle Low Confidence Gracefully

```php
$result = $classifier->run($userInput);
$data = $result->getMetadata();

if ($data['confidence'] < 0.5) {
    echo "I'm not sure I understood. Could you rephrase that?\n";
} elseif ($data['confidence'] < 0.7) {
    echo "Did you mean to {$data['intent']}?\n";
} else {
    // Process normally
}
```

### 5. Combine with Dialog Management

```php
// Intent classification is just the first step
$intentResult = $classifier->run($userInput);
$intent = $intentResult->getMetadata()['intent'];

// Use dialog agent for full conversation
$dialogAgent->handleIntent($intent, $intentResult->getMetadata());
```

## API Reference

### IntentClassifierAgent Methods

#### `__construct(ClaudePhp $client, array $options = [])`
Create a new IntentClassifierAgent instance.

**Parameters:**
- `$client`: ClaudePhp client instance
- `$options`: Configuration options

#### `run(string $task): AgentResult`
Classify intent from user input.

**Parameters:**
- `$task`: User input text to classify

**Returns:** AgentResult with classification metadata

#### `addIntent(string $intent, array $examples = [], ?string $description = null): void`
Add a new intent to the classifier.

**Parameters:**
- `$intent`: Intent name
- `$examples`: Training examples (optional)
- `$description`: Intent description (optional)

#### `addEntityType(string $entityType, ?string $description = null): void`
Add a custom entity type to extract.

**Parameters:**
- `$entityType`: Entity type name
- `$description`: Description to aid extraction (optional)

#### `removeIntent(string $intent): void`
Remove an intent from the classifier.

**Parameters:**
- `$intent`: Intent name to remove

#### `getIntents(): array`
Get all configured intents.

**Returns:** Map of intent names to configurations

#### `getEntityTypes(): array`
Get all configured entity types.

**Returns:** Map of entity type names to descriptions

#### `getName(): string`
Get the agent name.

**Returns:** Agent name string

### Classification Result Structure

```php
[
    'intent' => 'booking_request',
    'confidence' => 0.92,
    'entities' => [
        ['type' => 'destination', 'value' => 'Paris'],
        ['type' => 'date', 'value' => '2024-03-15'],
    ],
    'original_intent' => 'booking_request',  // Only if fallback applied
]
```

## Performance Considerations

### Optimize Intent Count

```php
// Good: Focused, specific intents (5-20 intents)
$classifier->addIntent('order_status');
$classifier->addIntent('order_cancel');
$classifier->addIntent('order_return');

// Consider: Too many intents (>50) may reduce accuracy
// Use hierarchical classification instead
```

### Cache Classifications

```php
class CachedClassifier
{
    private IntentClassifierAgent $classifier;
    private array $cache = [];
    
    public function classify(string $input): array
    {
        $key = md5($input);
        
        if (!isset($this->cache[$key])) {
            $result = $this->classifier->run($input);
            $this->cache[$key] = $result->getMetadata();
        }
        
        return $this->cache[$key];
    }
}
```

## Troubleshooting

### Low Classification Accuracy

**Problem**: Classifier frequently misclassifies intents.

**Solutions**:
1. Add more diverse training examples
2. Make intent definitions more distinct
3. Use descriptions to clarify intent meaning
4. Consider splitting ambiguous intents

### Entities Not Extracted

**Problem**: Expected entities are missing.

**Solutions**:
1. Define custom entity types explicitly
2. Add descriptions to entity types
3. Check entity values in the response
4. Verify input contains the expected information

### High API Costs

**Problem**: Too many API calls.

**Solutions**:
1. Cache classification results for identical inputs
2. Use batch processing where possible
3. Implement client-side rule matching for simple cases
4. Consider hierarchical classification to reduce calls

## See Also

- [IntentClassifierAgent Tutorial](tutorials/IntentClassifierAgent_Tutorial.md)
- [Basic Example](../examples/intent_classifier.php)
- [Advanced Example](../examples/advanced_intent_classifier.php)
- [Agent Selection Guide](agent-selection-guide.md)

## Additional Resources

- [Claude API Documentation](https://docs.anthropic.com/)
- [Rasa NLU Documentation](https://rasa.com/docs/rasa/nlu/)
- [claude-php-agent Repository](https://github.com/your-org/claude-php-agent)

