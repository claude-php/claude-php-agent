# Support Utilities - Quick Reference

## Import All Utilities

```php
use ClaudeAgents\Support\{
    ArrayHelper,
    ErrorHandler,
    Formatter,
    JsonHelper,
    LoggerFactory,
    RetryHandler,
    StringHelper,
    TimeHelper,
    TokenTracker,
    Validator
};
```

## Quick Method Reference

### ArrayHelper
```php
ArrayHelper::flatten($array)              // Flatten nested arrays
ArrayHelper::pluck($array, 'key')         // Extract column values
ArrayHelper::groupBy($array, 'key')       // Group items by key
ArrayHelper::keyBy($array, 'id')          // Index items by key
ArrayHelper::deepMerge($arr1, $arr2)      // Deep merge arrays
ArrayHelper::dotGet($array, 'a.b.c')      // Dot notation get
ArrayHelper::dotSet($array, 'a.b', $val)  // Dot notation set
```

### ErrorHandler
```php
ErrorHandler::wrap($exception, 'message') // Wrap with context
ErrorHandler::format($exception)          // Format for display
ErrorHandler::sanitize($exception)        // Remove sensitive data
ErrorHandler::formatForLog($exception)    // Format for logging
ErrorHandler::getRootCause($exception)    // Get root cause
ErrorHandler::handle($callable, $handler) // Execute with error handling
```

### Formatter
```php
Formatter::bytes(1024)                    // "1.00 KB"
Formatter::number(1234567)                // "1,234,567"
Formatter::percentage(0.75)               // "75.0%"
Formatter::money(99.99, '$')              // "$99.99"
Formatter::truncateMiddle($str, 20)       // "start...end"
Formatter::table($rows, $headers)         // ASCII table
Formatter::progressBar(0.5, 20)           // "[==========          ] 50%"
```

### JsonHelper
```php
JsonHelper::encode($data)                 // Safe JSON encode
JsonHelper::decode($json)                 // Safe JSON decode
JsonHelper::prettyPrint($data)            // Pretty JSON
JsonHelper::isValid($json)                // Validate JSON
JsonHelper::decodeOrFallback($json, [])   // Decode with fallback
```

### LoggerFactory
```php
LoggerFactory::create($logger)            // Create or get NullLogger
LoggerFactory::createConsole()            // Console logger
LoggerFactory::createFile('path.log')     // File logger
LoggerFactory::createMemory()             // Memory logger (testing)
```

### RetryHandler
```php
$retry = new RetryHandler($config);
$retry->onRetry($callback);               // Set retry callback
$retry->retryOn(Exception::class);        // Set retryable exceptions
$retry->execute($callable);               // Execute with retry
```

### StringHelper
```php
StringHelper::truncate($str, 100)         // Truncate string
StringHelper::excerpt($text, 'word', 200) // Extract excerpt
StringHelper::slugify('Hello World')      // "hello-world"
StringHelper::mask($apiKey, 4, 4)         // "sk-p***********xyz"
StringHelper::random(32)                  // Random string
StringHelper::camelCase('hello_world')    // "helloWorld"
StringHelper::snakeCase('HelloWorld')     // "hello_world"
```

### TimeHelper
```php
TimeHelper::elapsed($start)               // Calculate elapsed time
TimeHelper::formatDuration(125)           // "2m 5s"
TimeHelper::measure($callable)            // Measure execution time
TimeHelper::sleep(0.5)                    // Sleep 500ms
TimeHelper::parseDuration('1h30m')        // Parse to seconds
TimeHelper::retry($callable, 3)           // Retry with backoff
TimeHelper::ago(strtotime('-2 hours'))    // "2 hours ago"
```

### TokenTracker
```php
$tracker = new TokenTracker();
$tracker->record($input, $output);        // Record usage
$tracker->getTotalTokens();               // Get total
$tracker->getSummary();                   // Get summary array
$tracker->estimateCost();                 // Estimate cost
$tracker->reset();                        // Reset tracker
```

### Validator
```php
Validator::required($data, ['name'])      // Check required fields
Validator::schema($data, $schema)         // Validate JSON schema
Validator::email($email)                  // Validate email
Validator::url($url)                      // Validate URL
Validator::uuid($uuid)                    // Validate UUID
Validator::length($str, min: 5, max: 100) // Validate length
Validator::range($num, min: 0, max: 100)  // Validate range
$validator = Validator::make($rules);     // Fluent validation
```

## Common Patterns

### Validation + Error Handling
```php
try {
    $errors = Validator::schema($input, $schema);
    if (!empty($errors)) {
        throw new \InvalidArgumentException(implode(', ', $errors));
    }
    // Process...
} catch (\Throwable $e) {
    $logger->error('Failed', ErrorHandler::formatForLog($e));
}
```

### Retry + Logging
```php
$logger = LoggerFactory::createConsole();
$retry = new RetryHandler();
$retry->onRetry(fn($attempt, $e) => $logger->warning("Attempt {$attempt}"));
$result = $retry->execute(fn() => $api->call());
```

### Token Tracking + Formatting
```php
$tracker = new TokenTracker();
// ... track usage ...
$summary = $tracker->getSummary();
echo "Tokens: " . Formatter::number($summary['total_tokens']) . "\n";
echo "Cost: " . Formatter::money($tracker->estimateCost()) . "\n";
```

### Array Operations + JSON
```php
$data = JsonHelper::decode($json);
$value = ArrayHelper::dotGet($data, 'user.settings.theme', 'dark');
$grouped = ArrayHelper::groupBy($data['items'], 'category');
echo JsonHelper::prettyPrint($grouped);
```

### Time Measurement + Formatting
```php
$result = TimeHelper::measure(function() {
    return expensiveOperation();
});
echo "Completed in " . TimeHelper::formatDuration($result['duration']);
```

## File Sizes

```
ArrayHelper.php     11 KB   410 lines   21 methods
ErrorHandler.php    9.3 KB  310 lines   14 methods
Formatter.php       11 KB   419 lines   20 methods
JsonHelper.php      5.4 KB  196 lines   11 methods
LoggerFactory.php   10 KB   359 lines   7 methods + 3 classes
RetryHandler.php    3.6 KB  140 lines   5 methods
StringHelper.php    12 KB   403 lines   29 methods
TimeHelper.php      11 KB   358 lines   21 methods
TokenTracker.php    3.0 KB  135 lines   8 methods
Validator.php       11 KB   339 lines   22 methods + ValidationRules

Total: ~87 KB, 3,149 lines, 150+ public methods
```

## See Also

- [Full Documentation](README.md) - Comprehensive guide with examples
- [Implementation Summary](../../SUPPORT_UTILITIES_COMPLETE.md) - Complete implementation details

