#!/usr/bin/env php
<?php
/**
 * Router Chain Example
 *
 * Demonstrates how to route inputs to different chains based on conditions.
 * Perfect for building intelligent dispatching systems.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Chains\RouterChain;
use ClaudeAgents\Chains\TransformChain;
use ClaudeAgents\Prompts\PromptTemplate;
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
echo "║                      Router Chain Example                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Example 1: Simple Content Type Router
// ============================================================================

echo "═══ Example 1: Content Type Router ═══\n\n";

// Create specialized chains for different content types
$codeChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Review this code and provide 1 improvement suggestion:\n{content}'
    ))
    ->withMaxTokens(150);

$questionChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Answer this question concisely:\n{content}'
    ))
    ->withMaxTokens(150);

$textChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Summarize this text in 1 sentence:\n{content}'
    ))
    ->withMaxTokens(100);

// Create router
$contentRouter = RouterChain::create()
    ->addRoute(
        fn($input) => str_contains($input['content'] ?? '', '<?php') || 
                      str_contains($input['content'] ?? '', 'function '),
        $codeChain
    )
    ->addRoute(
        fn($input) => str_ends_with($input['content'] ?? '', '?'),
        $questionChain
    )
    ->setDefault($textChain);

// Test with code
echo "Input 1: Code snippet\n";
$codeInput = [
    'content' => '<?php function add($a, $b) { return $a + $b; }'
];
$result1 = $contentRouter->invoke($codeInput);
echo "Routed to: Code Review\n";
echo "Result: " . ($result1['result'] ?? 'N/A') . "\n\n";

// Test with question
echo "Input 2: Question\n";
$questionInput = [
    'content' => 'What is the capital of France?'
];
$result2 = $contentRouter->invoke($questionInput);
echo "Routed to: Question Answering\n";
echo "Result: " . ($result2['result'] ?? 'N/A') . "\n\n";

// Test with text
echo "Input 3: Regular text\n";
$textInput = [
    'content' => 'PHP is a popular server-side scripting language used for web development.'
];
$result3 = $contentRouter->invoke($textInput);
echo "Routed to: Text Summary (default)\n";
echo "Result: " . ($result3['result'] ?? 'N/A') . "\n\n";

// ============================================================================
// Example 2: Priority-based Router
// ============================================================================

echo "═══ Example 2: Priority Router ═══\n\n";

$urgentChain = TransformChain::create(function (array $input): array {
    return [
        'priority' => 'URGENT',
        'queue' => 'high',
        'message' => 'Escalating to immediate attention!',
    ];
});

$normalChain = TransformChain::create(function (array $input): array {
    return [
        'priority' => 'NORMAL',
        'queue' => 'standard',
        'message' => 'Added to standard queue.',
    ];
});

$lowChain = TransformChain::create(function (array $input): array {
    return [
        'priority' => 'LOW',
        'queue' => 'backlog',
        'message' => 'Added to backlog.',
    ];
});

$priorityRouter = RouterChain::create()
    ->addRoute(
        fn($input) => ($input['urgency'] ?? 0) >= 9,
        $urgentChain
    )
    ->addRoute(
        fn($input) => ($input['urgency'] ?? 0) >= 5,
        $normalChain
    )
    ->setDefault($lowChain);

// Test different priorities
$urgencies = [
    ['urgency' => 10, 'task' => 'Server outage'],
    ['urgency' => 6, 'task' => 'Bug report'],
    ['urgency' => 2, 'task' => 'Feature request'],
];

foreach ($urgencies as $task) {
    $result = $priorityRouter->invoke($task);
    echo "Task: {$task['task']} (urgency: {$task['urgency']})\n";
    echo "  Priority: " . ($result['priority'] ?? 'N/A') . "\n";
    echo "  Queue: " . ($result['queue'] ?? 'N/A') . "\n";
    echo "  " . ($result['message'] ?? '') . "\n\n";
}

// ============================================================================
// Example 3: Language Detection Router
// ============================================================================

echo "═══ Example 3: Language Router ═══\n\n";

$englishChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Respond in English: {text}'
    ))
    ->withMaxTokens(100);

$techChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Provide a technical explanation: {text}'
    ))
    ->withMaxTokens(150);

$generalChain = LLMChain::create($client)
    ->withPromptTemplate(PromptTemplate::create(
        'Respond appropriately: {text}'
    ))
    ->withMaxTokens(100);

$languageRouter = RouterChain::create()
    ->addRoute(
        fn($input) => preg_match('/\b(API|SDK|CPU|RAM|HTTP|JSON|SQL)\b/i', $input['text'] ?? ''),
        $techChain
    )
    ->addRoute(
        fn($input) => preg_match('/^[a-zA-Z0-9\s\.,!?]+$/', $input['text'] ?? ''),
        $englishChain
    )
    ->setDefault($generalChain);

echo "Input: 'Explain what an API is'\n";
$techResult = $languageRouter->invoke(['text' => 'Explain what an API is']);
echo "Routed to: Technical Chain\n";
echo "Result: " . ($techResult['result'] ?? 'N/A') . "\n\n";

// ============================================================================
// Example 4: Metadata-based Routing
// ============================================================================

echo "═══ Example 4: Metadata Routing ═══\n\n";

$authenticatedChain = TransformChain::create(function (array $input): array {
    return [
        'access' => 'granted',
        'features' => ['premium', 'analytics', 'support'],
        'message' => 'Welcome, authenticated user!',
    ];
});

$guestChain = TransformChain::create(function (array $input): array {
    return [
        'access' => 'limited',
        'features' => ['basic'],
        'message' => 'Guest access - limited features available.',
    ];
});

$authRouter = RouterChain::create()
    ->addRoute(
        fn($input) => !empty($input['user_id']) && !empty($input['token']),
        $authenticatedChain
    )
    ->setDefault($guestChain);

// Test with authenticated user
echo "Request 1: Authenticated user\n";
$authResult = $authRouter->invoke(['user_id' => 123, 'token' => 'abc123']);
echo "Access: " . ($authResult['access'] ?? 'N/A') . "\n";
echo "Features: " . json_encode($authResult['features'] ?? []) . "\n";
echo $authResult['message'] ?? '' . "\n\n";

// Test with guest
echo "Request 2: Guest user\n";
$guestResult = $authRouter->invoke(['user_id' => null]);
echo "Access: " . ($guestResult['access'] ?? 'N/A') . "\n";
echo "Features: " . json_encode($guestResult['features'] ?? []) . "\n";
echo $guestResult['message'] ?? '' . "\n\n";

// ============================================================================
// Example 5: Complex Routing Logic
// ============================================================================

echo "═══ Example 5: Complex Multi-condition Router ═══\n\n";

$vipChain = TransformChain::create(fn($i) => ['service' => 'VIP', 'response_time' => '1 hour']);
$standardChain = TransformChain::create(fn($i) => ['service' => 'Standard', 'response_time' => '24 hours']);
$basicChain = TransformChain::create(fn($i) => ['service' => 'Basic', 'response_time' => '3 days']);

$serviceRouter = RouterChain::create()
    ->addRoute(
        fn($input) => ($input['customer_tier'] ?? '') === 'vip' && ($input['issue_severity'] ?? 0) >= 7,
        $vipChain
    )
    ->addRoute(
        fn($input) => ($input['customer_tier'] ?? '') === 'premium' || ($input['issue_severity'] ?? 0) >= 5,
        $standardChain
    )
    ->setDefault($basicChain);

$customers = [
    ['customer_tier' => 'vip', 'issue_severity' => 8, 'name' => 'John'],
    ['customer_tier' => 'premium', 'issue_severity' => 4, 'name' => 'Jane'],
    ['customer_tier' => 'basic', 'issue_severity' => 3, 'name' => 'Bob'],
];

foreach ($customers as $customer) {
    $result = $serviceRouter->invoke($customer);
    echo "Customer: {$customer['name']} ({$customer['customer_tier']}, severity: {$customer['issue_severity']})\n";
    echo "  Service Level: " . ($result['service'] ?? 'N/A') . "\n";
    echo "  Response Time: " . ($result['response_time'] ?? 'N/A') . "\n\n";
}

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                         Examples Complete                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";

