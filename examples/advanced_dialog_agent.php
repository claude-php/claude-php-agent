#!/usr/bin/env php
<?php
/**
 * Advanced Dialog Agent Example
 *
 * Demonstrates advanced features including:
 * - ConversationManager for multi-user session management
 * - Custom conversation flows
 * - Session persistence and recovery
 * - Context window management
 * - Interactive chat loop
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Conversation\ConversationManager;
use ClaudeAgents\Conversation\Session;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Enhanced logger with color support
class ColorLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $colors = [
            'error' => "\033[31m",   // Red
            'warning' => "\033[33m", // Yellow
            'info' => "\033[36m",    // Cyan
            'debug' => "\033[90m",   // Gray
        ];
        $reset = "\033[0m";
        
        $timestamp = date('H:i:s');
        $color = $colors[$level] ?? '';
        echo "{$color}[{$timestamp}] [{$level}] {$message}{$reset}\n";
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

echo "\033[1;36m"; // Bold cyan
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                     Advanced Dialog Agent Example                         ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\033[0m\n";

$logger = new ColorLogger();

// Example 1: ConversationManager for Multi-User Sessions
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
echo "\033[1;33mExample 1: Multi-User Session Management\033[0m\n";
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

$conversationManager = new ConversationManager([
    'max_sessions' => 100,
    'session_timeout' => 3600, // 1 hour
    'logger' => $logger,
]);

$dialogAgent = new DialogAgent($client, [
    'name' => 'advanced_dialog_agent',
    'logger' => $logger,
]);

echo "🏢 Simulating customer support center with multiple users...\n\n";

// Create sessions for different users
$users = [
    'user_001' => ['name' => 'Alice', 'issue' => 'billing'],
    'user_002' => ['name' => 'Bob', 'issue' => 'technical'],
    'user_003' => ['name' => 'Carol', 'issue' => 'account'],
];

$userSessions = [];

foreach ($users as $userId => $userData) {
    $session = $conversationManager->createSession($userId);
    $session->updateState('user_name', $userData['name']);
    $session->updateState('issue_type', $userData['issue']);
    $userSessions[$userId] = $session;
    
    echo "✅ Created session for {$userData['name']} (ID: {$session->getId()})\n";
}

echo "\n";

// Simulate concurrent conversations
$conversations = [
    'user_001' => [
        "Hi, I have a question about my bill.",
        "I was charged twice for last month.",
    ],
    'user_002' => [
        "My application keeps crashing.",
        "It happens when I try to export data.",
    ],
    'user_003' => [
        "I can't log into my account.",
        "I've tried resetting my password.",
    ],
];

foreach ($conversations as $userId => $messages) {
    $session = $userSessions[$userId];
    $userName = $session->getState()['user_name'];
    
    echo "\033[1;32m💬 Conversation with {$userName}:\033[0m\n";
    
    foreach ($messages as $message) {
        echo "   👤 {$userName}: {$message}\n";
        $response = $dialogAgent->turn($message, $session->getId());
        $shortResponse = substr($response, 0, 100) . (strlen($response) > 100 ? '...' : '');
        echo "   🤖 Agent: {$shortResponse}\n";
    }
    echo "\n";
    sleep(1);
}

// Retrieve sessions by user
$aliceSessions = $conversationManager->getSessionsByUser('user_001');
echo "📊 Alice has " . count($aliceSessions) . " active session(s)\n\n";

sleep(1);

// Example 2: Custom Conversation Flow with Context Management
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
echo "\033[1;33mExample 2: Guided Conversation Flow\033[0m\n";
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

/**
 * Custom conversation flow for onboarding
 */
class OnboardingFlow
{
    private DialogAgent $agent;
    private Session $session;
    private array $steps = [
        'welcome' => 'What is your name?',
        'role' => 'What is your role? (developer/designer/manager)',
        'interests' => 'What are you most interested in learning?',
        'experience' => 'What is your experience level? (beginner/intermediate/advanced)',
    ];
    private int $currentStep = 0;

