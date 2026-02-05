---
name: php-refactoring
description: "Refactor PHP code to improve structure, readability, and maintainability. Use when asked to refactor, restructure, improve code design, or apply design patterns."
license: MIT
metadata:
  author: claude-php-agent
  version: "1.0.0"
  tags: [php, refactoring, design-patterns, clean-code]
---

# PHP Refactoring

## Overview

Apply systematic refactoring techniques to improve PHP code structure while preserving behavior. Supports modern PHP 8.1+ features and common design patterns.

## Refactoring Catalog

### Extract Method
When a code fragment can be grouped together:
```php
// Before
function printReport(): void {
    // print header
    echo "=== Report ===\n";
    echo "Date: " . date('Y-m-d') . "\n";

    // print body
    foreach ($items as $item) {
        echo "{$item['name']}: {$item['value']}\n";
    }
}

// After
function printReport(): void {
    $this->printHeader();
    $this->printBody($items);
}
```

### Replace Conditional with Polymorphism
```php
// Before
function calculateArea(string $shape, float ...$dims): float {
    return match($shape) {
        'circle' => M_PI * $dims[0] ** 2,
        'rectangle' => $dims[0] * $dims[1],
        'triangle' => 0.5 * $dims[0] * $dims[1],
    };
}

// After: Use interface + implementations
interface Shape {
    public function area(): float;
}
```

### Introduce Parameter Object
When multiple parameters travel together:
```php
// Before
function createUser(string $name, string $email, string $role, int $age): User

// After
function createUser(CreateUserRequest $request): User
```

### Use PHP 8.1+ Features
- Constructor promotion
- Readonly properties
- Enums instead of string constants
- Named arguments for clarity
- Fibers for async operations
- First-class callable syntax
- Intersection types
- Match expressions

## Rules

1. **Never change behavior** - refactoring preserves existing functionality
2. **Small steps** - make one refactoring at a time
3. **Test between steps** - run tests after each change
4. **Commit frequently** - each refactoring should be a separate commit
5. **Explain rationale** - document why each change improves the code

## Scripts

Use `scripts/detect-smells.php` to identify common code smells in a file or directory.
