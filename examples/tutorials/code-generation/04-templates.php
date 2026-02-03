<?php

/**
 * Code Generation Tutorial 4: Component Templates
 * 
 * Run: php examples/tutorials/code-generation/04-templates.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Generation\ComponentTemplate;

echo "=== Code Generation Tutorial 4: Component Templates ===\n\n";

// Generate class template
echo "Example 1: Class Template\n";
echo str_repeat('-', 60) . "\n";

$classCode = ComponentTemplate::classTemplate(
    name: 'UserRepository',
    namespace: 'App\\Repositories',
    options: [
        'properties' => [
            ['name' => 'connection', 'type' => 'PDO', 'visibility' => 'private'],
        ],
    ]
);

echo $classCode;

echo "\n\nExample 2: Interface Template\n";
echo str_repeat('-', 60) . "\n";

$interfaceCode = ComponentTemplate::interfaceTemplate(
    name: 'CacheInterface',
    namespace: 'App\\Contracts',
    options: [
        'methods' => [
            ['name' => 'get', 'params' => [['name' => 'key', 'type' => 'string']]],
            ['name' => 'set', 'params' => [
                ['name' => 'key', 'type' => 'string'],
                ['name' => 'value', 'type' => 'mixed'],
            ]],
        ],
    ]
);

echo $interfaceCode;

echo "\n✓ Example complete!\n";