    public function __construct(DialogAgent $agent, Session $session)
    {
        $this->agent = $agent;
        $this->session = $session;
    }

    public function start(): void
    {
        echo "🎯 Starting guided onboarding flow...\n\n";
        $this->session->updateState('flow_step', 'welcome');
        
        $greeting = "Welcome! I'll help you get started. " . $this->steps['welcome'];
        echo "🤖 Agent: {$greeting}\n\n";
    }

    public function processInput(string $userInput): bool
    {
        $currentStepKey = $this->session->getState()['flow_step'] ?? 'welcome';
        
        echo "👤 User: {$userInput}\n";
        
        // Store the response
        $this->session->updateState($currentStepKey . '_answer', $userInput);
        
        // Get agent's response
        $response = $this->agent->turn($userInput, $this->session->getId());
        echo "🤖 Agent: {$response}\n\n";
        
        // Move to next step
        $stepKeys = array_keys($this->steps);
        $currentIndex = array_search($currentStepKey, $stepKeys);
        
        if ($currentIndex === false || $currentIndex >= count($stepKeys) - 1) {
            return true; // Flow complete
        }
        
        $nextStepKey = $stepKeys[$currentIndex + 1];
        $this->session->updateState('flow_step', $nextStepKey);
        
        // Ask next question
        echo "🤖 Agent: " . $this->steps[$nextStepKey] . "\n\n";
        
        return false;
    }

    public function complete(): void
    {
        $state = $this->session->getState();
        
        echo "✅ Onboarding complete! Here's what we learned:\n";
        echo "   - Name: {$state['welcome_answer']}\n";
        echo "   - Role: {$state['role_answer']}\n";
        echo "   - Interests: {$state['interests_answer']}\n";
        echo "   - Experience: {$state['experience_answer']}\n";
    }
}

$onboardingSession = $dialogAgent->startConversation('onboarding_demo');
$flow = new OnboardingFlow($dialogAgent, $onboardingSession);

$flow->start();

// Simulate user responses
$responses = [
    'My name is David',
    'I am a developer',
    'I want to learn about AI and machine learning',
    'I would say intermediate',
];

foreach ($responses as $response) {
    sleep(1);
    $isComplete = $flow->processInput($response);
    if ($isComplete) {
        break;
    }
}

$flow->complete();
echo "\n";

sleep(1);

// Example 3: Session Persistence and Recovery
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
echo "\033[1;33mExample 3: Session Persistence\033[0m\n";
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

/**
 * Simple session serializer
 */
