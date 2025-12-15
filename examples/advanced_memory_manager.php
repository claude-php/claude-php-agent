#!/usr/bin/env php
<?php
/**
 * Memory Manager Agent Advanced Example
 *
 * Demonstrates advanced features including:
 * - Memory expiration with TTL
 * - Multi-agent memory sharing
 * - Complex search scenarios
 * - Memory analytics and optimization
 * - Persistent storage integration
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\MemoryManagerAgent;
use ClaudePhp\ClaudePhp;
use Psr\Log\AbstractLogger;

// Enhanced logger with memory tracking
class MemoryLogger extends AbstractLogger
{
    private array $operations = [];
    
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = microtime(true);
        $this->operations[] = [
            'time' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
        
        $time = date('H:i:s', (int)$timestamp);
        $emoji = match ($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📝',
        };
        echo "[{$time}] {$emoji} {$message}\n";
    }
    
    public function getOperationCount(): int
    {
        return count($this->operations);
    }
    
    public function getOperations(): array
    {
        return $this->operations;
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
echo "║                  Memory Manager Agent Advanced Example                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

$logger = new MemoryLogger();

// Example 1: Memory with TTL (Time To Live)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 1: Memory Expiration with TTL\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$shortLivedAgent = new MemoryManagerAgent($client, [
    'name' => 'ephemeral_memory',
    'default_ttl' => 2.0, // 2 seconds
    'logger' => $logger,
]);

$tempId = $shortLivedAgent->store('This memory will expire in 2 seconds');
echo "⏱️  Stored ephemeral memory: {$tempId}\n";
echo "✅ Immediate retrieval: " . $shortLivedAgent->retrieve($tempId) . "\n";

echo "💤 Waiting 2.5 seconds for expiration...\n";
sleep(3);

$expired = $shortLivedAgent->retrieve($tempId);
echo ($expired === null ? "✅ Memory expired as expected\n" : "❌ Memory still exists\n");
echo "\n";

// Example 2: Multi-Agent Knowledge Sharing
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 2: Multi-Agent Memory Sharing\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Create specialized agents
$researchAgent = new MemoryManagerAgent($client, [
    'name' => 'research_agent',
    'logger' => $logger,
]);

$analysisAgent = new MemoryManagerAgent($client, [
    'name' => 'analysis_agent',
    'logger' => $logger,
]);

// Research agent gathers information
echo "🔬 Research Agent gathering information...\n";
$researchAgent->store(
    'PHP 8.3 introduces typed class constants and readonly amendments',
    ['agent' => 'research', 'topic' => 'PHP'],
    ['php', 'programming', 'research']
);
$researchAgent->store(
    'The performance of PHP 8.3 shows 15% improvement over PHP 8.2',
    ['agent' => 'research', 'topic' => 'performance'],
    ['php', 'performance', 'research']
);

// Export from research agent
$researchData = $researchAgent->export();
echo "📤 Research agent exported " . count($researchData) . " memories\n";

// Import to analysis agent
$count = $analysisAgent->import($researchData);
echo "📥 Analysis agent imported {$count} memories\n";

// Analysis agent can now work with research data
$phpMemories = $analysisAgent->findByTag('php');
echo "📊 Analysis agent found " . count($phpMemories) . " PHP-related memories\n";
echo "\n";

// Example 3: Complex Search and Filtering
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 3: Advanced Search Scenarios\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$knowledgeBase = new MemoryManagerAgent($client, [
    'name' => 'knowledge_base',
    'logger' => $logger,
]);

// Store diverse knowledge
$topics = [
    ['Web servers like Apache and Nginx handle HTTP requests', ['category' => 'infrastructure'], ['web', 'servers']],
    ['Docker containers provide isolated application environments', ['category' => 'devops'], ['docker', 'containers']],
    ['Kubernetes orchestrates containerized applications at scale', ['category' => 'devops'], ['kubernetes', 'containers']],
    ['MySQL is a popular relational database management system', ['category' => 'database'], ['mysql', 'database']],
    ['Redis provides in-memory data structure storage', ['category' => 'database'], ['redis', 'cache', 'database']],
    ['GraphQL is a query language for APIs', ['category' => 'api'], ['graphql', 'api']],
    ['REST APIs use HTTP methods for CRUD operations', ['category' => 'api'], ['rest', 'api']],
];

foreach ($topics as [$content, $metadata, $tags]) {
    $knowledgeBase->store($content, $metadata, $tags);
}

echo "📚 Knowledge base populated with " . $knowledgeBase->getMemoryCount() . " entries\n\n";

// Perform semantic searches
echo "🔍 Search 1: 'How do I store data?'\n";
$results = $knowledgeBase->search('How do I store data?', 3);
foreach ($results as $result) {
    echo "   • {$result['content']}\n";
}
echo "\n";

echo "🔍 Search 2: 'container technology'\n";
$results = $knowledgeBase->search('container technology', 3);
foreach ($results as $result) {
    echo "   • {$result['content']}\n";
}
echo "\n";

// Tag-based filtering
$containerMemories = $knowledgeBase->findByTag('containers');
echo "🏷️  Found " . count($containerMemories) . " memories tagged with 'containers'\n";
foreach ($containerMemories as $mem) {
    echo "   • {$mem['content']}\n";
}
echo "\n";

// Example 4: Memory Analytics and Optimization
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 4: Memory Analytics\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Access some memories to update statistics
$knowledgeBase->retrieve($knowledgeBase->export()[0]['id']);
$knowledgeBase->retrieve($knowledgeBase->export()[1]['id']);
$knowledgeBase->retrieve($knowledgeBase->export()[0]['id']); // Access again

$stats = $knowledgeBase->getStats();

echo "📊 Detailed Memory Statistics:\n";
echo "   ┌─────────────────────────────────────────────────────┐\n";
echo "   │ Total Memories:        " . str_pad((string)$stats['total_memories'], 24) . "│\n";
echo "   │ Total Size:            " . str_pad(number_format($stats['total_size_bytes']) . " bytes", 24) . "│\n";
echo "   │ Total Accesses:        " . str_pad((string)$stats['total_access_count'], 24) . "│\n";
echo "   │ Unique Tags:           " . str_pad((string)$stats['unique_tags'], 24) . "│\n";
echo "   │ With Metadata:         " . str_pad((string)$stats['memories_with_metadata'], 24) . "│\n";
echo "   │ With Tags:             " . str_pad((string)$stats['memories_with_tags'], 24) . "│\n";
echo "   │ Index Size:            " . str_pad((string)$stats['index_size'] . " words", 24) . "│\n";
echo "   └─────────────────────────────────────────────────────┘\n";

$avgSize = $stats['total_memories'] > 0 ? 
    round($stats['total_size_bytes'] / $stats['total_memories']) : 0;
echo "   📈 Average memory size: {$avgSize} bytes\n";

$avgAccess = $stats['total_memories'] > 0 ? 
    round($stats['total_access_count'] / $stats['total_memories'], 2) : 0;
echo "   📈 Average access count: {$avgAccess}\n";
echo "\n";

// Example 5: Capacity Management
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 5: Memory Capacity Management\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$limitedAgent = new MemoryManagerAgent($client, [
    'name' => 'limited_capacity',
    'max_memories' => 5,
    'logger' => $logger,
]);

echo "🎯 Creating agent with max capacity of 5 memories\n";
echo "📝 Storing 8 memories (will trigger eviction)...\n\n";

$ids = [];
for ($i = 1; $i <= 8; $i++) {
    $id = $limitedAgent->store("Memory number {$i}");
    $ids[] = $id;
    echo "   {$i}. Stored: {$id} (Count: {$limitedAgent->getMemoryCount()})\n";
    usleep(10000); // Small delay to ensure different timestamps
}

echo "\n✅ Final count: {$limitedAgent->getMemoryCount()} (oldest memories evicted)\n";
echo "🔍 Verifying eviction:\n";
echo "   Memory 1 (oldest): " . ($limitedAgent->retrieve($ids[0]) === null ? "❌ Evicted" : "✅ Present") . "\n";
echo "   Memory 2:          " . ($limitedAgent->retrieve($ids[1]) === null ? "❌ Evicted" : "✅ Present") . "\n";
echo "   Memory 3:          " . ($limitedAgent->retrieve($ids[2]) === null ? "❌ Evicted" : "✅ Present") . "\n";
echo "   Memory 8 (newest): " . ($limitedAgent->retrieve($ids[7]) === null ? "❌ Evicted" : "✅ Present") . "\n";
echo "\n";

// Example 6: Persistence Simulation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 6: Memory Persistence (Export/Import)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Save to file
$exportData = $knowledgeBase->export();
$filename = sys_get_temp_dir() . '/memory_export.json';
file_put_contents($filename, json_encode($exportData, JSON_PRETTY_PRINT));
echo "💾 Saved " . count($exportData) . " memories to: {$filename}\n";
echo "📊 File size: " . number_format(filesize($filename)) . " bytes\n\n";

// Simulate restart - create new agent and load
echo "🔄 Simulating application restart...\n";
$restoredAgent = new MemoryManagerAgent($client, ['name' => 'restored_agent']);

$savedData = json_decode(file_get_contents($filename), true);
$restored = $restoredAgent->import($savedData);

echo "✅ Restored {$restored} memories from persistent storage\n";
echo "🔍 Verification: Agent has {$restoredAgent->getMemoryCount()} memories\n";
echo "🏷️  Available tags: " . implode(', ', $restoredAgent->getTags()) . "\n\n";

// Clean up temp file
unlink($filename);

// Example 7: Batch Operations Performance
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Example 7: Batch Operations Performance\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$perfAgent = new MemoryManagerAgent($client, ['name' => 'performance_test']);

$batchSize = 50;
echo "⚡ Performing batch operations ({$batchSize} memories)...\n\n";

$startTime = microtime(true);
$batchIds = [];
for ($i = 0; $i < $batchSize; $i++) {
    $batchIds[] = $perfAgent->store(
        "Batch memory {$i} with some content to test performance",
        ['batch' => 'test', 'index' => $i],
        ['batch', 'performance']
    );
}
$storeTime = microtime(true) - $startTime;

echo "📝 Store: {$batchSize} memories in " . number_format($storeTime, 4) . "s\n";
echo "   Average: " . number_format(($storeTime / $batchSize) * 1000, 2) . "ms per memory\n\n";

$startTime = microtime(true);
foreach ($batchIds as $id) {
    $perfAgent->retrieve($id);
}
$retrieveTime = microtime(true) - $startTime;

echo "📖 Retrieve: {$batchSize} memories in " . number_format($retrieveTime, 4) . "s\n";
echo "   Average: " . number_format(($retrieveTime / $batchSize) * 1000, 2) . "ms per memory\n\n";

$startTime = microtime(true);
$tagged = $perfAgent->findByTag('batch');
$tagTime = microtime(true) - $startTime;

echo "🏷️  Tag search: Found " . count($tagged) . " memories in " . number_format($tagTime, 4) . "s\n\n";

// Logger statistics
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Logger Statistics\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$operations = $logger->getOperations();
echo "📊 Total operations logged: " . count($operations) . "\n";

$levels = array_count_values(array_column($operations, 'level'));
foreach ($levels as $level => $count) {
    echo "   {$level}: {$count}\n";
}

echo "\n" . str_repeat("═", 80) . "\n";
echo "✨ Advanced Memory Manager example completed!\n";
echo "🎯 Key features demonstrated:\n";
echo "   • Memory expiration with TTL\n";
echo "   • Multi-agent knowledge sharing\n";
echo "   • Semantic search capabilities\n";
echo "   • Memory analytics and statistics\n";
echo "   • Automatic capacity management\n";
echo "   • Persistent storage integration\n";
echo "   • Performance benchmarking\n";
echo str_repeat("═", 80) . "\n";

