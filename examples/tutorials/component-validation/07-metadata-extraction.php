<?php

/**
 * Tutorial 7: Metadata Extraction
 * 
 * This example demonstrates:
 * - Extracting class information
 * - Getting detailed validation metadata
 * - Tracking validation metrics
 * 
 * Run: php examples/tutorials/component-validation/07-metadata-extraction.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

echo "=== Tutorial 7: Metadata Extraction ===\n\n";

$code = <<<'PHP'
<?php

namespace App\Services;

class EmailValidator
{
    private array $rules;
    
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }
    
    public function validate(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
PHP;

$service = new ComponentValidationService([
    'constructor_args' => [['strict' => true]],
]);

echo "Extracting class information...\n";

// Extract class info before validation
$classInfo = $service->extractClassInfo($code);

echo "\nClass Information:\n";
echo "- Class Name: {$classInfo['class_name']}\n";
echo "- Namespace: " . ($classInfo['namespace'] ?? 'None') . "\n";

echo "\nValidating component...\n";

// Validate and get full metadata
$result = $service->validate($code);

if ($result->isValid()) {
    $metadata = $result->getMetadata();
    
    echo "\nValidation Metadata:\n";
    foreach ($metadata as $key => $value) {
        if (is_scalar($value)) {
            echo "- $key: $value\n";
        }
    }
}

echo "\n✓ Example complete!\n";
