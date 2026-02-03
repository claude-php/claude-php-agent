<?php

/**
 * Tutorial 3: ValidationCoordinator Integration
 * 
 * This example demonstrates:
 * - Integrating ComponentInstantiationValidator with ValidationCoordinator
 * - Running multiple validators in sequence
 * - Understanding validator priorities
 * 
 * Run: php examples/tutorials/component-validation/03-coordinator-integration.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;

echo "=== Tutorial 3: ValidationCoordinator Integration ===\n\n";

// Create coordinator
$coordinator = new ValidationCoordinator([
    'stop_on_first_failure' => false, // Run all validators
]);

// Add validators in priority order
$coordinator->addValidator(new PHPSyntaxValidator([
    'priority' => 10, // Runs first
]));

$coordinator->addValidator(new ComponentInstantiationValidator([
    'priority' => 50, // Runs after syntax check
]));

// Test code
$code = <<<'PHP'
<?php

namespace App\Services;

class EmailValidator
{
    public function validate(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
PHP;

echo "Validating with multiple validators...\n\n";

$result = $coordinator->validate($code);

echo "Validation Result:\n";
echo "- Valid: " . ($result->isValid() ? 'Yes' : 'No') . "\n";
echo "- Validators Run: {$result->getMetadata()['validator_count']}\n";
echo "- Total Time: {$result->getMetadata()['duration_ms']}ms\n";
echo "- Errors: {$result->getErrorCount()}\n";
echo "- Warnings: {$result->getWarningCount()}\n";

echo "\n✓ Example complete!\n";
