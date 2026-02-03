<?php

declare(strict_types=1);

require_once __DIR__ . '/load-env.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\LLMReviewValidator;
use ClaudePhp\ClaudePhp;

/**
 * Example: Basic code generation with validation.
 *
 * Demonstrates:
 * - Creating a CodeGenerationAgent
 * - Setting up validators
 * - Generating PHP code from natural language
 * - Automatic validation and retry on failure
 */

$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    echo "Error: ANTHROPIC_API_KEY environment variable not set\n";
    exit(1);
}

$client = new ClaudePhp(apiKey: $apiKey);

// Setup validation coordinator with multiple validators
$validator = new ValidationCoordinator();
$validator->addValidator(new PHPSyntaxValidator());
$validator->addValidator(new LLMReviewValidator($client));

// Create code generation agent
$agent = new CodeGenerationAgent($client, [
    'max_validation_retries' => 3,
    'validation_coordinator' => $validator,
    'max_tokens' => 4096,
]);

// Setup callbacks for progress monitoring
$agent->onUpdate(function (string $type, array $data) {
    $timestamp = date('H:i:s');
    
    match ($type) {
        'code.generating' => printf("[%s] 🔄 Generating code...\n", $timestamp),
        'code.generated' => printf(
            "[%s] ✅ Generated %d lines (%d bytes)\n",
            $timestamp,
            $data['line_count'],
            $data['code_length']
        ),
        'validation.started' => printf("[%s] 🔍 Validating (attempt %d)...\n", $timestamp, $data['attempt'] + 1),
        'validation.passed' => printf("[%s] ✅ Validation passed!\n", $timestamp),
        'validation.failed' => printf(
            "[%s] ❌ Validation failed: %s\n",
            $timestamp,
            implode(', ', array_slice($data['errors'], 0, 2))
        ),
        'retry.attempt' => printf(
            "[%s] 🔄 Retry attempt %d/%d\n",
            $timestamp,
            $data['attempt'],
            $data['max_attempts']
        ),
        'component.completed' => printf(
            "[%s] 🎉 Component generated successfully after %d attempt(s)\n",
            $timestamp,
            $data['attempts']
        ),
        default => null,
    };
});

echo "=== Code Generation Example ===\n\n";

// Example 1: Generate a simple class
echo "Example 1: Generating a UserRepository class\n";
echo str_repeat('-', 50) . "\n\n";

try {
    $description = <<<DESC
Create a UserRepository class with the following:
- Namespace: App\Repository
- Methods: findById(int \$id), findAll(), save(User \$user), delete(int \$id)
- Use dependency injection for PDO
- Include proper type hints and docblocks
DESC;

    $result = $agent->generateComponent($description);

    if ($result->isValid()) {
        echo "\nGenerated code:\n";
        echo str_repeat('=', 50) . "\n";
        echo $result->getCode();
        echo str_repeat('=', 50) . "\n";

        // Optionally save to file
        $outputPath = '/tmp/UserRepository.php';
        if ($result->saveToFile($outputPath)) {
            echo "\n✅ Code saved to: {$outputPath}\n";
        }
    } else {
        echo "\n❌ Code generation failed validation:\n";
        foreach ($result->getValidation()->getErrors() as $error) {
            echo "  - {$error}\n";
        }
    }
} catch (\Throwable $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
}

echo "\n\n";

// Example 2: Generate an interface
echo "Example 2: Generating a CacheInterface\n";
echo str_repeat('-', 50) . "\n\n";

try {
    $description = <<<DESC
Create a CacheInterface with methods:
- get(string \$key): mixed
- set(string \$key, mixed \$value, int \$ttl = 3600): bool
- delete(string \$key): bool
- has(string \$key): bool
- clear(): bool
Include PSR-6 compatible docblocks
DESC;

    $result = $agent->generateComponent($description);

    echo "\n" . $result->getSummary() . "\n";
    
    if ($result->isValid()) {
        echo "\n✅ Code generation successful!\n";
        echo "Lines: " . substr_count($result->getCode(), "\n") . "\n";
        echo "Validation: " . $result->getValidation()->getSummary() . "\n";
    }
} catch (\Throwable $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
}

echo "\n=== Example Complete ===\n";
