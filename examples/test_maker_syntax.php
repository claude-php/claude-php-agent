#!/usr/bin/env php
<?php
/**
 * Test MAKER examples syntax and structure
 * 
 * This script verifies that the MAKER examples can be loaded and 
 * their classes/methods are properly defined without making API calls.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ClaudeAgents\Agents\MakerAgent;
use ClaudeAgents\Agents\MicroAgent;
use ClaudePhp\ClaudePhp;

echo "Testing MAKER Implementation...\n\n";

// Test 1: Check if classes exist
echo "1. Checking if classes exist...\n";
$classes = [
    'ClaudeAgents\Agents\MakerAgent',
    'ClaudeAgents\Agents\MicroAgent',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "   ✓ {$class} exists\n";
    } else {
        echo "   ✗ {$class} NOT FOUND\n";
        exit(1);
    }
}

// Test 2: Check if we can instantiate MakerAgent (with mock client)
echo "\n2. Testing MakerAgent instantiation...\n";
try {
    $mockClient = Mockery::mock(ClaudePhp::class);
    $maker = new MakerAgent($mockClient, [
        'name' => 'test_maker',
        'voting_k' => 2,
        'enable_red_flagging' => true,
    ]);
    echo "   ✓ MakerAgent instantiated successfully\n";
    echo "   ✓ Agent name: {$maker->getName()}\n";
} catch (\Throwable $e) {
    echo "   ✗ Failed: {$e->getMessage()}\n";
    exit(1);
}

// Test 3: Check configuration methods
echo "\n3. Testing configuration methods...\n";
try {
    $maker->setVotingK(3);
    echo "   ✓ setVotingK() works\n";
    
    $maker->setRedFlagging(false);
    echo "   ✓ setRedFlagging() works\n";
    
    $stats = $maker->getExecutionStats();
    echo "   ✓ getExecutionStats() works\n";
    echo "   ✓ Stats structure: " . implode(', ', array_keys($stats)) . "\n";
} catch (\Throwable $e) {
    echo "   ✗ Failed: {$e->getMessage()}\n";
    exit(1);
}

// Test 4: Check MicroAgent instantiation
echo "\n4. Testing MicroAgent instantiation...\n";
try {
    $roles = ['decomposer', 'executor', 'composer', 'validator', 'discriminator'];
    
    foreach ($roles as $role) {
        $microAgent = new MicroAgent($mockClient, ['role' => $role]);
        if ($microAgent->getRole() === $role) {
            echo "   ✓ MicroAgent with role '{$role}' works\n";
        } else {
            echo "   ✗ MicroAgent role mismatch\n";
            exit(1);
        }
    }
} catch (\Throwable $e) {
    echo "   ✗ Failed: {$e->getMessage()}\n";
    exit(1);
}

// Test 5: Verify example files exist and have correct structure
echo "\n5. Checking example files...\n";
$examples = [
    'maker_example.php',
    'maker_towers_hanoi.php',
];

foreach ($examples as $example) {
    $path = __DIR__ . '/' . $example;
    if (file_exists($path)) {
        echo "   ✓ {$example} exists\n";
        
        // Check for key content
        $content = file_get_contents($path);
        $checks = [
            'MakerAgent' => strpos($content, 'MakerAgent') !== false,
            'voting_k' => strpos($content, 'voting_k') !== false,
            'enable_red_flagging' => strpos($content, 'enable_red_flagging') !== false,
        ];
        
        foreach ($checks as $check => $result) {
            if ($result) {
                echo "     ✓ Contains '{$check}'\n";
            } else {
                echo "     ⚠ Missing '{$check}'\n";
            }
        }
    } else {
        echo "   ✗ {$example} NOT FOUND\n";
        exit(1);
    }
}

// Test 6: Verify documentation exists
echo "\n6. Checking documentation files...\n";
$docs = [
    'MAKER_IMPLEMENTATION.md',
    'MAKER_QUICK_START.md',
    'MAKER_SUMMARY.md',
];

foreach ($docs as $doc) {
    $path = __DIR__ . '/../' . $doc;
    if (file_exists($path)) {
        $size = filesize($path);
        echo "   ✓ {$doc} exists ({$size} bytes)\n";
    } else {
        echo "   ✗ {$doc} NOT FOUND\n";
        exit(1);
    }
}

// Test 7: Check if .env loading works
echo "\n7. Testing .env loading...\n";
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    echo "   ✓ .env file exists\n";
    
    // Parse .env
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $envVars = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $envVars[trim($name)] = trim($value);
    }
    
    if (isset($envVars['ANTHROPIC_API_KEY'])) {
        $keyLength = strlen($envVars['ANTHROPIC_API_KEY']);
        echo "   ✓ ANTHROPIC_API_KEY found (length: {$keyLength})\n";
    } else {
        echo "   ⚠ ANTHROPIC_API_KEY not in .env file\n";
    }
} else {
    echo "   ⚠ .env file not found (this is okay for CI)\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "✓ All MAKER implementation tests passed!\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\nThe MAKER agent is ready to use.\n";
echo "\nTo run examples (requires valid API key in .env):\n";
echo "  php examples/maker_example.php\n";
echo "  php examples/maker_towers_hanoi.php\n";
echo "\nTo run unit tests:\n";
echo "  vendor/bin/phpunit tests/Unit/MakerAgentTest.php\n";
echo "  vendor/bin/phpunit tests/Unit/MicroAgentTest.php\n";

Mockery::close();

