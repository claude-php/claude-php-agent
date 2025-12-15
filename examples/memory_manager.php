#!/usr/bin/env php
<?php
/**
 * Memory Manager Agent Basic Example
 *
 * Demonstrates basic usage of the MemoryManagerAgent for intelligent
 * memory storage, retrieval, and search capabilities.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\MemoryManagerAgent;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Simple console logger
class ConsoleLogger extends AbstractLogger
{
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('H:i:s');
        $emoji = match ($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📝',
        };
        echo "[{$timestamp}] {$emoji} [{$level}] {$message}\n";
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
echo "║                    Memory Manager Agent Basic Example                     ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Create memory manager agent with logger
$logger = new ConsoleLogger();
$memoryAgent = new MemoryManagerAgent($client, [
    'name' => 'demo_memory_manager',
    'max_memories' => 1000,
    'logger' => $logger,
]);

echo "🧠 Memory Manager Agent initialized\n\n";

// Example 1: Store basic memories
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Storing Memories\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$id1 = $memoryAgent->store(
    'PHP is a popular server-side scripting language used for web development',
    ['source' => 'programming', 'language' => 'PHP'],
    ['programming', 'php', 'web']
);
echo "✅ Stored memory 1: {$id1}\n";

$id2 = $memoryAgent->store(
    'Claude is an advanced AI assistant created by Anthropic',
    ['source' => 'AI', 'company' => 'Anthropic'],
    ['AI', 'claude', 'anthropic']
);
echo "✅ Stored memory 2: {$id2}\n";

$id3 = $memoryAgent->store(
    'Composer is a dependency manager for PHP projects',
    ['source' => 'tools', 'language' => 'PHP'],
    ['php', 'tools', 'composer']
);
echo "✅ Stored memory 3: {$id3}\n\n";

// Example 2: Retrieve memories
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Retrieving Memories\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$content = $memoryAgent->retrieve($id1);
echo "📖 Retrieved content: {$content}\n\n";

$fullMemory = $memoryAgent->retrieveFull($id2);
echo "📦 Full memory data:\n";
echo "   ID: {$fullMemory['id']}\n";
echo "   Content: {$fullMemory['content']}\n";
echo "   Tags: " . implode(', ', $fullMemory['tags']) . "\n";
echo "   Metadata: " . json_encode($fullMemory['metadata']) . "\n\n";

// Example 3: Search by tag
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Finding Memories by Tag\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$phpMemories = $memoryAgent->findByTag('php');
echo "🏷️  Found " . count($phpMemories) . " memories with tag 'php':\n";
foreach ($phpMemories as $mem) {
    echo "   • {$mem['content']}\n";
}
echo "\n";

// Example 4: Semantic search
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Semantic Search with LLM\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🔍 Searching for: 'tell me about programming languages'\n";
$results = $memoryAgent->search('tell me about programming languages', 2);
echo "📊 Found " . count($results) . " relevant memories:\n";
foreach ($results as $result) {
    echo "\n   Memory ID: {$result['id']}\n";
    echo "   Content: {$result['content']}\n";
    if (!empty($result['tags'])) {
        echo "   Tags: " . implode(', ', $result['tags']) . "\n";
    }
}
echo "\n";

// Example 5: Using run() method with natural language
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Natural Language Commands\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Store via run method
echo "💬 Command: 'store: Laravel is a PHP web application framework'\n";
$result = $memoryAgent->run('store: Laravel is a PHP web application framework');
if ($result->isSuccess()) {
    echo "✅ {$result->getAnswer()}\n";
    $newId = $result->getMetadata()['id'];
}
echo "\n";

// Retrieve via run method
echo "💬 Command: 'retrieve: {$newId}'\n";
$result = $memoryAgent->run("retrieve: {$newId}");
if ($result->isSuccess()) {
    echo "✅ Retrieved: {$result->getAnswer()}\n";
}
echo "\n";

// Get stats
echo "💬 Command: 'stats'\n";
$result = $memoryAgent->run('stats');
if ($result->isSuccess()) {
    echo "📊 Memory Statistics:\n";
    $stats = $result->getMetadata()['stats'];
    echo "   Total Memories: {$stats['total_memories']}\n";
    echo "   Total Size: {$stats['total_size_bytes']} bytes\n";
    echo "   Unique Tags: {$stats['unique_tags']}\n";
    echo "   Access Count: {$stats['total_access_count']}\n";
}
echo "\n";

// List all tags
echo "💬 Command: 'tags'\n";
$result = $memoryAgent->run('tags');
if ($result->isSuccess()) {
    $tags = $result->getMetadata()['tags'];
    echo "🏷️  Available tags: " . implode(', ', $tags) . "\n";
}
echo "\n";

// Example 6: Export and Import
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 6: Export and Import\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$exported = $memoryAgent->export();
echo "📦 Exported " . count($exported) . " memories\n";

// Create a new agent and import
$newAgent = new MemoryManagerAgent($client, ['name' => 'import_demo']);
$count = $newAgent->import($exported);
echo "📥 Imported {$count} memories into new agent\n";
echo "📊 New agent has {$newAgent->getMemoryCount()} memories\n\n";

// Example 7: Cleanup
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 7: Memory Management\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🗑️  Forgetting memory: {$id1}\n";
$forgotten = $memoryAgent->forget($id1);
echo ($forgotten ? "✅ Memory forgotten\n" : "❌ Memory not found\n");

$stats = $memoryAgent->getStats();
echo "📊 Current memory count: {$stats['total_memories']}\n\n";

// Final stats
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Final Memory Statistics\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$finalStats = $memoryAgent->getStats();
foreach ($finalStats as $key => $value) {
    $label = ucwords(str_replace('_', ' ', $key));
    echo "   {$label}: {$value}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "✨ Memory Manager Agent example completed!\n";
echo str_repeat("═", 80) . "\n";

