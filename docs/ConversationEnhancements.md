# Conversation System Enhancements

## Overview

The Conversation system has been enhanced with five major features, all implemented through dependency injection for maximum flexibility and extensibility.

## Features

### 1. Persistence Layer 💾

Save and load conversation sessions to/from persistent storage.

#### Interfaces

- **`SessionStorageInterface`** - Contract for session persistence
- **`SessionSerializerInterface`** - Contract for serialization

#### Implementations

- **`FileSessionStorage`** - File-based storage
- **`JsonSessionSerializer`** - JSON serialization
- **`InMemorySessionStorage`** - In-memory storage (testing)

#### Usage

```php
use ClaudeAgents\Conversation\ConversationManager;
use ClaudeAgents\Conversation\Storage\FileSessionStorage;
use ClaudeAgents\Conversation\Storage\JsonSessionSerializer;

// Create storage
$serializer = new JsonSessionSerializer();
$storage = new FileSessionStorage('/path/to/storage', $serializer);

// Inject into manager
$manager = new ConversationManager([
    'storage' => $storage,
]);

// Sessions are now automatically persisted
$session = $manager->createSession('user_123');

// Manually save/load
$manager->saveSession($session);
$loaded = $storage->load($session->getId());
```

#### Creating Custom Storage

```php
use ClaudeAgents\Contracts\SessionStorageInterface;

class DatabaseSessionStorage implements SessionStorageInterface
{
    public function save(Session $session): bool
    {
        // Save to database
    }
    
    public function load(string $sessionId): ?Session
    {
        // Load from database
    }
    
    // ... other methods
}

// Use it
$manager = new ConversationManager([
    'storage' => new DatabaseSessionStorage($pdo),
]);
```

---

### 2. Session Import/Export 📤

Export conversations to multiple formats for sharing, archiving, or analysis.

#### Available Exporters

| Exporter | Format | Use Case |
|----------|--------|----------|
| `JsonSessionExporter` | JSON | APIs, data exchange |
| `CsvSessionExporter` | CSV | Spreadsheets, analytics |
| `MarkdownSessionExporter` | Markdown | Documentation, reports |
| `HtmlSessionExporter` | HTML | Email, web display |

#### Usage

```php
use ClaudeAgents\Conversation\Export\JsonSessionExporter;
use ClaudeAgents\Conversation\Export\MarkdownSessionExporter;

$session = $manager->getSession('session_id');

// JSON Export
$jsonExporter = new JsonSessionExporter();
$json = $jsonExporter->export($session, [
    'pretty_print' => true,
    'include_metadata' => true,
]);
file_put_contents('conversation.json', $json);

// Markdown Export (multiple styles)
$mdExporter = new MarkdownSessionExporter();

// Default style
$md = $mdExporter->export($session);

// Chat style (with emojis)
$md = $mdExporter->export($session, ['style' => 'chat']);

// Detailed style (with table summary)
$md = $mdExporter->export($session, ['style' => 'detailed']);

file_put_contents('conversation.md', $md);

// CSV Export
$csvExporter = new CsvSessionExporter();
$csv = $csvExporter->export($session, [
    'delimiter' => ',',
    'include_metadata' => true,
]);

// HTML Export
$htmlExporter = new HtmlSessionExporter();
$html = $htmlExporter->export($session, [
    'title' => 'Customer Support Chat',
    'include_styles' => true,
]);
```

#### Creating Custom Exporters

```php
use ClaudeAgents\Contracts\SessionExporterInterface;

class PdfSessionExporter implements SessionExporterInterface
{
    public function export(Session $session, array $options = []): string
    {
        // Generate PDF
        return $pdfContent;
    }
    
    public function getFormat(): string { return 'pdf'; }
    public function getExtension(): string { return 'pdf'; }
    public function getMimeType(): string { return 'application/pdf'; }
}
```

---

### 3. Search & Query 🔍

Search conversation turns by content, metadata, time range, or patterns.

#### Interface

- **`TurnSearchInterface`** - Contract for searching turns

#### Implementation

- **`TurnSearch`** - Full-featured search implementation

#### Usage

```php
use ClaudeAgents\Conversation\Search\TurnSearch;

$search = new TurnSearch();
$session = $manager->getSession('session_id');

// Search by content (text)
$results = $search->searchByContent($session, 'PHP');

// Case-sensitive search
$results = $search->searchByContent($session, 'PHP', [
    'case_sensitive' => true,
]);

// Whole word search
$results = $search->searchByContent($session, 'Hi', [
    'whole_word' => true, // Won't match "This"
]);

// Search only in user input or agent response
$results = $search->searchByContent($session, 'error', [
    'search_in' => 'user', // 'user', 'agent', or 'both'
]);

// Search by metadata
$results = $search->searchByMetadata($session, [
    'priority' => 'high',
    'category' => 'billing',
]);

// Search by time range
$yesterday = microtime(true) - 86400;
$now = microtime(true);
$results = $search->searchByTimeRange($session, $yesterday, $now);

// Search by regex pattern
$results = $search->searchByPattern($session, '/\b[A-Z]{3,}\b/'); // 3+ caps

// Search pattern in specific field
$results = $search->searchByPattern(
    $session,
    '/error|exception/i',
    'agent_response'
);
```

