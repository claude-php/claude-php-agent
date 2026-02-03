<?php

declare(strict_types=1);

require_once __DIR__ . '/load-env.php';

use ClaudeAgents\Generation\ComponentTemplate;
use ClaudeAgents\Support\CodeFormatter;

/**
 * Example: Using ComponentTemplate for code scaffolding.
 *
 * Demonstrates:
 * - Generating classes, interfaces, and traits from templates
 * - Using templates for common patterns
 * - Customizing generated code
 */

echo "=== Component Template Example ===\n\n";

// Example 1: Generate a basic class
echo "Example 1: Basic Class Template\n";
echo str_repeat('-', 50) . "\n";

$basicClass = ComponentTemplate::classTemplate(
    name: 'Product',
    namespace: 'App\Models',
    options: [
        'properties' => [
            ['name' => 'id', 'type' => 'int', 'visibility' => 'private'],
            ['name' => 'name', 'type' => 'string', 'visibility' => 'private'],
            ['name' => 'price', 'type' => 'float', 'visibility' => 'private'],
        ],
        'methods' => [
            [
                'name' => 'getId',
                'visibility' => 'public',
                'return_type' => 'int',
                'body' => "        return \$this->id;\n",
            ],
            [
                'name' => 'getName',
                'visibility' => 'public',
                'return_type' => 'string',
                'body' => "        return \$this->name;\n",
            ],
            [
                'name' => 'getPrice',
                'visibility' => 'public',
                'return_type' => 'float',
                'body' => "        return \$this->price;\n",
            ],
        ],
    ]
);

echo CodeFormatter::formatForConsole($basicClass);
echo "\n\n";

// Example 2: Generate an interface
echo "Example 2: Interface Template\n";
echo str_repeat('-', 50) . "\n";

$interface = ComponentTemplate::interfaceTemplate(
    name: 'RepositoryInterface',
    namespace: 'App\Contracts',
    options: [
        'methods' => [
            [
                'name' => 'findById',
                'params' => [['type' => 'int', 'name' => 'id']],
                'return_type' => '?object',
            ],
            [
                'name' => 'findAll',
                'params' => [],
                'return_type' => 'array',
            ],
            [
                'name' => 'save',
                'params' => [['type' => 'object', 'name' => 'entity']],
                'return_type' => 'bool',
            ],
            [
                'name' => 'delete',
                'params' => [['type' => 'int', 'name' => 'id']],
                'return_type' => 'bool',
            ],
        ],
    ]
);

echo CodeFormatter::formatForConsole($interface);
echo "\n\n";

// Example 3: Generate a trait
echo "Example 3: Trait Template\n";
echo str_repeat('-', 50) . "\n";

$trait = ComponentTemplate::traitTemplate(
    name: 'Timestampable',
    namespace: 'App\Traits',
    options: [
        'methods' => [
            [
                'name' => 'setCreatedAt',
                'visibility' => 'public',
                'params' => [['type' => 'DateTime', 'name' => 'createdAt']],
                'return_type' => 'void',
                'body' => "        \$this->createdAt = \$createdAt;\n",
            ],
            [
                'name' => 'getCreatedAt',
                'visibility' => 'public',
                'return_type' => 'DateTime',
                'body' => "        return \$this->createdAt;\n",
            ],
        ],
    ]
);

echo CodeFormatter::formatForConsole($trait);
echo "\n\n";

// Example 4: Generate a service class with dependencies
echo "Example 4: Service Template with Dependencies\n";
echo str_repeat('-', 50) . "\n";

$service = ComponentTemplate::serviceTemplate(
    name: 'UserService',
    namespace: 'App\Services',
    options: [
        'dependencies' => [
            ['name' => 'repository', 'type' => 'UserRepository'],
            ['name' => 'logger', 'type' => 'LoggerInterface'],
            ['name' => 'cache', 'type' => 'CacheInterface'],
        ],
    ]
);

echo CodeFormatter::formatForConsole($service);
echo "\n\n";

// Example 5: Generate a class with inheritance and interfaces
echo "Example 5: Class with Inheritance\n";
echo str_repeat('-', 50) . "\n";

$controller = ComponentTemplate::classTemplate(
    name: 'UserController',
    namespace: 'App\Http\Controllers',
    options: [
        'extends' => 'BaseController',
        'implements' => ['AuthorizableInterface'],
        'properties' => [
            ['name' => 'userService', 'type' => 'UserService', 'visibility' => 'private'],
        ],
        'methods' => [
            [
                'name' => 'index',
                'visibility' => 'public',
                'return_type' => 'JsonResponse',
                'body' => "        \$users = \$this->userService->getAll();\n        return response()->json(\$users);\n",
            ],
        ],
    ]
);

echo CodeFormatter::formatForConsole($controller);
echo "\n\n";

// Example 6: Code statistics
echo "Example 6: Code Statistics\n";
echo str_repeat('-', 50) . "\n";

$stats = CodeFormatter::getStatistics($basicClass);
echo "Statistics for Product class:\n";
foreach ($stats as $key => $value) {
    echo "  - " . str_replace('_', ' ', ucfirst($key)) . ": {$value}\n";
}

echo "\n=== Example Complete ===\n";
