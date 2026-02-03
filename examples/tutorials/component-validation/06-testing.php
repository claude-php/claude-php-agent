<?php

/**
 * Tutorial 6: Testing Validated Components
 * 
 * This example demonstrates:
 * - Writing tests for validation logic
 * - Testing validation failures
 * - Integration with PHPUnit
 * 
 * Run: php examples/tutorials/component-validation/06-testing.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

echo "=== Tutorial 6: Testing Validated Components ===\n\n";

// Simulate unit test scenarios
class ValidationTestRunner
{
    private ComponentValidationService $service;
    private array $results = [];
    
    public function __construct()
    {
        $this->service = new ComponentValidationService();
    }
    
    public function testSimpleClass(): void
    {
        $code = '<?php class SimpleClass {}';
        $result = $this->service->validate($code);
        
        $this->results[] = [
            'test' => 'Simple Class',
            'passed' => $result->isValid(),
            'expected' => true,
        ];
    }
    
    public function testConstructorError(): void
    {
        $code = <<<'PHP'
<?php
class FailingClass
{
    public function __construct()
    {
        throw new \Exception('Error');
    }
}
PHP;
        $result = $this->service->validate($code);
        
        $this->results[] = [
            'test' => 'Constructor Error',
            'passed' => !$result->isValid(), // Should fail
            'expected' => true,
        ];
    }
    
    public function runAll(): void
    {
        $this->testSimpleClass();
        $this->testConstructorError();
        
        echo "Test Results:\n";
        foreach ($this->results as $result) {
            $status = $result['passed'] === $result['expected'] ? '✓' : '✗';
            echo "$status {$result['test']}: " . 
                ($result['passed'] === $result['expected'] ? 'Passed' : 'Failed') . "\n";
        }
    }
}

$runner = new ValidationTestRunner();
$runner->runAll();

echo "\n✓ Example complete!\n";
echo "Note: This is a simplified test runner. Use PHPUnit for real tests.\n";
