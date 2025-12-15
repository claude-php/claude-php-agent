# Conversation System - Quick Reference

## 🚀 Quick Start

### Basic Setup

```php
use ClaudeAgents\Conversation\ConversationManager;
use ClaudeAgents\Agents\DialogAgent;
use ClaudePhp\ClaudePhp;

$client = new ClaudePhp(apiKey: 'your-key');
$manager = new ConversationManager();
$agent = new DialogAgent($client);
```

---

## 💾 Persistence

### Enable File Storage

```php
use ClaudeAgents\Conversation\Storage\FileSessionStorage;
use ClaudeAgents\Conversation\Storage\JsonSessionSerializer;

$storage = new FileSessionStorage(
    '/path/to/storage',
    new JsonSessionSerializer()
);

$manager = new ConversationManager(['storage' => $storage]);
```

### Save & Load

```php
// Auto-saved when storage is configured
$session = $manager->createSession('user_123');

// Manual save
$manager->saveSession($session);

// Load
$loaded = $storage->load('session_id');
```

---

## 📤 Export

### JSON

```php
use ClaudeAgents\Conversation\Export\JsonSessionExporter;

$exporter = new JsonSessionExporter();
$json = $exporter->export($session, ['pretty_print' => true]);
file_put_contents('conversation.json', $json);
```

### CSV

```php
use ClaudeAgents\Conversation\Export\CsvSessionExporter;

$exporter = new CsvSessionExporter();
$csv = $exporter->export($session);
file_put_contents('conversation.csv', $csv);
```

### Markdown

```php
use ClaudeAgents\Conversation\Export\MarkdownSessionExporter;

$exporter = new MarkdownSessionExporter();

// Default style
$md = $exporter->export($session);

// Chat style
$md = $exporter->export($session, ['style' => 'chat']);

// Detailed style
$md = $exporter->export($session, ['style' => 'detailed']);

file_put_contents('conversation.md', $md);
```

### HTML

```php
use ClaudeAgents\Conversation\Export\HtmlSessionExporter;

$exporter = new HtmlSessionExporter();
$html = $exporter->export($session, [
    'title' => 'My Conversation',
    'include_styles' => true,
]);
file_put_contents('conversation.html', $html);
```

---

## 🔍 Search

### By Content

```php
use ClaudeAgents\Conversation\Search\TurnSearch;

$search = new TurnSearch();

// Simple search
$results = $search->searchByContent($session, 'PHP');

// Case-sensitive
$results = $search->searchByContent($session, 'PHP', [
    'case_sensitive' => true,
]);

// Whole word only
$results = $search->searchByContent($session, 'Hi', [
    'whole_word' => true,
]);

// Search only in user input
$results = $search->searchByContent($session, 'error', [
    'search_in' => 'user', // 'user', 'agent', or 'both'
]);
```

### By Metadata

```php
$results = $search->searchByMetadata($session, [
    'priority' => 'high',
    'category' => 'billing',
]);
```

### By Time Range

```php
$yesterday = microtime(true) - 86400;
$now = microtime(true);
$results = $search->searchByTimeRange($session, $yesterday, $now);
```

### By Pattern (Regex)

```php
// Find 3+ capital letters
$results = $search->searchByPattern($session, '/\b[A-Z]{3,}\b/');

// Search only in agent responses
$results = $search->searchByPattern(
    $session,
    '/error|exception/i',
    'agent_response'
);
```

---

## 📝 Summarization

### Basic (Rule-based, no API)

```php
use ClaudeAgents\Conversation\Summarization\BasicConversationSummarizer;

$summarizer = new BasicConversationSummarizer();

// Get summary
$summary = $summarizer->summarize($session, [
    'max_length' => 500,
    'include_turn_count' => true,
    'include_topics' => true,
]);

// Extract topics
$topics = $summarizer->extractTopics($session, 5);

// Summarize each turn
$turnSummaries = $summarizer->summarizeTurns($session);
```

