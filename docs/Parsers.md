# Output Parsers

Output parsers transform unstructured text from LLM responses into structured data formats that applications can easily consume. The parser system provides a standardized interface with multiple specialized implementations.

## Table of Contents

- [Overview](#overview)
- [Available Parsers](#available-parsers)
- [Parser Factory](#parser-factory)
- [Usage Examples](#usage-examples)
- [Creating Custom Parsers](#creating-custom-parsers)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)

## Overview

All parsers implement the `ParserInterface`, providing:

- **`parse(string $text): mixed`** - Parse text into structured data
- **`getFormatInstructions(): string`** - Get prompting instructions for the LLM
- **`getType(): string`** - Get parser type identifier

### Why Use Parsers?

- **Structured Output**: Convert free-form text into predictable data structures
- **Format Guidance**: Get instructions to include in prompts for better LLM output
- **Error Handling**: Robust parsing with clear error messages
- **Flexibility**: Multiple parsers for different output formats
- **Extensibility**: Easy to create custom parsers

## Available Parsers

### JsonParser

Extracts and parses JSON from LLM responses.

```php
use ClaudeAgents\Parsers\JsonParser;

$parser = new JsonParser();
$data = $parser->parse($response);
```

**Features:**
- Extracts JSON from code blocks (```json ... ```)
- Handles JSON embedded in text
- Optional JSON schema validation
- Returns arrays or objects
- Multiple JSON extraction

**Configuration:**

```php
$parser = (new JsonParser())
    ->withSchema([
        'type' => 'object',
        'required' => ['name', 'age'],
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer']
        ]
    ])
    ->asObject(); // Return objects instead of arrays
```

**Methods:**

```php
// Basic parsing
$data = $parser->parse('{"name": "John", "age": 30}');

// Extract all JSON blocks
$allJson = $parser->extractAll($text);

// Get format instructions for prompts
$instructions = $parser->getFormatInstructions();
```

### ListParser

Parses bullet lists, numbered lists, and line-separated items.

```php
use ClaudeAgents\Parsers\ListParser;

$parser = new ListParser();
$items = $parser->parse($response);
```

**Features:**
- Bullet lists (-, *, •)
- Numbered lists (1., 2., 3.)
- Custom patterns
- Nested list parsing
- CSV parsing

**Configuration:**

```php
$parser = (new ListParser())
    ->withPattern('/^\d+\)\s+(.+)$/m') // Custom pattern
    ->preserveEmpty(); // Keep empty lines
```

**Methods:**

```php
// Parse bullet list
$items = $parser->parseBullets($text);

// Parse numbered list
$items = $parser->parseNumbered($text);

// Parse CSV
$items = $parser->parseCsv("item1, item2, item3");

// Parse nested lists
$nested = $parser->parseNested($text);
// Returns: ['Parent' => ['Child1', 'Child2']]
```

### RegexParser

Pattern-based extraction using regular expressions.

```php
use ClaudeAgents\Parsers\RegexParser;

$parser = new RegexParser();
$matches = $parser->extract($text, '/pattern/', 1);
```

**Features:**
- Single and multiple match extraction
- Key-value pair extraction
- Common pattern helpers (emails, URLs, phone numbers, dates)
- Custom capture groups
- Configurable default patterns

**Configuration:**

```php
$parser = (new RegexParser())
    ->withPattern('/error:\s*(\w+)/i', 1);

$result = $parser->parse($text); // Uses configured pattern
```

**Methods:**

```php
// Extract all matches
$all = $parser->extract($text, '/(\d+)/', 1);

// Extract single match
$one = $parser->extractOne($text, '/error:\s*(.+)/', 1);

// Extract key-value pairs
$pairs = $parser->extractKeyValue($text, '/(?<key>\w+):\s*(?<value>[^\n]+)/');

// Common patterns
$number = $parser->extractNumber($text);
$emails = $parser->extractEmails($text);
$urls = $parser->extractUrls($text);
$phones = $parser->extractPhoneNumbers($text);
$dates = $parser->extractDates($text);
$codeBlocks = $parser->extractCodeBlocks($text, 'php');
$hashtags = $parser->extractHashtags($text);
$mentions = $parser->extractMentions($text);
```

### XmlParser

Parses XML and HTML from LLM responses.

```php
use ClaudeAgents\Parsers\XmlParser;

$parser = new XmlParser();
$data = $parser->parse($response);
```

**Features:**
- XML and HTML parsing
- XPath queries
- Tag extraction
- Text content extraction
- Array or SimpleXMLElement output

**Configuration:**

```php
$parser = (new XmlParser())
    ->asHtml()        // Parse as HTML
    ->asObject()      // Return SimpleXMLElement
    ->showErrors();   // Display parsing errors
```

**Methods:**

```php
// Parse XML/HTML
$data = $parser->parse($xmlText);

// XPath queries
$results = $parser->xpath($xmlText, '//item[@active="true"]');

// Extract specific tags
$titles = $parser->extractTag($xmlText, 'title');

// Extract plain text
$text = $parser->extractText($xmlText);
```

### MarkdownParser

Parses structured Markdown into components.

```php
use ClaudeAgents\Parsers\MarkdownParser;

$parser = new MarkdownParser();
$structure = $parser->parse($response);
```

**Features:**
- Extract headings, code blocks, lists, links, tables
- Section extraction by heading
- Plain text conversion
- Nested list support

**Configuration:**

```php
$parser = (new MarkdownParser())
    ->includeRaw(); // Include original markdown in output
```

**Methods:**

```php
// Parse full structure
$structure = $parser->parse($text);
// Returns: ['headings' => [...], 'code_blocks' => [...], 'lists' => [...], ...]

// Extract specific components
$headings = $parser->extractHeadings($text);
$codeBlocks = $parser->extractCodeBlocks($text);
$lists = $parser->extractLists($text);
$links = $parser->extractLinks($text);
$images = $parser->extractImages($text);
$tables = $parser->extractTables($text);

// Extract section by heading
$section = $parser->extractSection($text, 'Installation', 2);

// Convert to plain text
$plainText = $parser->toPlainText($text);
```

### CsvParser

Parses CSV and TSV data.

```php
use ClaudeAgents\Parsers\CsvParser;

$parser = new CsvParser();
$rows = $parser->parse($response);
```

**Features:**
- Configurable delimiters and enclosures
- Header detection and mapping
- Type conversion (strings to numbers/booleans)
- Quoted field handling
- CSV generation

**Configuration:**

```php
$parser = (new CsvParser())
    ->withDelimiter(';')      // Custom delimiter
    ->asTab()                 // TSV format
    ->asSemicolon()           // Semicolon delimiter
    ->withoutHeaders()        // No header row
    ->withTypeConversion();   // Convert types
```

**Methods:**

```php
// Parse CSV
$rows = $parser->parse($csvText);
// Returns: [['name' => 'John', 'age' => 30], ...]

// Convert array to CSV
$csv = $parser->toCsv($data, true);
```

## Parser Factory

The `ParserFactory` provides convenient access to all parsers with auto-detection capabilities.

### Basic Usage

```php
use ClaudeAgents\Parsers\ParserFactory;

$factory = ParserFactory::create();

// Get parser by type
$parser = $factory->get('json');
$data = $parser->parse($text);

// Or use convenience methods
$data = $factory->json()->parse($text);
$items = $factory->list()->parse($text);
$matches = $factory->regex('/pattern/', 1)->parse($text);
```

### Auto-Detection

```php
$factory = ParserFactory::create();

// Detect parser type from content
$type = $factory->detectType($text);
echo "Detected type: {$type}";

// Parse with auto-detected parser
$data = $factory->autoParse($text);
```

### Factory Methods

```php
// JSON with schema
$parser = $factory->json(['type' => 'object', 'required' => ['id']]);

// List with pattern
$parser = $factory->list('/^\d+\.\s+(.+)$/m');

// Regex with pattern
$parser = $factory->regex('/error:\s*(.+)/', 1);

// XML/HTML
$parser = $factory->xml();
$parser = $factory->html();

// Markdown
$parser = $factory->markdown();

// CSV/TSV
$parser = $factory->csv(',', true);  // delimiter, has headers
$parser = $factory->tsv(true);       // has headers
```

### Custom Parser Registration

```php
$factory = ParserFactory::create();

// Register custom parser
$factory->register('yaml', YamlParser::class);

// Use custom parser
$parser = $factory->get('yaml');

// Check if type exists
if ($factory->has('yaml')) {
    // ...
}

// Get all types
$types = $factory->getTypes();
```

## Usage Examples

### Example 1: JSON with Schema Validation

```php
use ClaudeAgents\Parsers\JsonParser;
use ClaudeAgents\Chains\LLMChain;
use ClaudeAgents\Prompts\PromptTemplate;

$parser = (new JsonParser())->withSchema([
    'type' => 'object',
    'required' => ['sentiment', 'confidence', 'topics'],
    'properties' => [
        'sentiment' => ['type' => 'string'],
        'confidence' => ['type' => 'number'],
        'topics' => ['type' => 'array']
    ]
]);

$prompt = PromptTemplate::create(
    "Analyze this text: {text}\n\n" . $parser->getFormatInstructions()
);

$chain = LLMChain::create($client)
    ->withPromptTemplate($prompt)
    ->withOutputParser(fn($text) => $parser->parse($text));

$result = $chain->invoke(['text' => 'Great product! Love it.']);
// Returns validated: ['sentiment' => 'positive', 'confidence' => 0.95, 'topics' => [...]]
```

### Example 2: Markdown Section Extraction

```php
use ClaudeAgents\Parsers\MarkdownParser;

$parser = new MarkdownParser();

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 2048,
    'messages' => [[
        'role' => 'user',
        'content' => 'Write documentation for a login API endpoint.'
    ]]
]);

$text = $response->content[0]->text;

// Extract specific section
$examples = $parser->extractSection($text, 'Examples');

// Extract all code blocks
$codeBlocks = $parser->extractCodeBlocks($text);
foreach ($codeBlocks as $block) {
    echo "Language: {$block['language']}\n";
    echo "Code: {$block['code']}\n\n";
}
```

### Example 3: CSV Data Extraction

```php
use ClaudeAgents\Parsers\CsvParser;

$parser = (new CsvParser())
    ->withTypeConversion();

$prompt = "Generate a CSV of top 5 programming languages with columns: " .
          "Name, Year, Type\n\n" . $parser->getFormatInstructions();

$response = $client->messages()->create([
    'model' => 'claude-haiku-4-5',
    'max_tokens' => 512,
    'messages' => [['role' => 'user', 'content' => $prompt]]
]);

$data = $parser->parse($response->content[0]->text);
// Returns: [
//   ['Name' => 'Python', 'Year' => 1991, 'Type' => 'Interpreted'],
//   ['Name' => 'Java', 'Year' => 1995, 'Type' => 'Compiled'],
//   ...
// ]
```

### Example 4: Regex Pattern Extraction

```php
use ClaudeAgents\Parsers\RegexParser;

$parser = new RegexParser();

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 1024,
    'messages' => [[
        'role' => 'user',
        'content' => 'List contact information for support channels'
    ]]
]);

$text = $response->content[0]->text;

// Extract all emails
$emails = $parser->extractEmails($text);

// Extract all URLs
$urls = $parser->extractUrls($text);

// Extract phone numbers
$phones = $parser->extractPhoneNumbers($text);

echo "Emails: " . implode(', ', $emails) . "\n";
echo "URLs: " . implode(', ', $urls) . "\n";
echo "Phones: " . implode(', ', $phones) . "\n";
```

### Example 5: XML/HTML Parsing

```php
use ClaudeAgents\Parsers\XmlParser;

$parser = (new XmlParser())->asHtml();

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 1024,
    'messages' => [[
        'role' => 'user',
        'content' => 'Create an HTML form for user registration'
    ]]
]);

$html = $response->content[0]->text;

// Extract specific tags
$inputs = $parser->extractTag($html, 'input');

// Get plain text
$text = $parser->extractText($html);

// Use XPath
$parser = $parser->asObject();
$required = $parser->xpath($html, '//input[@required]');
```

### Example 6: Auto-Detection with Factory

```php
use ClaudeAgents\Parsers\ParserFactory;

$factory = ParserFactory::create();

function parseResponse(string $response): mixed
{
    global $factory;
    
    // Auto-detect format
    $type = $factory->detectType($response);
    echo "Detected format: {$type}\n";
    
    // Parse with appropriate parser
    return $factory->autoParse($response);
}

$result = parseResponse($llmResponse);
```

## Creating Custom Parsers

Implement the `ParserInterface` to create custom parsers:

```php
use ClaudeAgents\Contracts\ParserInterface;

class YamlParser implements ParserInterface
{
    public function parse(string $text): mixed
    {
        // Extract YAML from code blocks
        if (preg_match('/```yaml\s*([\s\S]*?)\s*```/', $text, $matches)) {
            $yaml = $matches[1];
        } else {
            $yaml = $text;
        }
        
        // Parse YAML (requires symfony/yaml or similar)
        return yaml_parse($yaml);
    }
    
    public function getFormatInstructions(): string
    {
        return "Return your response as YAML in a ```yaml code block.";
    }
    
    public function getType(): string
    {
        return 'yaml';
    }
}
```

**Register with factory:**

```php
$factory = ParserFactory::create();
$factory->register('yaml', YamlParser::class);

$parser = $factory->get('yaml');
```

## Best Practices

### 1. Include Format Instructions in Prompts

```php
$parser = new JsonParser();

$prompt = "Analyze this text: {text}\n\n" . 
          $parser->getFormatInstructions();
```

### 2. Use Schema Validation for Critical Data

```php
$parser = (new JsonParser())->withSchema([
    'type' => 'object',
    'required' => ['status', 'data']
]);
```

### 3. Handle Parsing Errors Gracefully

```php
try {
    $data = $parser->parse($response);
} catch (\RuntimeException $e) {
    // Log error and use fallback
    $logger->error('Parse failed', ['error' => $e->getMessage()]);
    $data = $defaultData;
}
```

### 4. Chain Parsers with LLMChain

```php
$chain = LLMChain::create($client)
    ->withPromptTemplate($template)
    ->withOutputParser(fn($text) => $parser->parse($text));
```

### 5. Use Factory for Flexibility

```php
$factory = ParserFactory::create();

// Allow runtime parser selection
$parser = $factory->get($config['parser_type']);
```

### 6. Test with Multiple Response Formats

```php
// LLMs may format responses differently
$testCases = [
    '{"name": "test"}',
    '```json\n{"name": "test"}\n```',
    'The result is: {"name": "test"}'
];

foreach ($testCases as $test) {
    assert($parser->parse($test) !== null);
}
```

## API Reference

### ParserInterface

```php
interface ParserInterface
{
    public function parse(string $text): mixed;
    public function getFormatInstructions(): string;
    public function getType(): string;
}
```

### JsonParser

```php
class JsonParser implements ParserInterface
{
    public function withSchema(array $schema): self;
    public function asObject(): self;
    public function parse(string $text): array|object;
    public function extractAll(string $text): array;
    public function getFormatInstructions(): string;
    public function getType(): string;
}
```

### ListParser

```php
class ListParser implements ParserInterface
{
    public function withPattern(string $pattern): self;
    public function preserveEmpty(): self;
    public function parse(string $text, ?string $pattern = null): array;
    public function parseNumbered(string $text): array;
    public function parseBullets(string $text): array;
    public function parseCsv(string $text, string $delimiter = ','): array;
    public function parseNested(string $text): array;
    public function getFormatInstructions(): string;
    public function getType(): string;
}
```

### RegexParser

```php
class RegexParser implements ParserInterface
{
    public function withPattern(string $pattern, int $captureGroup = 1): self;
    public function parse(string $text): array|string;
    public function extract(string $text, string $pattern, int $captureGroup = 1): array;
    public function extractOne(string $text, string $pattern, int $captureGroup = 1): ?string;
    public function extractKeyValue(string $text, string $pattern): array;
    public function extractNumber(string $text): ?float;
    public function extractEmails(string $text): array;
    public function extractUrls(string $text): array;
    public function extractPhoneNumbers(string $text): array;
    public function extractDates(string $text): array;
    public function extractCodeBlocks(string $text, ?string $language = null): array;
    public function extractHashtags(string $text): array;
    public function extractMentions(string $text): array;
    public function getFormatInstructions(): string;
    public function getType(): string;
}
```

### XmlParser

```php
class XmlParser implements ParserInterface
{
    public function asHtml(): self;
    public function asObject(): self;
    public function showErrors(): self;
    public function parse(string $text): array|\SimpleXMLElement;
    public function xpath(string $text, string $xpath): array;
    public function extractText(string $text): string;
    public function extractTag(string $text, string $tagName): array;
    public function getFormatInstructions(): string;
    public function getType(): string;
}
```

### MarkdownParser

```php
class MarkdownParser implements ParserInterface
{
    public function includeRaw(): self;
    public function parse(string $text): array;
    public function extractHeadings(string $text): array;
    public function extractCodeBlocks(string $text): array;
    public function extractInlineCode(string $text): array;
    public function extractLists(string $text): array;
    public function extractLinks(string $text): array;
    public function extractImages(string $text): array;
    public function extractTables(string $text): array;
    public function extractSection(string $text, string $heading, ?int $level = null): string;
    public function toPlainText(string $text): string;
    public function getFormatInstructions(): string;
    public function getType(): string;
}
```

### CsvParser

```php
class CsvParser implements ParserInterface
{
    public function withDelimiter(string $delimiter): self;
    public function asTab(): self;
    public function asSemicolon(): self;
    public function withEnclosure(string $enclosure): self;
    public function withoutHeaders(): self;
    public function withTypeConversion(): self;
    public function parse(string $text): array;
    public function toCsv(array $data, bool $includeHeaders = true): string;
    public function getFormatInstructions(): string;
    public function getType(): string;
}
```

### ParserFactory

```php
class ParserFactory
{
    public static function create(): self;
    public function get(string $type): ParserInterface;
    public function json(?array $schema = null): JsonParser;
    public function list(?string $pattern = null): ListParser;
    public function regex(?string $pattern = null, int $captureGroup = 1): RegexParser;
    public function xml(): XmlParser;
    public function html(): XmlParser;
    public function markdown(): MarkdownParser;
    public function csv(string $delimiter = ',', bool $hasHeaders = true): CsvParser;
    public function tsv(bool $hasHeaders = true): CsvParser;
    public function register(string $type, string $class): self;
    public function has(string $type): bool;
    public function getTypes(): array;
    public function detectType(string $text): string;
    public function autoParse(string $text): mixed;
}
```

## See Also

- [Chains Documentation](Chains.md) - Using parsers with chains
- [Examples](../examples/) - Working code examples
- [Tests](../tests/Unit/Parsers/) - Test examples and patterns