class SessionSerializer
{
    public static function save(Session $session, string $filepath): void
    {
        $data = [
            'id' => $session->getId(),
            'state' => $session->getState(),
            'turns' => array_map(fn($turn) => $turn->toArray(), $session->getTurns()),
            'created_at' => $session->getCreatedAt(),
        ];
        
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function export(Session $session): string
    {
        $data = [
            'session_id' => $session->getId(),
            'turn_count' => $session->getTurnCount(),
            'state' => $session->getState(),
            'conversation' => [],
        ];

        foreach ($session->getTurns() as $i => $turn) {
            $data['conversation'][] = [
                'turn' => $i + 1,
                'user' => $turn->getUserInput(),
                'agent' => $turn->getAgentResponse(),
                'timestamp' => date('Y-m-d H:i:s', (int)$turn->getTimestamp()),
            ];
        }

        return json_encode($data, JSON_PRETTY_PRINT);
    }
}

echo "💾 Demonstrating session persistence...\n\n";

$persistSession = $dialogAgent->startConversation('persist_demo');
$persistSession->updateState('user_id', 'demo_user');

// Have a conversation
$dialogAgent->turn('Hello, I need help with something.', $persistSession->getId());
$dialogAgent->turn('How do I reset my password?', $persistSession->getId());
$dialogAgent->turn('Thank you!', $persistSession->getId());

// Save session
$saveFile = __DIR__ . '/session_backup.json';
SessionSerializer::save($persistSession, $saveFile);
echo "✅ Session saved to: {$saveFile}\n";

// Export session
$export = SessionSerializer::export($persistSession);
echo "📄 Session export:\n";
echo $export . "\n\n";

sleep(1);

// Example 4: Context Window Management
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
echo "\033[1;33mExample 4: Long Conversation with Context Management\033[0m\n";
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

$longSession = $dialogAgent->startConversation('long_conversation');

echo "🔄 Simulating long conversation (DialogAgent uses last 5 turns for context)...\n\n";

$topics = [
    'Tell me about renewable energy.',
    'What is solar power?',
    'How efficient are solar panels?',
    'What about wind energy?',
    'How do wind turbines work?',
    'Which is more efficient, solar or wind?',
    'What are the costs involved?',
    'How long do solar panels last?',
];

foreach ($topics as $i => $topic) {
    echo "👤 User (Turn " . ($i + 1) . "): {$topic}\n";
    $response = $dialogAgent->turn($topic, $longSession->getId());
    $shortResponse = substr($response, 0, 80) . '...';
    echo "🤖 Agent: {$shortResponse}\n\n";
    
    if ($i < count($topics) - 1) {
        usleep(500000); // 0.5 second pause
    }
}

echo "📊 Conversation statistics:\n";
echo "   - Total turns: {$longSession->getTurnCount()}\n";
echo "   - Session duration: " . 
     round(($longSession->getLastActivity() - $longSession->getCreatedAt()), 2) . " seconds\n";
echo "   - Context window: Last 5 turns (as per DialogAgent design)\n\n";

sleep(1);

// Example 5: Interactive Chat Loop (Optional)
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
echo "\033[1;33mExample 5: Interactive Chat (Type 'quit' to skip)\033[0m\n";
echo "\033[1;33m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

$interactiveSession = $dialogAgent->startConversation('interactive');

echo "💬 Interactive chat mode (or press Enter to auto-skip)...\n\n";

// Check if running in interactive mode
if (php_sapi_name() === 'cli' && stream_isatty(STDIN)) {
    echo "Type 'quit' to exit interactive mode.\n\n";
    
    $maxTurns = 3; // Limit for demo
    $turnCount = 0;
    
    while ($turnCount < $maxTurns) {
        echo "👤 You: ";
        $input = trim(fgets(STDIN));
        
        if (empty($input)) {
            echo "⏭️  Skipping interactive mode...\n\n";
            break;
        }
        
        if (strtolower($input) === 'quit') {
            echo "👋 Goodbye!\n\n";
            break;
        }
        
        $response = $dialogAgent->turn($input, $interactiveSession->getId());
        echo "🤖 Agent: {$response}\n\n";
        
        $turnCount++;
    }
    
    if ($turnCount >= $maxTurns) {
        echo "⏱️  Interactive mode limit reached (demo purposes).\n\n";
    }
} else {
    echo "⏭️  Non-interactive environment detected, skipping...\n\n";
}

// Cleanup
if (file_exists($saveFile)) {
    unlink($saveFile);
}

// Summary
echo "\033[1;36m" . str_repeat("═", 80) . "\033[0m\n";
echo "\033[1;36m✨ Advanced Dialog Agent example completed!\033[0m\n\n";
echo "📋 Features demonstrated:\n";
echo "   ✅ Multi-user session management with ConversationManager\n";
echo "   ✅ Guided conversation flows with custom logic\n";
echo "   ✅ Session persistence and export\n";
echo "   ✅ Long conversations with context window management\n";
echo "   ✅ Interactive chat capability\n";
echo "\n";
echo "💡 Key takeaways:\n";
echo "   • DialogAgent maintains last 5 turns in context\n";
echo "   • Sessions can be managed across multiple users\n";
echo "   • State management enables complex workflows\n";
echo "   • Sessions can be serialized for persistence\n";
echo "\033[1;36m" . str_repeat("═", 80) . "\033[0m\n";

