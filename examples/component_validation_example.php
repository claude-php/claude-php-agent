<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;

echo "=== Component Validation Examples ===\n\n";

// Example 1: Standalone service with valid component
echo "Example 1: Valid Component\n";
echo str_repeat('-', 50) . "\n";

$validCode = <<<'PHP'
<?php

namespace MyApp\Components;

class ValidComponent
{
    private string $name;
    
    public function __construct(string $name = 'default')
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        $this->name = $name;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
}
PHP;

$service = new ComponentValidationService([
    'load_strategy' => 'temp_file',
    'constructor_args' => ['TestComponent'],
]);

$result = $service->validate($validCode);

if ($result->isValid()) {
    echo "✅ Validation passed!\n";
    echo "Class: {$result->getMetadata()['class_name']}\n";
    echo "Namespace: {$result->getMetadata()['namespace']}\n";
    echo "Time: {$result->getMetadata()['instantiation_time_ms']}ms\n";
} else {
    echo "❌ Validation failed!\n";
    foreach ($result->getErrors() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// Example 2: Component with validation error
echo "Example 2: Component with Constructor Validation Error\n";
echo str_repeat('-', 50) . "\n";

$invalidCode = <<<'PHP'
<?php

class InvalidComponent
{
    public function __construct()
    {
        // This will fail validation
        if (!extension_loaded('nonexistent_extension')) {
            throw new \RuntimeException('Required extension not loaded');
        }
    }
}
PHP;

$result = $service->validate($invalidCode);

if ($result->isValid()) {
    echo "✅ Validation passed!\n";
} else {
    echo "❌ Validation failed (expected)!\n";
    foreach ($result->getErrors() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// Example 3: Using with ValidationCoordinator
echo "Example 3: Integration with ValidationCoordinator\n";
echo str_repeat('-', 50) . "\n";

$coordinator = new ValidationCoordinator();

// Add multiple validators
$coordinator->addValidator(new PHPSyntaxValidator(['priority' => 10]));
$coordinator->addValidator(new ComponentInstantiationValidator([
    'priority' => 50,
    'constructor_args' => ['test'],
]));

$result = $coordinator->validate($validCode);

echo "Validation Result: " . ($result->isValid() ? '✅ Valid' : '❌ Invalid') . "\n";
echo "Errors: " . $result->getErrorCount() . "\n";
echo "Warnings: " . $result->getWarningCount() . "\n";
echo "Validators run: " . $result->getMetadata()['validator_count'] . "\n";
echo "Total time: " . $result->getMetadata()['duration_ms'] . "ms\n";

echo "\n";

// Example 4: Component with constructor arguments
echo "Example 4: Component Requiring Constructor Arguments\n";
echo str_repeat('-', 50) . "\n";

$componentWithArgs = <<<'PHP'
<?php

class ConfigurableComponent
{
    private array $config;
    
    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new \InvalidArgumentException('Config cannot be empty');
        }
        
        $required = ['api_key', 'endpoint'];
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new \InvalidArgumentException("Missing required config: {$key}");
            }
        }
        
        $this->config = $config;
    }
}
PHP;

$serviceWithArgs = new ComponentValidationService([
    'constructor_args' => [
        ['api_key' => 'test_key', 'endpoint' => 'https://api.example.com']
    ],
]);

$result = $serviceWithArgs->validate($componentWithArgs);

if ($result->isValid()) {
    echo "✅ Component with constructor args validated!\n";
    echo "Args provided: {$result->getMetadata()['constructor_args_count']}\n";
} else {
    echo "❌ Validation failed!\n";
    foreach ($result->getErrors() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// Example 5: Missing required constructor arguments
echo "Example 5: Missing Required Constructor Arguments\n";
echo str_repeat('-', 50) . "\n";

$componentRequiringArgs = <<<'PHP'
<?php

class StrictComponent
{
    public function __construct(string $required, int $count)
    {
        // Requires arguments
    }
}
PHP;

$serviceNoArgs = new ComponentValidationService([
    'constructor_args' => [], // No args provided
]);

$result = $serviceNoArgs->validate($componentRequiringArgs);

if ($result->isValid()) {
    echo "✅ Validation passed!\n";
} else {
    echo "❌ Validation failed (expected - missing args)!\n";
    foreach ($result->getErrors() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

// Example 6: Class name extraction
echo "Example 6: Class Name Extraction\n";
echo str_repeat('-', 50) . "\n";

$complexCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace MyApp\Complex\Namespace;

use Some\Dependency;

/**
 * Complex component with lots of metadata
 */
class ComplexComponent extends BaseComponent implements ComponentInterface
{
    public function __construct()
    {
        // Constructor
    }
}
PHP;

$result = $service->validate($complexCode);

if ($result->isValid()) {
    echo "✅ Successfully extracted and validated complex class!\n";
    echo "Class: {$result->getMetadata()['class_name']}\n";
    echo "Namespace: {$result->getMetadata()['namespace']}\n";
    echo "FQCN: {$result->getMetadata()['fully_qualified_class_name']}\n";
}

echo "\n";

// Example 7: Using eval strategy (requires opt-in)
echo "Example 7: Using Eval Strategy (Opt-in Required)\n";
echo str_repeat('-', 50) . "\n";

$evalService = new ComponentValidationService([
    'load_strategy' => 'eval',
    'allow_eval' => true,  // Must explicitly allow
]);

$simpleCode = <<<'PHP'
<?php

class EvalTestComponent
{
    public function getValue(): string
    {
        return 'tested via eval';
    }
}
PHP;

$result = $evalService->validate($simpleCode);

if ($result->isValid()) {
    echo "✅ Validation via eval successful!\n";
    echo "Strategy: {$result->getMetadata()['load_strategy']}\n";
}

echo "\n";

// Example 8: Expected class name verification
echo "Example 8: Expected Class Name Verification\n";
echo str_repeat('-', 50) . "\n";

$codeWithDifferentName = <<<'PHP'
<?php

class ActualClassName {}
PHP;

$result = $service->validate($codeWithDifferentName, [
    'expected_class_name' => 'ExpectedClassName',
]);

if ($result->isValid()) {
    echo "✅ Class name matches!\n";
} else {
    echo "❌ Class name mismatch (expected)!\n";
    foreach ($result->getErrors() as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";

echo "=== All Examples Complete ===\n";
