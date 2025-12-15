# DialogAgent Documentation

## Overview

The `DialogAgent` is a conversational AI agent that manages multi-turn conversations with context awareness and session management. It enables natural dialogue flows where the agent remembers previous exchanges and maintains conversation state across multiple turns.

## Features

- 🗣️ **Multi-Turn Conversations**: Maintain coherent dialogues across multiple exchanges
- 🧠 **Context Awareness**: Automatically builds context from conversation history
- 📝 **Session Management**: Track and manage multiple conversation sessions
- 💾 **State Management**: Store and retrieve conversation state data
- 🔄 **Context Window**: Uses the last 5 turns for efficient context management
- 👥 **Multi-User Support**: Handle concurrent conversations with different users
- 🎯 **Single-Turn Mode**: Also supports one-off question-answer interactions

## Installation

The DialogAgent is included in the `claude-php-agent` package:

```bash
composer require your-org/claude-php-agent
```

## Basic Usage

```php
use ClaudeAgents\Agents\DialogAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$dialogAgent = new DialogAgent($client);

// Start a conversation
$session = $dialogAgent->startConversation();

// Have a multi-turn conversation
$response1 = $dialogAgent->turn('Hi! My name is Alice.', $session->getId());
$response2 = $dialogAgent->turn('What is my name?', $session->getId());
// The agent will remember: "Your name is Alice."
```

## Configuration

The DialogAgent accepts configuration options in its constructor:

```php
$dialogAgent = new DialogAgent($client, [
    'name' => 'my_dialog_agent',    // Agent name
    'logger' => $logger,             // PSR-3 logger instance
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | `'dialog_agent'` | Unique name for the agent |
| `logger` | LoggerInterface | `NullLogger` | PSR-3 compatible logger |

## Core Concepts

### Sessions

A **Session** represents a single conversation instance. Each session has:
- A unique ID
- Conversation history (turns)
- Custom state data
- Creation and last activity timestamps

```php
$session = $dialogAgent->startConversation();

// Access session properties
$sessionId = $session->getId();
$turnCount = $session->getTurnCount();
$createdAt = $session->getCreatedAt();
$lastActivity = $session->getLastActivity();
```

### Turns

A **Turn** represents one exchange in a conversation, consisting of:
- User input
- Agent response
- Timestamp
- Optional metadata

```php
$turns = $session->getTurns();

foreach ($turns as $turn) {
    echo "User: " . $turn->getUserInput() . "\n";
    echo "Agent: " . $turn->getAgentResponse() . "\n";
    echo "Time: " . date('Y-m-d H:i:s', (int)$turn->getTimestamp()) . "\n";
}
```

### State

**State** is a key-value store associated with each session, useful for:
- Storing user preferences
- Tracking conversation flow
- Maintaining custom data

```php
// Set multiple state values
$session->setState([
    'user_id' => '12345',
    'language' => 'en',
    'topic' => 'support'
]);

// Update a single value
$session->updateState('last_topic', 'billing');

// Retrieve state
$state = $session->getState();
```

## Using DialogAgent

### Starting a Conversation

```php
// Start with auto-generated session ID
$session = $dialogAgent->startConversation();

// Start with custom session ID
$session = $dialogAgent->startConversation('custom_session_id');
```

### Processing Turns

The `turn()` method processes user input and returns the agent's response:

```php
// Use the current session
$response = $dialogAgent->turn('Hello, how are you?');

// Use a specific session
$response = $dialogAgent->turn('Tell me more', $session->getId());
```

### Single-Turn Execution

For one-off interactions, use the `run()` method:

```php
$result = $dialogAgent->run('What is the capital of France?');

if ($result->isSuccess()) {
    echo $result->getAnswer();
    
    // Access the auto-created session
    $metadata = $result->getMetadata();
    $sessionId = $metadata['session_id'];
}
```

### Retrieving Sessions

```php
// Get a specific session
$session = $dialogAgent->getSession($sessionId);

if ($session) {
    echo "Session has {$session->getTurnCount()} turns\n";
}
```

## Context Management

The DialogAgent automatically manages conversation context by:

1. **Building Context**: Uses the last 5 turns to build context
2. **Including State**: Incorporates session state in the prompt
3. **Efficient Memory**: Prevents context from growing unbounded

```php
// The agent automatically builds context from recent turns
$session = $dialogAgent->startConversation();

$dialogAgent->turn('I have a dog named Max.', $session->getId());
$dialogAgent->turn('Max is 3 years old.', $session->getId());
$dialogAgent->turn('He loves playing fetch.', $session->getId());

// The agent remembers the context
$response = $dialogAgent->turn('Tell me about my dog.', $session->getId());
// Response will include information about Max
```

## Advanced Usage

### Multi-User Session Management

Use the `ConversationManager` for managing multiple user sessions:

```php
use ClaudeAgents\Conversation\ConversationManager;

$manager = new ConversationManager([
    'max_sessions' => 1000,
    'session_timeout' => 3600, // 1 hour
]);

// Create sessions for different users
$session1 = $manager->createSession('user_001');
$session2 = $manager->createSession('user_002');