#### Search Options

```php
[
    'case_sensitive' => false,  // Case-sensitive matching
    'whole_word' => false,      // Match whole words only
    'search_in' => 'both',      // 'user', 'agent', or 'both'
]
```

---

### 4. Conversation Summarization 📝

Automatically summarize conversations and extract key topics.

#### Interfaces

- **`ConversationSummarizerInterface`** - Contract for summarization

#### Implementations

- **`BasicConversationSummarizer`** - Rule-based (no API calls)
- **`AIConversationSummarizer`** - AI-powered with Claude

#### Basic Summarizer (Rule-based)

```php
use ClaudeAgents\Conversation\Summarization\BasicConversationSummarizer;

$summarizer = new BasicConversationSummarizer();

// Get conversation summary
$summary = $summarizer->summarize($session, [
    'max_length' => 500,
    'include_turn_count' => true,
    'include_topics' => true,
]);

echo $summary;
// Output: "Conversation with 5 turn(s). Duration: 3 minute(s). 
//          Topics: database, error, configuration. 
//          Started with: 'I need help with...'
//          Ended with: 'Thank you!'"

// Extract main topics
$topics = $summarizer->extractTopics($session, 5);
// Returns: ['database', 'configuration', 'error', 'connection', 'setup']

// Summarize each turn
$turnSummaries = $summarizer->summarizeTurns($session);
foreach ($turnSummaries as $summary) {
    echo $summary['turn_id'] . ": " . $summary['summary'] . "\n";
}
```

#### AI Summarizer (Claude-powered)

```php
use ClaudeAgents\Conversation\Summarization\AIConversationSummarizer;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-api-key');
$summarizer = new AIConversationSummarizer($client);

// Concise summary (default)
$summary = $summarizer->summarize($session);

// Detailed summary
$summary = $summarizer->summarize($session, [
    'style' => 'detailed',
    'max_tokens' => 500,
]);

// Bullet-point summary
$summary = $summarizer->summarize($session, [
    'style' => 'bullet_points',
]);

// Extract topics using AI
$topics = $summarizer->extractTopics($session, 5);

// AI-powered turn summaries
$turnSummaries = $summarizer->summarizeTurns($session);
```

---

### 5. Flexible Architecture 🏗️

All features use dependency injection for easy customization and testing.

#### Dependency Injection Examples

```php
// Inject custom storage
$manager = new ConversationManager([
    'storage' => new MyCustomStorage(),
    'session_timeout' => 3600,
]);

// Use multiple exporters
$exporters = [
    new JsonSessionExporter(),
    new CsvSessionExporter(),
    new MarkdownSessionExporter(),
    new HtmlSessionExporter(),
];

foreach ($exporters as $exporter) {
    $output = $exporter->export($session);
    $filename = "export." . $exporter->getExtension();
    file_put_contents($filename, $output);
}

// Inject custom search implementation
class AdvancedTurnSearch implements TurnSearchInterface
{
    // Your custom search logic
}

$search = new AdvancedTurnSearch();
$results = $search->searchByContent($session, 'query');
```

---

## Complete Example

```php
use ClaudeAgents\Agents\DialogAgent;
use ClaudeAgents\Conversation\ConversationManager;
use ClaudeAgents\Conversation\Export\MarkdownSessionExporter;
use ClaudeAgents\Conversation\Search\TurnSearch;
use ClaudeAgents\Conversation\Storage\FileSessionStorage;
use ClaudeAgents\Conversation\Storage\JsonSessionSerializer;
use ClaudeAgents\Conversation\Summarization\BasicConversationSummarizer;
use ClaudePhp\ClaudePhp;

// 1. Setup with persistence
$storage = new FileSessionStorage(
    '/storage/sessions',
    new JsonSessionSerializer()
);

$manager = new ConversationManager(['storage' => $storage]);
$client = new ClaudePhp(apiKey: 'key');
$agent = new DialogAgent($client);

// 2. Have a conversation
$session = $agent->startConversation('support_123');
$agent->turn('I need help', 'support_123');
$agent->turn('My app crashed', 'support_123');
$agent->turn('Can you help?', 'support_123');

// 3. Save session
$manager->saveSession($session);

// 4. Search conversation
$search = new TurnSearch();
$crashMentions = $search->searchByContent($session, 'crash');
echo "Found " . count($crashMentions) . " mentions of 'crash'\n";

// 5. Summarize
$summarizer = new BasicConversationSummarizer();
$summary = $summarizer->summarize($session);
echo "Summary: {$summary}\n";

$topics = $summarizer->extractTopics($session, 3);
echo "Topics: " . implode(', ', $topics) . "\n";

// 6. Export
$exporter = new MarkdownSessionExporter();
$markdown = $exporter->export($session, ['style' => 'detailed']);
file_put_contents('report.md', $markdown);

// 7. Load later
$loadedSession = $storage->load('support_123');
echo "Loaded session with {$loadedSession->getTurnCount()} turns\n";
```

