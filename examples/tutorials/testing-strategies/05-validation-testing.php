<?php

/**
 * Testing Strategies Tutorial 5: Validation Testing
 * 
 * Run: php examples/tutorials/testing-strategies/05-validation-testing.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Validation\ComponentValidationService;

echo "=== Testing Strategies Tutorial 5: Validation Testing ===\n\n";

// Simulate test scenarios
class ValidationTestScenarios
{
    private ComponentValidationService $service;
    
    public function __construct()
    {
        $this->service = new ComponentValidationService();
    }
    
    public function testValid(): bool
    {
        $code = '<?php class Valid {}';
        $result = $this->service->validate($code);
        return $result->isValid();
    }
    
    public function testInvalid(): bool
    {
        $code = '<?php class Invalid { /* missing closing brace';
        $result = $this->service->validate($code);
        return !$result->isValid(); // Should fail
    }
    
    public function runAll(): void
    {
        echo "Test Scenarios:\n";
        echo ($this->testValid() ? '✓' : '✗') . " Valid code\n";
        echo ($this->testInvalid() ? '✓' : '✗') . " Invalid code\n";
    }
}

$scenarios = new ValidationTestScenarios();
$scenarios->runAll();

echo "\n✓ Example complete!\n";
echo "Note: Use PHPUnit for real tests\n";
