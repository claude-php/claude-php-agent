# Support Utilities

The Support directory contains utility classes that provide common functionality used throughout the Claude PHP Agent library.

## Available Utilities

### Core Utilities

#### JsonHelper
Safe JSON encoding/decoding with comprehensive error handling.

```php
use ClaudeAgents\Support\JsonHelper;

// Safe encoding with error handling
$json = JsonHelper::encode($data);

// Safe decoding
$data = JsonHelper::decode($json);

// Pretty-print JSON
echo JsonHelper::prettyPrint($data);

// Validate JSON
if (JsonHelper::isValid($jsonString)) {
    // Process valid JSON
}

// With fallback values
$data = JsonHelper::decodeOrFallback($json, ['default' => 'value']);
```

#### ArrayHelper
Comprehensive array manipulation utilities.

```php
use ClaudeAgents\Support\ArrayHelper;

// Flatten nested arrays
$flat = ArrayHelper::flatten($nested);

// Pluck values from array of arrays
$names = ArrayHelper::pluck($users, 'name');

// Group by key
$grouped = ArrayHelper::groupBy($items, 'category');

// Index by key
$indexed = ArrayHelper::keyBy($items, 'id');

// Deep merge arrays
$merged = ArrayHelper::deepMerge($array1, $array2);

// Dot notation access
$value = ArrayHelper::dotGet($data, 'user.address.city', 'Unknown');
ArrayHelper::dotSet($data, 'user.name', 'John');

// Filter and map recursively
$filtered = ArrayHelper::filterRecursive($data, fn($v) => $v !== null);
$mapped = ArrayHelper::mapRecursive($data, fn($v) => strtoupper($v));
```

#### Validator
Input validation with JSON schema support.

```php
use ClaudeAgents\Support\Validator;

// Check required fields
$missing = Validator::required($data, ['name', 'email']);

// Validate against JSON schema
$errors = Validator::schema($data, [
    'type' => 'object',
    'required' => ['name', 'email'],
    'properties' => [
        'name' => ['type' => 'string', 'minLength' => 3],
        'email' => ['type' => 'string'],
        'age' => ['type' => 'integer', 'minimum' => 0],
    ],
]);

// Format validators
Validator::email('user@example.com');      // true
Validator::url('https://example.com');     // true
Validator::uuid($uuid);                    // true
Validator::json($jsonString);              // true

// Range validation
Validator::length($string, min: 5, max: 100);
Validator::range($number, min: 0, max: 100);

// Custom validators
Validator::in($value, ['allowed', 'values']);
Validator::regex($string, '/^[a-z]+$/');

// Fluent validation
$validator = Validator::make([
    'required' => ['name', 'email'],
    'properties' => [
        'name' => ['type' => 'string'],
    ],
]);

if ($validator->passes($data)) {
    // Data is valid
}
```

#### StringHelper
String manipulation and formatting.

```php
use ClaudeAgents\Support\StringHelper;

// Truncate strings
$short = StringHelper::truncate($long, 100, '...');

// Extract excerpts
$excerpt = StringHelper::excerpt($text, 'keyword', 200);

// Slugify for URLs
$slug = StringHelper::slugify('Hello World!'); // 'hello-world'

// Mask sensitive data
$masked = StringHelper::mask($apiKey, 4, 4); // 'sk-p***************xyz'

// Generate random strings
$random = StringHelper::random(32);

// Case conversion
$camel = StringHelper::camelCase('hello_world');     // 'helloWorld'
$snake = StringHelper::snakeCase('HelloWorld');      // 'hello_world'
$kebab = StringHelper::kebabCase('HelloWorld');      // 'hello-world'
$studly = StringHelper::studlyCase('hello_world');   // 'HelloWorld'

// String operations
StringHelper::startsWith($str, 'prefix');
StringHelper::endsWith($str, 'suffix');
StringHelper::contains($str, 'needle');
StringHelper::replaceFirst('find', 'replace', $str);
StringHelper::replaceLast('find', 'replace', $str);
```

### System Utilities

#### LoggerFactory
Standardized logger creation and management.

