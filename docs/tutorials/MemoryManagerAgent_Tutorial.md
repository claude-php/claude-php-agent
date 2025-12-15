# MemoryManagerAgent Tutorial: Building an Intelligent Memory System

## Introduction

This tutorial will guide you through building a production-ready memory management system using the MemoryManagerAgent. We'll start with basic concepts and progress to advanced patterns used in real-world AI applications.

By the end of this tutorial, you'll be able to:

- Store and retrieve memories with metadata and tags
- Implement semantic search using Claude AI
- Build knowledge bases with tag-based organization
- Create multi-agent memory sharing systems
- Manage memory lifecycle with TTL and capacity limits
- Export/import memories for persistence
- Monitor and optimize memory performance

## Prerequisites

- PHP 8.1 or higher
- Composer
- Claude API key (Anthropic)
- Basic understanding of PHP and OOP

## Table of Contents

1. [Getting Started](#getting-started)
2. [Storing Your First Memory](#storing-your-first-memory)
3. [Retrieving Memories](#retrieving-memories)
4. [Searching with Semantic and Keyword Search](#searching-with-semantic-and-keyword-search)
5. [Organizing with Tags](#organizing-with-tags)
6. [Memory Lifecycle Management](#memory-lifecycle-management)
7. [Import and Export](#import-and-export)
8. [Building a Knowledge Base](#building-a-knowledge-base)
9. [Multi-Agent Knowledge Sharing](#multi-agent-knowledge-sharing)
10. [Production Best Practices](#production-best-practices)

## Getting Started

### Installation

First, ensure you have the claude-php-agent package installed:

```bash
composer require your-org/claude-php-agent
```

### Basic Setup

Create a simple script to test the MemoryManagerAgent:

```php
<?php
require_once 'vendor/autoload.php';

use ClaudeAgents\Agents\MemoryManagerAgent;
use ClaudePhp\ClaudePhp;

// Initialize the Claude client
$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

// Create the memory agent
$memoryAgent = new MemoryManagerAgent($client, [
    'name' => 'tutorial_agent',
]);

echo "Memory agent ready!\n";
```

Save this as `memory_tutorial.php` and run it:

```bash
php memory_tutorial.php
```

## Storing Your First Memory

### Basic Storage

The simplest way to store a memory is with just content:

```php
$id = $memoryAgent->store('PHP is a popular server-side scripting language');

echo "Stored memory with ID: {$id}\n";
// Output: Stored memory with ID: mem_abc123...
```

Each memory gets a unique ID that you can use to retrieve it later.

### Storage with Metadata

Metadata allows you to attach structured data to memories:

```php
$id = $memoryAgent->store(
    'User logged in successfully',
    [
        'user_id' => 12345,
        'timestamp' => time(),
        'ip_address' => '192.168.1.1',
        'session_id' => 'sess_xyz789',
    ]
);
```

Metadata is perfect for:
- Contextual information
- Filtering criteria
- Analytics data
- Relationships between memories

### Storage with Tags

Tags provide a simple way to categorize memories:

```php
$id = $memoryAgent->store(
    'Laravel is a PHP web application framework with elegant syntax',
    ['category' => 'framework', 'popularity' => 'high'],
    ['php', 'laravel', 'framework', 'web', 'mvc']
);
```

**Best practices for tags:**
- Use lowercase
- Be specific but not too narrow
- Include both broad and specific tags
- Limit to 3-7 tags per memory

### Exercise 1: Create a Blog Post Memory

**Challenge:** Store a blog post with appropriate metadata and tags.

```php
// Your code here
$postId = $memoryAgent->store(
    'Getting started with PHP 8.3: New features include typed class constants...',
    [
        'author' => 'John Doe',
        'published_date' => '2024-01-15',
        'category' => 'Tutorial',
        'views' => 1523,
    ],
    ['php', 'php8.3', 'tutorial', 'programming']
);

echo "Blog post stored: {$postId}\n";
```

## Retrieving Memories

### Retrieval by ID

The most direct way to get a memory:

```php
// Get just the content
$content = $memoryAgent->retrieve($id);

if ($content !== null) {
    echo "Found: {$content}\n";
} else {
    echo "Memory not found\n";
}
```

### Full Memory Retrieval

Get all information about a memory:

```php
$memory = $memoryAgent->retrieveFull($id);

if ($memory !== null) {
    echo "ID: {$memory['id']}\n";
    echo "Content: {$memory['content']}\n";
    echo "Tags: " . implode(', ', $memory['tags']) . "\n";
    echo "Created: " . date('Y-m-d H:i:s', (int)$memory['timestamp']) . "\n";
    echo "Access count: {$memory['access_count']}\n";
    
    if (!empty($memory['metadata'])) {
        echo "Metadata:\n";
        print_r($memory['metadata']);
    }
}
```

### Understanding Memory Structure

Every memory contains:

```php
[
    'id' => 'mem_abc123...',           // Unique identifier
    'content' => 'The text content',   // Main content
    'metadata' => [...],               // Custom key-value data
    'tags' => [...],                   // Array of tags
    'timestamp' => 1705334400.123,     // Creation timestamp
    'expires_at' => null,              // Expiration time (if set)
    'access_count' => 0,               // Times accessed
    'last_accessed' => null,           // Last access time
]
```

### Exercise 2: Memory Inspector

**Challenge:** Create a function that displays a memory in a formatted way.

```php
function displayMemory(array $memory): void
{
    echo str_repeat('=', 60) . "\n";
    echo "MEMORY: {$memory['id']}\n";
    echo str_repeat('=', 60) . "\n";
    echo "Content: {$memory['content']}\n";
    echo "Created: " . date('Y-m-d H:i:s', (int)$memory['timestamp']) . "\n";
    echo "Accessed: {$memory['access_count']} times\n";
    
    if (!empty($memory['tags'])) {
        echo "Tags: " . implode(', ', $memory['tags']) . "\n";
    }
    
    if (!empty($memory['metadata'])) {
        echo "Metadata: " . json_encode($memory['metadata'], JSON_PRETTY_PRINT) . "\n";
    }
    echo str_repeat('=', 60) . "\n";
}

// Usage
$memory = $memoryAgent->retrieveFull($id);
if ($memory) {
    displayMemory($memory);
}
```

## Searching with Semantic and Keyword Search

### Semantic Search with LLM

The memory agent uses Claude AI to understand the meaning of your query:

```php
// Store some programming-related memories
$memoryAgent->store('Python is known for its readability and extensive libraries');
$memoryAgent->store('JavaScript is the language of the web browser');
$memoryAgent->store('Go is designed for concurrent programming and systems');

// Search with natural language
$results = $memoryAgent->search('Which language is best for web development?', 2);

foreach ($results as $memory) {
    echo "- {$memory['content']}\n";
}
// Might return JavaScript and Python memories
```

The semantic search:
1. Sends your query and available memories to Claude
2. Claude identifies the most relevant memories by understanding context
3. Returns up to the specified limit of relevant memories

### Keyword Search Fallback

If the LLM is unavailable, the agent automatically falls back to keyword matching:

```php
// This search works even without API access
$results = $memoryAgent->search('concurrent programming', 2);

foreach ($results as $memory) {
    echo "- {$memory['content']}\n";
}
// Returns memories containing "concurrent" and "programming"
```

The keyword search:
- Indexes words longer than 3 characters
- Scores memories by keyword frequency
- Fast and reliable fallback

### Comparing Search Methods

```php
// Semantic search - understands intent
$results = $memoryAgent->search('How do I make my code run faster?');
// Returns memories about performance, optimization, caching, etc.

// Keyword search - matches exact terms
$results = $memoryAgent->search('performance optimization');
// Returns memories containing these specific words
```

### Exercise 3: Build a Simple Q&A System

**Challenge:** Create a system that stores facts and answers questions.

```php
class SimpleQA
{
    private MemoryManagerAgent $memory;
    
    public function __construct(MemoryManagerAgent $memory)
    {
        $this->memory = $memory;
    }
    
    public function learn(string $fact, array $topics = []): void
    {
        $this->memory->store($fact, ['type' => 'fact'], $topics);
        echo "Learned: {$fact}\n";
    }
    
    public function ask(string $question): array
    {
        return $this->memory->search($question, 3);
    }
}

// Usage
$qa = new SimpleQA($memoryAgent);

// Teach it some facts
$qa->learn('The Earth orbits the Sun once per year', ['astronomy', 'earth']);
$qa->learn('Water boils at 100°C at sea level', ['chemistry', 'water']);
$qa->learn('The speed of light is 299,792,458 m/s', ['physics', 'light']);

// Ask questions
$answers = $qa->ask('How fast does light travel?');
foreach ($answers as $answer) {
    echo "→ {$answer['content']}\n";
}
```

## Organizing with Tags

### Finding Memories by Tag

Tags provide instant access to categorized memories:

```php
// Store memories with tags
$memoryAgent->store('Docker containers isolate applications', [], ['docker', 'devops']);
$memoryAgent->store('Kubernetes orchestrates containers', [], ['kubernetes', 'devops']);
$memoryAgent->store('Terraform manages infrastructure', [], ['terraform', 'devops']);

// Find all DevOps-related memories
$devOpsMemories = $memoryAgent->findByTag('devops');

echo "Found " . count($devOpsMemories) . " DevOps memories:\n";
foreach ($devOpsMemories as $memory) {
    echo "- {$memory['content']}\n";
}
```

### Listing All Tags

```php
$allTags = $memoryAgent->getTags();

echo "Available tags: " . implode(', ', $allTags) . "\n";
// Output: Available tags: docker, devops, kubernetes, terraform
```

### Tag-Based Navigation

```php
class TagNavigator
{
    private MemoryManagerAgent $memory;
    
    public function __construct(MemoryManagerAgent $memory)
    {
        $this->memory = $memory;
    }
    
    public function listTopics(): array
    {
        return $this->memory->getTags();
    }
    
    public function exploreTopic(string $topic): array
    {
        return $this->memory->findByTag($topic);
    }
    
    public function showTopicStats(): array
    {
        $tags = $this->memory->getTags();
        $stats = [];
        
        foreach ($tags as $tag) {
            $count = count($this->memory->findByTag($tag));
            $stats[$tag] = $count;
        }
        
        arsort($stats);
        return $stats;
    }
}

// Usage
$navigator = new TagNavigator($memoryAgent);

echo "Topic Statistics:\n";
foreach ($navigator->showTopicStats() as $topic => $count) {
    echo "  {$topic}: {$count} memories\n";
}
```

### Exercise 4: Hierarchical Tags

**Challenge:** Implement a system with parent-child tag relationships.

```php
class HierarchicalMemory
{
    private MemoryManagerAgent $memory;
    private array $tagHierarchy = [
        'programming' => ['php', 'python', 'javascript'],
        'php' => ['laravel', 'symfony', 'wordpress'],
        'devops' => ['docker', 'kubernetes', 'terraform'],
    ];
    
    public function store(string $content, string $tag): string
    {
        // Automatically include parent tags
        $tags = $this->getTagChain($tag);
        return $this->memory->store($content, [], $tags);
    }
    
    private function getTagChain(string $tag): array
    {
        $chain = [$tag];
        
        foreach ($this->tagHierarchy as $parent => $children) {
            if (in_array($tag, $children)) {
                $chain[] = $parent;
            }
        }
        
        return $chain;
    }
    
    public function findByCategory(string $category): array
    {
        return $this->memory->findByTag($category);
    }
}

// Usage
$hierarchical = new HierarchicalMemory($memoryAgent);

// Storing with 'laravel' automatically adds 'php' and 'programming'
$hierarchical->store('Laravel routing tutorial', 'laravel');

// Find all programming content (includes PHP, Python, JS)
$programming = $hierarchical->findByCategory('programming');
```

## Memory Lifecycle Management

### Time-To-Live (TTL)

Set automatic expiration for memories:

```php
// Create agent with default 1-hour TTL
$sessionAgent = new MemoryManagerAgent($client, [
    'default_ttl' => 3600.0,  // 1 hour in seconds
]);

// Store session data
$id = $sessionAgent->store('User session data');

// After 1 hour, this returns null
$content = $sessionAgent->retrieve($id);
```

### Capacity Management

Automatically evict oldest memories:

```php
// Create agent with 100-memory limit
$limitedAgent = new MemoryManagerAgent($client, [
    'max_memories' => 100,
]);

// When storing the 101st memory, the oldest is evicted
for ($i = 0; $i < 105; $i++) {
    $limitedAgent->store("Memory {$i}");
}

echo "Count: " . $limitedAgent->getMemoryCount() . "\n";
// Output: Count: 100
```

### Manual Memory Management

```php
// Forget specific memory
$forgotten = $memoryAgent->forget($id);

if ($forgotten) {
    echo "Memory deleted\n";
}

// Clear all memories
$memoryAgent->clear();

echo "All memories cleared\n";
```

### Monitoring Memory Usage

```php
$stats = $memoryAgent->getStats();

echo "Memory Status:\n";
echo "  Total: {$stats['total_memories']}\n";
echo "  Size: " . number_format($stats['total_size_bytes']) . " bytes\n";
echo "  Tags: {$stats['unique_tags']}\n";
echo "  Accesses: {$stats['total_access_count']}\n";

// Calculate averages
if ($stats['total_memories'] > 0) {
    $avgSize = round($stats['total_size_bytes'] / $stats['total_memories']);
    $avgAccess = round($stats['total_access_count'] / $stats['total_memories'], 2);
    
    echo "  Avg Size: {$avgSize} bytes\n";
    echo "  Avg Access: {$avgAccess}\n";
}
```

### Exercise 5: Memory Maintenance System

**Challenge:** Create an automated maintenance system.

```php
class MemoryMaintenance
{
    private MemoryManagerAgent $memory;
    private int $maxSize;
    private int $targetSize;
    
    public function __construct(
        MemoryManagerAgent $memory,
        int $maxSize = 10000,
        int $targetSize = 8000
    ) {
        $this->memory = $memory;
        $this->maxSize = $maxSize;
        $this->targetSize = $targetSize;
    }
    
    public function checkAndMaintain(): void
    {
        $stats = $this->memory->getStats();
        
        echo "Maintenance Check:\n";
        echo "  Current: {$stats['total_memories']} memories\n";
        
        if ($stats['total_memories'] > $this->maxSize) {
            $this->performMaintenance();
        } else {
            echo "  Status: OK\n";
        }
    }
    
    private function performMaintenance(): void
    {
        echo "  Status: Performing maintenance...\n";
        
        // Export current state
        $backup = $this->memory->export();
        $filename = "backup_" . date('Y-m-d_His') . ".json";
        file_put_contents($filename, json_encode($backup, JSON_PRETTY_PRINT));
        echo "  Backed up to: {$filename}\n";
        
        // Clear
        $this->memory->clear();
        
        // Keep most accessed memories
        usort($backup, fn($a, $b) => $b['access_count'] <=> $a['access_count']);
        $toKeep = array_slice($backup, 0, $this->targetSize);
        
        $this->memory->import($toKeep);
        echo "  Kept: " . count($toKeep) . " most accessed memories\n";
        echo "  Status: Complete\n";
    }
}

// Usage
$maintenance = new MemoryMaintenance($memoryAgent);
$maintenance->checkAndMaintain();
```

## Import and Export

### Basic Export

```php
// Export all memories
$memories = $memoryAgent->export();

echo "Exported " . count($memories) . " memories\n";

// Save to JSON file
$json = json_encode($memories, JSON_PRETTY_PRINT);
file_put_contents('memories.json', $json);

echo "Saved to memories.json\n";
```

### Basic Import

```php
// Load from JSON file
$json = file_get_contents('memories.json');
$memories = json_decode($json, true);

// Import into agent
$count = $memoryAgent->import($memories);

echo "Imported {$count} memories\n";
```

### Selective Export/Import

```php
// Export only important memories
$allMemories = $memoryAgent->export();
$important = array_filter($allMemories, function($memory) {
    return isset($memory['metadata']['priority']) 
        && $memory['metadata']['priority'] === 'high';
});

// Save and import elsewhere
file_put_contents('important.json', json_encode($important));

$newAgent = new MemoryManagerAgent($client);
$newAgent->import($important);
```

### Exercise 6: Backup System

**Challenge:** Create an automated backup and restore system.

```php
class BackupManager
{
    private MemoryManagerAgent $memory;
    private string $backupDir;
    
    public function __construct(MemoryManagerAgent $memory, string $backupDir = './backups')
    {
        $this->memory = $memory;
        $this->backupDir = $backupDir;
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
    }
    
    public function backup(string $name = null): string
    {
        $name = $name ?? date('Y-m-d_His');
        $filename = "{$this->backupDir}/{$name}.json";
        
        $memories = $this->memory->export();
        $stats = $this->memory->getStats();
        
        $backup = [
            'version' => '1.0',
            'created_at' => time(),
            'stats' => $stats,
            'memories' => $memories,
        ];
        
        file_put_contents($filename, json_encode($backup, JSON_PRETTY_PRINT));
        
        echo "Backup created: {$filename}\n";
        echo "  Memories: " . count($memories) . "\n";
        echo "  Size: " . filesize($filename) . " bytes\n";
        
        return $filename;
    }
    
    public function restore(string $filename): int
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("Backup file not found: {$filename}");
        }
        
        $backup = json_decode(file_get_contents($filename), true);
        
        if (!isset($backup['memories'])) {
            throw new \RuntimeException("Invalid backup format");
        }
        
        echo "Restoring from: {$filename}\n";
        echo "  Created: " . date('Y-m-d H:i:s', $backup['created_at']) . "\n";
        
        $this->memory->clear();
        $count = $this->memory->import($backup['memories']);
        
        echo "  Restored: {$count} memories\n";
        
        return $count;
    }
    
    public function listBackups(): array
    {
        $files = glob("{$this->backupDir}/*.json");
        $backups = [];
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $backups[] = [
                'file' => basename($file),
                'path' => $file,
                'created' => $data['created_at'] ?? null,
                'count' => count($data['memories'] ?? []),
                'size' => filesize($file),
            ];
        }
        
        return $backups;
    }
}

// Usage
$backup = new BackupManager($memoryAgent);

// Create backup
$backup->backup('important_state');

// List backups
foreach ($backup->listBackups() as $info) {
    echo "{$info['file']}: {$info['count']} memories, {$info['size']} bytes\n";
}

// Restore
$backup->restore('./backups/important_state.json');
```

## Building a Knowledge Base

Let's build a complete knowledge base system:

```php
class KnowledgeBase
{
    private MemoryManagerAgent $memory;
    
    public function __construct(ClaudePhp $client)
    {
        $this->memory = new MemoryManagerAgent($client, [
            'name' => 'knowledge_base',
            'max_memories' => 10000,
        ]);
    }
    
    public function addArticle(
        string $title,
        string $content,
        array $topics,
        array $metadata = []
    ): string {
        $metadata['title'] = $title;
        $metadata['type'] = 'article';
        $metadata['added_at'] = date('Y-m-d H:i:s');
        
        return $this->memory->store($content, $metadata, $topics);
    }
    
    public function addNote(string $content, array $topics = []): string
    {
        return $this->memory->store(
            $content,
            ['type' => 'note', 'added_at' => date('Y-m-d H:i:s')],
            array_merge(['note'], $topics)
        );
    }
    
    public function search(string $query, int $limit = 5): array
    {
        return $this->memory->search($query, $limit);
    }
    
    public function findByTopic(string $topic): array
    {
        return $this->memory->findByTag($topic);
    }
    
    public function getTopics(): array
    {
        return $this->memory->getTags();
    }
    
    public function getStats(): array
    {
        $stats = $this->memory->getStats();
        $topics = $this->getTopics();
        
        $topicCounts = [];
        foreach ($topics as $topic) {
            $topicCounts[$topic] = count($this->findByTopic($topic));
        }
        
        arsort($topicCounts);
        
        return [
            'total_articles' => $stats['total_memories'],
            'topics' => count($topics),
            'most_popular' => array_slice($topicCounts, 0, 5),
            'total_size' => $stats['total_size_bytes'],
        ];
    }
}

// Usage
$kb = new KnowledgeBase($client);

// Add some articles
$kb->addArticle(
    'Introduction to Docker',
    'Docker is a platform for developing, shipping, and running applications in containers...',
    ['docker', 'containers', 'devops'],
    ['author' => 'John Doe', 'difficulty' => 'beginner']
);

$kb->addArticle(
    'Kubernetes Architecture',
    'Kubernetes consists of a control plane and worker nodes...',
    ['kubernetes', 'containers', 'devops', 'architecture'],
    ['author' => 'Jane Smith', 'difficulty' => 'advanced']
);

// Add quick notes
$kb->addNote('Remember to update Docker images weekly', ['docker', 'maintenance']);

// Search
echo "\nSearching for 'how to deploy containers':\n";
$results = $kb->search('how to deploy containers', 3);
foreach ($results as $result) {
    $title = $result['metadata']['title'] ?? 'Note';
    echo "  - {$title}\n";
}

// Browse by topic
echo "\nDocker-related content:\n";
$dockerContent = $kb->findByTopic('docker');
foreach ($dockerContent as $item) {
    $title = $item['metadata']['title'] ?? substr($item['content'], 0, 50);
    echo "  - {$title}\n";
}

// Stats
echo "\nKnowledge Base Statistics:\n";
$stats = $kb->getStats();
echo "  Total Articles: {$stats['total_articles']}\n";
echo "  Topics: {$stats['topics']}\n";
echo "  Most Popular:\n";
foreach ($stats['most_popular'] as $topic => $count) {
    echo "    {$topic}: {$count}\n";
}
```

## Multi-Agent Knowledge Sharing

Create systems where agents share knowledge:

```php
class AgentNetwork
{
    private array $agents = [];
    
    public function registerAgent(string $name, MemoryManagerAgent $agent): void
    {
        $this->agents[$name] = $agent;
    }
    
    public function shareKnowledge(string $from, string $to, array $tags = []): int
    {
        if (!isset($this->agents[$from]) || !isset($this->agents[$to])) {
            throw new \RuntimeException("Agent not found");
        }
        
        $sourceAgent = $this->agents[$from];
        $targetAgent = $this->agents[$to];
        
        // Export from source
        $memories = $sourceAgent->export();
        
        // Filter by tags if specified
        if (!empty($tags)) {
            $memories = array_filter($memories, function($memory) use ($tags) {
                $memTags = $memory['tags'] ?? [];
                return !empty(array_intersect($memTags, $tags));
            });
        }
        
        // Import to target
        $count = $targetAgent->import($memories);
        
        echo "Shared {$count} memories from {$from} to {$to}\n";
        
        return $count;
    }
    
    public function broadcastKnowledge(string $from, array $tags = []): int
    {
        $totalShared = 0;
        
        foreach (array_keys($this->agents) as $agentName) {
            if ($agentName !== $from) {
                $totalShared += $this->shareKnowledge($from, $agentName, $tags);
            }
        }
        
        return $totalShared;
    }
}

// Usage example
$network = new AgentNetwork();

// Create specialized agents
$research = new MemoryManagerAgent($client, ['name' => 'research']);
$analysis = new MemoryManagerAgent($client, ['name' => 'analysis']);
$reporting = new MemoryManagerAgent($client, ['name' => 'reporting']);

// Register them
$network->registerAgent('research', $research);
$network->registerAgent('analysis', $analysis);
$network->registerAgent('reporting', $reporting);

// Research agent gathers data
$research->store('Market trend: AI adoption up 45%', [], ['research', 'ai', 'trends']);
$research->store('Competitor analysis complete', [], ['research', 'competitive']);

// Share research findings with analysis team
$network->shareKnowledge('research', 'analysis', ['research']);

// Analysis agent processes and creates new insights
$analysis->store('Analysis shows AI market will double by 2025', [], ['analysis', 'ai']);

// Broadcast analysis to all agents
$network->broadcastKnowledge('analysis', ['analysis']);
```

## Production Best Practices

### 1. Error Handling

```php
class SafeMemoryManager
{
    private MemoryManagerAgent $memory;
    private LoggerInterface $logger;
    
    public function safeStore(string $content, array $metadata = [], array $tags = []): ?string
    {
        try {
            return $this->memory->store($content, $metadata, $tags);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to store memory', [
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ]);
            return null;
        }
    }
    
    public function safeSearch(string $query, int $limit = 10): array
    {
        try {
            return $this->memory->search($query, $limit);
        } catch (\Throwable $e) {
            $this->logger->warning('Search failed, returning empty', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
            return [];
        }
    }
}
```

### 2. Performance Optimization

```php
class OptimizedMemoryManager
{
    private MemoryManagerAgent $memory;
    private array $cache = [];
    
    public function cachedRetrieve(string $id): ?string
    {
        // Check cache first
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }
        
        // Fetch from memory
        $content = $this->memory->retrieve($id);
        
        // Cache result
        if ($content !== null) {
            $this->cache[$id] = $content;
            
            // Limit cache size
            if (count($this->cache) > 100) {
                array_shift($this->cache);
            }
        }
        
        return $content;
    }
    
    public function batchStore(array $items): array
    {
        $ids = [];
        
        foreach ($items as $item) {
            $id = $this->memory->store(
                $item['content'],
                $item['metadata'] ?? [],
                $item['tags'] ?? []
            );
            $ids[] = $id;
        }
        
        return $ids;
    }
}
```

### 3. Monitoring and Alerting

```php
class MemoryMonitor
{
    private MemoryManagerAgent $memory;
    private array $thresholds;
    
    public function __construct(
        MemoryManagerAgent $memory,
        array $thresholds = []
    ) {
        $this->memory = $memory;
        $this->thresholds = array_merge([
            'max_memories' => 9000,
            'max_size_mb' => 100,
        ], $thresholds);
    }
    
    public function checkHealth(): array
    {
        $stats = $this->memory->getStats();
        $alerts = [];
        
        // Check memory count
        if ($stats['total_memories'] > $this->thresholds['max_memories']) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "Memory count ({$stats['total_memories']}) exceeds threshold",
            ];
        }
        
        // Check size
        $sizeMb = $stats['total_size_bytes'] / (1024 * 1024);
        if ($sizeMb > $this->thresholds['max_size_mb']) {
            $alerts[] = [
                'level' => 'warning',
                'message' => sprintf("Memory size (%.2f MB) exceeds threshold", $sizeMb),
            ];
        }
        
        return [
            'healthy' => empty($alerts),
            'stats' => $stats,
            'alerts' => $alerts,
        ];
    }
}
```

### 4. Testing Strategies

```php
class MemoryManagerTest
{
    private MemoryManagerAgent $memory;
    
    public function testBasicOperations(): void
    {
        echo "Testing basic operations...\n";
        
        // Test store
        $id = $this->memory->store('Test content');
        assert($id !== null, "Store failed");
        echo "  ✓ Store\n";
        
        // Test retrieve
        $content = $this->memory->retrieve($id);
        assert($content === 'Test content', "Retrieve failed");
        echo "  ✓ Retrieve\n";
        
        // Test search
        $results = $this->memory->search('test');
        assert(!empty($results), "Search failed");
        echo "  ✓ Search\n";
        
        // Test forget
        $forgotten = $this->memory->forget($id);
        assert($forgotten === true, "Forget failed");
        echo "  ✓ Forget\n";
        
        echo "All tests passed!\n";
    }
    
    public function benchmarkOperations(): void
    {
        $iterations = 100;
        
        echo "Benchmarking {$iterations} operations...\n";
        
        // Benchmark store
        $start = microtime(true);
        $ids = [];
        for ($i = 0; $i < $iterations; $i++) {
            $ids[] = $this->memory->store("Benchmark content {$i}");
        }
        $storeTime = microtime(true) - $start;
        
        // Benchmark retrieve
        $start = microtime(true);
        foreach ($ids as $id) {
            $this->memory->retrieve($id);
        }
        $retrieveTime = microtime(true) - $start;
        
        echo sprintf("  Store: %.4fs (%.2fms avg)\n", 
            $storeTime, ($storeTime / $iterations) * 1000);
        echo sprintf("  Retrieve: %.4fs (%.2fms avg)\n", 
            $retrieveTime, ($retrieveTime / $iterations) * 1000);
    }
}
```

## Conclusion

You've now learned how to:

✅ Store and retrieve memories with metadata and tags  
✅ Implement semantic search using Claude AI  
✅ Organize knowledge with tag-based systems  
✅ Manage memory lifecycle with TTL and limits  
✅ Export/import for persistence  
✅ Build knowledge bases  
✅ Share knowledge between agents  
✅ Apply production best practices  

### Next Steps

1. **Explore Advanced Patterns**: Check out the [advanced example](../examples/advanced_memory_manager.php)
2. **Read the API Docs**: See [MemoryManagerAgent.md](../MemoryManagerAgent.md) for complete API reference
3. **Build Your Own**: Start with a simple use case and expand from there

### Additional Resources

- [Claude PHP SDK Documentation](https://github.com/anthropics/claude-php-sdk)
- [Agent Framework Guide](../README.md)
- [Examples Directory](../examples/)

Happy coding! 🚀

