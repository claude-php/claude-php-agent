<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Parsers\JsonParser;
use ClaudeAgents\Parsers\ListParser;
use ClaudeAgents\Parsers\RegexParser;
use ClaudeAgents\Parsers\XmlParser;
use ClaudeAgents\Parsers\MarkdownParser;
use ClaudeAgents\Parsers\CsvParser;
use ClaudeAgents\Parsers\ParserFactory;

echo "=== Parser Demonstrations ===\n\n";

// 1. JSON Parser
echo "1. JSON Parser\n";
echo "--------------\n";

$jsonParser = new JsonParser();
$jsonResponse = <<<JSON
Here's the analysis:
```json
{
    "sentiment": "positive",
    "confidence": 0.95,
    "topics": ["quality", "value"]
}
```
JSON;

$jsonData = $jsonParser->parse($jsonResponse);
echo "Parsed JSON:\n";
print_r($jsonData);
echo "Format instructions: " . $jsonParser->getFormatInstructions() . "\n\n";

// 2. JSON Parser with Schema Validation
echo "2. JSON Parser with Schema Validation\n";
echo "--------------------------------------\n";

$schemaParser = (new JsonParser())->withSchema([
    'type' => 'object',
    'required' => ['sentiment', 'confidence'],
    'properties' => [
        'sentiment' => ['type' => 'string'],
        'confidence' => ['type' => 'number']
    ]
]);

try {
    $validatedData = $schemaParser->parse($jsonResponse);
    echo "✓ Schema validation passed\n";
    echo "Sentiment: {$validatedData['sentiment']}, Confidence: {$validatedData['confidence']}\n\n";
} catch (\RuntimeException $e) {
    echo "✗ Schema validation failed: {$e->getMessage()}\n\n";
}

// 3. List Parser
echo "3. List Parser\n";
echo "--------------\n";

$listParser = new ListParser();
$listResponse = <<<LIST
Key recommendations:
- Improve error handling
- Add unit tests
- Update documentation
- Refactor legacy code
LIST;

$listItems = $listParser->parse($listResponse);
echo "Parsed list items:\n";
foreach ($listItems as $i => $item) {
    echo "  " . ($i + 1) . ". $item\n";
}
echo "\n";

// 4. Numbered List Parser
echo "4. Numbered List Parser\n";
echo "-----------------------\n";

$numberedResponse = "Steps:\n1. Install dependencies\n2. Configure settings\n3. Run tests\n4. Deploy";
$numberedItems = $listParser->parseNumbered($numberedResponse);
echo "Parsed numbered list:\n";
foreach ($numberedItems as $i => $item) {
    echo "  Step " . ($i + 1) . ": $item\n";
}
echo "\n";

// 5. Nested List Parser
echo "5. Nested List Parser\n";
echo "---------------------\n";

$nestedResponse = <<<NESTED
- Backend
  - API endpoints
  - Database schema
- Frontend
  - Components
  - Styling
NESTED;

$nestedItems = $listParser->parseNested($nestedResponse);
echo "Parsed nested list:\n";
foreach ($nestedItems as $parent => $children) {
    echo "  $parent:\n";
    foreach ($children as $child) {
        echo "    - $child\n";
    }
}
echo "\n";

// 6. Regex Parser - Emails and URLs
echo "6. Regex Parser - Emails and URLs\n";
echo "---------------------------------\n";

$regexParser = new RegexParser();
$contactResponse = <<<CONTACT
For support, email us at support@example.com or sales@example.com.
Visit our website at https://example.com or https://docs.example.com.
Call us at +1-555-123-4567 or +1-555-987-6543.
CONTACT;

$emails = $regexParser->extractEmails($contactResponse);
$urls = $regexParser->extractUrls($contactResponse);
$phones = $regexParser->extractPhoneNumbers($contactResponse);

echo "Emails found: " . implode(', ', $emails) . "\n";
echo "URLs found: " . implode(', ', $urls) . "\n";
echo "Phones found: " . implode(', ', $phones) . "\n\n";

// 7. Regex Parser - Custom Pattern
echo "7. Regex Parser - Custom Pattern\n";
echo "--------------------------------\n";

$errorResponse = "Error: File not found. Error: Permission denied. Error: Timeout.";
$customParser = (new RegexParser())->withPattern('/Error:\s*(.+?)\./', 1);
$errors = $customParser->parse($errorResponse);
echo "Errors found:\n";
foreach ($errors as $error) {
    echo "  - $error\n";
}
echo "\n";

// 8. XML Parser
echo "8. XML Parser\n";
echo "-------------\n";

$xmlResponse = <<<XML
```xml
<?xml version="1.0"?>
<products>
    <product id="1">
        <name>Laptop</name>
        <price>999.99</price>
    </product>
    <product id="2">
        <name>Mouse</name>
        <price>29.99</price>
    </product>
</products>
```
XML;

$xmlParser = new XmlParser();
$xmlData = $xmlParser->parse($xmlResponse);
echo "Parsed XML structure:\n";
print_r($xmlData);
echo "\n";

// 9. HTML Parser
echo "9. HTML Parser\n";
echo "--------------\n";

$htmlResponse = "<div><h1>Welcome</h1><p>Hello World</p><p>Another paragraph</p></div>";
$htmlParser = (new XmlParser())->asHtml();
$paragraphs = $htmlParser->extractTag($htmlResponse, 'p');
echo "Extracted paragraphs:\n";
foreach ($paragraphs as $p) {
    echo "  - $p\n";
}
echo "\n";

