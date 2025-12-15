<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Conversation\ConversationManager;
use ClaudeAgents\Conversation\Export\CsvSessionExporter;
use ClaudeAgents\Conversation\Export\HtmlSessionExporter;
use ClaudeAgents\Conversation\Export\JsonSessionExporter;
use ClaudeAgents\Conversation\Export\MarkdownSessionExporter;
use ClaudeAgents\Conversation\Search\TurnSearch;
use ClaudeAgents\Conversation\Storage\FileSessionStorage;
use ClaudeAgents\Conversation\Storage\JsonSessionSerializer;
use ClaudeAgents\Conversation\Summarization\BasicConversationSummarizer;
use ClaudeAgents\Conversation\Turn;
use ClaudePhp\ClaudePhp;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? null;

if (!$apiKey) {
    echo "Error: ANTHROPIC_API_KEY not found in .env file\n";
    exit(1);
}

// Initialize Claude client
$client = new ClaudePhp(apiKey: $apiKey);

echo "=== Advanced Conversation Features Demo ===\n\n";

// =================================================================
// 1. PERSISTENCE LAYER - Save/Load Sessions
// =================================================================
echo "1. PERSISTENCE LAYER\n";
echo str_repeat('-', 50) . "\n";

$storageDir = __DIR__ . '/../storage/sessions';
$serializer = new JsonSessionSerializer();
$storage = new FileSessionStorage($storageDir, $serializer);

// Create manager with persistent storage
$manager = new ConversationManager([
    'storage' => $storage,
    'session_timeout' => 3600,
]);

echo "✓ Created conversation manager with file storage\n";
echo "  Storage directory: {$storageDir}\n\n";

// Create a session and add some conversation
$dialogAgent = new DialogAgent($client);
$session = $dialogAgent->startConversation('demo_session_001');

echo "Creating conversation...\n";
$dialogAgent->turn('Hi, I want to learn about PHP', 'demo_session_001');
$dialogAgent->turn('What is dependency injection?', 'demo_session_001');
$dialogAgent->turn('Can you give me an example?', 'demo_session_001');

// Save session to storage
$manager->saveSession($session);
echo "✓ Session saved to storage\n";

// Load it back
$loadedSession = $storage->load('demo_session_001');
echo "✓ Session loaded from storage\n";
echo "  Turns in session: {$loadedSession->getTurnCount()}\n\n";

// =================================================================
// 2. SEARCH & QUERY - Find specific turns
// =================================================================
echo "2. SEARCH & QUERY\n";
echo str_repeat('-', 50) . "\n";

$search = new TurnSearch();

// Create a test session with searchable content
$testSession = $dialogAgent->getSession('demo_session_001');

// Search by content
$phpResults = $search->searchByContent($testSession, 'PHP');
echo "✓ Searched for 'PHP': found " . count($phpResults) . " turn(s)\n";

// Search with case sensitivity
$results = $search->searchByContent($testSession, 'dependency', [
    'case_sensitive' => false,
    'search_in' => 'both'
]);
echo "✓ Case-insensitive search for 'dependency': found " . count($results) . " turn(s)\n";

// Search by time range
$now = microtime(true);
$timeResults = $search->searchByTimeRange($testSession, $now - 3600, $now);
echo "✓ Time range search (last hour): found " . count($timeResults) . " turn(s)\n";

// Search by regex pattern
$patternResults = $search->searchByPattern($testSession, '/\b[A-Z]{3,}\b/'); // Words with 3+ caps
echo "✓ Pattern search (3+ capital letters): found " . count($patternResults) . " turn(s)\n\n";

// =================================================================
// 3. SUMMARIZATION - Summarize conversations
// =================================================================
echo "3. SUMMARIZATION\n";
echo str_repeat('-', 50) . "\n";

$summarizer = new BasicConversationSummarizer();

// Get conversation summary
$summary = $summarizer->summarize($testSession);
echo "Conversation Summary:\n";
echo wordwrap($summary, 70) . "\n\n";

// Extract topics
$topics = $summarizer->extractTopics($testSession, 5);
echo "Topics Discussed:\n";
foreach ($topics as $topic) {
    echo "  • {$topic}\n";
}
echo "\n";

// Turn-by-turn summaries
$turnSummaries = $summarizer->summarizeTurns($testSession);
echo "Turn Summaries:\n";
foreach ($turnSummaries as $idx => $turnSummary) {
    echo "  Turn " . ($idx + 1) . ": " . $turnSummary['summary'] . "\n";
}
echo "\n";

// =================================================================
// 4. EXPORT FORMATS - Export to multiple formats
// =================================================================
echo "4. EXPORT FORMATS\n";
echo str_repeat('-', 50) . "\n";