### AI-Powered (Claude API)

```php
use ClaudeAgents\Conversation\Summarization\AIConversationSummarizer;

$summarizer = new AIConversationSummarizer($client);

// Concise summary
$summary = $summarizer->summarize($session);

// Detailed summary
$summary = $summarizer->summarize($session, [
    'style' => 'detailed',
    'max_tokens' => 500,
]);

// Bullet points
$summary = $summarizer->summarize($session, [
    'style' => 'bullet_points',
]);

// AI topic extraction
$topics = $summarizer->extractTopics($session, 5);
```

---

## 🏗️ Custom Implementations

### Custom Storage

```php
use ClaudeAgents\Contracts\SessionStorageInterface;

class DatabaseStorage implements SessionStorageInterface
{
    public function save(Session $session): bool { /* ... */ }
    public function load(string $id): ?Session { /* ... */ }
    public function delete(string $id): bool { /* ... */ }
    public function exists(string $id): bool { /* ... */ }
    public function listSessions(): array { /* ... */ }
    public function findByUser(string $userId): array { /* ... */ }
}

$manager = new ConversationManager([
    'storage' => new DatabaseStorage($pdo),
]);
```

### Custom Exporter

```php
use ClaudeAgents\Contracts\SessionExporterInterface;

class PdfExporter implements SessionExporterInterface
{
    public function export(Session $session, array $options = []): string
    {
        // Generate PDF
    }
    
    public function getFormat(): string { return 'pdf'; }
    public function getExtension(): string { return 'pdf'; }
    public function getMimeType(): string { return 'application/pdf'; }
}
```

---

## 📊 Common Patterns

### Support Ticket Handler

```php
// Save ticket
$session = $agent->startConversation("ticket_{$ticketId}");
$session->updateState('user_id', $userId);
$session->updateState('priority', 'high');
$manager->saveSession($session);

// Search for issues
$search = new TurnSearch();
$urgent = $search->searchByMetadata($session, ['priority' => 'high']);

// Export report
$exporter = new HtmlSessionExporter();
$report = $exporter->export($session);
email($report);
```

### Analytics Dashboard

```php
$sessions = $storage->findByUser($userId);
$summarizer = new BasicConversationSummarizer();

foreach ($sessions as $session) {
    $data[] = [
        'id' => $session->getId(),
        'turns' => $session->getTurnCount(),
        'topics' => $summarizer->extractTopics($session, 3),
        'summary' => $summarizer->summarize($session),
    ];
}
```

### Batch Export

```php
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
```

---

## 🔑 Interfaces

### Storage
- `SessionStorageInterface` - Persist sessions
- `SessionSerializerInterface` - Serialize/deserialize

### Export
- `SessionExporterInterface` - Export to formats

### Search
- `TurnSearchInterface` - Search conversations

### Summarization
- `ConversationSummarizerInterface` - Summarize & extract topics

---

## 📖 Full Documentation

- [Complete Guide](ConversationEnhancements.md)
- [DialogAgent Docs](DialogAgent.md)
- [Example Code](../examples/advanced_conversation_features.php)

---

## 🧪 Testing

```bash
# Run all tests
./vendor/bin/phpunit tests/Unit/Conversation/

# Run specific tests
./vendor/bin/phpunit tests/Unit/Conversation/Storage/
./vendor/bin/phpunit tests/Unit/Conversation/Search/
./vendor/bin/phpunit tests/Unit/Conversation/Export/
./vendor/bin/phpunit tests/Unit/Conversation/Summarization/
```

---

## ✨ Key Features

- ✅ **Optional** - Enable only what you need
- ✅ **Dependency Injection** - Swap implementations easily
- ✅ **Well-Tested** - 106 tests, 330 assertions
- ✅ **Production-Ready** - Used in real systems
- ✅ **Extensible** - Interface-based design