```php
use ClaudeAgents\Support\LoggerFactory;
use Psr\Log\LogLevel;

// Create with optional logger (returns NullLogger if null)
$logger = LoggerFactory::create($customLogger);

// Create console logger
$logger = LoggerFactory::createConsole(LogLevel::DEBUG);

// Create file logger
$logger = LoggerFactory::createFile('/path/to/log.txt', LogLevel::INFO);

// Create memory logger (useful for testing)
$logger = LoggerFactory::createMemory();
$logger->info('Test message');
$logs = $logger->getLogs();
```

#### ErrorHandler
Exception handling and formatting utilities.

```php
use ClaudeAgents\Support\ErrorHandler;

// Wrap exceptions with context
$wrapped = ErrorHandler::wrap($exception, 'Failed to process', [
    'user_id' => 123,
]);

// Format exceptions for display
$formatted = ErrorHandler::format($exception, includeTrace: true);

// Sanitize sensitive data from exceptions
$safe = ErrorHandler::sanitize($exception);

// Format for logging
$logData = ErrorHandler::formatForLog($exception, sanitize: true);

// Get root cause of exception chain
$root = ErrorHandler::getRootCause($exception);

// Convert to HTTP status code
$status = ErrorHandler::toHttpStatus($exception); // 500

// Execute with error handling
$result = ErrorHandler::handle(
    fn() => riskyOperation(),
    fn($e) => 'fallback value'
);

// Suppress exceptions
$result = ErrorHandler::suppress(fn() => riskyOperation(), 'default');
```

#### RetryHandler
Exponential backoff retry logic.

```php
use ClaudeAgents\Support\RetryHandler;
use ClaudeAgents\Config\RetryConfig;

// Create with default config
$retry = new RetryHandler();

// Execute with retry logic
$result = $retry->execute(function() {
    // Your code that might fail
    return $api->call();
});

// Configure retry behavior
$config = new RetryConfig(
    maxAttempts: 5,
    initialDelay: 1000,    // milliseconds
    maxDelay: 30000,
    multiplier: 2.0
);
$retry = new RetryHandler($config);

// Add retry callback
$retry->onRetry(function($attempt, $exception) {
    echo "Retry attempt {$attempt}: {$exception->getMessage()}\n";
});

// Specify retryable exceptions
$retry->retryOn(\RuntimeException::class, \ApiException::class);
```

#### TokenTracker
Track token usage and estimate costs.

```php
use ClaudeAgents\Support\TokenTracker;

$tracker = new TokenTracker();

// Record token usage
$tracker->record(
    inputTokens: 1000,
    outputTokens: 500
);

// Get statistics
$inputTokens = $tracker->getInputTokens();
$outputTokens = $tracker->getOutputTokens();
$totalTokens = $tracker->getTotalTokens();
$requestCount = $tracker->getRequestCount();
$averagePerRequest = $tracker->getAverageTokensPerRequest();

// Get summary
$summary = $tracker->getSummary();
// [
//     'input_tokens' => 1000,
//     'output_tokens' => 500,
//     'total_tokens' => 1500,
//     'request_count' => 1,
//     'average_per_request' => 1500.0,
// ]

// Estimate cost (default Claude Sonnet pricing)
$cost = $tracker->estimateCost(
    inputPricePerMillion: 3.0,
    outputPricePerMillion: 15.0
);

// Get usage history
$history = $tracker->getHistory();

// Reset tracker
$tracker->reset();
```

### Time and Formatting

#### TimeHelper
Time, duration, and scheduling utilities.

```php
use ClaudeAgents\Support\TimeHelper;

// Measure elapsed time
$start = TimeHelper::now();
// ... do work ...
$elapsed = TimeHelper::elapsed($start);

// Format durations
echo TimeHelper::formatDuration(125.5);      // "2m 5s"
echo TimeHelper::formatDurationLong(7384);   // "2 hours, 3 minutes, 4 seconds"

// Measure execution
$result = TimeHelper::measure(function() {
    return expensiveOperation();
});
// ['result' => ..., 'duration' => 1.234]

// Sleep with precision
TimeHelper::sleep(0.5);  // Sleep 500ms

// Parse durations
$seconds = TimeHelper::parseDuration('1h30m');  // 5400

// Retry with backoff
$result = TimeHelper::retry(
    callable: fn() => $api->call(),
    maxAttempts: 3,
    initialDelay: 1.0,
    multiplier: 2.0
);

// Time ago formatting
echo TimeHelper::ago(strtotime('-2 hours'));  // "2 hours ago"

// Throttle function calls
$throttled = TimeHelper::throttle($callback, intervalSeconds: 1.0);
```