$exportDir = __DIR__ . '/../storage/exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0755, true);
}

// JSON Export
$jsonExporter = new JsonSessionExporter();
$jsonOutput = $jsonExporter->export($testSession, ['pretty_print' => true]);
file_put_contents("{$exportDir}/conversation.json", $jsonOutput);
echo "✓ Exported to JSON: conversation.json\n";

// CSV Export
$csvExporter = new CsvSessionExporter();
$csvOutput = $csvExporter->export($testSession);
file_put_contents("{$exportDir}/conversation.csv", $csvOutput);
echo "✓ Exported to CSV: conversation.csv\n";

// Markdown Export (default style)
$mdExporter = new MarkdownSessionExporter();
$mdOutput = $mdExporter->export($testSession);
file_put_contents("{$exportDir}/conversation.md", $mdOutput);
echo "✓ Exported to Markdown: conversation.md\n";

// Markdown Export (chat style)
$mdChatOutput = $mdExporter->export($testSession, ['style' => 'chat']);
file_put_contents("{$exportDir}/conversation_chat.md", $mdChatOutput);
echo "✓ Exported to Markdown (chat style): conversation_chat.md\n";

// HTML Export
$htmlExporter = new HtmlSessionExporter();
$htmlOutput = $htmlExporter->export($testSession, [
    'title' => 'PHP Learning Conversation',
    'include_styles' => true,
]);
file_put_contents("{$exportDir}/conversation.html", $htmlOutput);
echo "✓ Exported to HTML: conversation.html\n";

echo "\nExport directory: {$exportDir}\n\n";

// =================================================================
// 5. COMBINED EXAMPLE - Real-world workflow
// =================================================================
echo "5. COMPLETE WORKFLOW EXAMPLE\n";
echo str_repeat('-', 50) . "\n";

// Create a customer support conversation
$supportSession = $dialogAgent->startConversation('support_12345');
$supportSession->updateState('user_id', 'customer_789');
$supportSession->updateState('issue_type', 'billing');
$supportSession->updateState('priority', 'high');

echo "Creating support conversation...\n";
$dialogAgent->turn('I have a billing issue', 'support_12345');
$dialogAgent->turn('My card was charged twice', 'support_12345');
$dialogAgent->turn('What should I do?', 'support_12345');

// Save to persistent storage
$manager->saveSession($supportSession);
echo "✓ Support session saved\n";

// Search for specific issues
$billingTurns = $search->searchByMetadata($supportSession, ['priority' => 'high']);
echo "✓ Found " . count($billingTurns) . " high-priority turn(s)\n";

// Generate summary for manager
$supportSummary = $summarizer->summarize($supportSession, [
    'include_turn_count' => true,
    'include_topics' => true,
]);
echo "\nSupport Summary:\n";
echo wordwrap($supportSummary, 70) . "\n\n";

// Export for record keeping
$recordJson = $jsonExporter->export($supportSession);
file_put_contents("{$exportDir}/support_12345.json", $recordJson);
echo "✓ Support record exported\n";

// Generate HTML report for email
$reportHtml = $htmlExporter->export($supportSession, [
    'title' => 'Support Case #12345',
]);
file_put_contents("{$exportDir}/support_12345_report.html", $reportHtml);
echo "✓ HTML report generated\n\n";

// =================================================================
// 6. SESSION MANAGEMENT WITH PERSISTENCE
// =================================================================
echo "6. SESSION MANAGEMENT\n";
echo str_repeat('-', 50) . "\n";

// List all sessions in storage
$allSessions = $storage->listSessions();
echo "Sessions in storage: " . count($allSessions) . "\n";
foreach ($allSessions as $sessionId) {
    echo "  • {$sessionId}\n";
}
echo "\n";

// Find sessions by user
$userSessions = $storage->findByUser('customer_789');
echo "Sessions for customer_789: " . count($userSessions) . "\n";
echo "\n";

// =================================================================
// SUMMARY
// =================================================================
echo "=== DEMO COMPLETE ===\n\n";

echo "Features Demonstrated:\n";
echo "  ✓ Persistent session storage (file-based)\n";
echo "  ✓ Session serialization & deserialization\n";
echo "  ✓ Content search (text, metadata, time, patterns)\n";
echo "  ✓ Conversation summarization\n";
echo "  ✓ Topic extraction\n";
echo "  ✓ Multiple export formats (JSON, CSV, Markdown, HTML)\n";
echo "  ✓ Dependency injection architecture\n";
echo "  ✓ Production-ready workflow\n\n";

echo "All features are optional and injected via dependency injection!\n";
echo "You can easily swap implementations or add your own.\n\n";

echo "Check the exports in: {$exportDir}\n";