// Use with DialogAgent
$dialogAgent->turn('Hello', $session1->getId());
$dialogAgent->turn('Hi there', $session2->getId());

// Retrieve sessions by user
$userSessions = $manager->getSessionsByUser('user_001');

// Delete expired sessions
$manager->deleteSession($sessionId);
```

### Guided Conversation Flows

Implement custom conversation flows with state tracking:

```php
class OnboardingFlow
{
    private DialogAgent $agent;
    private Session $session;
    private array $steps = ['welcome', 'name', 'role', 'interests'];
    
    public function processStep(string $userInput): bool
    {
        $currentStep = $this->session->getState()['step'] ?? 'welcome';
        
        // Store user's answer
        $this->session->updateState($currentStep . '_answer', $userInput);
        
        // Get agent response
        $response = $this->agent->turn($userInput, $this->session->getId());
        
        // Move to next step
        $nextStep = $this->getNextStep($currentStep);
        if ($nextStep) {
            $this->session->updateState('step', $nextStep);
            return false; // Not complete
        }
        
        return true; // Flow complete
    }
}
```

### Session Persistence

Sessions can be serialized for persistence:

```php
class SessionSerializer
{
    public static function export(Session $session): array
    {
        return [
            'id' => $session->getId(),
            'state' => $session->getState(),
            'turns' => array_map(fn($turn) => $turn->toArray(), $session->getTurns()),
            'created_at' => $session->getCreatedAt(),
            'last_activity' => $session->getLastActivity(),
        ];
    }
    
