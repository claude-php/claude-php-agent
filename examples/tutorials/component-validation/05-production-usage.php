<?php

/**
 * Tutorial 5: Production Usage
 * 
 * This example demonstrates:
 * - Production-ready error handling
 * - Metadata extraction
 * - Performance monitoring
 * 
 * Run: php examples/tutorials/component-validation/05-production-usage.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;
use ClaudeAgents\Validation\Exceptions\ComponentValidationException;

echo "=== Tutorial 5: Production Usage ===\n\n";

// Production-ready validation function
function validateComponent(string $code): bool
{
    $service = new ComponentValidationService();
    
    try {
        $result = $service->validate($code);
        
        if ($result->isValid()) {
            error_log(sprintf(
                'Validation succeeded: %s in %sms',
                $result->getMetadata()['class_name'],
                $result->getMetadata()['instantiation_time_ms']
            ));
            return true;
        } else {
            error_log('Validation failed: ' . implode(', ', $result->getErrors()));
            return false;
        }
    } catch (ComponentValidationException $e) {
        error_log(sprintf(
            'Validation exception: %s (class: %s)',
            $e->getMessage(),
            $e->getClassName()
        ));
        return false;
    } catch (\Throwable $e) {
        error_log('Unexpected error: ' . $e->getMessage());
        return false;
    }
}

// Test with valid code
$validCode = <<<'PHP'
<?php

class ProductionComponent
{
    public function process(): string
    {
        return 'processed';
    }
}
PHP;

echo "Testing production validation function...\n";
$success = validateComponent($validCode);
echo ($success ? '✓' : '✗') . " Validation: " . ($success ? 'Success' : 'Failed') . "\n";

echo "\n✓ Example complete!\n";
