<?php

/**
 * Code Generation Tutorial 6: Testing Generated Code
 * 
 * Run: php examples/tutorials/code-generation/06-testing-generated.php
 * Requires: ANTHROPIC_API_KEY
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Agents\CodeGenerationAgent;
use ClaudeAgents\Validation\ValidationCoordinator;
use ClaudeAgents\Validation\Validators\PHPSyntaxValidator;
use ClaudeAgents\Validation\Validators\ComponentInstantiationValidator;
use ClaudeAgents\Validation\ComponentValidationService;
use ClaudePhp\ClaudePhp;

echo "=== Code Generation Tutorial 6: Testing Generated Code ===\n\n";

if (!getenv('ANTHROPIC_API_KEY')) {
    die("❌ ANTHROPIC_API_KEY not set\n");
}

$client = new ClaudePhp(apiKey: getenv('ANTHROPIC_API_KEY'));

$coordinator = new ValidationCoordinator();
$coordinator
    ->addValidator(new PHPSyntaxValidator(['priority' => 10]))
    ->addValidator(new ComponentInstantiationValidator(['priority' => 50]));

$agent = new CodeGenerationAgent($client, [
    'validation_coordinator' => $coordinator,
]);

echo "Generating Counter class...\n";

$result = $agent->generateComponent('Create a Counter class with increment and getValue methods');

if ($result->isValid()) {
    echo "✓ Code generated\n\n";
    
    // Save to temp file
    $tempFile = sys_get_temp_dir() . '/Counter_' . uniqid() . '.php';
    $result->saveToFile($tempFile);
    
    echo "Testing generated code...\n";
    
    // Load and test
    require_once $tempFile;
    
    // Extract class name
    $service = new ComponentValidationService();
    $classInfo = $service->extractClassInfo($result->getCode());
    $className = $classInfo['class_name'];
    
    // Instantiate
    $fqcn = $classInfo['namespace'] 
        ? $classInfo['namespace'] . '\\' . $className 
        : $className;
    
    if (class_exists($className)) {
        echo "✓ Class loaded: $className\n";
        
        // Cleanup
        unlink($tempFile);
    }
}

echo "\n✓ Example complete!\n";