    public static function save(Session $session, string $filepath): void
    {
        $data = self::export($session);
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// Usage
$session = $dialogAgent->startConversation();
// ... have conversation ...
SessionSerializer::save($session, '/path/to/session.json');
```

### Concurrent Conversations

Handle multiple independent conversations simultaneously:

```php
$techSupport = $dialogAgent->startConversation('tech_support');
$sales = $dialogAgent->startConversation('sales');

// Interleave conversations
$dialogAgent->turn('My app is crashing', $techSupport->getId());
$dialogAgent->turn('What are your prices?', $sales->getId());
$dialogAgent->turn('It crashes when I export', $techSupport->getId());
$dialogAgent->turn('Do you have enterprise plans?', $sales->getId());

// Each maintains separate context
```

## Integration Examples

### Customer Support Chatbot

```php
class SupportBot
{
    private DialogAgent $agent;
    private ConversationManager $manager;
    
    public function handleMessage(string $userId, string $message): string
    {
        // Get or create user session
        $sessions = $this->manager->getSessionsByUser($userId);
        
        if (empty($sessions)) {
            $session = $this->manager->createSession($userId);
            $session->updateState('department', 'support');
        } else {
            $session = reset($sessions);
        }
        
        // Process message
        return $this->agent->turn($message, $session->getId());
    }
}
```

### Multi-Language Support

```php
$session = $dialogAgent->startConversation();
$session->updateState('language', 'es');

$response = $dialogAgent->turn('Hola, ¿cómo estás?', $session->getId());
// Agent will respond in Spanish based on state
```

### Conversation Analytics

```php
class ConversationAnalytics
{
    public function analyze(Session $session): array
    {
        $turns = $session->getTurns();
        
        return [
            'total_turns' => count($turns),
            'duration' => $session->getLastActivity() - $session->getCreatedAt(),
            'avg_user_length' => $this->averageLength($turns, 'user'),
            'avg_agent_length' => $this->averageLength($turns, 'agent'),
            'topics' => $this->extractTopics($turns),
        ];
    }
    
    private function averageLength(array $turns, string $role): float
    {
        $lengths = array_map(
            fn($turn) => strlen(
                $role === 'user' 
                    ? $turn->getUserInput() 
                    : $turn->getAgentResponse()
            ),
            $turns
        );
        
        return array_sum($lengths) / count($lengths);
    }
}
```

## Best Practices

### 1. Use Appropriate Session Management

```php
// For short-lived conversations (web requests)
$session = $dialogAgent->startConversation();
// Process turn(s)
// Let session be garbage collected

// For long-lived conversations (chat apps)
$manager = new ConversationManager();
$session = $manager->createSession($userId);
// Manager handles cleanup
```

### 2. Leverage State for Context

```php
// Store important context in state
$session->updateState('user_name', $userName);
$session->updateState('issue_type', 'billing');
$session->updateState('priority', 'high');

// This helps the agent provide better responses
$response = $dialogAgent->turn($message, $session->getId());
```

### 3. Handle Session Expiry

```php
$session = $dialogAgent->getSession($sessionId);

if (!$session) {
    // Session expired or doesn't exist
    echo "Your session has expired. Starting a new conversation.\n";
    $session = $dialogAgent->startConversation();
}
```

### 4. Monitor Conversation Length

```php
$session = $dialogAgent->getSession($sessionId);

if ($session->getTurnCount() > 50) {
    // Consider starting a new session or summarizing
    echo "This conversation is getting long. Would you like to start fresh?\n";
}
```

### 5. Use Meaningful Session IDs

```php
// For user-specific conversations
$sessionId = "user_{$userId}_" . date('Ymd');
$session = $dialogAgent->startConversation($sessionId);

// For topic-specific conversations
$sessionId = "support_ticket_{$ticketId}";
$session = $dialogAgent->startConversation($sessionId);
```

## API Reference

### DialogAgent Methods

#### `__construct(ClaudePhp $client, array $options = [])`
Create a new DialogAgent instance.

**Parameters:**
- `$client`: ClaudePhp client instance
- `$options`: Configuration options (name, logger)

#### `startConversation(?string $sessionId = null): Session`
Start a new conversation session.

**Parameters:**
- `$sessionId`: Optional custom session ID

**Returns:** Session object

#### `turn(string $userInput, ?string $sessionId = null): string`
Process a turn in the conversation.

**Parameters:**
- `$userInput`: User's message
- `$sessionId`: Optional session ID (uses current session if not provided)

**Returns:** Agent's response string

#### `getSession(string $sessionId): ?Session`
Retrieve a session by ID.

**Parameters:**
- `$sessionId`: Session identifier

**Returns:** Session object or null if not found

#### `run(string $task): AgentResult`
Execute a single-turn interaction.

**Parameters:**
- `$task`: User's task or question

**Returns:** AgentResult with answer and metadata

#### `getName(): string`
Get the agent name.

**Returns:** Agent name string

### Session Methods

#### `getId(): string`
Get the session ID.

#### `getTurns(): array`
Get all turns in the session.

**Returns:** Array of Turn objects

#### `getTurnCount(): int`
Get the number of turns in the session.

#### `getState(): array`
Get the entire session state.

#### `setState(array $state): void`
Set the entire session state.

#### `updateState(string $key, mixed $value): void`
Update a single state value.

#### `getCreatedAt(): float`
Get session creation timestamp.

#### `getLastActivity(): ?float`
Get last activity timestamp.

### Turn Methods

#### `getId(): string`
Get the turn ID.

#### `getUserInput(): string`
Get the user's input.

#### `getAgentResponse(): string`
Get the agent's response.

#### `getMetadata(): array`
Get turn metadata.

#### `getTimestamp(): float`
Get turn timestamp.

#### `toArray(): array`
Convert turn to array representation.

### ConversationManager Methods

#### `createSession(?string $userId = null): Session`
Create a new session, optionally associated with a user.

#### `getSession(string $sessionId): ?Session`
Retrieve a session by ID.

#### `deleteSession(string $sessionId): bool`
Delete a session.

#### `getSessionsByUser(string $userId): array`
Get all sessions for a specific user.

## Error Handling

```php
try {
    $response = $dialogAgent->turn($userInput, $sessionId);
} catch (\Exception $e) {
    // Handle API errors, network issues, etc.
    error_log("Dialog error: " . $e->getMessage());
    
    // Provide fallback response
    $response = "I'm having trouble processing your request. Please try again.";
}
```

## Performance Considerations

### Context Window Size

The DialogAgent uses the last 5 turns for context. This is a balance between:
- **Performance**: Smaller context = faster API calls
- **Memory**: Fewer tokens = lower costs
- **Coherence**: Enough context for meaningful conversations

### Session Cleanup

```php
// Use ConversationManager for automatic cleanup
$manager = new ConversationManager([
    'session_timeout' => 3600,  // 1 hour
    'max_sessions' => 1000,
]);

// Or manually clean up
if ($session->getLastActivity() < time() - 3600) {
    // Session is stale, create new one
    $session = $dialogAgent->startConversation();
}
```

## Troubleshooting

### Agent Doesn't Remember Context

**Problem**: Agent seems to forget previous turns.

**Solutions**:
1. Verify you're using the correct session ID
2. Check that turns are being recorded
3. Ensure session isn't being recreated

```php
$session = $dialogAgent->getSession($sessionId);
echo "Turn count: " . $session->getTurnCount() . "\n";
```

### Multiple Conversations Getting Mixed

**Problem**: Context from different conversations is bleeding together.

**Solution**: Use separate session IDs

```php
// DON'T do this
$singleSession = $dialogAgent->startConversation();
// Use for all users

// DO this
$userSession = $dialogAgent->startConversation("user_{$userId}");
```

### Session Not Found

**Problem**: `getSession()` returns null.

**Solutions**:
1. Session may have been garbage collected
2. Session ID might be incorrect
3. Session may have expired (if using ConversationManager)

```php
$session = $dialogAgent->getSession($sessionId);
if (!$session) {
    // Start new session
    $session = $dialogAgent->startConversation();
}
```

## See Also

- [DialogAgent Tutorial](tutorials/DialogAgent_Tutorial.md)
- [Basic Example](../examples/dialog_agent.php)
- [Advanced Example](../examples/advanced_dialog_agent.php)
- [ConversationalInterface](../src/Contracts/ConversationalInterface.php)

## Additional Resources

- [Claude API Documentation](https://docs.anthropic.com/)
- [Agent Selection Guide](agent-selection-guide.md)
- [claude-php-agent Repository](https://github.com/your-org/claude-php-agent)

