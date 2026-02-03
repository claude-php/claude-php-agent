<?php

declare(strict_types=1);

require_once __DIR__ . '/load-env.php';

use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\StaticAnalysisValidator;
use ClaudeAgents\Validation\Validators\CustomScriptValidator;
use ClaudeAgents\Validation\Validators\LLMReviewValidator;
use ClaudePhp\ClaudePhp;

/**
 * Example: Validating PHP code with multiple validators.
 *
 * Demonstrates:
 * - Setting up a ValidationCoordinator
 * - Using different validator types
 * - Handling validation results
 * - Validator priorities
 */

$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    echo "Error: ANTHROPIC_API_KEY environment variable not set\n";
    exit(1);
}

$client = new ClaudePhp(apiKey: $apiKey);

echo "=== Validation System Example ===\n\n";

// Setup validation coordinator
$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => false, // Run all validators even if one fails
]);

// Add validators in priority order
$coordinator->addValidator(new PHPSyntaxValidator()); // Priority 10 - runs first
$coordinator->addValidator(new LLMReviewValidator($client)); // Priority 100 - runs last

echo "Registered validators:\n";
foreach ($coordinator->getValidators() as $validator) {
    printf(
        "  - %s (priority: %d)\n",
        $validator->getName(),
        $validator->getPriority()
    );
}
echo "\n";

// Example 1: Valid code
echo "Example 1: Validating VALID code\n";
echo str_repeat('-', 50) . "\n";

$validCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
    
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}
PHP;

$result = $coordinator->validate($validCode);

echo "Result: " . ($result->isValid() ? '✅ VALID' : '❌ INVALID') . "\n";
echo "Errors: " . $result->getErrorCount() . "\n";
echo "Warnings: " . $result->getWarningCount() . "\n";

if ($result->hasWarnings()) {
    echo "\nWarnings:\n";
    foreach ($result->getWarnings() as $warning) {
        echo "  - {$warning}\n";
    }
}

echo "\n\n";

// Example 2: Code with syntax error
echo "Example 2: Validating code with SYNTAX ERROR\n";
echo str_repeat('-', 50) . "\n";

$invalidCode = <<<'PHP'
<?php

namespace App;

class Broken
{
    public function test()
    {
        // Missing semicolon and closing brace
        return "broken"
    }
PHP;

$result = $coordinator->validate($invalidCode);

echo "Result: " . ($result->isValid() ? '✅ VALID' : '❌ INVALID') . "\n";
echo "Errors: " . $result->getErrorCount() . "\n";

if ($result->hasErrors()) {
    echo "\nErrors:\n";
    foreach ($result->getErrors() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n\n";

// Example 3: Individual validator usage
echo "Example 3: Using validators individually\n";
echo str_repeat('-', 50) . "\n";

$syntaxValidator = new PHPSyntaxValidator();

$testCode = <<<'PHP'
<?php

function greet(string $name): string
{
    return "Hello, {$name}!";
}
PHP;

echo "Testing with PHPSyntaxValidator...\n";
$result = $syntaxValidator->validate($testCode);
echo "Result: " . $result->getSummary() . "\n";

echo "\n\n";

// Example 4: Validation with context
echo "Example 4: Validation with context\n";
echo str_repeat('-', 50) . "\n";

$context = [
    'purpose' => 'API endpoint handler',
    'framework' => 'Laravel',
    'requirements' => ['validation', 'authorization', 'error handling'],
];

$apiHandlerCode = <<<'PHP'
<?php

namespace App\Http\Controllers;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $user = User::create($request->all());
        return response()->json($user);
    }
}
PHP;

echo "Validating API handler with context...\n";
$result = $coordinator->validate($apiHandlerCode, $context);

echo "Result: " . $result->getSummary() . "\n";
echo "Metadata: " . json_encode($result->getMetadata(), JSON_PRETTY_PRINT) . "\n";

echo "\n\n";

// Example 5: Custom script validator
echo "Example 5: Custom script validator\n";
echo str_repeat('-', 50) . "\n";

// Create a custom validator that runs a PHP script
$customValidator = CustomScriptValidator::phpScript(
    __DIR__ . '/../tests/fixtures/custom-validator.php'
);

// You can also create validators for specific test frameworks
// $phpunitValidator = CustomScriptValidator::phpunit('tests/Unit/MyTest.php');
// $pestValidator = CustomScriptValidator::pest('tests/Feature/MyTest.php');

echo "Custom validator: {$customValidator->getName()}\n";
echo "Priority: {$customValidator->getPriority()}\n";

echo "\n=== Example Complete ===\n";