#### Formatter
Display formatting utilities.

```php
use ClaudeAgents\Support\Formatter;

// Format bytes
echo Formatter::bytes(1024);           // "1.00 KB"
echo Formatter::bytes(1048576);        // "1.00 MB"

// Format numbers
echo Formatter::number(1234567.89, 2); // "1,234,567.89"

// Format percentages
echo Formatter::percentage(0.856, 1);  // "85.6%"

// Format money
echo Formatter::money(1234.56, '$');   // "$1,234.56"

// Truncate from middle
echo Formatter::truncateMiddle('very_long_hash_string', 20); // "very_lo...string"

// Format lists
echo Formatter::list(['a', 'b', 'c'], ', ', 'and'); // "a, b, and c"

// Format tables
echo Formatter::table(
    [
        ['John', '25', 'Engineer'],
        ['Jane', '30', 'Designer'],
    ],
    headers: ['Name', 'Age', 'Role']
);

// Format booleans
echo Formatter::boolean(true);         // "Yes"
echo Formatter::boolean(false);        // "No"

// Format dates
echo Formatter::date(time());          // "2024-12-15 10:30:45"

// Format JSON
echo Formatter::json($data);

// Progress bars
echo Formatter::progressBar(0.75, 20); // "[===============     ] 75%"

// Ordinal numbers
echo Formatter::ordinal(1);            // "1st"
echo Formatter::ordinal(42);           // "42nd"
```

## Usage Patterns

### Validation Pipeline

```php
use ClaudeAgents\Support\{Validator, ArrayHelper, ErrorHandler};

try {
    // Validate input
    $errors = Validator::schema($input, $schema);
    if (!empty($errors)) {
        throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
    }
    
    // Extract and process data
    $userId = ArrayHelper::dotGet($input, 'user.id');
    
    // Process...
} catch (\Throwable $e) {
    $logger->error('Processing failed', ErrorHandler::formatForLog($e));
}
```

### Retry with Logging

```php
use ClaudeAgents\Support\{RetryHandler, LoggerFactory, TimeHelper};

$logger = LoggerFactory::createConsole();
$retry = new RetryHandler();

$retry->onRetry(function($attempt, $exception) use ($logger) {
    $logger->warning("Retry attempt {$attempt}", [
        'error' => $exception->getMessage(),
    ]);
});

$result = $retry->execute(function() {
    return $apiClient->makeRequest();
});
```

### Token Tracking with Cost Estimation

```php
use ClaudeAgents\Support\{TokenTracker, Formatter};

$tracker = new TokenTracker();

foreach ($responses as $response) {
    $tracker->record(
        $response->usage->input_tokens,
        $response->usage->output_tokens
    );
}

$summary = $tracker->getSummary();
$cost = $tracker->estimateCost();

echo "Total tokens: " . Formatter::number($summary['total_tokens']) . "\n";
echo "Estimated cost: " . Formatter::money($cost) . "\n";
```

## Design Principles

1. **Type Safety**: All utilities use strict types and provide detailed PHPDoc annotations
2. **Error Handling**: Graceful error handling with meaningful exceptions
3. **Immutability**: Most operations return new values rather than modifying in place
4. **Composability**: Utilities can be combined for complex operations
5. **Performance**: Efficient implementations optimized for common use cases
6. **Zero Dependencies**: No external dependencies beyond PSR interfaces

## Testing

All support utilities include comprehensive test coverage. See `tests/Unit/Support/` for examples.

## Contributing

When adding new utilities:
1. Follow the existing code style and patterns
2. Add comprehensive PHPDoc documentation
3. Include usage examples in this README
4. Write unit tests for all public methods
5. Ensure no linter errors