// 10. Markdown Parser
echo "10. Markdown Parser\n";
echo "-------------------\n";

$markdownResponse = <<<MD
# Installation Guide

## Prerequisites

Before starting, ensure you have:
- PHP 8.1 or higher
- Composer installed

## Installation Steps

1. Clone the repository
2. Run `composer install`
3. Configure your `.env` file

## Code Example

```php
<?php
echo "Hello World";
```

For more info, visit [our docs](https://docs.example.com).
MD;

$mdParser = new MarkdownParser();
$mdStructure = $mdParser->parse($markdownResponse);

echo "Headings:\n";
foreach ($mdStructure['headings'] as $heading) {
    echo str_repeat('  ', $heading['level'] - 1) . "- {$heading['text']}\n";
}

echo "\nCode blocks found: " . count($mdStructure['code_blocks']) . "\n";
foreach ($mdStructure['code_blocks'] as $block) {
    echo "  Language: {$block['language']}\n";
    echo "  Lines: " . substr_count($block['code'], "\n") + 1 . "\n";
}

echo "\nLinks found:\n";
foreach ($mdStructure['links'] as $link) {
    echo "  - {$link['text']}: {$link['url']}\n";
}
echo "\n";

// 11. Markdown Section Extraction
echo "11. Markdown Section Extraction\n";
echo "-------------------------------\n";

$installSection = $mdParser->extractSection($markdownResponse, 'Installation Steps', 2);
echo "Installation Steps section:\n";
echo $installSection . "\n\n";

// 12. CSV Parser
echo "12. CSV Parser\n";
echo "--------------\n";

$csvResponse = <<<CSV
```csv
Name,Age,Department,Salary
John Doe,30,Engineering,95000
Jane Smith,28,Marketing,85000
Bob Johnson,35,Sales,90000
```
CSV;

$csvParser = (new CsvParser())->withTypeConversion();
$csvData = $csvParser->parse($csvResponse);

echo "Parsed CSV data:\n";
foreach ($csvData as $row) {
    echo "  {$row['Name']}: {$row['Department']}, \${$row['Salary']}\n";
}
echo "\n";

// 13. TSV Parser
echo "13. TSV Parser\n";
echo "--------------\n";

$tsvResponse = "Product\tQuantity\tPrice\nLaptop\t5\t999.99\nMouse\t50\t29.99";
$tsvParser = (new CsvParser())->asTab()->withTypeConversion();
$tsvData = $tsvParser->parse($tsvResponse);

echo "Parsed TSV data:\n";
foreach ($tsvData as $row) {
    echo "  {$row['Product']}: {$row['Quantity']} @ \${$row['Price']}\n";
}
echo "\n";

// 14. Parser Factory - Basic Usage
echo "14. Parser Factory - Basic Usage\n";
echo "--------------------------------\n";

$factory = ParserFactory::create();

// Get parser by type
$factoryJson = $factory->get('json');
echo "JSON parser type: " . $factoryJson->getType() . "\n";

$factoryList = $factory->get('list');
echo "List parser type: " . $factoryList->getType() . "\n";

// Available types
echo "Available parsers: " . implode(', ', $factory->getTypes()) . "\n\n";

// 15. Parser Factory - Auto Detection
echo "15. Parser Factory - Auto Detection\n";
echo "-----------------------------------\n";

$testCases = [
    '{"name": "John"}' => 'json',
    "- Item 1\n- Item 2" => 'list',
    "<?xml version='1.0'?><root/>" => 'xml',
    "# Heading\nText" => 'markdown',
    "a,b,c\n1,2,3" => 'csv',
];

foreach ($testCases as $text => $expected) {
    $detected = $factory->detectType($text);
    $match = $detected === $expected ? '✓' : '✗';
    echo "$match Detected '$detected' for: " . substr($text, 0, 30) . "...\n";
}
echo "\n";

// 16. Parser Factory - Auto Parse
echo "16. Parser Factory - Auto Parse\n";
echo "-------------------------------\n";

$autoJson = '{"status": "success", "count": 42}';
$autoResult = $factory->autoParse($autoJson);
echo "Auto-parsed JSON:\n";
print_r($autoResult);
echo "\n";

// 17. All Parsers Comparison
echo "17. All Parsers - Format Instructions\n";
echo "-------------------------------------\n";

$allParsers = [
    'JSON' => $factory->json(),
    'List' => $factory->list(),
    'Regex' => $factory->regex(),
    'XML' => $factory->xml(),
    'Markdown' => $factory->markdown(),
    'CSV' => $factory->csv(),
];

foreach ($allParsers as $name => $parser) {
    echo "\n$name Parser:\n";
    echo str_repeat('-', strlen($name) + 8) . "\n";
    echo wordwrap($parser->getFormatInstructions(), 70) . "\n";
}

echo "\n=== Demo Complete ===\n\n";

echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║ 💡 Also Available: ResponseParserChain Pattern (Strategy Pattern)     ║\n";
echo "║                                                                        ║\n";
echo "║ For automatic format detection and parsing:                           ║\n";
echo "║  • ResponseParserChain([JsonParser(), MarkdownParser(), XmlParser()])║\n";
echo "║  • Automatically tries parsers in sequence until one succeeds         ║\n";
echo "║  • See: docs/Parsers.md#responseparserchain                           ║\n";
echo "║  • Example: examples/design_patterns_demo.php                         ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n";