---

## Production Workflows

### Customer Support System

```php
// Support ticket with full tracking
class SupportTicket
{
    private ConversationManager $manager;
    private TurnSearch $search;
    private SessionExporterInterface $exporter;
    
    public function handleTicket(string $ticketId): void
    {
        $session = $this->manager->getSession($ticketId);
        
        // Search for keywords
        $urgentIssues = $this->search->searchByMetadata($session, [
            'priority' => 'urgent'
        ]);
        
        // Generate report
        $report = $this->exporter->export($session);
        $this->emailReport($report);
    }
}
```

### Analytics & Reporting

```php
// Analyze multiple conversations
class ConversationAnalytics
{
    public function analyzeAll(array $sessionIds): array
    {
        $search = new TurnSearch();
        $summarizer = new BasicConversationSummarizer();
        
        $analytics = [];
        foreach ($sessionIds as $id) {
            $session = $this->storage->load($id);
            
            $analytics[] = [
                'session_id' => $id,
                'turn_count' => $session->getTurnCount(),
                'duration' => $this->calculateDuration($session),
                'topics' => $summarizer->extractTopics($session),
                'summary' => $summarizer->summarize($session),
            ];
        }
        
        return $analytics;
    }
}
```

---

## API Reference

### Storage Interfaces

```php
interface SessionStorageInterface
{
    public function save(Session $session): bool;
    public function load(string $sessionId): ?Session;
    public function delete(string $sessionId): bool;
    public function exists(string $sessionId): bool;
    public function listSessions(): array;
    public function findByUser(string $userId): array;
}

interface SessionSerializerInterface
{
    public function serialize(Session $session): mixed;
    public function deserialize(mixed $data): ?Session;
}
```

### Export Interface

```php
interface SessionExporterInterface
{
    public function export(Session $session, array $options = []): string;
    public function getFormat(): string;
    public function getExtension(): string;
    public function getMimeType(): string;
}
```

### Search Interface

```php
interface TurnSearchInterface
{
    public function searchByContent(Session $session, string $query, array $options = []): array;
    public function searchByMetadata(Session $session, array $criteria): array;
    public function searchByTimeRange(Session $session, float $start, float $end): array;
    public function searchByPattern(Session $session, string $pattern, string $field = 'both'): array;
}
```

### Summarization Interface

```php
interface ConversationSummarizerInterface
{
    public function summarize(Session $session, array $options = []): string;
    public function extractTopics(Session $session, int $maxTopics = 5): array;
    public function summarizeTurns(Session $session): array;
}
```

---

## Testing

All features include comprehensive unit tests. Run them with:

```bash
./vendor/bin/phpunit tests/Unit/Conversation/
```

---

## Best Practices

### 1. Use Dependency Injection

```php
// Good: Inject dependencies
class MyService
{
    public function __construct(
        private SessionStorageInterface $storage,
        private TurnSearchInterface $search
    ) {}
}

// Bad: Hard-coded dependencies
class MyService
{
    private $storage;
    
    public function __construct()
    {
        $this->storage = new FileSessionStorage(...); // Hard-coded!
    }
}
```

### 2. Choose the Right Storage

- **InMemorySessionStorage**: Tests, temporary sessions
- **FileSessionStorage**: Small-scale apps, development
- **Custom DatabaseStorage**: Production apps, scalability

### 3. Export for Different Audiences

- **JSON**: APIs, data exchange
- **CSV**: Analysts, spreadsheets
- **Markdown**: Developers, documentation
- **HTML**: Managers, email reports

### 4. Optimize Search

```php
// Cache frequently-used searches
$cache = [];
$cacheKey = md5($query . serialize($options));

if (!isset($cache[$cacheKey])) {
    $cache[$cacheKey] = $search->searchByContent($session, $query, $options);
}

return $cache[$cacheKey];
```

---

## See Also

- [DialogAgent Documentation](DialogAgent.md)
- [Example: advanced_conversation_features.php](../examples/advanced_conversation_features.php)
- [Tutorial: DialogAgent](tutorials/DialogAgent_Tutorial.md)

