# DialogAgent Tutorial: Building Conversational AI Applications

## Introduction

This tutorial will guide you through building production-ready conversational AI applications using the DialogAgent. We'll start with basic concepts and progress to advanced patterns used in real-world chatbots, customer support systems, and interactive applications.

By the end of this tutorial, you'll be able to:
- Create and manage multi-turn conversations
- Implement context-aware dialogues
- Build custom conversation flows
- Handle multiple concurrent users
- Persist and recover conversation sessions
- Integrate DialogAgent into real applications

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and object-oriented programming

## Table of Contents

1. [Getting Started](#getting-started)
2. [Your First Conversation](#your-first-conversation)
3. [Understanding Context and State](#understanding-context-and-state)
4. [Managing Multiple Sessions](#managing-multiple-sessions)
5. [Building Guided Conversation Flows](#building-guided-conversation-flows)
6. [Session Persistence](#session-persistence)
7. [Real-World Applications](#real-world-applications)
8. [Production Best Practices](#production-best-practices)

## Getting Started

### Installation

First, ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to test the DialogAgent:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\DialogAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the dialog agent
$dialogAgent = new DialogAgent($client, [
    'name' => 'tutorial_agent',
]);

echo "Dialog agent ready!\n";
```

## Your First Conversation

Let's create a basic multi-turn conversation to understand the fundamentals.

### Step 1: Starting a Conversation

Every conversation begins with a session:

```php
// Start a new conversation
$session = $dialogAgent->startConversation();

echo "Conversation started: {$session->getId()}\n";
```

The session object manages:
- Conversation history (turns)
- Custom state data
- Timestamps and metadata

### Step 2: Processing Turns

A "turn" is one exchange in the conversation (user input + agent response):

```php
// First turn
$response1 = $dialogAgent->turn('Hello! My name is Alice.', $session->getId());
echo "User: Hello! My name is Alice.\n";
echo "Agent: {$response1}\n\n";

// Second turn - the agent remembers the context
$response2 = $dialogAgent->turn('What is my name?', $session->getId());
echo "User: What is my name?\n";
echo "Agent: {$response2}\n";
// Output: "Your name is Alice."
```

The magic here is that the agent **remembers** the previous turn. The DialogAgent automatically builds context from recent conversation history.

### Step 3: Complete Example

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\DialogAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));
$dialogAgent = new DialogAgent($client);

// Start conversation
$session = $dialogAgent->startConversation();

// Have a conversation
$conversations = [
    "Hi! I'm planning a trip to Japan.",
    "I'll be there for two weeks.",
    "What cities should I visit?",
    "Thanks for the suggestions!",
];

foreach ($conversations as $userInput) {
    echo "You: {$userInput}\n";
    $response = $dialogAgent->turn($userInput, $session->getId());
    echo "Agent: {$response}\n\n";
}

echo "Conversation had {$session->getTurnCount()} turns.\n";
```

**Output:**
```
You: Hi! I'm planning a trip to Japan.
Agent: That's wonderful! Japan is an incredible destination...

You: I'll be there for two weeks.
Agent: Two weeks is a great amount of time to explore Japan...

You: What cities should I visit?
Agent: With two weeks for your Japan trip, I'd recommend...

You: Thanks for the suggestions!
Agent: You're welcome! Have a fantastic time in Japan...

Conversation had 4 turns.
```

## Understanding Context and State

### How Context Works

The DialogAgent maintains context by:

1. **Storing Turn History**: Every exchange is recorded
2. **Building Context**: Uses the last 5 turns to create context
3. **Including State**: Incorporates session state in responses

```php
$session = $dialogAgent->startConversation();

// Build context over multiple turns
$dialogAgent->turn('I have a dog named Max.', $session->getId());
$dialogAgent->turn('Max is a golden retriever.', $session->getId());
$dialogAgent->turn('He is 3 years old.', $session->getId());

// Agent remembers all this context
$response = $dialogAgent->turn('Tell me about my dog.', $session->getId());
// Response will include: name, breed, and age
```

### Using Session State

State is perfect for storing structured data:

```php
$session = $dialogAgent->startConversation();

// Set initial state
$session->setState([
    'user_name' => 'Bob',
    'language_preference' => 'en',
    'topic_interest' => 'technology',
]);

// Or update individual values
$session->updateState('last_topic', 'AI');
$session->updateState('conversation_type', 'support');

// Retrieve state
$state = $session->getState();
echo "User: {$state['user_name']}\n";
echo "Language: {$state['language_preference']}\n";
```

### Context vs. State: When to Use What

**Use Context (Turn History) for:**
- Natural conversation flow
- Recent exchanges
- User's questions and agent's answers

**Use State for:**
- User profile information
- Preferences and settings
- Conversation flow tracking
- Structured data

### Example: Combining Context and State

```php
class CustomerSupportBot
{
    private DialogAgent $agent;
    
    public function startSupport(string $userId, string $issueType): Session
    {
        $session = $this->agent->startConversation();
        
        // Store structured data in state
        $session->setState([
            'user_id' => $userId,
            'issue_type' => $issueType,
            'priority' => 'normal',
            'started_at' => time(),
        ]);
        
        return $session;
    }
    
    public function processMessage(Session $session, string $message): string
    {
        // Context is automatically built from turn history
        // State provides additional context to the agent
        return $this->agent->turn($message, $session->getId());
    }
}
```

## Managing Multiple Sessions

Real applications need to handle multiple concurrent conversations.

### The Problem

```php
// DON'T do this - single session for all users
$globalSession = $dialogAgent->startConversation();

function handleUserMessage($userId, $message) use ($dialogAgent, $globalSession) {
    // This mixes all user conversations together! ❌
    return $dialogAgent->turn($message, $globalSession->getId());
}
```

### The Solution: ConversationManager

```php
use ClaudeAgents\Conversation\ConversationManager;

// Create a conversation manager
$manager = new ConversationManager([
    'max_sessions' => 1000,      // Maximum active sessions
    'session_timeout' => 3600,   // 1 hour timeout
]);

// Create sessions for different users
$aliceSession = $manager->createSession('user_alice');
$bobSession = $manager->createSession('user_bob');

// Each user has independent conversations
$dialogAgent->turn("I like pizza", $aliceSession->getId());
$dialogAgent->turn("I like sushi", $bobSession->getId());

// Context is maintained separately
$response1 = $dialogAgent->turn("What do I like?", $aliceSession->getId());
// Response: "You like pizza"

$response2 = $dialogAgent->turn("What do I like?", $bobSession->getId());
// Response: "You like sushi"
```

### Practical Example: Chat Application

```php
class ChatApplication
{
    private DialogAgent $agent;
    private ConversationManager $manager;
    
    public function __construct(ClaudePhp $client)
    {
        $this->agent = new DialogAgent($client);
        $this->manager = new ConversationManager([
            'max_sessions' => 5000,
            'session_timeout' => 7200, // 2 hours
        ]);
    }
    
    public function handleMessage(string $userId, string $message): string
    {
        // Get or create user session
        $sessions = $this->manager->getSessionsByUser($userId);
        
        if (empty($sessions)) {
            $session = $this->manager->createSession($userId);
            $session->updateState('user_id', $userId);
            $session->updateState('started_at', time());
        } else {
            $session = reset($sessions);
        }
        
        // Process message
        return $this->agent->turn($message, $session->getId());
    }
    
    public function endConversation(string $userId): void
    {
        $sessions = $this->manager->getSessionsByUser($userId);
        foreach ($sessions as $session) {
            $this->manager->deleteSession($session->getId());
        }
    }
}

// Usage
$chat = new ChatApplication($client);

echo $chat->handleMessage('alice', 'Hello!') . "\n";
echo $chat->handleMessage('bob', 'Hi there!') . "\n";
echo $chat->handleMessage('alice', 'How are you?') . "\n";
// Each user maintains separate context
```

### Session Retrieval and Management

```php
// Get all sessions for a user
$userSessions = $manager->getSessionsByUser('user_123');
echo "User has " . count($userSessions) . " active sessions\n";

// Get specific session
$session = $manager->getSession($sessionId);

// Delete session
$manager->deleteSession($sessionId);

// Automatic cleanup of expired sessions
// (happens automatically when creating new sessions)
```

## Building Guided Conversation Flows

Sometimes you need structured conversations with specific steps.

### Simple Linear Flow

```php
class OnboardingFlow
{
    private DialogAgent $agent;
    private Session $session;
    private array $steps = [
        'name' => 'What is your name?',
        'email' => 'What is your email address?',
        'role' => 'What is your role? (developer/designer/manager)',
        'interests' => 'What are you interested in learning?',
    ];
    
    public function __construct(DialogAgent $agent)
    {
        $this->agent = $agent;
        $this->session = $agent->startConversation();
        $this->session->updateState('current_step', 'name');
    }
    
    public function start(): string
    {
        return "Welcome! Let's get you set up. " . $this->steps['name'];
    }
    
    public function processInput(string $input): array
    {
        $currentStep = $this->session->getState()['current_step'];
        
        // Save the answer
        $this->session->updateState($currentStep . '_answer', $input);
        
        // Get agent's acknowledgment
        $response = $this->agent->turn($input, $this->session->getId());
        
        // Move to next step
        $stepKeys = array_keys($this->steps);
        $currentIndex = array_search($currentStep, $stepKeys);
        
        if ($currentIndex === count($stepKeys) - 1) {
            return [
                'complete' => true,
                'response' => $response,
                'data' => $this->collectData(),
            ];
        }
        
        $nextStep = $stepKeys[$currentIndex + 1];
        $this->session->updateState('current_step', $nextStep);
        
        return [
            'complete' => false,
            'response' => $response,
            'next_question' => $this->steps[$nextStep],
        ];
    }
    
    private function collectData(): array
    {
        $state = $this->session->getState();
        return [
            'name' => $state['name_answer'] ?? null,
            'email' => $state['email_answer'] ?? null,
            'role' => $state['role_answer'] ?? null,
            'interests' => $state['interests_answer'] ?? null,
        ];
    }
}

// Usage
$flow = new OnboardingFlow($dialogAgent);

echo $flow->start() . "\n";

$responses = [
    'My name is Sarah',
    'sarah@example.com',
    'I am a developer',
    'Machine learning and AI',
];

foreach ($responses as $response) {
    $result = $flow->processInput($response);
    echo "Agent: {$result['response']}\n";
    
    if ($result['complete']) {
        echo "Onboarding complete!\n";
        print_r($result['data']);
        break;
    } else {
        echo "Next: {$result['next_question']}\n\n";
    }
}
```

### Advanced: Branching Flow

```php
class SupportTicketFlow
{
    private DialogAgent $agent;
    private Session $session;
    
    public function processMessage(string $message): array
    {
        $state = $this->session->getState();
        $currentStage = $state['stage'] ?? 'initial';
        
        $response = $this->agent->turn($message, $this->session->getId());
        
        switch ($currentStage) {
            case 'initial':
                // Determine issue type
                $issueType = $this->detectIssueType($message);
                $this->session->updateState('issue_type', $issueType);
                $this->session->updateState('stage', 'gathering_info');
                return [
                    'response' => $response,
                    'next_action' => 'ask_details',
                ];
                
            case 'gathering_info':
                // Collect specific details based on issue type
                if ($this->hasEnoughInfo()) {
                    $this->session->updateState('stage', 'resolution');
                    return [
                        'response' => $response,
                        'next_action' => 'provide_solution',
                    ];
                }
                return [
                    'response' => $response,
                    'next_action' => 'ask_more',
                ];
                
            case 'resolution':
                // Confirm resolution
                return [
                    'response' => $response,
                    'next_action' => 'close_ticket',
                ];
        }
    }
    
    private function detectIssueType(string $message): string
    {
        $lower = strtolower($message);
        if (strpos($lower, 'password') !== false) return 'password';
        if (strpos($lower, 'billing') !== false) return 'billing';
        if (strpos($lower, 'bug') !== false) return 'technical';
        return 'general';
    }
    
    private function hasEnoughInfo(): bool
    {
        $turnCount = $this->session->getTurnCount();
        return $turnCount >= 3; // Simple heuristic
    }
}
```

## Session Persistence

For production applications, you'll want to save and restore sessions.

### Exporting Sessions

```php
class SessionExporter
{
    public static function export(Session $session): array
    {
        return [
            'session_id' => $session->getId(),
            'created_at' => $session->getCreatedAt(),
            'last_activity' => $session->getLastActivity(),
            'turn_count' => $session->getTurnCount(),
            'state' => $session->getState(),
            'turns' => array_map(
                fn($turn) => [
                    'user_input' => $turn->getUserInput(),
                    'agent_response' => $turn->getAgentResponse(),
                    'timestamp' => $turn->getTimestamp(),
                ],
                $session->getTurns()
            ),
        ];
    }
    
    public static function toJson(Session $session): string
    {
        return json_encode(self::export($session), JSON_PRETTY_PRINT);
    }
    
    public static function save(Session $session, string $filepath): void
    {
        file_put_contents($filepath, self::toJson($session));
    }
}

// Usage
$session = $dialogAgent->startConversation();
// ... have conversation ...

SessionExporter::save($session, '/path/to/session.json');
```

### Database Storage

```php
class SessionDatabase
{
    private PDO $db;
    
    public function saveSession(Session $session): void
    {
        $data = SessionExporter::export($session);
        
        $stmt = $this->db->prepare(
            'INSERT INTO sessions (id, data, created_at, last_activity) 
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
             data = VALUES(data), 
             last_activity = VALUES(last_activity)'
        );
        
        $stmt->execute([
            $session->getId(),
            json_encode($data),
            $data['created_at'],
            $data['last_activity'],
        ]);
    }
    
    public function loadSessionData(string $sessionId): ?array
    {
        $stmt = $this->db->prepare('SELECT data FROM sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? json_decode($row['data'], true) : null;
    }
    
    public function deleteExpiredSessions(int $timeout = 3600): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM sessions WHERE last_activity < ?'
        );
        $stmt->execute([microtime(true) - $timeout]);
        
        return $stmt->rowCount();
    }
}
```

### Redis Storage (High Performance)

```php
class RedisSessionStorage
{
    private Redis $redis;
    private int $ttl = 3600; // 1 hour
    
    public function save(Session $session): void
    {
        $key = "session:{$session->getId()}";
        $data = SessionExporter::toJson($session);
        
        $this->redis->setex($key, $this->ttl, $data);
    }
    
    public function load(string $sessionId): ?array
    {
        $key = "session:{$sessionId}";
        $data = $this->redis->get($key);
        
        return $data ? json_decode($data, true) : null;
    }
    
    public function extend(string $sessionId): void
    {
        $key = "session:{$sessionId}";
        $this->redis->expire($key, $this->ttl);
    }
    
    public function delete(string $sessionId): void
    {
        $key = "session:{$sessionId}";
        $this->redis->del($key);
    }
}
```

## Real-World Applications

### Example 1: Customer Support Chatbot

```php
class CustomerSupportChatbot
{
    private DialogAgent $agent;
    private ConversationManager $manager;
    private SessionDatabase $storage;
    
    public function __construct(
        ClaudePhp $client,
        SessionDatabase $storage
    ) {
        $this->agent = new DialogAgent($client, [
            'name' => 'support_bot',
        ]);
        
        $this->manager = new ConversationManager([
            'max_sessions' => 10000,
            'session_timeout' => 1800, // 30 minutes
        ]);
        
        $this->storage = $storage;
    }
    
    public function handleCustomerMessage(
        string $customerId,
        string $message
    ): array {
        // Get or create session
        $session = $this->getOrCreateSession($customerId);
        
        // Process message
        $response = $this->agent->turn($message, $session->getId());
        
        // Update session metrics
        $session->updateState('last_message_time', time());
        $session->updateState('message_count', 
            ($session->getState()['message_count'] ?? 0) + 1
        );
        
        // Save to database
        $this->storage->saveSession($session);
        
        return [
            'response' => $response,
            'session_id' => $session->getId(),
            'turn_count' => $session->getTurnCount(),
        ];
    }
    
    public function escalateToHuman(string $sessionId): array
    {
        $session = $this->manager->getSession($sessionId);
        
        if (!$session) {
            return ['error' => 'Session not found'];
        }
        
        // Export conversation history for human agent
        return [
            'customer_id' => $session->getState()['customer_id'],
            'conversation' => $this->exportConversation($session),
            'duration' => time() - $session->getCreatedAt(),
        ];
    }
    
    private function getOrCreateSession(string $customerId): Session
    {
        $sessions = $this->manager->getSessionsByUser($customerId);
        
        if (!empty($sessions)) {
            return reset($sessions);
        }
        
        $session = $this->manager->createSession($customerId);
        $session->setState([
            'customer_id' => $customerId,
            'created_at' => time(),
            'issue_type' => null,
            'priority' => 'normal',
        ]);
        
        return $session;
    }
    
    private function exportConversation(Session $session): array
    {
        $turns = $session->getTurns();
        $conversation = [];
        
        foreach ($turns as $turn) {
            $conversation[] = [
                'timestamp' => date('Y-m-d H:i:s', (int)$turn->getTimestamp()),
                'customer' => $turn->getUserInput(),
                'bot' => $turn->getAgentResponse(),
            ];
        }
        
        return $conversation;
    }
}
```

### Example 2: Educational Tutor

```php
class TutorBot
{
    private DialogAgent $agent;
    private array $topics = [
        'math' => 'mathematics and problem solving',
        'science' => 'scientific concepts',
        'history' => 'historical events and context',
        'programming' => 'coding and software development',
    ];
    
    public function startLesson(string $studentId, string $topic): Session
    {
        if (!isset($this->topics[$topic])) {
            throw new \InvalidArgumentException("Unknown topic: {$topic}");
        }
        
        $session = $this->agent->startConversation("student_{$studentId}_{$topic}");
        
        $session->setState([
            'student_id' => $studentId,
            'topic' => $topic,
            'topic_description' => $this->topics[$topic],
            'difficulty_level' => 'beginner',
            'questions_asked' => 0,
            'correct_answers' => 0,
        ]);
        
        return $session;
    }
    
    public function ask(Session $session, string $question): array
    {
        $state = $session->getState();
        
        // Update metrics
        $state['questions_asked']++;
        $session->setState($state);
        
        // Get response
        $response = $this->agent->turn($question, $session->getId());
        
        // Analyze if this was likely correct
        if ($this->looksCorrect($response)) {
            $state['correct_answers']++;
            $session->setState($state);
        }
        
        // Adjust difficulty if needed
        $this->adjustDifficulty($session);
        
        return [
            'response' => $response,
            'progress' => [
                'questions' => $state['questions_asked'],
                'correct' => $state['correct_answers'],
                'accuracy' => $state['questions_asked'] > 0 
                    ? round($state['correct_answers'] / $state['questions_asked'] * 100, 1)
                    : 0,
            ],
        ];
    }
    
    private function adjustDifficulty(Session $session): void
    {
        $state = $session->getState();
        $accuracy = $state['questions_asked'] > 0
            ? $state['correct_answers'] / $state['questions_asked']
            : 0;
        
        if ($accuracy > 0.8 && $state['questions_asked'] >= 5) {
            if ($state['difficulty_level'] === 'beginner') {
                $session->updateState('difficulty_level', 'intermediate');
            } elseif ($state['difficulty_level'] === 'intermediate') {
                $session->updateState('difficulty_level', 'advanced');
            }
        }
    }
    
    private function looksCorrect(string $response): bool
    {
        $positive = ['correct', 'right', 'excellent', 'good', 'yes'];
        $lower = strtolower($response);
        
        foreach ($positive as $word) {
            if (strpos($lower, $word) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
```

### Example 3: Interactive FAQ Bot

```php
class FAQBot
{
    private DialogAgent $agent;
    private array $faqs;
    
    public function __construct(ClaudePhp $client, array $faqs)
    {
        $this->agent = new DialogAgent($client);
        $this->faqs = $faqs;
    }
    
    public function handleQuery(string $userId, string $query): array
    {
        $session = $this->agent->startConversation("faq_{$userId}");
        
        // Add FAQ context to state
        $session->updateState('available_faqs', array_keys($this->faqs));
        
        // Process query with FAQ context
        $contextMessage = "Available FAQ topics: " . implode(', ', array_keys($this->faqs));
        $fullQuery = "{$contextMessage}\n\nUser question: {$query}";
        
        $response = $this->agent->turn($fullQuery, $session->getId());
        
        // Find most relevant FAQ
        $relevantFaq = $this->findRelevantFAQ($query);
        
        return [
            'response' => $response,
            'suggested_faq' => $relevantFaq,
            'related_topics' => $this->findRelatedTopics($query),
        ];
    }
    
    private function findRelevantFAQ(string $query): ?array
    {
        // Simple keyword matching (in production, use better similarity)
        $queryWords = str_word_count(strtolower($query), 1);
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($this->faqs as $question => $answer) {
            $faqWords = str_word_count(strtolower($question), 1);
            $matches = count(array_intersect($queryWords, $faqWords));
            
            if ($matches > $bestScore) {
                $bestScore = $matches;
                $bestMatch = ['question' => $question, 'answer' => $answer];
            }
        }
        
        return $bestMatch;
    }
    
    private function findRelatedTopics(string $query): array
    {
        // Return top 3 related topics
        $related = [];
        // ... implementation ...
        return array_slice($related, 0, 3);
    }
}
```

## Production Best Practices

### 1. Implement Proper Error Handling

```php
class RobustDialogAgent
{
    private DialogAgent $agent;
    private Logger $logger;
    private int $maxRetries = 3;
    
    public function safeTurn(string $message, string $sessionId): string
    {
        $retries = 0;
        
        while ($retries < $this->maxRetries) {
            try {
                return $this->agent->turn($message, $sessionId);
            } catch (\Exception $e) {
                $this->logger->error("Dialog error", [
                    'error' => $e->getMessage(),
                    'session_id' => $sessionId,
                    'retry' => $retries,
                ]);
                
                $retries++;
                
                if ($retries >= $this->maxRetries) {
                    return "I'm having trouble processing your request. Please try again later.";
                }
                
                sleep(pow(2, $retries)); // Exponential backoff
            }
        }
        
        return "Service temporarily unavailable.";
    }
}
```

### 2. Monitor Conversation Metrics

```php
class ConversationMetrics
{
    public function analyze(Session $session): array
    {
        $turns = $session->getTurns();
        $duration = $session->getLastActivity() - $session->getCreatedAt();
        
        return [
            'session_id' => $session->getId(),
            'total_turns' => count($turns),
            'duration_seconds' => $duration,
            'avg_turn_length' => $this->avgLength($turns),
            'user_engagement' => $this->calculateEngagement($turns),
            'session_health' => $this->assessHealth($session),
        ];
    }
    
    private function avgLength(array $turns): float
    {
        if (empty($turns)) return 0;
        
        $total = array_reduce(
            $turns,
            fn($sum, $turn) => $sum + strlen($turn->getUserInput()),
            0
        );
        
        return $total / count($turns);
    }
    
    private function calculateEngagement(array $turns): string
    {
        $count = count($turns);
        
        if ($count >= 10) return 'high';
        if ($count >= 5) return 'medium';
        return 'low';
    }
    
    private function assessHealth(Session $session): string
    {
        $turnCount = $session->getTurnCount();
        $duration = $session->getLastActivity() - $session->getCreatedAt();
        
        // Too many turns might indicate confusion
        if ($turnCount > 50) return 'needs_attention';
        
        // Very quick turns might indicate issues
        if ($turnCount > 10 && $duration < 60) return 'suspicious';
        
        return 'healthy';
    }
}
```

### 3. Implement Rate Limiting

```php
class RateLimitedDialog
{
    private DialogAgent $agent;
    private array $userLimits = [];
    private int $maxPerMinute = 10;
    
    public function turn(string $userId, string $message, string $sessionId): string
    {
        if (!$this->checkRateLimit($userId)) {
            return "You're sending messages too quickly. Please wait a moment.";
        }
        
        $this->recordRequest($userId);
        
        return $this->agent->turn($message, $sessionId);
    }
    
    private function checkRateLimit(string $userId): bool
    {
        $now = time();
        $userRequests = $this->userLimits[$userId] ?? [];
        
        // Remove old requests (older than 1 minute)
        $userRequests = array_filter(
            $userRequests,
            fn($timestamp) => $now - $timestamp < 60
        );
        
        return count($userRequests) < $this->maxPerMinute;
    }
    
    private function recordRequest(string $userId): void
    {
        if (!isset($this->userLimits[$userId])) {
            $this->userLimits[$userId] = [];
        }
        
        $this->userLimits[$userId][] = time();
    }
}
```

### 4. Implement Session Cleanup

```php
class SessionCleaner
{
    private ConversationManager $manager;
    private SessionDatabase $storage;
    
    public function cleanupExpired(int $timeout = 3600): int
    {
        // Clean from database
        $deleted = $this->storage->deleteExpiredSessions($timeout);
        
        // Log cleanup
        error_log("Cleaned up {$deleted} expired sessions");
        
        return $deleted;
    }
    
    public function scheduleCleanup(): void
    {
        // Run cleanup every hour
        while (true) {
            sleep(3600);
            $this->cleanupExpired();
        }
    }
}
```

### 5. Add Comprehensive Logging

```php
class LoggedDialogAgent
{
    private DialogAgent $agent;
    private Logger $logger;
    
    public function turn(string $message, string $sessionId): string
    {
        $startTime = microtime(true);
        
        $this->logger->info('Processing turn', [
            'session_id' => $sessionId,
            'message_length' => strlen($message),
        ]);
        
        try {
            $response = $this->agent->turn($message, $sessionId);
            
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('Turn completed', [
                'session_id' => $sessionId,
                'duration' => $duration,
                'response_length' => strlen($response),
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Turn failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
}
```

## Conclusion

You now have a comprehensive understanding of the DialogAgent! You've learned:

✅ How to create and manage multi-turn conversations  
✅ Understanding context and state management  
✅ Handling multiple concurrent users  
✅ Building guided conversation flows  
✅ Implementing session persistence  
✅ Real-world application patterns  
✅ Production best practices  

## Next Steps

- Review the [DialogAgent API Documentation](../DialogAgent.md)
- Check out the [examples directory](../../examples/) for more code
- Explore integration patterns for your use case
- Build your own conversational AI application!

## Additional Resources

- [DialogAgent.md](../DialogAgent.md) - Complete API reference
- [dialog_agent.php](../../examples/dialog_agent.php) - Basic example
- [advanced_dialog_agent.php](../../examples/advanced_dialog_agent.php) - Advanced patterns
- [Claude API Documentation](https://docs.anthropic.com/)

Happy building! 🚀

